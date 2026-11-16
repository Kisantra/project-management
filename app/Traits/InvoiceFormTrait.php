<?php

namespace App\Traits;

use Asmit\FilamentUpload\Enums\PdfViewFit;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Support\RawJs;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Models\Invoice;
use App\Services\ClientTypeService;
use App\Services\TaxCalculationService;
use App\Services\FileManagementService;
use Asmit\FilamentUpload\Forms\Components\AdvancedFileUpload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait InvoiceFormTrait
{
    /**
     * Get the complete invoice form schema. Manual entry only: AI extraction
     * lives in "Faktur baru" (BulkInvoiceAIImport) and "Impor JSON".
     */
    public function getInvoiceFormSchema(bool $isRevision = false, $originalRecord = null): array
    {
        $schema = [
            Forms\Components\Hidden::make('created_by')
                ->default(auth()->id()),
        ];

        // Add revision fields if this is a revision
        if ($isRevision) {
            $schema = array_merge($schema, [
                Forms\Components\Hidden::make('is_revision')
                    ->default(true),
                Forms\Components\Hidden::make('original_invoice_id'),
                Forms\Components\Hidden::make('revision_number')
                    ->default(0),
            ]);
        }

        $wizardSteps = $isRevision ? [$this->getRevisionInfoStep()] : [];

        $wizardSteps = array_merge($wizardSteps, [
            $this->getBasicInfoStep($isRevision),
            $this->getFinancialDetailsStep(),
            $this->getDocumentsStep($isRevision),
        ]);

        // No persistStepInQueryString(): the step stuck in the URL made the next
        // modal (another invoice, or a revision with one step more) open midway.
        $schema[] = Forms\Components\Wizard::make($wizardSteps)
            ->skippable()
            ->columnSpanFull();

        return $schema;
    }

    /**
     * Prefill for "Buat Revisi": the newest version of the invoice (its latest
     * revision, else the original). Amounts use the format the money mask and
     * TaxCalculationService::cleanMonetaryInput() read (1,234,567.89); the old
     * Indonesian format (1.234.567,89) was re-masked to 1.23.
     */
    protected function getRevisionFormData(Invoice $original): array
    {
        $source = $original->latestRevision() ?? $original;
        $next = (int) $original->revisions()->max('revision_number') + 1;

        return [
            'is_revision' => true,
            'original_invoice_id' => $original->id,
            'revision_number' => $next,
            'revision_reason' => '',
            'invoice_number' => "{$original->invoice_number}-REV{$next}",
            'invoice_date' => $source->invoice_date ? Carbon::parse($source->invoice_date)->format('Y-m-d') : null,
            'company_name' => $source->company_name,
            'npwp' => $source->npwp,
            'type' => $source->type,
            'client_type' => $source->client_type,
            'has_ppn' => (bool) $source->has_ppn,
            'is_business_related' => (int) $source->is_business_related,
            'ppn_percentage' => $source->ppn_percentage ?: '11',
            'dpp' => TaxCalculationService::formatCurrency((float) $source->dpp),
            'dpp_nilai_lainnya' => TaxCalculationService::formatCurrency((float) $source->dpp_nilai_lainnya),
            'ppn' => TaxCalculationService::formatCurrency((float) $source->ppn),
            'notes' => $source->notes,
            'created_by' => auth()->id(),
        ];
    }

    /**
     * Saves the revision form as a new revision of $original. The link, the
     * report and the number are set here rather than taken from hidden form
     * fields; the number is taken under a lock so two revisions saved at the
     * same time cannot both become #2.
     */
    protected function createInvoiceRevision(Invoice $original, array $data): Invoice
    {
        return DB::transaction(function () use ($original, $data) {
            $source = $original->latestRevision() ?? $original;
            $number = (int) $original->revisions()->lockForUpdate()->max('revision_number') + 1;

            return Invoice::create([
                ...$data,
                'tax_report_id' => $original->tax_report_id,
                'original_invoice_id' => $original->id,
                'is_revision' => true,
                'revision_number' => $number,
                // Not on the form; a revision keeps it from the version it replaces.
                'nihil' => (bool) $source->nihil,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Get revision info step
     */
    private function getRevisionInfoStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Informasi Revisi')
            ->icon('heroicon-o-arrow-path')
            ->description('Faktur yang direvisi dan alasannya')
            ->schema([
                Section::make('Detail Revisi')
                    ->description('Data di langkah berikutnya sudah diisi dari versi terakhir faktur ini. Ubah bagian yang direvisi saja.')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        Forms\Components\Placeholder::make('original_invoice_info')
                            ->label('Faktur yang direvisi')
                            ->content(function (Forms\Get $get) {
                                $original = Invoice::find($get('original_invoice_id'));

                                if (! $original) {
                                    return 'Tidak ditemukan';
                                }

                                $source = $original->latestRevision() ?? $original;
                                $parts = array_filter([
                                    $original->invoice_number,
                                    $original->company_name,
                                    $source->invoice_date ? Carbon::parse($source->invoice_date)->locale('id')->translatedFormat('j M Y') : null,
                                    'DPP Rp ' . number_format((float) $source->dpp, 0, ',', '.'),
                                    'PPN Rp ' . number_format((float) $source->ppn, 0, ',', '.'),
                                ]);
                                $from = $source->is($original) ? '' : " (diisi dari Revisi #{$source->revision_number})";

                                return implode(' · ', $parts) . $from;
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('revision_number_info')
                            ->label('Nomor revisi')
                            ->content(fn (Forms\Get $get) => 'Revisi #' . $get('revision_number')),

                        Forms\Components\Textarea::make('revision_reason')
                            ->label('Alasan Revisi')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Mis. koreksi nilai DPP, salah NPWP lawan transaksi, perubahan jumlah barang')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Get basic info step
     */
    private function getBasicInfoStep(bool $isRevision = false): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Informasi Dasar')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Informasi Faktur Pajak')
                    ->columns(12)
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Faktur')
                            ->required()
                            // A revision form is mounted on the original invoice; ignoring that record
                            // would let the revision reuse its number and fail on the unique index.
                            ->unique(ignoreRecord: ! $isRevision)
                            ->maxLength(255)
                            ->placeholder('Masukkan nomor faktur sesuai dokumen')
                            ->helperText($isRevision ? 'Nomor faktur revisi (dapat diedit)' : 'Salin nomor faktur persis sesuai dokumen')
                            ->columnSpan(6)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) use ($isRevision) {
                                if (!$isRevision && $state && strlen($state) >= 2) {
                                    $clientTypeData = ClientTypeService::getClientTypeFromInvoiceNumber($state);
                                    $set('client_type', $clientTypeData['type']);
                                    $set('has_ppn', $clientTypeData['has_ppn']);
                                }
                            }),
                            
                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Tanggal Faktur')
                            ->required()
                            ->native(false)
                            ->default(now())
                            ->columnSpan(6),
                            
                        Forms\Components\Select::make('client_type')
                            ->label('Tipe Client')
                            ->options(ClientTypeService::getClientTypeOptions())
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('has_ppn', $state ? ClientTypeService::hasPPN($state) : true))
                            ->helperText($isRevision ? 'Dapat diubah sesuai kebutuhan revisi' : 'Otomatis terdeteksi dari 2 digit awal nomor faktur')
                            ->columnSpan(8),
                            
                        Forms\Components\Toggle::make('has_ppn')
                            ->label('Subject PPN')
                            ->disabled()
                            // Disabled fields are not saved by default, so this was always stored as 1.
                            ->dehydrated()
                            ->helperText('Otomatis terdeteksi berdasarkan tipe client')
                            ->columnSpan(4),
                    
                        Forms\Components\Select::make('type')
                            ->label('Jenis Faktur')
                            ->native(false)
                            ->options([
                                'Faktur Keluaran' => 'Faktur Keluaran',
                                'Faktur Masuk' => 'Faktur Masuk',
                            ])
                            ->required()
                            ->reactive()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if ($state === 'Faktur Masuk' && method_exists($this, 'getOwnerRecord')) {
                                    $taxReportId = $get('tax_report_id') ?? $this->getOwnerRecord()->id;
                                    $taxReport = \App\Models\TaxReport::with('client')->find($taxReportId);
                                    
                                    if ($taxReport && $taxReport->client) {
                                        $set('company_name', $taxReport->client->name);
                                        $set('npwp', $taxReport->client->NPWP);
                                    }
                                }
                            })
                            ->columnSpan(12),
                            
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nama Perusahaan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(6),
                            
                        Forms\Components\TextInput::make('npwp')
                            ->label('NPWP')
                            ->required()
                            ->placeholder('00.000.000.0-000.000')
                            ->helperText('Format: 00.000.000.0-000.000')
                            ->maxLength(255)
                            ->columnSpan(6),
                                                                               
                        Forms\Components\Select::make('is_business_related')
                            ->label('Keterkaitan Bisnis')
                            ->options([
                                true => 'Terkait Aktivitas Bisnis Utama',
                                false => 'Tidak Terkait Bisnis Utama (Personal/Non-Operasional)',
                            ])
                            ->required()
                            ->default(true)
                            ->native(false)
                            ->live()
                            ->helperText(function (Forms\Get $get) {
                                $isBusinessRelated = $get('is_business_related');
                                if ($isBusinessRelated === true) {
                                    return '✅ Faktur ini terkait dengan kegiatan operasional bisnis utama client';
                                } elseif ($isBusinessRelated === false) {
                                    return '⚠️ Faktur ini untuk keperluan personal atau non-operasional (misal: pembelian pribadi, investasi non-operasional)';
                                }
                                return 'Pilih apakah faktur ini terkait dengan aktivitas bisnis utama atau tidak';
                            })
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?bool $state) {
                                // Optional: Auto-adjust tax treatment based on business relation
                                if ($state === false) {
                                }
                            })
                            ->columnSpan(12),
                    ]),
            ]);
    }

    /**
     * Get financial details step
     */
    private function getFinancialDetailsStep(): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Rincian Keuangan')
            ->icon('heroicon-o-currency-dollar')
            ->schema([
                Section::make('Detail Perpajakan')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('ppn_percentage')
                            ->label('Tarif PPN')
                            ->options(TaxCalculationService::getPPNPercentageOptions())
                            ->default('11')
                            ->native(false)
                            ->required()
                            ->live(debounce: 500)
                            ->helperText('Pilih tarif PPN yang berlaku')
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if ($state === '11') {
                                    $set('dpp_nilai_lainnya', '0.00');
                                } else {
                                    $set('dpp', '0.00');
                                }
                                $set('ppn', '0.00');
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('dpp_nilai_lainnya')
                            ->label('DPP Nilai Lainnya')
                            ->required(fn (Forms\Get $get) => $get('ppn_percentage') === '12')
                            ->prefix('Rp')
                            ->placeholder('0.00')
                            ->mask(RawJs::make('$money($input)'))
                            ->dehydrateStateUsing(fn ($state) => TaxCalculationService::cleanMonetaryInput($state))
                            ->default('0.00')
                            ->helperText('Nilai DPP untuk perhitungan pajak 12%')
                            ->visible(fn (Forms\Get $get) => $get('ppn_percentage') === '12')
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if (method_exists($this, 'calculateFromDppNilaiLainnya')) {
                                    $this->calculateFromDppNilaiLainnya($get, $set, $state);
                                }
                            })
                            ->live(2000)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('dpp')
                            ->label(function (Forms\Get $get) {
                                return $get('ppn_percentage') === '12' 
                                    ? 'DPP (Dihitung Otomatis)' 
                                    : 'DPP (Dasar Pengenaan Pajak)';
                            })
                            ->required()
                            ->prefix('Rp')
                            ->placeholder('0.00')
                            ->mask(RawJs::make('$money($input)'))
                            ->dehydrateStateUsing(fn ($state) => TaxCalculationService::cleanMonetaryInput($state))
                            ->rules(['required'])
                            ->readOnly(fn (Forms\Get $get) => $get('ppn_percentage') === '12')
                            ->helperText(function (Forms\Get $get) {
                                return $get('ppn_percentage') === '12' 
                                    ? 'Otomatis dihitung dari DPP Nilai Lainnya × 12/11'
                                    : 'Masukkan nilai DPP';
                            })
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if ($get('ppn_percentage') === '11' && method_exists($this, 'calculatePPNFromDpp')) {
                                    $this->calculatePPNFromDpp($get, $set, $state);
                                }
                            })
                            ->live(2000),

                        Forms\Components\TextInput::make('ppn')
                            ->label('PPN')
                            ->prefix('Rp')
                            ->placeholder('0.00')
                            ->required()
                            ->mask(RawJs::make('$money($input)'))
                            ->dehydrateStateUsing(fn ($state) => TaxCalculationService::cleanMonetaryInput($state))
                            ->rules(['required'])
                            ->helperText('Terisi otomatis 11% dari DPP — bisa diubah manual bila ada selisih pembulatan'),
                    ]),
            ]);
    }

    /**
     * Get documents step
     */
    private function getDocumentsStep(bool $isRevision = false): Forms\Components\Wizard\Step
    {
        return Forms\Components\Wizard\Step::make('Berkas & Catatan')
            ->icon('heroicon-o-paper-clip')
            ->schema([
                Section::make('Berkas & Catatan')
                    ->description('Semua berkas opsional dan bisa ditambahkan nanti lewat Ubah.')
                    ->schema([
                        // Stored under the invoice's type and number when the form is saved, so
                        // the name is right even though it is uploaded after those fields.
                        AdvancedFileUpload::make('file_path')
                            ->label($isRevision ? 'Berkas Faktur Revisi (Opsional)' : 'Berkas Faktur (Opsional)')
                            ->openable()
                            ->downloadable()
                            ->disk('public')
                            ->pdfPreviewHeight(600)
                            ->pdfDisplayPage(1)
                            ->pdfToolbar(true)
                            ->pdfZoomLevel(100)
                            ->pdfFitType(PdfViewFit::FIT)
                            ->directory(fn (Forms\Get $get) => method_exists($this, 'generateDirectoryPath') ? $this->generateDirectoryPath($get) : 'invoices')
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Forms\Get $get): string =>
                                method_exists($this, 'generateFileName')
                                    ? $this->generateFileName($get, $file->getClientOriginalName())
                                    : $file->getClientOriginalName()
                            )
                            ->maxSize(10240) // 10MB
                            ->helperText('PDF atau gambar, maks. 10 MB.')
                            ->columnSpanFull(),

                        FileUpload::make('bukti_setor')
                            ->label('Bukti Setor (Opsional)')
                            ->openable()
                            ->downloadable()
                            ->disk('public')
                            ->directory(function (Forms\Get $get) {
                                if (method_exists($this, 'generateDirectoryPath')) {
                                    $basePath = $this->generateDirectoryPath($get);
                                    return $basePath . '/Bukti-Setor';
                                }
                                return 'invoices/bukti-setor';
                            })
                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, Forms\Get $get): string {
                                $invoiceType = $get('type') ?? 'Unknown Type';
                                $invoiceNumber = $get('invoice_number') ?? 'Unknown Number';
                                
                                return FileManagementService::generateBuktiSetorFileName($invoiceType, $invoiceNumber, $file->getClientOriginalName());
                            })
                            ->helperText(function (Forms\Get $get) {
                                if (method_exists($this, 'generateDirectoryPath')) {
                                    $path = $this->generateDirectoryPath($get);
                                    return "Akan disimpan di: storage/{$path}/Bukti-Setor/";
                                }
                                return "Upload bukti setor pajak";
                            })
                            ->columnSpanFull(),
                            
                        Forms\Components\RichEditor::make('notes')
                            ->label('Catatan')
                            ->placeholder(function (Forms\Get $get) {
                                return $get('is_revision') 
                                    ? 'Tambahkan catatan tentang revisi ini dan perubahan yang dilakukan...'
                                    : 'Tambahkan catatan relevan tentang faktur ini...';
                            })
                            ->toolbarButtons([
                                'blockquote', 'bold', 'bulletList', 'h2', 'h3', 
                                'italic', 'link', 'orderedList', 'redo', 'strike', 'undo',
                            ])
                            ->columnSpanFull(),
                            
                    ]),
            ]);
    }
}