<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\LaporanPajak;
use App\Filament\Exports\TaxReportExporter;
use App\Filament\Resources\TaxReportResource\Pages;
use App\Filament\Resources\TaxReportResource\RelationManagers;
use App\Filament\Resources\TaxReportResource\RelationManagers\IncomeTaxsRelationManager;
use App\Models\Client;
use App\Models\TaxReport;
use App\Models\TaxCompensation;
use Filament\Tables\Actions\ExportAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\Table\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Support\RawJs;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Closure;
use Filament\Tables\Grouping\Group;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Swis\Filament\Activitylog\Tables\Actions\ActivitylogAction;

use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Components\Tab;

class TaxReportResource extends Resource
{
    protected static ?string $model = TaxReport::class;

    protected static ?string $modelLabel = 'Laporan Pajaks';

    protected static ?string $navigationLabel = 'Laporan Pajak';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    // protected static ?string $cluster = LaporanPajak::class;
    protected static ?string $navigationGroup = 'Tax Management';
    protected static ?int $navigationSort = 2;


    public static function shouldRegisterNavigation(): bool
    {
        return !auth()->user()->hasRole('client');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Tax Report Information')
                    ->schema([
                        Select::make('client_id')
                            ->label('Client')
                            ->required()
                            ->relationship(
                                'client',
                                'name',
                                fn ($query) => $query
                                    ->where('status', 'Active')
                                    ->where(function ($q) {
                                        $q->where('ppn_contract', true)
                                        ->orWhere('pph_contract', true)
                                        ->orWhere('bupot_contract', true)
                                        ->orWhere('pph_badan_contract', true); // ✅ ADD THIS
                                    })
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Hanya menampilkan klien aktif dengan kontrak PPN, PPH, atau Bupot')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('NPWP')
                                    ->label('NPWP')
                                    ->maxLength(255),
                                TextInput::make('KPP')
                                    ->label('KPP')
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->unique(ignorable: fn($record) => $record)
                                    ->maxLength(255),
                                Select::make('status')
                                    ->options([
                                        'Active' => 'Active',
                                        'Inactive' => 'Inactive',
                                    ])
                                    ->default('Active'),
                            ]),

                        Select::make('month')
                            ->required()
                            ->native(false)
                            ->options([
                                'January' => 'January',
                                'February' => 'February',
                                'March' => 'March',
                                'April' => 'April',
                                'May' => 'May',
                                'June' => 'June',
                                'July' => 'July',
                                'August' => 'August',
                                'September' => 'September',
                                'October' => 'October',
                                'November' => 'November',
                                'December' => 'December',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // CRITICAL: Add eager loading with tax_calculation_summaries AND client contracts
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->with([
                        'client:id,name,ppn_contract,pph_contract,bupot_contract,pph_badan_contract',
                        'createdBy:id,name',
                        'taxCalculationSummaries:id,tax_report_id,tax_type,report_status,reported_at,status_final,saldo_final,bayar_status,bayar_at,bukti_bayar',
                    ])
                    ->withSum('invoices', 'ppn')
                    ->withSum([
                        'invoices as ppn_masuk_sum' => function ($query) {
                            $query->where('type', 'Faktur Masuk');
                        }
                    ], 'ppn')
                    ->withSum([
                        'invoices as ppn_keluar_sum' => function ($query) {
                            $query->where('type', 'Faktur Keluaran')
                                ->where(function ($q) {
                                    $q->where('invoice_number', 'NOT LIKE', '02%')
                                        ->where('invoice_number', 'NOT LIKE', '03%')
                                        ->where('invoice_number', 'NOT LIKE', '07%')
                                        ->where('invoice_number', 'NOT LIKE', '08%');
                                });
                        }
                    ], 'ppn')
                    ->withSum([
                        'invoices as peredaran_bruto_sum' => function ($query) {
                            $query->where('type', 'Faktur Keluaran');
                        }
                    ], 'dpp')
                    ->withSum('incomeTaxs', 'pajak_penghasilan')  // ✅ CHANGED FROM 'pph_21_amount'
                    ->withSum('bupots', 'bupot_amount')
                    ->withCount([
                        'invoices as total_invoices_count',
                        'invoices as invoices_masuk_count' => function ($query) {
                            $query->where('type', 'Faktur Masuk');
                        },
                        'invoices as invoices_keluar_count' => function ($query) {
                            $query->where('type', 'Faktur Keluaran');
                        },
                        'incomeTaxs as income_taxes_count',
                        'bupots as bupots_count',
                        // ✅ UPDATED - use 'bukti_potong' for income_taxes
                        'incomeTaxs as income_taxes_with_bukti_count' => function ($query) {
                            $query->whereNotNull('bukti_potong')->where('bukti_potong', '!=', '');
                        },
                    ]);
            })
            // Use custom content view for card layout
            ->content(view('filament.pages.tax-report.components.tax-report-cards'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('month')
                    ->label('Periode')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // PPN Status from tax_calculation_summaries
                Tables\Columns\IconColumn::make('ppn_status')
                    ->label('Status PPN')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function (TaxReport $record): bool {
                        $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                        return $ppnSummary && $ppnSummary->report_status === 'Sudah Lapor';
                    })
                    ->tooltip(function (TaxReport $record): string {
                        $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                        
                        if ($ppnSummary && $ppnSummary->report_status === 'Sudah Lapor') {
                            $dateText = '';
                            if ($ppnSummary->reported_at) {
                                try {
                                    $dateText = ' pada ' . \Carbon\Carbon::parse($ppnSummary->reported_at)->format('d M Y');
                                } catch (\Exception $e) {
                                    $dateText = '';
                                }
                            }
                            return 'PPN sudah dilaporkan' . $dateText;
                        }
                        return 'PPN belum dilaporkan';
                    })
                    ->alignCenter()
                    ->sortable(false),

                // PPh Status from tax_calculation_summaries
                Tables\Columns\IconColumn::make('pph_status')
                    ->label('Status PPh')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function (TaxReport $record): bool {
                        $pphSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph');
                        return $pphSummary && $pphSummary->report_status === 'Sudah Lapor';
                    })
                    ->tooltip(function (TaxReport $record): string {
                        $pphSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph');
                        
                        if ($pphSummary && $pphSummary->report_status === 'Sudah Lapor') {
                            $dateText = '';
                            if ($pphSummary->reported_at) {
                                try {
                                    $dateText = ' pada ' . \Carbon\Carbon::parse($pphSummary->reported_at)->format('d M Y');
                                } catch (\Exception $e) {
                                    $dateText = '';
                                }
                            }
                            return 'PPh sudah dilaporkan' . $dateText;
                        }
                        return 'PPh belum dilaporkan';
                    })
                    ->alignCenter()
                    ->sortable(false),

                // Bupot Status from tax_calculation_summaries
                Tables\Columns\IconColumn::make('bupot_status')
                    ->label('Status Bupot')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function (TaxReport $record): bool {
                        $bupotSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'bupot');
                        return $bupotSummary && $bupotSummary->report_status === 'Sudah Lapor';
                    })
                    ->tooltip(function (TaxReport $record): string {
                        $bupotSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'bupot');
                        
                        if ($bupotSummary && $bupotSummary->report_status === 'Sudah Lapor') {
                            $dateText = '';
                            if ($bupotSummary->reported_at) {
                                try {
                                    $dateText = ' pada ' . \Carbon\Carbon::parse($bupotSummary->reported_at)->format('d M Y');
                                } catch (\Exception $e) {
                                    $dateText = '';
                                }
                            }
                            return 'Bupot sudah dilaporkan' . $dateText;
                        }
                        return 'Bupot belum dilaporkan';
                    })
                    ->alignCenter()
                    ->sortable(false),

                Tables\Columns\IconColumn::make('pph_badan_status')
                    ->label('Status PPh Badan')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(function (TaxReport $record): bool {
                        $pphBadanSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph_badan');
                        return $pphBadanSummary && $pphBadanSummary->report_status === 'Sudah Lapor';
                    })
                    ->tooltip(function (TaxReport $record): string {
                        $pphBadanSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph_badan');
                        
                        if ($pphBadanSummary && $pphBadanSummary->report_status === 'Sudah Lapor') {
                            $dateText = '';
                            if ($pphBadanSummary->reported_at) {
                                try {
                                    $dateText = ' pada ' . \Carbon\Carbon::parse($pphBadanSummary->reported_at)->format('d M Y');
                                } catch (\Exception $e) {
                                    $dateText = '';
                                }
                            }
                            return 'PPh Badan sudah dilaporkan' . $dateText;
                        }
                        return 'PPh Badan belum dilaporkan';
                    })
                    ->alignCenter()
                    ->sortable(false),

                // Payment Status from PPN summary
                TextColumn::make('payment_status')
                    ->label('Status Bayar')
                    ->badge()
                    ->getStateUsing(function (TaxReport $record): string {
                        $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                        return $ppnSummary?->status_final ?? 'N/A';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Lebih Bayar' => 'success',
                        'Kurang Bayar' => 'warning',
                        'Nihil' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->deferLoading()
            ->filters([
                // NEW: Client Contract Filter (Default Active)
                Tables\Filters\Filter::make('has_contracts')
                    ->label('Kontrak Aktif')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('client', function (Builder $q) {
                            $q->where('status', 'Active')
                            ->where(function ($subQuery) {
                                $subQuery->where('ppn_contract', true)
                                        ->orWhere('pph_contract', true)
                                        ->orWhere('bupot_contract', true)
                                        ->orWhere('pph_badan_contract', true); // ✅ ADD THIS
                            });
                        });
                    })
                    ->default()
                    ->toggle(),

                // Status filters using tax_calculation_summaries
                Tables\Filters\Filter::make('ppn_report_status')
                    ->label('Status Laporan PPN')
                    ->form([
                        Select::make('value')
                            ->label('Status')
                            ->options([
                                'Belum Lapor' => 'Belum Lapor',
                                'Sudah Lapor' => 'Sudah Lapor',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->whereHas('taxCalculationSummaries', function ($q) use ($status) {
                                $q->where('tax_type', 'ppn')
                                  ->where('report_status', $status);
                            })
                        );
                    }),

                Tables\Filters\Filter::make('pph_report_status')
                    ->label('Status Laporan PPh')
                    ->form([
                        Select::make('value')
                            ->label('Status')
                            ->options([
                                'Belum Lapor' => 'Belum Lapor',
                                'Sudah Lapor' => 'Sudah Lapor',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->whereHas('taxCalculationSummaries', function ($q) use ($status) {
                                $q->where('tax_type', 'pph')
                                  ->where('report_status', $status);
                            })
                        );
                    }),

                Tables\Filters\Filter::make('bupot_report_status')
                    ->label('Status Laporan Bupot')
                    ->form([
                        Select::make('value')
                            ->label('Status')
                            ->options([
                                'Belum Lapor' => 'Belum Lapor',
                                'Sudah Lapor' => 'Sudah Lapor',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->whereHas('taxCalculationSummaries', function ($q) use ($status) {
                                $q->where('tax_type', 'bupot')
                                  ->where('report_status', $status);
                            })
                        );
                    }),
                
                Tables\Filters\Filter::make('pph_badan_report_status')
                    ->label('Status Laporan PPh Badan')
                    ->form([
                        Select::make('value')
                            ->label('Status')
                            ->options([
                                'Belum Lapor' => 'Belum Lapor',
                                'Sudah Lapor' => 'Sudah Lapor',
                            ])
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $query, $status): Builder => $query->whereHas('taxCalculationSummaries', function ($q) use ($status) {
                                $q->where('tax_type', 'pph_badan')
                                ->where('report_status', $status);
                            })
                        );
                    }),

                // Client filter
                Tables\Filters\SelectFilter::make('client_status')
                    ->label('Client Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }
                        
                        return $query->whereHas('client', function (Builder $query) use ($data) {
                            $query->whereIn('status', $data['values']);
                        });
                    })
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ])
                    ->multiple()
                    ->default(['Active'])
                    ->preload(),

                // Payment Status Filter
                Tables\Filters\Filter::make('payment_status')
                    ->label('Status Pembayaran')
                    ->form([
                        Select::make('values')
                            ->label('Status')
                            ->options([
                                'Lebih Bayar' => 'Lebih Bayar',
                                'Kurang Bayar' => 'Kurang Bayar',
                                'Nihil' => 'Nihil',
                            ])
                            ->multiple()
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            !empty($data['values']),
                            fn (Builder $query): Builder => $query->whereHas('taxCalculationSummaries', function ($q) use ($data) {
                                $q->where('tax_type', 'ppn')
                                  ->whereIn('status_final', $data['values']);
                            })
                        );
                    }),

                // Filter per jenis kontrak — tampilkan laporan klien yang punya kontrak terpilih.
                Tables\Filters\Filter::make('contract_types')
                    ->label('Jenis Kontrak')
                    ->form([
                        Select::make('values')
                            ->label('Jenis Kontrak')
                            ->options([
                                'ppn_contract'       => 'PPN',
                                'pph_contract'       => 'PPh',
                                'bupot_contract'     => 'Bupot',
                                'pph_badan_contract' => 'PPh Badan',
                            ])
                            ->multiple()
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $cols = array_intersect(
                            $data['values'] ?? [],
                            ['ppn_contract', 'pph_contract', 'bupot_contract', 'pph_badan_contract']
                        );

                        return $query->when(
                            ! empty($cols),
                            fn (Builder $query): Builder => $query->whereHas('client', function (Builder $q) use ($cols) {
                                $q->where(function (Builder $sub) use ($cols) {
                                    foreach ($cols as $col) {
                                        $sub->orWhere($col, true);
                                    }
                                });
                            })
                        );
                    }),

            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn (\Filament\Tables\Actions\Action $action) => $action
                    ->button()
                    ->label('Filter')
                    ->icon('heroicon-m-funnel')
                    ->color('gray'),
            )
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->icon('heroicon-o-pencil')
                        ->color('info'),

                    Tables\Actions\DeleteAction::make()
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Laporan Pajak')
                        ->modalDescription('Apakah Anda yakin ingin menghapus laporan pajak ini? Tindakan ini tidak dapat dibatalkan.')
                        ->successNotificationTitle('Laporan pajak berhasil dihapus'),

                    Tables\Actions\Action::make('update_bayar_status')
                        ->label('Update Status Bayar')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->form([
                            Select::make('tax_type')
                                ->label('Jenis Pajak')
                                ->options([
                                    'ppn' => 'PPN',
                                    'pph' => 'PPh',
                                    'bupot' => 'PPh Unifikasi',
                                    'pph_badan' => 'PPh Badan',
                                ])
                                ->required()
                                ->native(false)
                                ->reactive()
                                ->afterStateUpdated(function (Set $set, Get $get, $state, TaxReport $record) {
                                    // Load current bayar_status when tax_type changes
                                    $summary = $record->taxCalculationSummaries->firstWhere('tax_type', $state);
                                    if ($summary) {
                                        $set('bayar_status', $summary->bayar_status);
                                        $set('bayar_at', $summary->bayar_at);
                                        $set('bukti_bayar', $summary->bukti_bayar);
                                    }
                                }),
                            
                            Select::make('bayar_status')
                                ->label('Status Pembayaran')
                                ->options([
                                    'Belum Bayar' => 'Belum Bayar',
                                    'Sudah Bayar' => 'Sudah Bayar',
                                ])
                                ->required()
                                ->native(false)
                                ->reactive()
                                ->visible(fn(Get $get): bool => filled($get('tax_type'))),
                            
                            Forms\Components\DatePicker::make('bayar_at')
                                ->label('Tanggal Pembayaran')
                                ->visible(fn(Get $get): bool => $get('bayar_status') === 'Sudah Bayar')
                                ->required(fn(Get $get): bool => $get('bayar_status') === 'Sudah Bayar')
                                ->default(now()),
                            
                            Forms\Components\TextInput::make('bukti_bayar')
                                ->label('Nomor NTPN / Bukti Bayar')
                                ->visible(fn(Get $get): bool => $get('bayar_status') === 'Sudah Bayar')
                                ->maxLength(100)
                                ->placeholder('Masukkan nomor NTPN atau keterangan bukti bayar'),
                        ])
                        ->fillForm(function (TaxReport $record): array {
                            // Default to PPN
                            $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                            return [
                                'tax_type' => 'ppn',
                                'bayar_status' => $ppnSummary?->bayar_status ?? 'Belum Bayar',
                                'bayar_at' => $ppnSummary?->bayar_at,
                                'bukti_bayar' => $ppnSummary?->bukti_bayar,
                            ];
                        })
                        ->action(function (TaxReport $record, array $data): void {
                            $taxType = $data['tax_type'];
                            $summary = $record->taxCalculationSummaries()->firstOrCreate(
                                ['tax_type' => $taxType],
                                [
                                    'pajak_masuk' => 0,
                                    'pajak_keluar' => 0,
                                    'selisih' => 0,
                                    'status' => 'Nihil',
                                    'saldo_final' => 0,
                                    'status_final' => 'Nihil',
                                    'report_status' => 'Belum Lapor',
                                ]
                            );

                            $summary->update([
                                'bayar_status' => $data['bayar_status'],
                                'bayar_at' => $data['bayar_status'] === 'Sudah Bayar' ? $data['bayar_at'] : null,
                                'bukti_bayar' => $data['bayar_status'] === 'Sudah Bayar' ? $data['bukti_bayar'] : null,
                            ]);

                            $taxTypeLabel = match($taxType) {
                                'ppn' => 'PPN',
                                'pph' => 'PPh',
                                'bupot' => 'PPh Unifikasi',
                                'pph_badan' => 'PPh Badan',
                            };

                            Notification::make()
                                ->title('Status Pembayaran Berhasil Diupdate')
                                ->body("Status pembayaran {$taxTypeLabel} diubah menjadi: {$data['bayar_status']}")
                                ->success()
                                ->send();
                        })
                        ->modalHeading('Update Status Pembayaran')
                        ->modalWidth('md'),

                    Tables\Actions\Action::make('update_ppn_status')
                        ->label('Update Status PPN')
                        ->icon('heroicon-o-document-check')
                        ->color(function (TaxReport $record): string {
                            $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                            return $ppnSummary && $ppnSummary->report_status === 'Sudah Lapor' ? 'success' : 'warning';
                        })
                        ->form([
                            Select::make('report_status')
                                ->label('Status Laporan PPN')
                                ->options([
                                    'Belum Lapor' => 'Belum Lapor',
                                    'Sudah Lapor' => 'Sudah Lapor',
                                ])
                                ->required()
                                ->native(false)
                                ->reactive(),
                            
                            Forms\Components\DatePicker::make('reported_at')
                                ->label('Tanggal Pelaporan')
                                ->visible(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->required(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->default(now()),
                        ])
                        ->fillForm(function (TaxReport $record): array {
                            $ppnSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                            return [
                                'report_status' => $ppnSummary?->report_status ?? 'Belum Lapor',
                                'reported_at' => $ppnSummary?->reported_at,
                            ];
                        })
                        ->action(function (TaxReport $record, array $data): void {
                            $ppnSummary = $record->taxCalculationSummaries()->firstOrCreate(
                                ['tax_type' => 'ppn'],
                                [
                                    'pajak_masuk' => 0,
                                    'pajak_keluar' => 0,
                                    'selisih' => 0,
                                    'status' => 'Nihil',
                                    'saldo_final' => 0,
                                    'status_final' => 'Nihil',
                                ]
                            );

                            $ppnSummary->update([
                                'report_status' => $data['report_status'],
                                'reported_at' => $data['report_status'] === 'Sudah Lapor' ? $data['reported_at'] : null,
                            ]);

                            Notification::make()
                                ->title('Status PPN Berhasil Diupdate')
                                ->body("Status PPN diubah menjadi: {$data['report_status']}")
                                ->success()
                                ->send();
                        })
                        ->modalHeading('Update Status Laporan PPN')
                        ->modalWidth('md'),

                    Tables\Actions\Action::make('update_pph_status')
                        ->label('Update Status PPh')
                        ->icon('heroicon-o-receipt-percent')
                        ->color(function (TaxReport $record): string {
                            $pphSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph');
                            return $pphSummary && $pphSummary->report_status === 'Sudah Lapor' ? 'success' : 'warning';
                        })
                        ->form([
                            Select::make('report_status')
                                ->label('Status Laporan PPh')
                                ->options([
                                    'Belum Lapor' => 'Belum Lapor',
                                    'Sudah Lapor' => 'Sudah Lapor',
                                ])
                                ->native(false)
                                ->required()
                                ->reactive(),
                            
                            Forms\Components\DatePicker::make('reported_at')
                                ->label('Tanggal Pelaporan')
                                ->visible(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->required(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->default(now()),
                        ])
                        ->fillForm(function (TaxReport $record): array {
                            $pphSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph');
                            return [
                                'report_status' => $pphSummary?->report_status ?? 'Belum Lapor',
                                'reported_at' => $pphSummary?->reported_at,
                            ];
                        })
                        ->action(function (TaxReport $record, array $data): void {
                            $pphSummary = $record->taxCalculationSummaries()->firstOrCreate(
                                ['tax_type' => 'pph'],
                                [
                                    'pajak_masuk' => 0,
                                    'pajak_keluar' => 0,
                                    'selisih' => 0,
                                    'status' => 'Nihil',
                                    'saldo_final' => 0,
                                    'status_final' => 'Nihil',
                                ]
                            );

                            $pphSummary->update([
                                'report_status' => $data['report_status'],
                                'reported_at' => $data['report_status'] === 'Sudah Lapor' ? $data['reported_at'] : null,
                            ]);

                            Notification::make()
                                ->title('Status PPh Berhasil Diupdate')
                                ->body("Status PPh diubah menjadi: {$data['report_status']}")
                                ->success()
                                ->send();
                        })
                        ->modalHeading('Update Status Laporan PPh')
                        ->modalWidth('md'),

                    Tables\Actions\Action::make('update_bupot_status')
                        ->label('Update Status Bupot')
                        ->icon('heroicon-o-document-text')
                        ->color(function (TaxReport $record): string {
                            $bupotSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'bupot');
                            return $bupotSummary && $bupotSummary->report_status === 'Sudah Lapor' ? 'success' : 'warning';
                        })
                        ->form([
                            Select::make('report_status')
                                ->label('Status Laporan Bupot')
                                ->options([
                                    'Belum Lapor' => 'Belum Lapor',
                                    'Sudah Lapor' => 'Sudah Lapor',
                                ])
                                ->native(false)
                                ->required()
                                ->reactive(),
                            
                            Forms\Components\DatePicker::make('reported_at')
                                ->label('Tanggal Pelaporan')
                                ->visible(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->required(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->default(now()),
                        ])
                        ->fillForm(function (TaxReport $record): array {
                            $bupotSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'bupot');
                            return [
                                'report_status' => $bupotSummary?->report_status ?? 'Belum Lapor',
                                'reported_at' => $bupotSummary?->reported_at,
                            ];
                        })
                        ->action(function (TaxReport $record, array $data): void {
                            $bupotSummary = $record->taxCalculationSummaries()->firstOrCreate(
                                ['tax_type' => 'bupot'],
                                [
                                    'pajak_masuk' => 0,
                                    'pajak_keluar' => 0,
                                    'selisih' => 0,
                                    'status' => 'Nihil',
                                    'saldo_final' => 0,
                                    'status_final' => 'Nihil',
                                ]
                            );

                            $bupotSummary->update([
                                'report_status' => $data['report_status'],
                                'reported_at' => $data['report_status'] === 'Sudah Lapor' ? $data['reported_at'] : null,
                            ]);

                            Notification::make()
                                ->title('Status Bupot Berhasil Diupdate')
                                ->body("Status Bupot diubah menjadi: {$data['report_status']}")
                                ->success()
                                ->send();
                        })
                        ->modalHeading('Update Status Laporan Bupot')
                        ->modalWidth('md'),
                    
                    Tables\Actions\Action::make('update_pph_badan_status')
                        ->label('Update Status PPh Badan')
                        ->icon('heroicon-o-building-office')
                        ->color(function (TaxReport $record): string {
                            $pphBadanSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph_badan');
                            return $pphBadanSummary && $pphBadanSummary->report_status === 'Sudah Lapor' ? 'success' : 'warning';
                        })
                        ->form([
                            Select::make('report_status')
                                ->label('Status Laporan PPh Badan')
                                ->options([
                                    'Belum Lapor' => 'Belum Lapor',
                                    'Sudah Lapor' => 'Sudah Lapor',
                                ])
                                ->native(false)
                                ->required()
                                ->reactive(),
                            
                            Forms\Components\DatePicker::make('reported_at')
                                ->label('Tanggal Pelaporan')
                                ->visible(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->required(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->default(now()),
                        ])
                        ->fillForm(function (TaxReport $record): array {
                            $pphBadanSummary = $record->taxCalculationSummaries->firstWhere('tax_type', 'pph_badan');
                            return [
                                'report_status' => $pphBadanSummary?->report_status ?? 'Belum Lapor',
                                'reported_at' => $pphBadanSummary?->reported_at,
                            ];
                        })
                        ->action(function (TaxReport $record, array $data): void {
                            $pphBadanSummary = $record->taxCalculationSummaries()->firstOrCreate(
                                ['tax_type' => 'pph_badan'],
                                [
                                    'pajak_masuk' => 0,
                                    'pajak_keluar' => 0,
                                    'selisih' => 0,
                                    'status' => 'Nihil',
                                    'saldo_final' => 0,
                                    'status_final' => 'Nihil',
                                ]
                            );

                            $pphBadanSummary->update([
                                'report_status' => $data['report_status'],
                                'reported_at' => $data['report_status'] === 'Sudah Lapor' ? $data['reported_at'] : null,
                            ]);

                            Notification::make()
                                ->title('Status PPh Badan Berhasil Diupdate')
                                ->body("Status PPh Badan diubah menjadi: {$data['report_status']}")
                                ->success()
                                ->send();
                        })
                        ->modalHeading('Update Status Laporan PPh Badan')
                        ->modalWidth('md'),
                    ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->label('Actions')
                    ->size('sm')
                    ->color('gray')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Laporan Pajak Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus laporan pajak yang dipilih? Tindakan ini tidak dapat dibatalkan.')
                        ->successNotificationTitle('Laporan pajak berhasil dihapus'),
                    
                    Tables\Actions\BulkAction::make('bulk_update_status')
                        ->label('Update Status Laporan')
                        ->icon('heroicon-o-document-check')
                        ->color('info')
                        ->form([
                            Select::make('tax_type')
                                ->label('Jenis Laporan')
                                ->options([
                                    'ppn' => 'PPN',
                                    'pph' => 'PPh',
                                    'bupot' => 'Bupot',
                                    'pph_badan' => 'PPh Badan',
                                ])
                                ->required()
                                ->reactive(),
                            
                            Select::make('report_status')
                                ->label('Status Baru')
                                ->options([
                                    'Belum Lapor' => 'Belum Lapor',
                                    'Sudah Lapor' => 'Sudah Lapor',
                                ])
                                ->required()
                                ->native(false)
                                ->reactive(),
                            
                            Forms\Components\DatePicker::make('reported_at')
                                ->label('Tanggal Pelaporan')
                                ->visible(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->required(fn(Get $get): bool => $get('report_status') === 'Sudah Lapor')
                                ->default(now()),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $taxType = $data['tax_type'];
                            $reportStatus = $data['report_status'];
                            $reportedAt = $reportStatus === 'Sudah Lapor' ? $data['reported_at'] : null;
                            
                            $count = 0;
                            foreach ($records as $record) {
                                $summary = $record->taxCalculationSummaries()->firstOrCreate(
                                    ['tax_type' => $taxType],
                                    [
                                        'pajak_masuk' => 0,
                                        'pajak_keluar' => 0,
                                        'selisih' => 0,
                                        'status' => 'Nihil',
                                        'saldo_final' => 0,
                                        'status_final' => 'Nihil',
                                    ]
                                );
                                
                                $summary->update([
                                    'report_status' => $reportStatus,
                                    'reported_at' => $reportedAt,
                                ]);
                                
                                $count++;
                            }
                            
                            $taxTypeLabel = match($taxType) {
                                'ppn' => 'PPN',
                                'pph' => 'PPh', 
                                'bupot' => 'Bupot',
                                'pph_badan' => 'PPh Badan',
                            };
                            
                            Notification::make()
                                ->title('Status Berhasil Diupdate')
                                ->body("Status laporan {$taxTypeLabel} untuk {$count} record berhasil diubah menjadi: {$reportStatus}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->modalHeading('Update Status Laporan (Bulk)')
                        ->modalWidth('md'),
                ]),
            ])
            ->recordUrl(fn (TaxReport $record): string => 
                static::getUrl('view', ['record' => $record])
            )
            ->emptyStateHeading('Belum Ada Laporan Pajak')
            ->emptyStateDescription('Laporan pajak akan muncul di sini setelah Anda membuatnya.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Buat Laporan Pajak')
                    ->icon('heroicon-o-plus')
                    ->url(static::getUrl('create')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\IncomeTaxsRelationManager::class,
            RelationManagers\BupotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            // 'dashboard' => Pages\TaxReportDashboard::route('/dashboard'),
            'index' => Pages\ListTaxReports::route('/'),
            'create' => Pages\CreateTaxReport::route('/create'),
            'view' => Pages\ViewTaxReport::route('/{record}'),
            'edit' => Pages\EditTaxReport::route('/{record}/edit'),
        ];
    }
}