<?php

namespace App\Livewire\TaxReport\Pph;

use App\Models\IncomeTax;
use App\Models\Employee;
use App\Models\TaxReport;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Component;
use Filament\Tables\Filters\SelectFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\FileManagementService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TaxReport\PPh\PphTaxListExport;

class PphTaxList extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $taxReportId;
    public $taxReport;
    
    // Statistics
    public $pph21Total = 0;
    public $pph21Count = 0;
    public $pph23Total = 0;
    public $pph23Count = 0;
    public $pph42Total = 0;
    public $pph42Count = 0;
    public $totalPph = 0;
    public $totalCount = 0;

    public function mount($taxReportId)
    {
        $this->taxReportId = $taxReportId;
        $this->taxReport = TaxReport::with('client')->findOrFail($taxReportId);
        $this->calculateStatistics(false);
    }

    public function table(Table $table): Table
    {
        $component = $this;

        return $table
            ->query(
                IncomeTax::query()
                    ->where('tax_report_id', $this->taxReportId)
                    ->where('jenis_pajak', 'Pasal 21') // fokus PPh 21; jenis lain di PPh Unifikasi
                    ->with(['employee', 'createdBy'])
            )
            ->columns([
                TextColumn::make('row_number')
                    ->label('No.')
                    ->getStateUsing(function ($record) use ($component) {
                        static $computed = [];
                        static $counters = [];
                        static $seq = [0];

                        if (isset($computed[$record->id])) {
                            return $computed[$record->id];
                        }

                        $isGrouped = method_exists($component, 'getTableGrouping')
                            && $component->getTableGrouping() !== null;

                        if ($isGrouped) {
                            $jenisPajak = $record->jenis_pajak ?? 'other';
                            if (!isset($counters[$jenisPajak])) {
                                $counters[$jenisPajak] = 0;
                            }
                            $computed[$record->id] = ++$counters[$jenisPajak];
                        } else {
                            $computed[$record->id] = ++$seq[0];
                        }

                        return $computed[$record->id];
                    })
                    ->alignCenter()
                    ->extraCellAttributes(['class' => 'tabular-nums text-gray-500 text-xs']),

                TextColumn::make('masa_pajak')
                    ->label('Masa Pajak')
                    ->formatStateUsing(fn ($state) => $this->formatMasaPajak($state))
                    ->sortable()
                    ->searchable(),
                
                TextColumn::make('nomor_pemotongan')
                    ->label('No. Pemotongan')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor disalin!')
                    ->copyMessageDuration(1500),
                
                TextColumn::make('nama')
                    ->label('Nama Penerima')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('npwp')
                    ->label('NPWP')
                    ->formatStateUsing(fn ($state) => $this->formatNpwp($state))
                    ->searchable(),
                
                TextColumn::make('dasar_pengenaan_pajak')
                    ->label('DPP')
                    ->formatStateUsing(fn ($state) => \App\Services\TaxReport\ReportOverview::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable(),
                
                TextColumn::make('pajak_penghasilan')
                    ->label('PPh')
                    ->formatStateUsing(fn ($state) => \App\Services\TaxReport\ReportOverview::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'NORMAL' => 'gray',
                        'PEMBETULAN' => 'primary',
                        'PEMBATALAN' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('dilaporkan_dalam_spt')
                    ->label('Lapor SPT')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                    ->color(fn ($state): string => $state ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('bukti_potong')
                    ->label('Bukti')
                    ->formatStateUsing(fn ($state) => $state ? '✓' : '-')
                    ->alignCenter()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Fokus PPh 21 — filter jenis dihilangkan (jenis lain ada di PPh Unifikasi).
            ])
            ->headerActions([
                    Action::make('import_excel')
                        ->label('Impor Excel DJP')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->form([
                            FileUpload::make('file')
                                ->label('File Excel')
                                ->required()
                                ->acceptedFileTypes([
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'application/vnd.ms-excel.sheet.macroEnabled.12',
                                    // .xlsx adalah arsip ZIP — sebagian file terdeteksi sebagai zip/octet-stream
                                    'application/zip',
                                    'application/octet-stream',
                                ])
                                ->maxSize(10240) // 10MB
                                ->helperText(new \Illuminate\Support\HtmlString('
                                    Upload file Excel (.xlsx atau .xls) dengan format yang sesuai. Maksimal 10MB.<br>
                                    <a href="' . route('download.pph.example') . '" 
                                    class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 mt-2"
                                    download>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Download Contoh Format Excel
                                    </a>
                                '))
                                ->disk('local')
                                ->directory('temp-imports')
                                ->visibility('private'),
                        ])
                        ->modalHeading('Import Data Bukti Potong PPh')
                        ->modalDescription('Upload file Excel yang berisi data bukti potong PPh dari DJP. File harus mengikuti format template yang telah ditentukan.')
                        ->modalSubmitActionLabel('Import Sekarang')
                        ->modalWidth('2xl') // Wider modal
                        ->action(function (array $data): void {
                            try {
                                $this->importExcelFile($data['file']);
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Import Gagal')
                                    ->body('Error: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                
                Action::make('download_template')
                    ->label('Download Template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function () {
                        $templatePath = storage_path('app/templates/income_tax_template.xlsx');
                        
                        if (file_exists($templatePath)) {
                            return response()->download($templatePath, 'Template_Import_PPh.xlsx');
                        }
                        
                        Notification::make()
                            ->title('Template Tidak Ditemukan')
                            ->body('Silakan jalankan: php artisan generate:income-tax-template')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn () => file_exists(storage_path('app/templates/income_tax_template.xlsx'))),
                
                Action::make('export_excel')
                    ->label('Ekspor Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportToExcel())
                    ->visible(fn () => IncomeTax::where('tax_report_id', $this->taxReportId)->count() > 0),

            ])
            ->actions([
                Action::make('edit')
                    ->label('Ubah')
                    ->iconButton()
                    ->tooltip('Ubah')
                    ->icon('heroicon-o-pencil')
                    ->color('gray')
                    ->form([
                        TextInput::make('nomor_pemotongan')
                            ->label('Nomor Pemotongan')
                            ->required()
                            ->maxLength(50),
                        
                        Select::make('jenis_pajak')
                            ->label('Jenis Pajak')
                            ->required()
                            ->default('Pasal 21')
                            ->disabled()
                            ->dehydrated()
                            ->options([
                                'Pasal 21' => 'PPh 21',
                            ]),
                        
                        TextInput::make('nama')
                            ->label('Nama Penerima')
                            ->maxLength(255),
                        
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->mask('99.999.999.9-999.999')
                            ->maxLength(20),
                        
                        TextInput::make('dasar_pengenaan_pajak')
                            ->label('DPP')
                            ->numeric()
                            ->prefix('Rp'),
                        
                        TextInput::make('pajak_penghasilan')
                            ->label('Pajak Penghasilan')
                            ->numeric()
                            ->prefix('Rp'),
                        
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'NORMAL' => 'Normal',
                                'PEMBETULAN' => 'Pembetulan',
                                'PEMBATALAN' => 'Pembatalan',
                            ]),
                    ])
                    ->fillForm(fn (IncomeTax $record): array => [
                        'nomor_pemotongan' => $record->nomor_pemotongan,
                        'jenis_pajak' => $record->jenis_pajak,
                        'nama' => $record->nama,
                        'npwp' => $record->npwp,
                        'dasar_pengenaan_pajak' => $record->dasar_pengenaan_pajak,
                        'pajak_penghasilan' => $record->pajak_penghasilan,
                        'status' => $record->status,
                    ])
                    ->action(function (IncomeTax $record, array $data): void {
                        $record->update($data);
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Data Berhasil Diupdate')
                            ->success()
                            ->send();
                    }),
                
                Action::make('delete')
                    ->label('Hapus')
                    ->iconButton()
                    ->tooltip('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (IncomeTax $record) {
                        $record->delete();
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Data Berhasil Dihapus')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->after(function () {
                        $this->calculateStatistics();
                    }),
                
                BulkAction::make('update_status')
                    ->label('Update Status')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->form([
                        Select::make('status')
                            ->label('Status Baru')
                            ->options([
                                'NORMAL' => 'Normal',
                                'PEMBETULAN' => 'Pembetulan',
                                'PEMBATALAN' => 'Pembatalan',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['status' => $data['status']]);
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Status Berhasil Diupdate')
                            ->body(count($records) . ' data berhasil diupdate.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->heading('Bukti potong PPh 21')
            ->description(fn () => 'Rp ' . number_format((float) $this->pph21Total, 0, ',', '.') . ' dari ' . $this->pph21Count . ' bukti potong')
            ->emptyStateHeading('Belum ada data PPh 21')
            ->emptyStateDescription('Tambahkan data PPh menggunakan tombol Import Excel')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    protected function importExcelFile(string $filePath): void
    {
        DB::beginTransaction();
        
        try {
            $fullPath = Storage::disk('local')->path($filePath);
            
            // Load Excel file
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get the highest row number
            $highestRow = $worksheet->getHighestRow();
            
            $successCount = 0;
            $errorCount = 0;
            $updateCount = 0; // Track updates
            $errors = [];
            
            // Start from row 2 (skip header)
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    // Format DJP (bukti pemotongan PPh 21) — 14 kolom:
                    // A=No. B=NIK/NPWP C=Nama D=Nomor Bukti Potong E=Tgl Bukti F=Kode Objek
                    // G=Bruto H=Tarif(%) I=PPh J=Fasilitas K=Negara L=ID Tempat Usaha(NITKU) M=KAP-KJS N=Status
                    // Catatan: Tarif, Negara, KAP-KJS sengaja diabaikan (tak ada kolom DB).
                    $rowData = [
                        'npwp'                  => $worksheet->getCell('B' . $row)->getValue(),
                        'nama'                  => $worksheet->getCell('C' . $row)->getValue(),
                        'nomor_pemotongan'      => $worksheet->getCell('D' . $row)->getValue(),
                        'masa_pajak'            => $this->parseExcelDate($worksheet->getCell('E' . $row)->getValue()),
                        'kode_objek_pajak'      => $worksheet->getCell('F' . $row)->getValue(),
                        'dasar_pengenaan_pajak' => $worksheet->getCell('G' . $row)->getValue() ?: 0,
                        'pajak_penghasilan'     => $worksheet->getCell('I' . $row)->getValue() ?: 0,
                        'fasilitas_pajak'       => $worksheet->getCell('J' . $row)->getValue() ?: 'Tanpa Fasilitas',
                        'nitku'                 => $worksheet->getCell('L' . $row)->getValue(),
                        'status'                => $worksheet->getCell('N' . $row)->getValue() ?: 'NORMAL',
                    ];

                    // Skip baris kosong
                    if (empty($rowData['npwp']) && empty($rowData['nomor_pemotongan'])) {
                        continue;
                    }

                    // Clean and format data
                    $rowData = $this->cleanRowData($rowData);

                    // Karyawan dari NPWP + Nama (buat bila belum ada)
                    $employeeId = $this->findOrCreateEmployeeId($rowData['npwp'], $rowData['nama']);

                    // Dedup per Nomor Bukti Potong
                    $existingRecord = IncomeTax::where('tax_report_id', $this->taxReportId)
                        ->where('nomor_pemotongan', $rowData['nomor_pemotongan'])
                        ->first();

                    // Create or update record
                    $record = IncomeTax::updateOrCreate(
                        [
                            'tax_report_id'    => $this->taxReportId,
                            'nomor_pemotongan' => $rowData['nomor_pemotongan'],
                        ],
                        [
                            'employee_id'           => $employeeId,
                            'jenis_pajak'           => 'Pasal 21',
                            'npwp'                  => $rowData['npwp'],
                            'nama'                  => $rowData['nama'],
                            'nitku'                 => $rowData['nitku'],
                            'masa_pajak'            => $rowData['masa_pajak'],
                            'kode_objek_pajak'      => $rowData['kode_objek_pajak'],
                            'dasar_pengenaan_pajak' => $rowData['dasar_pengenaan_pajak'],
                            'pajak_penghasilan'     => $rowData['pajak_penghasilan'],
                            'fasilitas_pajak'       => $rowData['fasilitas_pajak'],
                            'status'                => $rowData['status'],
                            'created_by'            => auth()->id(),
                        ]
                    );

                    if ($existingRecord) {
                        $updateCount++;
                    } else {
                        $successCount++;
                    }
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = "Baris {$row}: " . $e->getMessage();
                }
            }
            
            DB::commit();
            
            // Save the imported file to permanent location
            $this->saveImportedFile($filePath);
            
            // Recalculate statistics
            $this->calculateStatistics();
            
            // Show notification with update info
            if ($errorCount > 0) {
                Notification::make()
                    ->title('Import Selesai dengan Peringatan')
                    ->body("Baru: {$successCount} | Diupdate: {$updateCount} | Gagal: {$errorCount}")
                    ->warning()
                    ->send();
                    
                if (count($errors) <= 10) {
                    foreach ($errors as $error) {
                        Notification::make()
                            ->title('Error Detail')
                            ->body($error)
                            ->danger()
                            ->send();
                    }
                }
            } else {
                $message = "Import berhasil! Baru: {$successCount} data";
                if ($updateCount > 0) {
                    $message .= ", Diupdate: {$updateCount} data";
                }
                
                Notification::make()
                    ->title('Import Berhasil!')
                    ->body($message)
                    ->success()
                    ->send();
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Delete temporary file
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            
            throw $e;
        }
    }

    /**
     * Save imported Excel file to permanent storage
     */
    protected function saveImportedFile(string $tempFilePath): void
    {
        try {
            // Generate the permanent directory path
            $permanentPath = \App\Services\FileManagementService::generatePphImportDirectoryPath(
                $this->taxReport,
                'PPH 21'
            );
            
            // Generate filename with timestamp
            $timestamp = now()->format('Y-m-d_His');
            $fileName = "Import_PPh21_{$timestamp}.xlsx";
            
            // Full path for the file
            $fullPath = "{$permanentPath}/{$fileName}";
            
            // Get the file content from temp storage
            $fileContent = Storage::disk('local')->get($tempFilePath);
            
            // Save to permanent location (public disk)
            Storage::disk('public')->put($fullPath, $fileContent);
            
            // Delete temporary file
            Storage::disk('local')->delete($tempFilePath);
            
            \Log::info("Import file saved to: {$fullPath}");
            
        } catch (\Exception $e) {
            \Log::error("Failed to save import file: " . $e->getMessage());
            
            // Still delete temp file even if save fails
            if (Storage::disk('local')->exists($tempFilePath)) {
                Storage::disk('local')->delete($tempFilePath);
            }
        }
    }

    protected function cleanRowData(array $data): array
    {
        // Clean NPWP - remove formatting
        if (!empty($data['npwp'])) {
            $data['npwp'] = preg_replace('/[^0-9]/', '', (string) $data['npwp']);
        }
        
        // Format Masa Pajak to string
        if (!empty($data['masa_pajak'])) {
            $data['masa_pajak'] = (string) $data['masa_pajak'];
        }
        
        // Ensure numbers are numeric
        $data['dasar_pengenaan_pajak'] = (float) ($data['dasar_pengenaan_pajak'] ?? 0);
        $data['pajak_penghasilan'] = (float) ($data['pajak_penghasilan'] ?? 0);

        return $data;
    }

    /**
     * Normalisasi tanggal bukti pemotongan ke string 'd-m-Y'.
     * Menangani serial date Excel maupun string (dd-mm-yyyy / yyyy-mm-dd / dll).
     */
    protected function parseExcelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Serial date Excel (angka)
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('d-m-Y');
            } catch (\Throwable $e) {
                // lanjut ke parsing string
            }
        }

        $str = trim((string) $value);

        // Format dd-mm-yyyy eksplisit dulu (ambigu untuk strtotime/Carbon)
        foreach (['d-m-Y', 'd/m/Y', 'd-m-y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $str);
            if ($dt !== false) {
                return $dt->format('d-m-Y');
            }
        }

        // ISO 8601 (mis. 2026-06-01T00:00:00+07:00) & format lain
        try {
            return \Illuminate\Support\Carbon::parse($str)->format('d-m-Y');
        } catch (\Throwable $e) {
            // biarkan, pakai fallback di bawah
        }

        return mb_substr($str, 0, 20); // jaga agar muat kolom varchar(20)
    }

    protected function findOrCreateEmployeeId(?string $npwp, ?string $nama): ?int
    {
        if (empty($npwp) && empty($nama)) {
            return null;
        }
        
        // Try to find existing employee
        $employee = null;
        
        if (!empty($npwp)) {
            $employee = Employee::where('npwp', $npwp)->first();
        }
        
        if (!$employee && !empty($nama)) {
            $employee = Employee::where('name', $nama)->first();
        }
        
        // If employee doesn't exist, create a new one
        if (!$employee && !empty($nama)) {
            try {
                $employee = Employee::create([
                    'client_id' => $this->taxReport->client_id,
                    'name' => $nama,
                    'npwp' => $npwp,
                    'status' => 'active',
                    'type' => 'Karyawan Tetap',
                    'marital_status' => 'single',
                    'tk' => 0,
                    'k' => 0,
                ]);
                
                // Log the creation
                \Log::info("Auto-created employee: {$nama} (NPWP: {$npwp})");
                
            } catch (\Exception $e) {
                \Log::error("Failed to create employee: {$nama} - " . $e->getMessage());
                return null;
            }
        }
        
        return $employee ? $employee->id : null;
    }

    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $value = strtoupper(trim((string) $value));
        
        return in_array($value, ['TRUE', '1', 'YES', 'Y']);
    }

    /**
     * Totals for the table heading. After a change (import, edit, delete) the
     * PPh workspace and the page strip re-read their numbers too.
     */
    public function calculateStatistics(bool $changed = true): void
    {
        if ($changed) {
            $this->dispatch('tax-report-changed', taxReportId: $this->taxReportId);
        }

        $incomeTaxes = IncomeTax::where('tax_report_id', $this->taxReportId)->get();
        
        // PPh 21
        $pph21 = $incomeTaxes->where('jenis_pajak', 'Pasal 21');
        $this->pph21Total = $pph21->sum('pajak_penghasilan');
        $this->pph21Count = $pph21->count();
        
        // PPh 23
        $pph23 = $incomeTaxes->where('jenis_pajak', 'Pasal 23');
        $this->pph23Total = $pph23->sum('pajak_penghasilan');
        $this->pph23Count = $pph23->count();
        
        // PPh 4(2)
        $pph42 = $incomeTaxes->whereIn('jenis_pajak', ['Pasal 4(2)', 'Pasal 4 ayat 2']);
        $this->pph42Total = $pph42->sum('pajak_penghasilan');
        $this->pph42Count = $pph42->count();
        
        // Totals
        $this->totalPph = $incomeTaxes->sum('pajak_penghasilan');
        $this->totalCount = $incomeTaxes->count();
    }

    private function formatNpwp(?string $npwp): string
    {
        if (!$npwp) return '-';
        
        // Remove any existing formatting
        $npwp = preg_replace('/[^0-9]/', '', $npwp);
        
        // Format: XX.XXX.XXX.X-XXX.XXX
        if (strlen($npwp) === 15) {
            return preg_replace(
                '/(\d{2})(\d{3})(\d{3})(\d{1})(\d{3})(\d{3})/',
                '$1.$2.$3.$4-$5.$6',
                $npwp
            );
        }
        
        return $npwp;
    }

    

    private function formatMasaPajak(?string $masaPajak): string
    {
        if (!$masaPajak) return '-';

        $monthNames = [
            '01' => 'Jan', '02' => 'Feb', '03' => 'Mar',
            '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
            '07' => 'Jul', '08' => 'Agu', '09' => 'Sep',
            '10' => 'Okt', '11' => 'Nov', '12' => 'Des',
        ];

        // Format baru: dd-mm-yyyy (tanggal bukti pemotongan)
        $dt = \DateTime::createFromFormat('d-m-Y', $masaPajak);
        if ($dt !== false) {
            return $dt->format('d') . ' ' . ($monthNames[$dt->format('m')] ?? $dt->format('m')) . ' ' . $dt->format('Y');
        }

        // Format lama: MMDDYYYY (mis. 06062025)
        if (preg_match('/^\d{8}$/', $masaPajak)) {
            $month = substr($masaPajak, 0, 2);
            $year = substr($masaPajak, 4, 4);
            return ($monthNames[$month] ?? $month) . ' ' . $year;
        }

        return $masaPajak;
    }

    protected function exportToExcel()
    {
        try {
            $incomeTaxes = IncomeTax::where('tax_report_id', $this->taxReportId)->count();
            
            if ($incomeTaxes === 0) {
                Notification::make()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ada data PPh untuk diekspor')
                    ->warning()
                    ->send();
                return;
            }
            
            // Generate directory path using FileManagementService
            $directoryPath = FileManagementService::generatePphImportDirectoryPath(
                $this->taxReport,
                'PPH 21'
            );
            
            // Generate filename
            $monthName = FileManagementService::convertToIndonesianMonth($this->taxReport->month);
            preg_match('/(\d{4})/', $this->taxReport->month, $matches);
            $year = $matches[1] ?? date('Y');
            $clientName = \Illuminate\Support\Str::slug($this->taxReport->client->name);
            $timestamp = now()->format('Y-m-d_His');
            
            $filename = "Rekapan_PPh_{$clientName}_{$monthName}_{$year}_{$timestamp}.xlsx";
            $fullPath = "{$directoryPath}/{$filename}";
            
            // Create the export
            $export = new PphTaxListExport($this->taxReportId);
            
            // Save to storage (public disk)
            Excel::store($export, $fullPath, 'public');
            
            // Also download for user
            $downloadResponse = Excel::download($export, $filename);
            
            // Show success notification
            Notification::make()
                ->title('Export Berhasil!')
                ->body("File disimpan di: {$directoryPath}")
                ->success()
                ->duration(5000)
                ->send();
            
            return $downloadResponse;
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Gagal')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
                
            \Log::error('PPh Export Error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.tax-report.pph.pph-tax-list');
    }
}