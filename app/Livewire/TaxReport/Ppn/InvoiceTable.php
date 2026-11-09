<?php

namespace App\Livewire\TaxReport\Ppn;

use App\Models\Invoice;
use App\Models\TaxReport;
use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Collection;
use Filament\Support\RawJs;

// Import services
use App\Services\ClientTypeService;
use App\Services\TaxCalculationService;
use App\Services\FileManagementService;

// Import trait
use App\Traits\InvoiceFormTrait;

class InvoiceTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InvoiceFormTrait;

    public $taxReportId;
    public $taxReport;

    public function mount($taxReportId)
    {
        $this->taxReportId = $taxReportId;
        $this->taxReport = TaxReport::with('client')->findOrFail($taxReportId);
    }

    /**
     * Generate dynamic directory path for file uploads
     */
    private function generateDirectoryPath($get): string
    {
        $invoiceType = $get('type');
        return FileManagementService::generateInvoiceDirectoryPath($this->taxReport, $invoiceType);
    }

    /**
     * Generate filename with invoice type and number
     */
    private function generateFileName($get, $originalFileName): string
    {
        $invoiceType = $get('type') ?? 'Unknown Type';
        $invoiceNumber = $get('invoice_number') ?? 'Unknown Number';
        
        return FileManagementService::generateInvoiceFileName($invoiceType, $invoiceNumber, $originalFileName);
    }

    public function table(Table $table): Table
    {
        $component = $this;

        return $table
            ->query(
                Invoice::query()
                    ->where('tax_report_id', $this->taxReportId)
                    ->with(['createdBy', 'revisions', 'originalInvoice'])
                    ->withCount('revisions')
            )
            ->columns([
                Tables\Columns\TextColumn::make('row_number')
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
                            $type = $record->type ?? 'other';
                            if (!isset($counters[$type])) {
                                $counters[$type] = 0;
                            }
                            $computed[$record->id] = ++$counters[$type];
                        } else {
                            $computed[$record->id] = ++$seq[0];
                        }

                        return $computed[$record->id];
                    })
                    ->alignCenter()
                    ->extraCellAttributes(['class' => 'font-mono text-gray-500 text-xs']),

                Tables\Columns\ImageColumn::make('user_avatar')
                    ->label('Dibuat Oleh')
                    ->circular()
                    ->state(function ($record) {
                        $user = $record->createdBy;
                        if ($user && method_exists($user, 'getAvatarUrl')) {
                            return $user->getAvatarUrl();
                        }
                        return null;
                    })
                    ->defaultImageUrl(asset('images/default-avatar.png'))
                    ->size(40)
                    ->tooltip(function ($record): string {
                        $user = $record->createdBy;
                        return $user ? $user->name : 'No System';
                    }),

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
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Jenis Faktur')
                    ->colors([
                        'success' => 'Faktur Keluaran',
                        'warning' => 'Faktur Masuk',
                    ])
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
                    ->suffix('%')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        '11' => 'success',
                        '12' => 'warning',
                        null => 'gray',
                        default => 'gray',
                    })
                    ->getStateUsing(fn ($record) => $record->ppn_percentage ?? '11')
                    ->sortable(),

                Tables\Columns\TextColumn::make('dpp_nilai_lainnya')
                    ->label('DPP Nilai Lainnya')
                    ->money('Rp.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn ($record) => ($record->ppn_percentage ?? '11') === '12')
                    ->tooltip('DPP Nilai Lainnya - untuk tarif 12%'),

                Tables\Columns\TextColumn::make('dpp')
                    ->label('DPP')
                    ->money('Rp.')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->money('Rp.')
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
                    ->money('Rp.')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->money('Rp.')
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
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->groups(['type'])
            ->defaultSort('created_at', 'desc')
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

                Tables\Filters\Filter::make('no_bukti_setor')
                    ->label('Belum Ada Bukti Setor')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->whereNull('bukti_setor')->orWhere('bukti_setor', '');
                    })),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_all')
                    ->label('Ekspor Semua')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
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
                        $monthYear = FileManagementService::convertToIndonesianMonth($this->taxReport->month) . '_' . date('Y');
                        
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

                Tables\Actions\Action::make('create_invoice')
                    ->label('Faktur Baru')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->modalHeading('Tambah Faktur Pajak')
                    ->modalDescription('Upload 1–10 berkas faktur. AI akan mengekstrak data setiap dokumen secara otomatis.')
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn () => view(
                        'livewire.tax-report.ppn.bulk-invoice-ai-import-modal',
                        ['taxReportId' => $this->taxReportId]
                    ))
                    ->tooltip(function () {
                        if (!$this->taxReport->client || !$this->taxReport->client->ppn_contract) {
                            return 'Klien tidak memiliki kontrak PPN aktif. Aktifkan kontrak PPN terlebih dahulu.';
                        }
                        return 'Tambah 1–10 faktur sekaligus — AI mengekstrak data otomatis';
                    })
                    ->disabled(function () {
                        return !($this->taxReport->client && $this->taxReport->client->ppn_contract);
                    }),

                Tables\Actions\Action::make('import_json')
                    ->label('Import JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading('Import Faktur dari JSON')
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn () => view(
                        'livewire.tax-report.ppn.bulk-invoice-json-import-modal',
                        ['taxReportId' => $this->taxReportId]
                    ))
                    ->tooltip(function () {
                        if (!$this->taxReport->client || !$this->taxReport->client->ppn_contract) {
                            return 'Klien tidak memiliki kontrak PPN aktif.';
                        }
                        return 'Impor banyak faktur sekaligus dari JSON hasil AI beserta file-nya';
                    })
                    ->disabled(function () {
                        return !($this->taxReport->client && $this->taxReport->client->ppn_contract);
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Input Manual')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->successNotificationTitle('Faktur berhasil disimpan')
                    ->modalHeading('Input Faktur Manual')
                    ->modalWidth('7xl')
                    ->form($this->getInvoiceFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tax_report_id'] = $this->taxReportId;
                        $data['created_by']    = auth()->id();
                        return $data;
                    })
                    ->tooltip(function () {
                        if (!$this->taxReport->client || !$this->taxReport->client->ppn_contract) {
                            return 'Klien tidak memiliki kontrak PPN aktif.';
                        }
                        return 'Isi data faktur secara manual (gunakan jika AI tidak tersedia)';
                    })
                    ->disabled(function () {
                        return !($this->taxReport->client && $this->taxReport->client->ppn_contract);
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat')
                        ->modalWidth('7xl')
                        ->form($this->getInvoiceFormSchema()),

                    Tables\Actions\EditAction::make()
                        ->label('Edit')
                        ->modalWidth('7xl')
                        ->form($this->getInvoiceFormSchema()),

                    Tables\Actions\Action::make('create_revision')
                        ->label('Buat Revisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn ($record) => $record && !$record->is_revision)
                        ->form(function ($record) {
                            return $this->getInvoiceFormSchema(true, $record);
                        })
                        ->fillForm(function ($record) {
                            $nextRevisionNumber = $record->revisions()->max('revision_number') + 1;
                            $revisionInvoiceNumber = $record->invoice_number . '-REV' . $nextRevisionNumber;
                            
                            return [
                                'is_revision' => true,
                                'original_invoice_id' => $record->id,
                                'revision_number' => $nextRevisionNumber,
                                'revision_reason' => '',
                                'invoice_number' => $revisionInvoiceNumber,
                                'invoice_date' => $record->invoice_date instanceof \Carbon\Carbon 
                                    ? $record->invoice_date->format('Y-m-d') 
                                    : $record->invoice_date,
                                'company_name' => $record->company_name,
                                'npwp' => $record->npwp,
                                'type' => $record->type,
                                'client_type' => $record->client_type,
                                'has_ppn' => $record->has_ppn,
                                'ppn_percentage' => $record->ppn_percentage ?? '11',
                                'dpp' => number_format($record->dpp, 2, ',', '.'),
                                'dpp_nilai_lainnya' => number_format($record->dpp_nilai_lainnya ?? 0, 2, ',', '.'),
                                'ppn' => number_format($record->ppn, 2, ',', '.'),
                                'nihil' => $record->nihil,
                                'notes' => $record->notes,
                                'created_by' => auth()->id(),
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $revision = new Invoice();
                            $revision->fill($data);
                            $revision->tax_report_id = $this->taxReportId;
                            $revision->save();
                            
                            Notification::make()
                                ->title('Revisi Berhasil Dibuat')
                                ->body("Revisi #{$data['revision_number']} untuk faktur {$record->invoice_number} berhasil dibuat dengan nomor {$data['invoice_number']}.")
                                ->success()
                                ->send();
                        })
                        ->modalHeading(fn ($record) => 'Buat Revisi untuk Faktur: ' . $record->invoice_number)
                        ->modalSubmitActionLabel('Simpan Revisi')
                        ->modalWidth('7xl')
                        ->tooltip('Buat revisi dari faktur ini dengan form lengkap'),

                    Tables\Actions\Action::make('view_revisions')
                        ->label('Lihat Revisi')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->visible(fn ($record) => $record && !$record->is_revision && $record->hasRevisions())
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
                            $count = $record->revisions()->count();
                            return "Lihat {$count} revisi dari faktur ini";
                        }),

                    Tables\Actions\Action::make('view_original')
                        ->label('Lihat Faktur Asli')
                        ->icon('heroicon-o-document')
                        ->color('primary')
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

                    Tables\Actions\Action::make('upload_bukti_setor')
                        ->label('Upload Bukti Setor')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('info')
                        ->visible(fn ($record) => $record && empty($record->bukti_setor))
                        ->form(function ($record) {
                            return [
                                Section::make('Upload Bukti Setor Pajak')
                                    ->description('Upload dokumen bukti setor untuk faktur ini')
                                    ->schema([
                                        FileUpload::make('bukti_setor')
                                            ->label('Bukti Setor')
                                            ->required()
                                            ->openable()
                                            ->downloadable()
                                            ->disk('public')
                                            ->directory(function () use ($record) {
                                                return FileManagementService::generateBuktiSetorDirectoryPath(
                                                    $record->taxReport, 
                                                    $record->type
                                                );
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) use ($record): string {
                                                return FileManagementService::generateBuktiSetorFileName(
                                                    $record->type, 
                                                    $record->invoice_number, 
                                                    $file->getClientOriginalName()
                                                );
                                            })
                                            ->acceptedFileTypes(FileManagementService::getAcceptedFileTypes())
                                            ->helperText('Unggah dokumen bukti setor pajak (PDF atau gambar)')
                                            ->columnSpanFull(),
                                    ])
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $record->update(['bukti_setor' => $data['bukti_setor']]);
                            
                            Notification::make()
                                ->title('Bukti Setor Berhasil Diupload')
                                ->body('Bukti setor untuk faktur ' . $record->invoice_number . ' berhasil diupload.')
                                ->success()
                                ->send();
                        })
                        ->modalWidth('2xl'),
                    
                        Tables\Actions\Action::make('upload_surat_penagihan')
                        ->label('Upload Surat Penagihan')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->visible(fn ($record) => $record && empty($record->surat_penagihan))
                        ->form(function ($record) {
                            return [
                                Section::make('Upload Surat Penagihan')
                                    ->description('Upload dokumen surat penagihan untuk faktur ini')
                                    ->schema([
                                        FileUpload::make('surat_penagihan')
                                            ->label('Surat Penagihan')
                                            ->required()
                                            ->openable()
                                            ->downloadable()
                                            ->disk('public')
                                            ->directory(function () use ($record) {
                                                return FileManagementService::generateSuratPenagihanDirectoryPath(
                                                    $record->taxReport, 
                                                    $record->type
                                                );
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) use ($record): string {
                                                return FileManagementService::generateSuratPenagihanFileName(
                                                    $record->type, 
                                                    $record->invoice_number, 
                                                    $file->getClientOriginalName()
                                                );
                                            })
                                            ->acceptedFileTypes(FileManagementService::getAcceptedFileTypes())
                                            ->helperText('Unggah dokumen surat penagihan (PDF atau gambar)')
                                            ->columnSpanFull(),
                                    ])
                            ];
                        })
                        ->action(function ($record, array $data) {
                            $record->update(['surat_penagihan' => $data['surat_penagihan']]);
                            
                            Notification::make()
                                ->title('Surat Penagihan Berhasil Diupload')
                                ->body('Surat penagihan untuk faktur ' . $record->invoice_number . ' berhasil diupload.')
                                ->success()
                                ->send();
                        })
                        ->modalWidth('2xl'),

                    Tables\Actions\Action::make('view_bukti_setor')
                        ->label('Lihat Bukti Setor')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->visible(fn ($record) => $record && !empty($record->bukti_setor))
                        ->url(fn ($record) => asset('storage/' . $record->bukti_setor))
                        ->openUrlInNewTab()
                        ->tooltip('Lihat bukti setor pajak'),
                    
                    Tables\Actions\Action::make('view_surat_penagihan')
                        ->label('Lihat Surat Penagihan')
                        ->icon('heroicon-o-document-text')
                        ->color('warning')
                        ->visible(fn ($record) => $record && !empty($record->surat_penagihan))
                        ->url(fn ($record) => asset('storage/' . $record->surat_penagihan))
                        ->openUrlInNewTab()
                        ->tooltip('Lihat surat penagihan'),

                    Tables\Actions\Action::make('download')
                        ->label('Unduh Berkas')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->url(fn ($record) => $record && $record->file_path ? asset('storage/' . $record->file_path) : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => $record && $record->file_path)
                        ->tooltip('Unduh berkas faktur pajak'),

                    Tables\Actions\DeleteAction::make()
                        ->label('Hapus')
                        ->modalHeading('Hapus Faktur')
                        ->modalDescription('Apakah Anda yakin ingin menghapus faktur ini? Tindakan ini tidak dapat dibatalkan.'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Ekspor Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
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
                            $monthYear = FileManagementService::convertToIndonesianMonth($this->taxReport->month) . '_' . date('Y');
                            
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

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus')
                        ->modalHeading('Hapus Faktur Terpilih')
                        ->modalDescription('Apakah Anda yakin ingin menghapus faktur yang terpilih? Tindakan ini tidak dapat dibatalkan.'),
                ]),
            ])
            ->emptyStateHeading('Belum Ada Faktur Pajak')
            ->emptyStateDescription('Faktur pajak untuk laporan pajak ini akan muncul di sini. Tambahkan faktur masukan dan keluaran untuk melacak PPN.')
            ->emptyStateIcon('heroicon-o-document-duplicate');
    }

    /**
     * Process invoice with AI using the AI Service
     */
    private function processInvoiceWithAI($filePath, Forms\Get $get, Forms\Set $set)
    {
        try {
            $set('ai_processing_status', 'processing');
            $set('ai_output', 'Sedang memproses dokumen dengan AI...');
            
            // Get client name
            $clientName = 'unknown-client';
            $monthName = 'unknown-month';
            
            if ($this->taxReport && $this->taxReport->client) {
                $clientName = $this->taxReport->client->name; // Use actual client name, not slugged
                $monthName = FileManagementService::convertToIndonesianMonth($this->taxReport->month);
            }
            
            if (is_object($filePath)) {
                $fullPath = $filePath->getRealPath();
            } else {
                $fullPath = storage_path('app/public/' . $filePath);
            }
            
            if (!file_exists($fullPath)) {
                throw new \Exception('File tidak ditemukan: ' . $filePath);
            }
            
            $aiService = new \App\Services\InvoiceAIService();
            
            // Pass actual client name for type detection
            $result = $aiService->processInvoice($filePath, $clientName, $monthName);
            
            $output = $aiService->formatOutput($result);
            $set('ai_output', $output);
            
            if ($result['success'] && !$result['debug']) {
                $set('ai_extracted_data', json_encode($result['data']));
                $set('ai_processing_status', 'completed');
                
                Notification::make()
                    ->title('AI Processing Selesai')
                    ->body('Data faktur berhasil diekstrak. Klik "Terapkan Data AI ke Form" untuk mengisi form secara otomatis.')
                    ->success()
                    ->duration(5000)
                    ->send();
            } elseif ($result['debug']) {
                $set('ai_processing_status', 'completed');
                
                Notification::make()
                    ->title('Debug Mode Aktif')
                    ->body('Menampilkan informasi debug. Periksa response structure.')
                    ->warning()
                    ->send();
            } else {
                $set('ai_processing_status', 'error');
                
                Notification::make()
                    ->title('Error AI Processing')
                    ->body('Terjadi kesalahan: ' . $result['error'])
                    ->danger()
                    ->send();
            }
            
        } catch (\Exception $e) {
            $set('ai_processing_status', 'error');
            $set('ai_output', '❌ **Error:** ' . $e->getMessage());
            
            Notification::make()
                ->title('Error AI Processing')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }


    /**
     * Apply AI extracted data to form fields
     */
    private function applyAIDataToForm(Forms\Get $get, Forms\Set $set)
    {
        $extractedDataJson = $get('ai_extracted_data');
        
        if (!$extractedDataJson) {
            Notification::make()
                ->title('Tidak Ada Data')
                ->body('Tidak ada data AI yang tersimpan untuk diterapkan.')
                ->warning()
                ->send();
            return;
        }
        
        $data = json_decode($extractedDataJson, true);
        
        if (!$data) {
            Notification::make()
                ->title('Data Tidak Valid')
                ->body('Data AI yang tersimpan tidak valid.')
                ->warning()
                ->send();
            return;
        }
        
        foreach ($data as $field => $value) {
            if ($field === 'ppn_percentage') {
                $set($field, $value);
            } elseif ($field === 'invoice_number') {
                $set($field, $value);
                if ($value && strlen($value) >= 2) {
                    $clientTypeData = ClientTypeService::getClientTypeFromInvoiceNumber($value);
                    $set('client_type', $clientTypeData['type']);
                    $set('has_ppn', $clientTypeData['has_ppn']);
                }
            } elseif (in_array($field, ['dpp', 'dpp_nilai_lainnya', 'ppn'])) {
                continue;
            } else {
                $set($field, $value);
            }
        }
        
        $ppnPercentage = $data['ppn_percentage'] ?? '11';
        
        if ($ppnPercentage === '12') {
            if (isset($data['dpp'])) {
                $set('dpp_nilai_lainnya', TaxCalculationService::formatCurrency($data['dpp']));
                $this->calculateFromDppNilaiLainnya($get, $set, $data['dpp']);
            }
        } else {
            if (isset($data['dpp'])) {
                $set('dpp', TaxCalculationService::formatCurrency($data['dpp']));
                $this->calculatePPNFromDpp($get, $set, $data['dpp']);
            }
            $set('dpp_nilai_lainnya', '0.00');
        }
        
        $set('ai_extracted_data', '');
        
        Notification::make()
            ->title('Data Diterapkan')
            ->body('Data AI berhasil diterapkan ke form.')
            ->success()
            ->send();
    }

    /**
     * Calculate DPP and PPN from DPP Nilai Lainnya (when PPN is 12%)
     */
    private function calculateFromDppNilaiLainnya(Forms\Get $get, Forms\Set $set, ?string $state): void
    {
        $dppNilaiLainnya = TaxCalculationService::cleanMonetaryInput($state);
        
        if ($dppNilaiLainnya > 0) {
            $result = TaxCalculationService::calculateFromDppNilaiLainnya($dppNilaiLainnya);
            $set('dpp', $result['dpp_formatted']);
            $set('ppn', $result['ppn_formatted']);
        } else {
            $set('dpp', '0.00');
            $set('ppn', '0.00');
        }
    }

    /**
     * Calculate PPN from DPP (when PPN is 11%)
     */
    private function calculatePPNFromDpp(Forms\Get $get, Forms\Set $set, ?string $state): void
    {
        $dpp = TaxCalculationService::cleanMonetaryInput($state);
        
        if ($dpp > 0) {
            $result = TaxCalculationService::calculatePPNFromDpp($dpp);
            $set('ppn', $result['ppn_formatted']);
        } else {
            $set('ppn', '0.00');
        }
    }

    #[On('faktur-bulk-saved')]
    public function onFakturBulkSaved(): void
    {
        $this->unmountTableAction();
    }

    public function render()
    {
        return view('livewire.tax-report.ppn.invoice-table');
    }
}