<?php

namespace App\Livewire\Client\Panel\TaxReport\Components;

use App\Models\Invoice;
use App\Models\TaxReport;
use App\Models\UserClient;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Collection;

// Import services
use App\Services\ClientTypeService;
use App\Services\FileManagementService;

class InvoiceTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    #[Locked]
    public $taxReportId;
    #[Locked]
    public $taxReport;

    public function mount($taxReportId)
    {
        $this->taxReportId = $taxReportId;
        
        // Verify access
        $this->verifyAccess();
        
        $this->taxReport = TaxReport::with('client')->findOrFail($taxReportId);
    }

    /**
     * Verify user has access to the client
     */
    protected function verifyAccess()
    {
        $taxReport = TaxReport::findOrFail($this->taxReportId);
        
        $hasAccess = UserClient::where('user_id', auth()->id())
            ->where('client_id', $taxReport->client_id)
            ->exists();
            
        if (!$hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke laporan pajak ini.');
        }
    }

    /**
     * Get view-only form schema for invoices
     */
    protected function getViewFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Informasi Faktur')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('invoice_number')
                                ->label('Nomor Faktur')
                                ->disabled(),
                            
                            Forms\Components\DatePicker::make('invoice_date')
                                ->label('Tanggal Faktur')
                                ->disabled(),
                            
                            Forms\Components\TextInput::make('company_name')
                                ->label('Nama Perusahaan')
                                ->disabled(),
                            
                            Forms\Components\TextInput::make('npwp')
                                ->label('NPWP')
                                ->disabled(),
                            
                            Forms\Components\Select::make('type')
                                ->label('Jenis Faktur')
                                ->options([
                                    'Faktur Keluaran' => 'Faktur Keluaran',
                                    'Faktur Masuk' => 'Faktur Masuk',
                                ])
                                ->disabled(),
                            
                            Forms\Components\Select::make('client_type')
                                ->label('Tipe Client')
                                ->options(ClientTypeService::getClientTypeOptions())
                                ->disabled(),
                        ]),
                ]),
            
            Forms\Components\Section::make('Informasi Pajak')
                ->schema([
                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\Select::make('ppn_percentage')
                                ->label('Tarif PPN')
                                ->options([
                                    '11' => '11%',
                                    '12' => '12%',
                                ])
                                ->disabled(),
                            
                            Forms\Components\TextInput::make('dpp')
                                ->label('DPP')
                                ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.'))
                                ->prefix('Rp')
                                ->disabled(),
                            
                            Forms\Components\TextInput::make('dpp_nilai_lainnya')
                                ->label('DPP Nilai Lainnya')
                                ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.'))
                                ->prefix('Rp')
                                ->disabled()
                                ->visible(fn ($get) => $get('ppn_percentage') === '12'),
                            
                            Forms\Components\TextInput::make('ppn')
                                ->label('PPN')
                                ->formatStateUsing(fn ($state) => number_format((float) $state, 0, ',', '.'))
                                ->prefix('Rp')
                                ->disabled(),
                            
                            Forms\Components\Toggle::make('has_ppn')
                                ->label('Subject PPN')
                                ->disabled(),
                            
                            Forms\Components\Toggle::make('nihil')
                                ->label('Nihil')
                                ->disabled(),
                        ]),
                ]),
            
            Forms\Components\Section::make('Catatan')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->visible(fn ($get) => !empty($get('notes'))),
            
            Forms\Components\Section::make('Informasi Revisi')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('revision_number')
                                ->label('Nomor Revisi')
                                ->disabled(),
                            
                            Forms\Components\Textarea::make('revision_reason')
                                ->label('Alasan Revisi')
                                ->rows(2)
                                ->disabled()
                                ->columnSpanFull(),
                        ]),
                ])
                ->visible(fn ($get) => $get('is_revision')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Invoice::query()->where('tax_report_id', $this->taxReportId)->withCount('revisions'))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor Faktur')
                    ->searchable()
                    ->sortable()
                    ->description(function ($record) {
                        if (!$record) return null;
                        
                        if ($record->is_revision) {
                            return "Revisi #{$record->revision_number}";
                        }
                        if ($record->revisions_count > 0) {
                            return "Memiliki {$record->revisions_count} revisi";
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Nama Perusahaan')
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                // A category, not a status: neutral (DESIGN.md, Badges).
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis Faktur')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state === 'Faktur Masuk' ? 'Masukan' : ($state === 'Faktur Keluaran' ? 'Keluaran' : $state))
                    ->tooltip(function ($record) {
                        if (!$record || $record->type !== 'Faktur Masuk') {
                            return null;
                        }
                        
                        return $record->is_business_related 
                            ? 'Faktur Masukan untuk aktivitas bisnis utama'
                            : 'Faktur Masukan untuk keperluan non-bisnis';
                    }),

                Tables\Columns\TextColumn::make('ppn_percentage')
                    ->label('Tarif PPN')
                    ->wrapHeader()
                    ->suffix('%')
                    ->getStateUsing(fn ($record) => $record->ppn_percentage ?? '11')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dpp_nilai_lainnya')
                    ->label('DPP Nilai Lainnya')
                    ->formatStateUsing(fn ($state) => self::rupiah($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('DPP Nilai Lainnya, untuk tarif 12%'),

                Tables\Columns\TextColumn::make('dpp')
                    ->label('DPP')
                    ->formatStateUsing(fn ($state) => self::rupiah($state))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn ($state) => self::rupiah($state))
                            ->label('Peredaran Bruto')
                            ->query(fn (QueryBuilder $query) => $query->where(function ($q) {
                                // Include original invoices that don't have any revisions
                                $q->where('is_revision', false)
                                ->whereNotExists(function ($subQuery) {
                                    $subQuery->select(\DB::raw(1))
                                            ->from('invoices as revisions')
                                            ->whereColumn('revisions.original_invoice_id', 'invoices.id')
                                            ->where('revisions.is_revision', true);
                                });
                            })->orWhere(function ($q) {
                                // Include only the latest revision for each original invoice
                                $q->where('is_revision', true)
                                ->whereIn('id', function ($subQuery) {
                                    $subQuery->selectRaw('MAX(id)')
                                            ->from('invoices as latest_revisions')
                                            ->where('latest_revisions.is_revision', true)
                                            ->whereNotNull('latest_revisions.original_invoice_id')
                                            ->groupBy('latest_revisions.original_invoice_id');
                                });
                            }))
                    )
                    ->description(function ($record) {
                        if (($record->ppn_percentage ?? '11') === '12' && ($record->dpp_nilai_lainnya ?? 0) > 0) {
                            return 'Dihitung dari DPP Nilai Lainnya';
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('ppn')
                    ->label('PPN')
                    ->formatStateUsing(fn ($state) => self::rupiah($state))
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn ($state) => self::rupiah($state))
                            ->label('Total PPN')
                            ->query(fn (QueryBuilder $query) => $query->where(function ($q) {
                                // Include original invoices that don't have any revisions
                                $q->where('is_revision', false)
                                ->whereNotExists(function ($subQuery) {
                                    $subQuery->select(\DB::raw(1))
                                            ->from('invoices as revisions')
                                            ->whereColumn('revisions.original_invoice_id', 'invoices.id')
                                            ->where('revisions.is_revision', true);
                                });
                            })->orWhere(function ($q) {
                                // Include only the latest revision for each original invoice
                                $q->where('is_revision', true)
                                ->whereIn('id', function ($subQuery) {
                                    $subQuery->selectRaw('MAX(id)')
                                            ->from('invoices as latest_revisions')
                                            ->where('latest_revisions.is_revision', true)
                                            ->whereNotNull('latest_revisions.original_invoice_id')
                                            ->groupBy('latest_revisions.original_invoice_id');
                                });
                            }))
                    ),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Tanggal Faktur')
                    ->wrapHeader()
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->groups([
                Tables\Grouping\Group::make('type')->label('Jenis Faktur')->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('type')
            ->groupingSettingsInDropdownOnDesktop()
            ->headerActionsPosition(Tables\Actions\HeaderActionsPosition::Bottom)
            ->defaultSort('invoice_date', 'desc')
            ->searchPlaceholder('Cari faktur')
            ->recordAction('view')
            ->recordUrl(null)
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Faktur')
                    ->options([
                        'Faktur Keluaran' => 'Faktur Keluaran',
                        'Faktur Masuk' => 'Faktur Masuk',
                    ]),

                Tables\Filters\SelectFilter::make('client_type')
                    ->label('Tipe Client')
                    ->options(ClientTypeService::getClientTypeOptions()),

                Tables\Filters\Filter::make('has_ppn')
                    ->label('Subject PPN')
                    ->query(fn (Builder $query): Builder => $query->where('has_ppn', true)),

                Tables\Filters\Filter::make('no_ppn')
                    ->label('Tidak Subject PPN')
                    ->query(fn (Builder $query): Builder => $query->where('has_ppn', false)),

                Tables\Filters\Filter::make('is_revision')
                    ->label('Hanya Revisi')
                    ->query(fn (Builder $query): Builder => $query->where('is_revision', true)),

                Tables\Filters\Filter::make('originals_only')
                    ->label('Hanya Asli')
                    ->query(fn (Builder $query): Builder => $query->where('is_revision', false)),

                Tables\Filters\Filter::make('has_bukti_setor')
                    ->label('Memiliki Bukti Setor')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('bukti_setor')->where('bukti_setor', '!=', '')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_all')
                    ->label('Ekspor Semua')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->form([
                        Forms\Components\Radio::make('format')
                            ->label('Pilih Format Ekspor')
                            ->options([
                                'excel' => 'Excel (.xlsx)',
                                'pdf' => 'PDF (.pdf)',
                            ])
                            ->default('excel')
                            ->required()
                            ->inline()
                            ->descriptions([
                                'excel' => 'Format Excel untuk analisis dan editing data',
                                'pdf' => 'Format PDF untuk printing dan archiving',
                            ]),
                    ])
                    ->action(function (array $data) {
                        $monthYear = $this->exportPeriod();

                        if ($data['format'] === 'pdf') {
                            $exporter = new \App\Exports\TaxReport\PPN\TaxReportInvoicesPdfExport($this->taxReport);
                            return $exporter->download();
                        } else {
                            $filename = 'Faktur_' . $monthYear . '.xlsx';
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\TaxReportInvoicesExport($this->taxReport),
                                $filename
                            );
                        }
                    })
                    ->modalHeading('Ekspor Semua Faktur')
                    ->modalDescription('Pilih format file yang ingin Anda ekspor')
                    ->modalSubmitActionLabel('Ekspor')
                    ->modalWidth('md'),
            ])
            ->actions([
                // One menu per row keeps the table inside the card on laptops; a row click opens Lihat Detail.
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat Detail')
                        ->modalHeading(fn ($record) => 'Detail Faktur ' . $record->invoice_number)
                        ->modalCancelActionLabel('Tutup')
                        ->modalWidth('7xl')
                        ->form($this->getViewFormSchema()),

                    Tables\Actions\Action::make('view_revisions')
                        ->label('Lihat Revisi')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn ($record) => $record && !$record->is_revision && $record->revisions_count > 0)
                        ->modalContent(function ($record) {
                            $revisions = $record->revisions()->orderBy('revision_number')->get();
                            return view('components.invoices.revisions-modal', [
                                'originalInvoice' => $record,
                                'revisions' => $revisions
                            ]);
                        })
                        ->modalHeading(fn ($record) => 'Revisi Faktur: ' . $record->invoice_number)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalWidth('7xl')
                        ->tooltip(function ($record) {
                            return "Lihat {$record->revisions_count} revisi dari faktur ini";
                        }),

                    Tables\Actions\Action::make('view_original')
                        ->label('Lihat Faktur Asli')
                        ->icon('heroicon-o-document')
                        ->color('gray')
                        ->visible(fn ($record) => $record && $record->is_revision)
                        ->modalContent(function ($record) {
                            if ($record->originalInvoice) {
                                return view('components.invoices.original-invoice-modal', [
                                    'originalInvoice' => $record->originalInvoice
                                ]);
                            }
                            return view('components.invoices.original-invoice-not-found');
                        })
                        ->modalHeading(fn ($record) => 'Faktur Asli: ' . ($record->originalInvoice ? $record->originalInvoice->invoice_number : 'Tidak Ditemukan'))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalWidth('7xl')
                        ->tooltip('Lihat detail lengkap faktur asli yang direvisi'),

                    Tables\Actions\Action::make('view_bukti_setor')
                        ->label('Lihat Bukti Setor')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn ($record) => $record && !empty($record->bukti_setor))
                        ->url(fn ($record) => asset('storage/' . $record->bukti_setor))
                        ->openUrlInNewTab()
                        ->tooltip('Lihat bukti setor pajak'),

                    Tables\Actions\Action::make('download')
                        ->label('Unduh Berkas')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn ($record) => $record && $record->file_path ? asset('storage/' . $record->file_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record && $record->file_path)
                        ->tooltip('Unduh berkas faktur pajak'),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Aksi faktur'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->form([
                            Forms\Components\Radio::make('format')
                                ->label('Pilih Format Ekspor')
                                ->options([
                                    'excel' => 'Excel (.xlsx)',
                                    'pdf' => 'PDF (.pdf)',
                                ])
                                ->default('excel')
                                ->required()
                                ->inline()
                                ->descriptions([
                                    'excel' => 'Format Excel untuk analisis dan editing data',
                                    'pdf' => 'Format PDF untuk printing dan archiving',
                                ]),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $selectedIds = $records->pluck('id')->toArray();
                            $monthYear = $this->exportPeriod();
                            
                            if ($data['format'] === 'pdf') {
                                $exporter = new \App\Exports\TaxReport\PPN\TaxReportInvoicesPdfExport($this->taxReport, $selectedIds);
                                return $exporter->download();
                            } else {
                                $filename = 'Faktur_Terpilih_' . $monthYear . '.xlsx';
                                return \Maatwebsite\Excel\Facades\Excel::download(
                                    new \App\Exports\TaxReportInvoicesExport($this->taxReport, $selectedIds),
                                    $filename
                                );
                            }
                        })
                        ->modalHeading('Ekspor Faktur Terpilih')
                        ->modalDescription(function (Collection $records) {
                            $count = $records->count();
                            return "Anda akan mengekspor {$count} faktur yang terpilih. Pilih format file yang diinginkan.";
                        })
                        ->modalSubmitActionLabel('Ekspor')
                        ->modalWidth('md')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Belum Ada Faktur Pajak')
            ->emptyStateDescription('Belum ada faktur pajak untuk periode ini.')
            ->emptyStateIcon('heroicon-o-document-duplicate');
    }

    /** "Rp 1.234.567": Indonesian grouping, no decimals (DESIGN.md, The Aligned Figures Rule). */
    public static function rupiah($value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    /** "Januari_2026": export file names use the report's period, not today's year. */
    protected function exportPeriod(): string
    {
        return FileManagementService::convertToIndonesianMonth($this->taxReport->month) . '_' . ($this->taxReport->year ?: $this->taxReport->created_at?->year);
    }

    public function render()
    {
        return view('livewire.client.panel.tax-report.components.invoice-table');
    }
}