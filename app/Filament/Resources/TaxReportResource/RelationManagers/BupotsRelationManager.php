<?php

namespace App\Filament\Resources\TaxReportResource\RelationManagers;

use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Swis\Filament\Activitylog\Tables\Actions\ActivitylogAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;


class BupotsRelationManager extends RelationManager
{
    protected static string $relationship = 'bupots';

    public function isReadOnly(): bool
    {
        return false;
    }
    protected static ?string $title = 'Bupot';

    /**
     * Generate dynamic directory path for file uploads
     */
    private function generateDirectoryPath($get): string
    {
        // Get tax report to access client information
        $taxReportId = $get('tax_report_id') ?? $this->getOwnerRecord()->id;
        $taxReport = \App\Models\TaxReport::with('client')->find($taxReportId);
        
        // Default values
        $clientName = 'unknown-client';
        $monthName = 'unknown-month';
        
        if ($taxReport && $taxReport->client) {
            // Clean client name for folder structure
            $clientName = Str::slug($taxReport->client->name);
            
            // Convert month from tax report to Indonesian month name
            $monthName = $this->convertToIndonesianMonth($taxReport->month);
        }
        
        return "clients/{$clientName}/SPT/{$monthName}/Bupot";
    }

    /**
     * Generate filename for Bupot documents
     */
    private function generateFileName($get, $originalFileName, $prefix = 'Bupot'): string
    {
        $companyName = $get('company_name') ?? 'Unknown Company';
        $bupotType = $get('bupot_type') ?? 'Unknown Type';
        $pphType = $get('pph_type') ?? 'Unknown PPh';
        
        // Clean company name for filename
        $cleanCompanyName = Str::slug($companyName);
        $cleanBupotType = Str::slug($bupotType);
        $cleanPphType = Str::slug($pphType);
        
        // Get file extension
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        
        return "{$prefix}-{$cleanPphType}-{$cleanBupotType}-{$cleanCompanyName}.{$extension}";
    }

    /**
     * Convert month format to Indonesian month names
     */
    private function convertToIndonesianMonth($month): string
    {
        // Handle different month formats
        $monthNames = [
            '01' => 'Januari', '1' => 'Januari', 'january' => 'Januari', 'jan' => 'Januari',
            '02' => 'Februari', '2' => 'Februari', 'february' => 'Februari', 'feb' => 'Februari',
            '03' => 'Maret', '3' => 'Maret', 'march' => 'Maret', 'mar' => 'Maret',
            '04' => 'April', '4' => 'April', 'april' => 'April', 'apr' => 'April',
            '05' => 'Mei', '5' => 'Mei', 'may' => 'Mei',
            '06' => 'Juni', '6' => 'Juni', 'june' => 'Juni', 'jun' => 'Juni',
            '07' => 'Juli', '7' => 'Juli', 'july' => 'Juli', 'jul' => 'Juli',
            '08' => 'Agustus', '8' => 'Agustus', 'august' => 'Agustus', 'aug' => 'Agustus',
            '09' => 'September', '9' => 'September', 'september' => 'September', 'sep' => 'September',
            '10' => 'Oktober', 'october' => 'Oktober', 'oct' => 'Oktober',
            '11' => 'November', 'november' => 'November', 'nov' => 'November',
            '12' => 'Desember', 'december' => 'Desember', 'dec' => 'Desember',
        ];

        $cleanMonth = strtolower(trim($month));
        
        // If it's a date format like "2025-01", extract the month part
        if (preg_match('/\d{4}-(\d{1,2})/', $month, $matches)) {
            $cleanMonth = $matches[1];
        }
        
        return $monthNames[$cleanMonth] ?? Str::title($cleanMonth);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('created_by')
                    ->default(auth()->id()),
                    
                Wizard::make([
                    Wizard\Step::make('Informasi Umum')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('Data Bukti Potong')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Select::make('invoice_id')
                                        ->label('Faktur Terkait')
                                        ->options(function () {
                                            // Get invoices related to the current tax report
                                            $taxReport = $this->getOwnerRecord();
                                            if ($taxReport) {
                                                return Invoice::where('tax_report_id', $taxReport->id)
                                                    ->pluck('invoice_number', 'id')
                                                    ->toArray();
                                            }
                                            return [];
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Pilih faktur (opsional)')
                                        ->live()
                                        ->columnSpanFull()
                                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                                            if ($state) {
                                                $invoice = Invoice::find($state);
                                                if ($invoice) {
                                                    $set('company_name', $invoice->company_name);
                                                    $set('npwp', $invoice->npwp);
                                                    
                                                    // Clean DPP value from invoice and format it properly
                                                    $cleanedDpp = preg_replace('/[^0-9.]/', '', $invoice->dpp ?? '0');
                                                    if (is_numeric($cleanedDpp)) {
                                                        // Format for display
                                                        $set('dpp', number_format(floatval($cleanedDpp), 2, '.', ','));
                                                        
                                                        // Also calculate and set bupot_amount based on percentage
                                                        $bupotPercentage = floatval('2'); // Default to 2%
                                                        $bupotAmount = floatval($cleanedDpp) * ($bupotPercentage / 100);
                                                        $set('bupot_amount', number_format($bupotAmount, 2, '.', ','));
                                                    }
                                                    
                                                    // Set bupot type based on invoice type (REVERSED logic as requested)
                                                    if ($invoice->type === 'Faktur Masukan') {
                                                        $set('bupot_type', 'Bupot Keluaran');
                                                    } elseif ($invoice->type === 'Faktur Keluaran') {
                                                        $set('bupot_type', 'Bupot Masukan');
                                                    }
                                                }
                                            }
                                        }),
                                        
                                    Forms\Components\Select::make('tax_period')
                                        ->label('Periode Pajak')
                                        ->native(false)
                                        ->options([
                                            'Januari' => 'Januari',
                                            'Februari' => 'Februari',
                                            'Maret' => 'Maret',
                                            'April' => 'April',
                                            'Mei' => 'Mei',
                                            'Juni' => 'Juni',
                                            'Juli' => 'Juli',
                                            'Agustus' => 'Agustus',
                                            'September' => 'September',
                                            'Oktober' => 'Oktober',
                                            'November' => 'November',
                                            'Desember' => 'Desember',
                                        ])
                                        ->default(function() {
                                            // Get current month in Indonesian
                                            $months = [
                                                1 => 'Januari',
                                                2 => 'Februari',
                                                3 => 'Maret',
                                                4 => 'April',
                                                5 => 'Mei',
                                                6 => 'Juni',
                                                7 => 'Juli',
                                                8 => 'Agustus',
                                                9 => 'September',
                                                10 => 'Oktober',
                                                11 => 'November',
                                                12 => 'Desember',
                                            ];
                                            $currentMonth = (int)date('n');
                                            return $months[$currentMonth];
                                        })
                                        ->required()
                                        ->helperText('Pilih bulan untuk periode pajak ini'),
                                        
                                    Forms\Components\TextInput::make('company_name')
                                        ->label('Nama Perusahaan')
                                        ->required()
                                        ->maxLength(255),
                                        
                                    Forms\Components\TextInput::make('npwp')
                                        ->label('NPWP')
                                        ->required()
                                        ->placeholder('00.000.000.0-000.000')
                                        ->helperText('Format: 00.000.000.0-000.000')
                                        ->maxLength(255),
                                        

                                        
                                    Forms\Components\Select::make('pph_type')
                                        ->label('Jenis PPh')
                                        ->required()
                                        ->native(false)
                                        ->options([
                                            'PPh 21' => 'PPh 21',
                                            'PPh 22' => 'PPh 22',
                                            'PPh 23' => 'PPh 23',
                                        ])
                                        ->default('PPh 23'),
                                    Forms\Components\Select::make('bupot_type')
                                        ->label('Jenis Bukti Potong')
                                        ->required()
                                        ->native(false)
                                        ->options([
                                            'Bupot Masukan' => 'Bupot Masukan',
                                            'Bupot Keluaran' => 'Bupot Keluaran',
                                        ])
                                        ->columnSpanFull()
                                        ->default('Bupot Keluaran'),
                                ]),
                        ]),
                        
                    Wizard\Step::make('Nilai Pajak')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Section::make('Detail Nominal')
                                ->columns(2)
                                ->schema([
                                    // In the Nilai Pajak section
                                    Forms\Components\TextInput::make('dpp')
                                        ->label('DPP (Dasar Pengenaan Pajak)')
                                        ->required()
                                        ->prefix('Rp')
                                        ->placeholder('0.00')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->dehydrateStateUsing(fn ($state) => preg_replace('/[^0-9.]/', '', $state))
                                        ->rules(['required'])
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                            // Clean the input by removing non-numeric characters except decimal point
                                            $cleanedInput = preg_replace('/[^0-9.]/', '', $state);
                                            
                                            if (is_numeric($cleanedInput) && floatval($cleanedInput) > 0) {
                                                // Get percentage and calculate bupot amount
                                                $bupotPercentage = floatval($get('bupot_percentage') ?? 2); // Default to 2% if not set
                                                $bupotAmount = floatval($cleanedInput) * ($bupotPercentage / 100);
                                                
                                                // Format the calculated amount with Indonesian money format
                                                $set('bupot_amount', number_format($bupotAmount, 2, '.', ','));
                                            }
                                        }),
                                        
                                    // New field for BUPOT percentage selection
                                    Forms\Components\Select::make('bupot_percentage')
                                        ->label('Persentase Bukti Potong')
                                        ->options([
                                            '1.2' => '1.2%',
                                            '1.5' => '1.5%',
                                            '1.75' => '1.75%',
                                            '2' => '2%',
                                        ])
                                        ->native(false)
                                        ->required()
                                        ->live()
                                        ->helperText('Pilih persentase bukti potong yang berlaku')
                                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                            $dpp = $get('dpp');
                                            
                                            $cleanedInput = preg_replace('/[^0-9.]/', '', $dpp);
                                            
                                            if (is_numeric($cleanedInput) && floatval($cleanedInput) > 0) {
                                                $percentage = floatval($state) / 100;
                                                $bupotAmount = floatval($cleanedInput) * $percentage;
                                                
                                                $set('bupot_amount', number_format($bupotAmount, 2, '.', ','));
                                            }
                                        }),
                                        
                                    Forms\Components\TextInput::make('bupot_amount')
                                        ->label('Nilai Bukti Potong')
                                        ->required()
                                        ->prefix('Rp')
                                        ->placeholder('0.00')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->dehydrateStateUsing(fn ($state) => preg_replace('/[^0-9.]/', '', $state))
                                        ->rules(['required'])
                                        ->helperText(function (Forms\Get $get) {
                                            $percentage = $get('bupot_percentage') ?? '2';
                                            return "Otomatis dihitung sebagai {$percentage}% dari DPP";
                                        }),
                                ]),
                        ]),
                        
                    Wizard\Step::make('Dokumen')
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Section::make('Bukti Dokumen')
                                ->schema([
                                    Forms\Components\FileUpload::make('file_path')
                                        ->label('Bukti Potong')
                                        ->required()
                                        ->disk('public')
                                        ->openable()
                                        ->downloadable()
                                        ->directory(function (Forms\Get $get) {
                                            return $this->generateDirectoryPath($get);
                                        })
                                        ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Forms\Get $get): string {
                                            return $this->generateFileName($get, $file->getClientOriginalName(), 'Bupot');
                                        })
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                                        ->helperText(function (Forms\Get $get) {
                                            $path = $this->generateDirectoryPath($get);
                                            return "Akan disimpan di: storage/{$path}/Bupot-[PPh Type]-[Bupot Type]-[Company].[ext]";
                                        })
                                        ->columnSpanFull(),
                                        
                                    // New field for bukti setor (optional)
                                        Forms\Components\FileUpload::make('bukti_setor')
                                            ->label('Bukti Setor (Opsional)')
                                            ->openable()
                                            ->downloadable()
                                            ->disk('public')
                                            ->directory(function (Forms\Get $get) {
                                                $basePath = $this->generateDirectoryPath($get);
                                                return $basePath . '/Bukti-Setor';
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Forms\Get $get): string {
                                                return $this->generateFileName($get, $file->getClientOriginalName(), 'Bukti-Setor-Bupot');
                                            })
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                                            ->helperText(function (Forms\Get $get) {
                                                $path = $this->generateDirectoryPath($get);
                                                return "Akan disimpan di: storage/{$path}/Bukti-Setor/";
                                            })
                                            ->columnSpanFull(),
                                        
                                    Forms\Components\RichEditor::make('notes')
                                        ->label('Catatan')
                                        ->placeholder('Tambahkan catatan relevan tentang bukti potong ini')
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->skippable()
                ->persistStepInQueryString('bupot-wizard-step')
                ->columnSpanFull(),
            ]);
    }

    /** Bupot count and status on the tax report page follow the table. */
    public function bupotsChanged(): void
    {
        $this->dispatch('tax-report-changed', taxReportId: $this->getOwnerRecord()->getKey());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('createdBy'))
            ->heading('Bukti potong PPh Unifikasi')
            ->recordTitleAttribute('company_name')
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Lawan transaksi')
                    ->weight('semibold')
                    ->description(fn ($record) => \App\Livewire\Clients\ClientListClickup::formatNpwp($record->npwp))
                    ->searchable()
                    ->sortable(),
                // Shown under the counterparty; kept as a column so it stays searchable.
                Tables\Columns\TextColumn::make('npwp')
                    ->label('NPWP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('bupot_type')
                    ->label('Jenis')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('pph_type')
                    ->label('PPh')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('dpp')
                    ->label('DPP')
                    ->formatStateUsing(fn ($state) => \App\Services\TaxReport\ReportOverview::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bupot_amount')
                    ->label('Nilai bupot')
                    ->formatStateUsing(fn ($state) => \App\Services\TaxReport\ReportOverview::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable(),
                    
                // New column for bukti setor
                Tables\Columns\IconColumn::make('has_bukti_setor')
                    ->label('Bukti Setor')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        return !empty($record->bukti_setor);
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('primary')
                    ->falseColor('danger')
                    ->tooltip(function ($record) {
                        if (!empty($record->bukti_setor)) {
                            return "Bukti setor tersedia";
                        }
                        
                        return "Bukti setor belum diupload";
                    }),
                    
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Faktur Terkait')
                    ->placeholder('-')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Oleh')
                    ->placeholder('Sistem')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bupot_type')
                    ->label('Jenis Bukti Potong')
                    ->options([
                        'Bupot Keluaran' => 'Bupot Keluaran',
                        'Bupot Masukan' => 'Bupot Masukan',
                    ]),
                    
                Tables\Filters\SelectFilter::make('pph_type')
                    ->label('Jenis PPh')
                    ->options([
                        'PPh 21' => 'PPh 21',
                        'PPh 23' => 'PPh 23',
                    ]),
                    
                Tables\Filters\Filter::make('has_invoice')
                    ->label('Terkait Faktur')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('invoice_id')),
                    
                // New filter for bukti setor
                Tables\Filters\Filter::make('has_bukti_setor')
                    ->label('Memiliki Bukti Setor')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('bukti_setor')->where('bukti_setor', '!=', '')),
                    
                Tables\Filters\Filter::make('no_bukti_setor')
                    ->label('Belum Ada Bukti Setor')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('bukti_setor')->orWhere('bukti_setor', '');
                    })),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Bukti Potong')
                    ->modalHeading('Tambah Bukti Potong')
                    ->modalWidth('7xl')
                    ->successNotificationTitle('Bukti potong berhasil ditambahkan')
                    ->disabled(function () {
                        // Get the tax report first
                        $taxReport = $this->getOwnerRecord();
                        
                        // If we have a tax report, check the client's contract status
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            
                            // Disable the button if the client doesn't have an active bupot_contract
                            return !($client && $client->bupot_contract);
                        }
                        
                        return true; // Disable if no tax report is found
                    })
                    ->tooltip(function () {
                        $taxReport = $this->getOwnerRecord();
                        
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            
                            if (!$client || !$client->bupot_contract) {
                                return 'Klien tidak memiliki kontrak BUPOT aktif. Aktifkan kontrak BUPOT terlebih dahulu.';
                            }
                        }
                        
                        return 'Tambah Bukti Potong';
                    })
                    ->before(function (array $data) {
                        // Get the tax report
                        $taxReport = $this->getOwnerRecord();
                        
                        if ($taxReport) {
                            // Double-check the client's contract status as a safeguard
                            $client = \App\Models\Client::find($taxReport->client_id);
                            if (!$client || !$client->bupot_contract) {
                                // Use notification
                                \Filament\Notifications\Notification::make()
                                    ->title('Kontrak BUPOT Tidak Aktif')
                                    ->body('Klien tidak memiliki kontrak BUPOT aktif. Aktifkan kontrak BUPOT terlebih dahulu.')
                                    ->danger()
                                    ->send();
                                
                                // Throw validation exception to stop the process
                                throw new \Illuminate\Validation\ValidationException(
                                    validator: validator([], []),
                                    response: response()->json([
                                        'message' => 'Klien tidak memiliki kontrak BUPOT aktif.',
                                    ], 422)
                                );
                            }
                        }
                    })
                    ->after(fn () => $this->bupotsChanged()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    ActivitylogAction::make(),
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat')
                        ->modalWidth('7xl'),
                        
                    Tables\Actions\EditAction::make()
                        ->label('Ubah')
                        ->modalWidth('7xl')
                        ->after(fn () => $this->bupotsChanged()),
                    
                    // New action for uploading bukti setor
                    Tables\Actions\Action::make('upload_bukti_setor')
                        ->label('Upload Bukti Setor')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('gray')
                        ->visible(fn ($record) => empty($record->bukti_setor))
                        ->form(function ($record) {
                            return [
                                Section::make('Upload Bukti Setor Bupot')
                                    ->description('Upload dokumen bukti setor untuk bukti potong ini')
                                    ->schema([
                                        Forms\Components\FileUpload::make('bukti_setor')
                                            ->label('Bukti Setor')
                                            ->required()
                                            ->openable()
                                            ->downloadable()
                                            ->disk('public')
                                            ->directory(function () use ($record) {
                                                // Generate path for existing record
                                                $taxReport = $record->taxReport;
                                                $clientName = Str::slug($taxReport->client->name);
                                                $monthName = $this->convertToIndonesianMonth($taxReport->month);
                                                return "clients/{$clientName}/SPT/{$monthName}/Bupot/Bukti-Setor";
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) use ($record): string {
                                                $cleanCompanyName = Str::slug($record->company_name);
                                                $cleanBupotType = Str::slug($record->bupot_type);
                                                $cleanPphType = Str::slug($record->pph_type);
                                                $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
                                                return "Bukti-Setor-Bupot-{$cleanPphType}-{$cleanBupotType}-{$cleanCompanyName}.{$extension}";
                                            })
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                                            ->helperText('Unggah dokumen bukti setor bukti potong (PDF atau gambar)')
                                            ->columnSpanFull(),
                                    ])
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $record->update([
                                'bukti_setor' => $data['bukti_setor']
                            ]);
                            
                            Notification::make()
                                ->title('Bukti Setor Berhasil Diupload')
                                ->body('Bukti setor untuk ' . $record->bupot_type . ' ' . $record->company_name . ' berhasil diupload.')
                                ->success()
                                ->send();
                        })
                        ->modalWidth('2xl'),
                    
                    // Action to view/download bukti setor
                    Tables\Actions\Action::make('view_bukti_setor')
                        ->label('Lihat Bukti Setor')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->visible(fn ($record) => !empty($record->bukti_setor))
                        ->url(fn ($record) => asset('storage/' . $record->bukti_setor))
                        ->openUrlInNewTab()
                        ->tooltip('Lihat bukti setor bukti potong'),
                        
                    Tables\Actions\Action::make('download')
                        ->label('Unduh Berkas')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->url(fn ($record) => $record->file_path ? asset('storage/' . $record->file_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record->file_path)
                        ->tooltip('Unduh berkas bukti potong'),
                        
                    Tables\Actions\DeleteAction::make()
                        ->label('Hapus')
                        ->modalHeading('Hapus Bukti Potong')
                        ->modalDescription('Apakah Anda yakin ingin menghapus bukti potong ini? Tindakan ini tidak dapat dibatalkan.')
                        ->after(fn () => $this->bupotsChanged()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus')
                        ->modalHeading('Hapus Bukti Potong Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus bukti potong yang terpilih? Tindakan ini tidak dapat dibatalkan.')
                        ->after(fn () => $this->bupotsChanged()),
                        
                    Tables\Actions\BulkAction::make('export')
                        ->label('Ekspor ke Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn () => null) // Implement export functionality here
                        ->requiresConfirmation()
                        ->modalHeading('Ekspor Bukti Potong')
                        ->modalDescription('Apakah Anda yakin ingin mengekspor bukti potong yang terpilih?')
                        ->modalSubmitActionLabel('Ya, Ekspor'),
                ]),
            ])
            ->emptyStateHeading('Belum Ada Bukti Potong')
            ->emptyStateDescription('Bukti potong pajak (untuk PPh 21 dan PPh 23) akan muncul di sini. Tambahkan bukti potong untuk melengkapi laporan pajak Anda.')
            ->emptyStateIcon('heroicon-o-document-check')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Bukti Potong')
                    ->modalWidth('7xl')
                    ->icon('heroicon-o-plus')
                    ->disabled(function () {
                        // Get the tax report
                        $taxReport = $this->getOwnerRecord();
                        
                        // If we have a tax report, check the client's contract status
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            
                            // Disable the button if the client doesn't have an active bupot_contract
                            return !($client && $client->bupot_contract);
                        }
                        
                        return true; // Disable if no tax report is found
                    })
                    ->tooltip(function () {
                        $taxReport = $this->getOwnerRecord();
                        
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            
                            if (!$client || !$client->bupot_contract) {
                                return 'Klien tidak memiliki kontrak BUPOT aktif. Aktifkan kontrak BUPOT terlebih dahulu.';
                            }
                        }
                        
                        return 'Tambah Bukti Potong';
                    }),
                    
                ]);
    }
}