<?php

namespace App\Filament\Resources\TaxReportResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Collection;
// Import our services
use App\Services\ClientTypeService;
use App\Services\TaxCalculationService;
use App\Services\FileManagementService;
// Import the trait
use App\Traits\InvoiceFormTrait;

class InvoicesRelationManager extends RelationManager
{
    use InvoiceFormTrait;
    
    protected static string $relationship = 'invoices';
    protected static ?string $title = 'PPN';

    /**
     * Generate dynamic directory path for file uploads
     */
    private function generateDirectoryPath($get): string
    {
        $taxReportId = $get('tax_report_id') ?? $this->getOwnerRecord()->id;
        $taxReport = \App\Models\TaxReport::with('client')->find($taxReportId);
        
        return FileManagementService::generateInvoiceDirectoryPath($taxReport);
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

    public function form(Form $form): Form
    {
        return $form->schema($this->getInvoiceFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('company_name')
            ->columns([
                Tables\Columns\ImageColumn::make('user_avatar')
                    ->label('Dibuat Oleh')
                    ->circular()
                    ->state(function ($record) {
                        if ($record->created_by) {
                            $user = \App\Models\User::find($record->created_by);
                            if ($user && method_exists($user, 'getAvatarUrl')) {
                                return $user->getAvatarUrl();
                            }
                        }
                        return null;
                    })
                    ->defaultImageUrl(asset('images/default-avatar.png'))
                    ->size(40)
                    ->tooltip(function ($record): string {
                        if ($record->created_by) {
                            $user = \App\Models\User::find($record->created_by);
                            return $user ? $user->name : 'User #' . $record->created_by;
                        }
                        return 'No System';
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
                        if ($record->hasRevisions()) {
                            $revisionCount = $record->revisions()->count();
                            return "Memiliki {$revisionCount} revisi";
                        }
                        return null;
                    }),
                    
                // Tables\Columns\BadgeColumn::make('revision_status')
                //     ->label('Status')
                //     ->getStateUsing(function ($record) {
                //         if (!$record) return 'Asli';
                        
                //         if ($record->is_revision) {
                //             return 'Revisi';
                //         }
                //         if ($record->hasRevisions()) {
                //             return 'Direvisi';
                //         }
                //         return 'Asli';
                //     })
                //     ->colors([
                //         'warning' => 'Revisi',
                //         'info' => 'Direvisi',
                //         'success' => 'Asli',
                //     ])
                //     ->icons([
                //         'heroicon-o-arrow-path' => 'Revisi',
                //         'heroicon-o-document-duplicate' => 'Direvisi',
                //         'heroicon-o-document' => 'Asli',
                //     ]),

                    
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
                    ->description(function ($record) {
                        if (!$record || $record->type !== 'Faktur Masuk') {
                            return null;
                        }
                        
                        $businessStatus = $record->is_business_related ? 'Bisnis Berkaitan' : 'Bisnis Tidak Berkaitan';
                        return $businessStatus;
                    })
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
                    
                Tables\Columns\IconColumn::make('has_bupots')
                    ->label('Bukti Potong')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->bupots()->count() > 0)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function ($record) {
                        $count = $record->bupots()->count();
                        return $count > 0 
                            ? "Faktur ini memiliki {$count} bukti potong terkait"
                            : "Faktur ini tidak memiliki bukti potong";
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
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
                    ->label('Ekspor Semua ke Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $taxReport = $this->getOwnerRecord();
                        $monthYear = FileManagementService::convertToIndonesianMonth($taxReport->month) . '_' . date('Y');
                        $filename = 'Faktur_' . $monthYear . '.xlsx';

                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\TaxReportInvoicesExport($taxReport),
                            $filename
                        );
                    })
                    ->tooltip('Ekspor semua faktur ke format Excel'),

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
                        ['taxReportId' => $this->getOwnerRecord()->id]
                    ))
                    ->tooltip(function () {
                        $taxReport = $this->getOwnerRecord();
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            if (!$client || !$client->ppn_contract) {
                                return 'Klien tidak memiliki kontrak PPN aktif.';
                            }
                        }
                        return 'Tambah 1–10 faktur sekaligus — AI mengekstrak data otomatis';
                    })
                    ->disabled(function () {
                        $taxReport = $this->getOwnerRecord();
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            return !($client && $client->ppn_contract);
                        }
                        return true;
                    }),

                Tables\Actions\CreateAction::make()
                    ->label('Input Manual')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->successNotificationTitle('Faktur berhasil disimpan')
                    ->modalHeading('Input Faktur Manual')
                    ->modalWidth('7xl')
                    ->tooltip(function () {
                        $taxReport = $this->getOwnerRecord();
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            if (!$client || !$client->ppn_contract) {
                                return 'Klien tidak memiliki kontrak PPN aktif.';
                            }
                        }
                        return 'Isi data faktur secara manual, tanpa AI. Berkas faktur opsional.';
                    })
                    ->disabled(function () {
                        $taxReport = $this->getOwnerRecord();
                        if ($taxReport) {
                            $client = \App\Models\Client::find($taxReport->client_id);
                            return !($client && $client->ppn_contract);
                        }
                        return true;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('Lihat')
                        ->modalWidth('7xl'),
                        
                    Tables\Actions\EditAction::make()
                        ->label('Edit')
                        ->modalWidth('7xl'),
                    
                    Tables\Actions\Action::make('create_revision')
                        ->label('Buat Revisi')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn ($record) => $record && !$record->is_revision)
                        ->form(fn ($record) => $this->getInvoiceFormSchema(true, $record))
                        ->fillForm(fn (\App\Models\Invoice $record) => $this->getRevisionFormData($record))
                        ->action(function (\App\Models\Invoice $record, array $data) {
                            $revision = $this->createInvoiceRevision($record, $data);

                            Notification::make()
                                ->title('Revisi Berhasil Dibuat')
                                ->body("Revisi #{$revision->revision_number} untuk faktur {$record->invoice_number} berhasil dibuat dengan nomor {$revision->invoice_number}.")
                                ->success()
                                ->send();
                        })
                        ->modalHeading(fn ($record) => 'Buat Revisi untuk Faktur: ' . $record->invoice_number)
                        ->modalSubmitActionLabel('Simpan Revisi')
                        ->modalWidth('7xl')
                        ->tooltip('Buat revisi manual dari versi terakhir faktur ini'),
                    
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
                                                $taxReport = $record->taxReport;
                                                return FileManagementService::generateBuktiSetorDirectoryPath($taxReport);
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
                    
                    Tables\Actions\Action::make('view_bukti_setor')
                        ->label('Lihat Bukti Setor')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->visible(fn ($record) => $record && !empty($record->bukti_setor))
                        ->url(fn ($record) => asset('storage/' . $record->bukti_setor))
                        ->openUrlInNewTab()
                        ->tooltip('Lihat bukti setor pajak'),
                        
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
                        ->label('Ekspor Terpilih ke Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $taxReport = $this->getOwnerRecord();
                            $selectedIds = $records->pluck('id')->toArray();
                            $monthYear = FileManagementService::convertToIndonesianMonth($taxReport->month) . '_' . date('Y');
                            $filename = 'Faktur_Terpilih_' . $monthYear . '.xlsx';
                            
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\TaxReportInvoicesExport($taxReport, $selectedIds),
                                $filename
                            );
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Ekspor Faktur Terpilih')
                        ->modalDescription(function (Collection $records) {
                            $count = $records->count();
                            return "Apakah Anda yakin ingin mengekspor {$count} faktur yang terpilih ke Excel?";
                        })
                        ->modalSubmitActionLabel('Ya, Ekspor')
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

    public function isReadOnly(): bool
    {
        return false;
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

    #[On('faktur-bulk-saved')]
    public function onFakturBulkSaved(): void
    {
        $this->unmountTableAction();
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
}