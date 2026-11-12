<?php

namespace App\Livewire\TaxReport\Pph;

use App\Models\Employee;
use App\Models\Client;
use App\Models\TaxReport;
use App\Services\FileManagementService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TaxReport\Pph\KaryawanListExport;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Component;
use Filament\Tables\Filters\SelectFilter;

class KaryawanList extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $clientId;
    public $client;
    public $taxReportId;
    
    // Statistics
    public $totalKaryawan = 0;
    public $activeKaryawan = 0;
    public $inactiveKaryawan = 0;
    public $totalGaji = 0;

    public function mount($clientId, $taxReportId = null)
    {
        $this->clientId = $clientId;
        $this->taxReportId = $taxReportId;
        $this->client = Client::find($clientId);
        $this->calculateStatistics();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Employee::query()
                    ->where('client_id', $this->clientId)
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable()
                    ->copyMessage('Nama disalin!')
                    ->description(fn (Employee $record): string => 
                        $record->position ?? 'Tidak ada posisi'
                    ),
                
                TextColumn::make('npwp')
                    ->label('NPWP')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $this->formatNpwp($state))
                    ->copyable()
                    ->placeholder('Belum ada NPWP'),
                
                TextColumn::make('position')
                    ->label('Posisi')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak ada posisi'),
                
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Karyawan Tetap' => 'gray',
                        'Harian' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                
                TextColumn::make('marital_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'single' => 'TK',
                        'married' => 'K',
                        default => 'TK',
                    })
                    ->description(fn (Employee $record): string => 
                        $record->marital_status === 'married' 
                            ? "K/{$record->k}" 
                            : "TK/{$record->tk}"
                    )
                    ->color(fn ($state): string => match($state) {
                        'married' => 'gray',
                        'single' => 'gray',
                        default => 'gray',
                    }),
                
                TextColumn::make('salary')
                    ->label('Gaji')
                    ->formatStateUsing(fn ($state) => \App\Services\TaxReport\ReportOverview::rupiah((float) $state))
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('Belum diatur'),
                
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak aktif',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'primary',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ])
                    ->default('active'),
                
                SelectFilter::make('type')
                    ->label('Tipe Karyawan')
                    ->options([
                        'Karyawan Tetap' => 'Karyawan Tetap',
                        'Harian' => 'Harian',
                    ])
                    ->multiple(),
                
                SelectFilter::make('marital_status')
                    ->label('Status Pernikahan')
                    ->options([
                        'single' => 'Belum Menikah (TK)',
                        'married' => 'Menikah (K)',
                    ])
                    ->multiple(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Ekspor Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportToExcel())
                    ->visible(fn () => Employee::where('client_id', $this->clientId)->count() > 0),
                
                Action::make('create')
                    ->label('Tambah Karyawan')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->mask('99.999.999.9-999.999')
                            ->placeholder('00.000.000.0-000.000')
                            ->maxLength(20),
                        
                        TextInput::make('position')
                            ->label('Posisi/Jabatan')
                            ->maxLength(255),
                        
                        Select::make('type')
                            ->label('Tipe Karyawan')
                            ->options([
                                'Karyawan Tetap' => 'Karyawan Tetap',
                                'Harian' => 'Harian',
                            ])
                            ->default('Karyawan Tetap')
                            ->required(),
                        
                        Select::make('marital_status')
                            ->label('Status Pernikahan')
                            ->options([
                                'single' => 'Belum Menikah (TK)',
                                'married' => 'Menikah (K)',
                            ])
                            ->default('single')
                            ->required()
                            ->reactive(),
                        
                        TextInput::make('tk')
                            ->label('Jumlah Tanggungan (TK)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(3)
                            ->visible(fn (callable $get) => $get('marital_status') === 'single')
                            ->helperText('0-3 tanggungan untuk TK'),
                        
                        TextInput::make('k')
                            ->label('Jumlah Tanggungan (K)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(3)
                            ->visible(fn (callable $get) => $get('marital_status') === 'married')
                            ->helperText('0-3 tanggungan untuk K'),
                        
                        TextInput::make('salary')
                            ->label('Gaji Bulanan')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('5000000'),
                        
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        // Clean NPWP
                        if (isset($data['npwp'])) {
                            $data['npwp'] = preg_replace('/[^0-9]/', '', $data['npwp']);
                        }
                        
                        // Set client_id
                        $data['client_id'] = $this->clientId;
                        
                        // Set default tanggungan based on marital status
                        if ($data['marital_status'] === 'single') {
                            $data['k'] = 0;
                        } else {
                            $data['tk'] = 0;
                        }
                        
                        Employee::create($data);
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Karyawan Berhasil Ditambahkan')
                            ->success()
                            ->send();
                    }),
                
                Action::make('refresh_statistics')
                    ->label('Refresh Statistik')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Statistik Diperbarui')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Ubah')
                    ->iconButton()
                    ->tooltip('Ubah')
                    ->icon('heroicon-o-pencil')
                    ->color('gray')
                    ->form([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->mask('99.999.999.9-999.999')
                            ->maxLength(20),
                        
                        TextInput::make('position')
                            ->label('Posisi/Jabatan')
                            ->maxLength(255),
                        
                        Select::make('type')
                            ->label('Tipe Karyawan')
                            ->options([
                                'Karyawan Tetap' => 'Karyawan Tetap',
                                'Harian' => 'Harian',
                            ])
                            ->required(),
                        
                        Select::make('marital_status')
                            ->label('Status Pernikahan')
                            ->options([
                                'single' => 'Belum Menikah (TK)',
                                'married' => 'Menikah (K)',
                            ])
                            ->required()
                            ->reactive(),
                        
                        TextInput::make('tk')
                            ->label('Jumlah Tanggungan (TK)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3)
                            ->visible(fn (callable $get) => $get('marital_status') === 'single'),
                        
                        TextInput::make('k')
                            ->label('Jumlah Tanggungan (K)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(3)
                            ->visible(fn (callable $get) => $get('marital_status') === 'married'),
                        
                        TextInput::make('salary')
                            ->label('Gaji Bulanan')
                            ->numeric()
                            ->prefix('Rp'),
                        
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->required(),
                    ])
                    ->fillForm(fn (Employee $record): array => [
                        'name' => $record->name,
                        'npwp' => $record->npwp,
                        'position' => $record->position,
                        'type' => $record->type,
                        'marital_status' => $record->marital_status,
                        'tk' => $record->tk,
                        'k' => $record->k,
                        'salary' => $record->salary,
                        'status' => $record->status,
                    ])
                    ->action(function (Employee $record, array $data): void {
                        // Clean NPWP
                        if (isset($data['npwp'])) {
                            $data['npwp'] = preg_replace('/[^0-9]/', '', $data['npwp']);
                        }
                        
                        // Set tanggungan based on marital status
                        if ($data['marital_status'] === 'single') {
                            $data['k'] = 0;
                        } else {
                            $data['tk'] = 0;
                        }
                        
                        $record->update($data);
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Data Karyawan Berhasil Diupdate')
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
                    ->action(function (Employee $record) {
                        $record->delete();
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Karyawan Berhasil Dihapus')
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
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['status' => $data['status']]);
                        
                        $this->calculateStatistics();
                        
                        Notification::make()
                            ->title('Status Berhasil Diupdate')
                            ->body(count($records) . ' karyawan berhasil diupdate.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                
                BulkAction::make('update_type')
                    ->label('Update Tipe')
                    ->icon('heroicon-o-tag')
                    ->color('gray')
                    ->form([
                        Select::make('type')
                            ->label('Tipe Baru')
                            ->options([
                                'Karyawan Tetap' => 'Karyawan Tetap',
                                'Harian' => 'Harian',
                            ])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each->update(['type' => $data['type']]);
                        
                        Notification::make()
                            ->title('Tipe Berhasil Diupdate')
                            ->body(count($records) . ' karyawan berhasil diupdate.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Belum ada karyawan')
            ->emptyStateDescription('Tambahkan karyawan untuk klien ini')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                // Opens the header "Tambah Karyawan" form (it used to click the first table button on the page).
                Action::make('create_first')
                    ->label('Tambah Karyawan')
                    ->icon('heroicon-o-plus')
                    ->action(fn () => $this->replaceMountedTableAction('create')),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll('30s');
    }

    /**
     * Export employee data to Excel and save to DATA MENTAH folder
     */
    protected function exportToExcel()
    {
        try {
            $employeeCount = Employee::where('client_id', $this->clientId)->count();
            
            if ($employeeCount === 0) {
                Notification::make()
                    ->title('Tidak Ada Data')
                    ->body('Tidak ada data karyawan untuk diekspor')
                    ->warning()
                    ->send();
                return;
            }
            
            // Get tax report info to build path
            $taxReport = null;
            if ($this->taxReportId) {
                $taxReport = TaxReport::find($this->taxReportId);
            }
            
            // Generate directory path
            if ($taxReport) {
                $directoryPath = $this->generateDataMentahPath($taxReport);
            } else {
                // Fallback if no tax report (use current date)
                $clientName = \Illuminate\Support\Str::slug($this->client->name);
                $currentYear = date('Y');
                $currentMonth = FileManagementService::convertToIndonesianMonth(date('m'));
                $directoryPath = "clients/{$clientName}/Kegiatan Perusahaan/{$currentYear}/SPT MASA/{$currentMonth}/PPH 21/DATA MENTAH";
            }
            
            // Fixed filename (will overwrite existing file)
            $filename = "Data_Karyawan.xlsx";
            $fullPath = "{$directoryPath}/{$filename}";
            
            // Create the export
            $export = new KaryawanListExport($this->clientId, $this->client->name);
            
            // Delete existing file if it exists (to ensure overwrite)
            if (Storage::disk('public')->exists($fullPath)) {
                Storage::disk('public')->delete($fullPath);
            }
            
            // Save to storage (public disk) - this will overwrite
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
                
            \Log::error('Karyawan Export Error: ' . $e->getMessage());
        }
    }

    /**
     * Generate DATA MENTAH directory path
     */
    protected function generateDataMentahPath($taxReport): string
    {
        $clientName = \Illuminate\Support\Str::slug($taxReport->client->name);
        $monthName = FileManagementService::convertToIndonesianMonth($taxReport->month);
        
        preg_match('/(\d{4})/', $taxReport->month, $matches);
        $year = $matches[1] ?? date('Y');
        
        return "clients/{$clientName}/Kegiatan Perusahaan/{$year}/SPT MASA/{$monthName}/PPH 21/DATA MENTAH";
    }

    /**
     * Calculate employee statistics
     */
    public function calculateStatistics(): void
    {
        $employees = Employee::where('client_id', $this->clientId)->get();
        
        $this->totalKaryawan = $employees->count();
        $this->activeKaryawan = $employees->where('status', 'active')->count();
        $this->inactiveKaryawan = $employees->where('status', 'inactive')->count();
        $this->totalGaji = $employees->where('status', 'active')->sum('salary');
    }

    /**
     * Format NPWP for display
     */
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

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.tax-report.pph.karyawan-list');
    }
}