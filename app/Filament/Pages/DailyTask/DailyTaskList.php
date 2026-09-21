<?php

namespace App\Filament\Pages\DailyTask;

use App\Models\DailyTask;
use App\Models\Project;
use App\Models\User;
use App\Models\Client;
use Filament\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class DailyTaskList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationGroup = 'Tugas Harian';
    
    protected static ?string $title = 'Tugas Harian';
    
    
    protected static ?string $navigationLabel = 'Tugas Harian';
    
    protected static string $view = 'filament.pages.daily-task.daily-task-list';

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from the sidebar for now (menu parked); the page itself stays reachable.
        return false;
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()->can('daily-task.list.*');
    }
    protected static ?int $navigationSort = 2;

    /**
     * Get the header actions for the page
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_task')
                ->label('Tambah Tugas Baru')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->size('lg')
                ->modalHeading('Buat Tugas Baru')
                ->modalDescription('Ikuti langkah-langkah berikut untuk membuat tugas harian baru')
                ->modalSubmitActionLabel('Buat Tugas')
                ->modalCancelActionLabel('Batal')
                ->modalWidth(MaxWidth::SevenExtraLarge)
                ->form([
                    Forms\Components\Wizard::make([
                        Forms\Components\Wizard\Step::make('task_info')
                            ->label('Informasi Tugas')
                            ->description('Detail dasar tugas')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Judul Tugas')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Masukkan judul tugas...')
                                    ->columnSpanFull(),
                                
                                Forms\Components\Textarea::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('Deskripsi tugas (opsional)...')
                                    ->rows(4)
                                    ->columnSpanFull(),
                                
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\DatePicker::make('start_task_date')
                                            ->label('Tanggal Mulai')
                                            ->placeholder('Pilih tanggal mulai...')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Tanggal kapan tugas akan dimulai (opsional)')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\DatePicker::make('task_date')
                                            ->label('Tanggal Deadline')
                                            ->required()
                                            ->default(today())
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->helperText('Tanggal batas waktu penyelesaian tugas')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('priority')
                                            ->label('Prioritas')
                                            ->options([
                                                'low' => 'Rendah',
                                                'normal' => 'Normal', 
                                                'high' => 'Tinggi',
                                                'urgent' => 'Mendesak',
                                            ])
                                            ->default('normal')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan(1),
                                    ]),
                            ]),
                        
                        Forms\Components\Wizard\Step::make('project_info')
                            ->label('Klien & Proyek')
                            ->description('Pilih klien dan proyek terkait')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('Klien')
                                    ->placeholder('Pilih klien (opsional)')
                                    ->options(Client::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Reset project when client changes
                                        $set('project_id', null);
                                    })
                                    ->helperText('Pilih klien terlebih dahulu untuk memfilter proyek'),
                                
                                Forms\Components\Select::make('project_id')
                                    ->label('Proyek')
                                    ->placeholder('Pilih proyek (opsional)')
                                    ->options(function (callable $get) {
                                        $clientId = $get('client_id');
                                        if (!$clientId) {
                                            return Project::orderBy('name')->pluck('name', 'id');
                                        }
                                        return Project::where('client_id', $clientId)
                                            ->orderBy('name')
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->helperText(function (callable $get) {
                                        $clientId = $get('client_id');
                                        if (!$clientId) {
                                            return 'Pilih klien terlebih dahulu untuk memfilter proyek berdasarkan klien';
                                        }
                                        return 'Proyek yang tersedia berdasarkan klien yang dipilih';
                                    }),
                            ]),
                        
                        Forms\Components\Wizard\Step::make('assignment')
                            ->label('Penugasan')
                            ->description('Atur siapa yang bertanggung jawab')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Forms\Components\Select::make('assignees')
                                    ->label('Ditugaskan Kepada')
                                    ->placeholder('Pilih pengguna...')
                                    ->options(User::orderBy('name')->pluck('name', 'id'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText('Pilih satu atau lebih pengguna untuk ditugaskan. Jika kosong, akan otomatis ditugaskan kepada Anda.'),
                            ]),
                        
                        Forms\Components\Wizard\Step::make('subtasks')
                            ->label('Sub Tugas')
                            ->description('Buat daftar sub tugas (opsional)')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Forms\Components\Repeater::make('subtasks')
                                    ->label('Daftar Sub Tugas')
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label('Judul Sub Tugas')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Contoh: Review dokumen, Buat presentasi, dll.')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('Tambah Sub Tugas')
                                    ->reorderableWithButtons()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'Sub Tugas Baru')
                                    ->defaultItems(0)
                                    ->helperText('Sub tugas membantu memecah tugas besar menjadi bagian-bagian kecil yang lebih mudah dikelola.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                ])
                ->action(function (array $data): void {
                    try {
                        // Create the main task
                        $task = DailyTask::create([
                            'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                            'task_date' => $data['task_date'],
                            'start_task_date' => $data['start_task_date'] ?? null,
                            'priority' => $data['priority'],
                            'project_id' => $data['project_id'] ?? null,
                            'created_by' => Auth::id(),
                            'status' => 'pending',
                        ]);

                        // Assign users to the task if any selected
                        if (!empty($data['assignees'])) {
                            foreach ($data['assignees'] as $userId) {
                                $task->assignToUser(User::find($userId));
                            }
                        } else {
                            // If no assignees selected, assign to current user
                            $task->assignToUser(Auth::user());
                        }
                        
                        // Create subtasks if any
                        if (!empty($data['subtasks'])) {
                            foreach ($data['subtasks'] as $subtaskData) {
                                if (!empty($subtaskData['title'])) {
                                    $task->addSubtask($subtaskData['title']);
                                }
                            }
                        }
                        
                        // Show success notification
                        $subtaskCount = !empty($data['subtasks']) ? count(array_filter($data['subtasks'], fn($st) => !empty($st['title']))) : 0;
                        $successMessage = "Tugas '{$task->title}' telah berhasil dibuat.";
                        if ($subtaskCount > 0) {
                            $successMessage .= " Termasuk {$subtaskCount} sub tugas.";
                        }
                        
                        Notification::make()
                            ->title('Tugas Berhasil Dibuat')
                            ->body($successMessage)
                            ->success()
                            ->duration(5000)
                            ->send();
                            
                        // Refresh the page component to show new task
                        $this->dispatch('task-created');
                        
                    } catch (\Exception $e) {
                        // Show error notification
                        Notification::make()
                            ->title('Gagal Membuat Tugas')
                            ->body('Terjadi kesalahan saat membuat tugas: ' . $e->getMessage())
                            ->danger()
                            ->duration(7000)
                            ->send();
                    }
                }),
                
            Actions\Action::make('view_calendar')
                ->label('Lihat Kalender')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url('#') // You can add calendar view later
                ->visible(false), // Hide for now until calendar is implemented
        ];
    }

    /**
     * Get the header widgets
     */
    protected function getHeaderWidgets(): array
    {
        return [
            // You can add header widgets here later for stats/summary cards
        ];
    }
}