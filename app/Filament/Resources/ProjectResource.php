<?php

namespace App\Filament\Resources;
use Closure;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Filament\Resources\ProjectResource\RelationManagers\ClientRelationManager;
use App\Filament\Resources\ProjectResource\RelationManagers\StepsRelationManager;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use App\Models\Sop;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Support\Enums\Alignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Forms\Components\Section as FormSection;
use Filament\Tables\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use IbrahimBougaoua\FilaProgress\Infolists\Components\ProgressBarEntry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Guava\FilamentModalRelationManagers\Actions\Table\RelationManagerAction;
use Filament\Actions\CreateAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Swis\Filament\Activitylog\Tables\Actions\ActivitylogAction;
use App\Exports\ProjectMultiSheetExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProjectNote;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationGroup = 'Project Management';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard';
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Project Details')
                        ->description('Basic project information')
                        ->icon('heroicon-o-clipboard-document')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->label('Project Name')
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('client_id')
                                ->required()
                                ->label('Client')
                                ->options(fn () => Client::pluck('name', 'id'))
                                ->disableOptionWhen(
                                    fn(string $value): bool =>
                                    Client::find($value)?->status === 'Inactive'
                                )
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    // Reset PIC when client changes
                                    $set('pic_id', null);
                                })
                                ->native(false)
                                ->columnSpan(2)
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nama Client')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Masukkan nama client baru')
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    // Create client
                                    $client = Client::create([
                                        'name' => $data['name'],
                                        'status' => 'Active', // Set default status
                                    ]);

                                    // Automatically assign current user to this client
                                    \App\Models\UserClient::create([
                                        'user_id' => auth()->id(),
                                        'client_id' => $client->id,
                                    ]);

                                    // Send notification to user
                                    \Filament\Notifications\Notification::make()
                                        ->title('Client Baru Dibuat')
                                        ->body("Client '{$client->name}' berhasil dibuat dan Anda telah ditugaskan sebagai anggota tim.")
                                        ->success()
                                        ->send();

                                    return $client->id;
                                }),

                            Select::make('pic_id')
                                ->label('Person in Charge (PIC)')
                                ->options(function (Forms\Get $get) {
                                    $clientId = $get('client_id');

                                    if (!$clientId) {
                                        return [];
                                    }

                                    // Get users related to the selected client through user_clients
                                    // FILTER: hanya user aktif dengan role tertentu
                                    return User::whereHas('userClients', function ($query) use ($clientId) {
                                        $query->where('client_id', $clientId);
                                    })
                                        ->where('status', 'active')
                                        ->whereHas('roles', function ($query) {
                                        $query->whereIn('name', ['project-manager', 'direktur', 'super-admin']);
                                    })
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->live()
                                ->native(false)
                                ->helperText(function (Forms\Get $get) {
                                    $clientId = $get('client_id');
                                    return $clientId
                                        ? 'Select a Project Manager, Director, or Super Admin as PIC'
                                        : 'Please select a client first';
                                })
                                ->columnSpan(2),

                            Select::make('department_id')
                                ->label('Departemen')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->placeholder('Pilih departemen')
                                ->columnSpan(2),

                            Select::make('type')
                                ->label('Type')
                                ->options([
                                    'single' => 'On Spot',
                                    'monthly' => 'Monthly',
                                    'yearly' => 'Yearly',
                                ])
                                ->required()
                                ->native(false),
                            Select::make('priority')
                                ->label('Priority')
                                ->options([
                                    'urgent' => 'Urgent',
                                    'normal' => 'Normal',
                                    'low' => 'Low',
                                ])
                                ->required()
                                ->native(false)
                                ->default('normal'),
                            Select::make('sop_id')
                                ->label('Standard Operating Procedure')
                                ->options(Sop::query()->pluck('name', 'id'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    if (!$state) {
                                        $set('steps', []);
                                        return;
                                    }

                                    $sop = Sop::with(['steps.tasks', 'steps.requiredDocuments'])->find($state);
                                    if (!$sop)
                                        return;

                                    // Set project type and type from SOP
                                    $set('type', $sop->type);
                                    $set('project_type', $sop->project_type);

                                    // Prepare steps data
                                    $stepsData = $sop->steps->map(function ($step) {
                                        return [
                                            'name' => $step->name,
                                            'description' => $step->description,
                                            'order' => $step->order,
                                            'priority' => $step->priority,
                                            'tasks' => $step->tasks->map(fn($task) => [
                                                'title' => $task->title,
                                                'description' => $task->description,
                                                'requires_document' => $task->requires_document,
                                            ])->toArray(),
                                            'requiredDocuments' => $step->requiredDocuments->map(fn($doc) => [
                                                'name' => $doc->name,
                                                'description' => $doc->description,
                                                'is_required' => $doc->is_required,
                                            ])->toArray(),
                                        ];
                                    })->toArray();

                                    $set('steps', $stepsData);
                                })
                                ->native(false),
                            DatePicker::make('due_date')
                                ->required()
                                ->date()
                                ->afterStateHydrated(function ($state) {
                                    return $state ? Carbon::parse($state)->format('d M Y') : '-';
                                })
                                ->beforeStateDehydrated(function ($state) {
                                    return $state ? Carbon::parse($state)->format('Y-m-d') : null;
                                })
                                ->native(false),

                            Forms\Components\RichEditor::make('description')
                                ->columnSpanFull(),

                        ])->columns(4),

                    Forms\Components\Wizard\Step::make('Project Steps')
                        ->description('Configure steps, tasks, and documents')
                        ->icon('heroicon-o-squares-plus')
                        ->schema([
                            Repeater::make('steps')
                                ->label('Project Step')
                                ->addActionLabel('Add New Step')
                                ->relationship()
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->columnSpanFull(),
                                    Forms\Components\RichEditor::make('description')
                                        ->toolbarButtons([
                                            'bold',
                                            'bulletList',
                                            'italic',
                                            'link',
                                            'orderedList',
                                            'undo',
                                            'redo',
                                        ])
                                        ->columnSpanFull(),
                                    FormSection::make('Tasks')
                                        ->description('Add and manage tasks for this project step')
                                        ->collapsible()
                                        ->schema([
                                            Repeater::make('tasks')
                                                ->label('Project Task')
                                                ->relationship('tasks')
                                                ->schema([
                                                    TextInput::make('title')
                                                        ->label('Task Title')
                                                        ->required(),
                                                    Forms\Components\RichEditor::make('description')
                                                        ->toolbarButtons([
                                                            'bold',
                                                            'bulletList',
                                                            'italic',
                                                            'link',
                                                            'orderedList',
                                                            'undo',
                                                            'redo',
                                                        ]),
                                                ])
                                                ->collapsed()
                                                ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                                                ->addActionLabel('Add New Task'),
                                        ]),
                                    FormSection::make('Required Documents')
                                        ->description('Specify required documents for this project step')
                                        ->collapsible()
                                        ->schema([
                                            Repeater::make('requiredDocuments')
                                                ->label('Required Documents')
                                                ->relationship('requiredDocuments')
                                                ->schema([
                                                    TextInput::make('name')
                                                        ->label('Documents Name')
                                                        ->required(),
                                                    Forms\Components\RichEditor::make('description')
                                                        ->toolbarButtons([
                                                            'bold',
                                                            'bulletList',
                                                            'italic',
                                                            'link',
                                                            'orderedList',
                                                            'undo',
                                                            'redo',
                                                        ]),
                                                ])
                                                ->collapsed()
                                                ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                                ->addActionLabel('Add New Document')
                                        ])
                                ])
                                ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                ->orderColumn('order')
                                ->collapsed()
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Wizard\Step::make('Project Members')
                        ->description('Assign team members to the project')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Repeater::make('userProject')
                                ->label('')
                                ->relationship()
                                ->schema([
                                    Select::make('user_id')
                                        ->label('Member')
                                        ->options(function (Forms\Get $get) {
                                            $clientId = $get('../../client_id');

                                            if (!$clientId) {
                                                return [];
                                            }

                                            return User::whereHas('userClients', function ($query) use ($clientId) {
                                                $query->where('client_id', $clientId);
                                            })
                                                ->whereDoesntHave('roles', function ($query) {
                                                    $query->where('name', 'direktur');
                                                })
                                                ->where('status', 'active')
                                                ->pluck('name', 'id');
                                        })
                                        ->live()
                                        ->required()
                                        ->searchable()
                                        ->distinct()
                                        ->native(false)
                                        ->helperText(function (Forms\Get $get) {
                                            $clientId = $get('../../client_id');
                                            return $clientId
                                                ? 'Select a user from this client'
                                                : 'Please select a client first';
                                        })
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ])
                                ->addActionLabel('Add New Member')
                                ->columnSpanFull(),
                        ]),
                ])
                    ->columnSpanFull()
                    ->skippable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                $user = auth()->user();

                // Optimize base query with eager loading of commonly used relationships
                $baseQuery = Project::query()
                    ->with([
                        'client',
                        'pic', // Add PIC relationship to eager loading
                        'steps' => function ($query) {
                        $query->select('id', 'project_id', 'name', 'status', 'order')
                            ->orderBy('order');
                    },
                        'steps.tasks' => function ($query) {
                        $query->select('id', 'project_step_id', 'title', 'status');
                    },
                        'steps.requiredDocuments' => function ($query) {
                        $query->select('id', 'project_step_id', 'name', 'status');
                    }
                    ]);

                // No user_clients gate — all authenticated users see all projects.
                return $baseQuery;
            })
            ->columns([
                Tables\Columns\TextColumn::make('index')
                    ->label('No. ')
                    ->rowIndex()
                    ->sortable(false),

                // Client Information
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Nama Klien')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->numeric()
                    ->sortable(),

                // Project Information
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Proyek')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                // Person in Charge
                Tables\Columns\TextColumn::make('pic.name')
                    ->label('PIC')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->placeholder('PIC Belum Ditugaskan')
                    ->tooltip(fn($record) => $record->pic ? "Person in Charge: {$record->pic->name}" : 'No PIC assigned'),

                // Project Status — category-driven color and label so the
                // table works for user-created statuses.
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(function ($record): string {
                        return match ($record->statusRecord?->category) {
                            'not_started' => 'gray',
                            'active'      => 'warning',
                            'done'        => 'success',
                            'closed'      => 'danger',
                            default       => 'gray',
                        };
                    })
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        return $record->statusRecord?->label
                            ?? Str::title(str_replace('_', ' ', (string) $state));
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'normal' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'urgent' => 'heroicon-m-fire',
                        'normal' => 'heroicon-m-arrow-trending-up',
                        'low' => 'heroicon-m-arrow-trending-down',
                        default => 'heroicon-m-minus',
                    })
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn(string $state): string => __(Str::title($state)))
                    ->alignCenter()
                    ->tooltip(function (string $state): string {
                        return match ($state) {
                            'urgent' => 'High priority - requires immediate attention',
                            'normal' => 'Standard priority - handle in regular workflow',
                            'low' => 'Low priority - handle when resources available',
                            default => 'Priority not set',
                        };
                    }),

                // SOP Name
                Tables\Columns\TextColumn::make('sop.name')
                    ->label('SOP')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Lapor SPT Tahunan Nihil' => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('No SOP')
                    ->toggleable(),

                // Progress Bar
                ProgressBar::make('bar')
                    ->label('Progress')
                    ->getStateUsing(function ($record) {
                        // Get all steps for the project
                        $steps = $record->steps;

                        // Initialize counters for total and completed items
                        $totalItems = 0;
                        $completedItems = 0;

                        foreach ($steps as $step) {
                            // Count step itself
                            $totalItems++;
                            if ($step->status === 'completed') {
                                $completedItems++;
                            }

                            // Count and check tasks
                            $tasks = $step->tasks;
                            $totalItems += $tasks->count();
                            $completedItems += $tasks->where('status', 'completed')->count();

                            // Count and check required documents
                            $documents = $step->requiredDocuments;
                            $totalItems += $documents->count();
                            $completedItems += $documents->where('status', 'approved')->count();
                        }

                        return [
                            'total' => $totalItems ?: 1, // Prevent division by zero
                            'progress' => $completedItems,
                        ];
                    }),

                // Timestamps
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Notes indicator
                Tables\Columns\IconColumn::make('notes_indicator')
                    ->label('')
                    ->icon(fn(Project $record): string => $record->notes()->exists() ? 'heroicon-s-chat-bubble-bottom-center-text' : '')
                    ->color(fn(Project $record): string => match (optional($record->notes()->latest()->first())->type) {
                        'blocker' => 'danger',
                        'important' => 'warning',
                        default => 'info',
                    })
                    ->tooltip(
                        fn(Project $record): ?string => $record->notes()->exists()
                        ? $record->notes()->count() . ' note(s) — Latest: ' . optional($record->notes()->latest()->first())->created_at?->diffForHumans()
                        : null
                    )
                    ->alignCenter()
                    ->sortable(false)
                    ->getStateUsing(fn(Project $record) => $record->notes()->exists()),
            ])
            // Filters
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(fn () => \App\Models\ProjectStatus::ordered()->pluck('label', 'key')->toArray())
                    ->default(['draft', 'in_progress']),



                SelectFilter::make('pic_id')
                    ->label('Person in Charge')
                    ->options(fn () =>
                        User::where('status', 'active')
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['project-manager', 'direktur', 'super-admin']))
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('client_status')
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
                    ->default(['Active']) // Default hanya tampilkan client dengan status Active
                    ->preload(),

                SelectFilter::make('client_id')
                    ->label('Client')
                    ->options(fn () => Client::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        // Explicitly use projects.client_id to avoid ambiguity
                        return $query->where('projects.client_id', $data['value']);
                    }),

                DateRangeFilter::make('due_date'),
            ])
            // Row Actions
            ->actions([
                // Combined Notes action — history at top, add-note form at bottom
                Tables\Actions\Action::make('notes')
                    ->label('Notes')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->badge(fn(Project $record): ?string => ($count = $record->notes()->count()) ? (string) $count : null)
                    ->badgeColor('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn(Project $record) => 'Notes — ' . $record->name)
                    ->modalContent(function (Project $record) {
                        $notes = $record->notes()->with('user')->get();
                        return view('filament.modals.projects.project-notes', [
                            'notes' => $notes,
                            'project' => $record,
                        ]);
                    })
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Note Type')
                            ->options([
                                'general' => '💬 Umum',
                                'important' => '⚠️ Penting',
                                'blocker' => '🚫 Penghambat',
                            ])
                            ->default('general')
                            ->required()
                            ->native(false),
                        Forms\Components\Textarea::make('content')
                            ->label('New Note')
                            ->placeholder('Write your note here...')
                            ->required()
                            ->minLength(3)
                            ->maxLength(2000)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (Project $record, array $data): void {
                        ProjectNote::create([
                            'project_id' => $record->id,
                            'user_id' => auth()->id(),
                            'content' => $data['content'],
                            'type' => $data['type'],
                        ]);

                        Notification::make()
                            ->title('Note Added')
                            ->body('Your note has been saved.')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Add Note')
                    ->modalCancelActionLabel('Close'),
            ])

            ->recordUrl(
                fn(Project $record): string =>
                static::getUrl('view', ['record' => $record])
            )
            ->defaultSort('status', 'asc')
            ->groups([
                'client.name',
                'pic.name', // Add PIC grouping option
            ])
            ->deferLoading()
            // Bulk Actions
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_assign_pic')
                        ->label('Assign PIC to Projects')
                        ->icon('heroicon-o-user-plus')
                        ->color('info')
                        ->form([
                            Select::make('pic_id')
                                ->label('Select Person in Charge (PIC)')
                                ->options(fn () =>
                                    User::where('status', 'active')
                                        ->whereHas('roles', fn ($q) => $q->whereIn('name', ['project-manager', 'direktur', 'super-admin']))
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->native(false)
                                ->helperText('Only Project Managers, Directors, and Super Admins can be assigned as PIC'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $picUser = User::find($data['pic_id']);
                            $updatedCount = 0;

                            if (!$picUser) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('Selected PIC tidak ditemukan.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \DB::transaction(function () use ($records, $data, &$updatedCount) {
                                foreach ($records as $project) {
                                    $project->update(['pic_id' => $data['pic_id']]);
                                    $updatedCount++;
                                }
                            });

                            if ($updatedCount > 0) {
                                Notification::make()
                                    ->title('PIC Berhasil Ditugaskan')
                                    ->body("Berhasil menugaskan {$picUser->name} sebagai PIC untuk {$updatedCount} proyek.")
                                    ->success()
                                    ->send();

                                // Send notification to the assigned PIC
                                if ($picUser->id !== auth()->id()) {
                                    Notification::make()
                                        ->title('Anda Ditugaskan sebagai PIC')
                                        ->body("Anda telah ditugaskan sebagai Person in Charge untuk {$updatedCount} proyek baru.")
                                        ->info()
                                        ->sendToDatabase($picUser);
                                }
                            } else {
                                Notification::make()
                                    ->title('Tidak Ada Proyek yang Diperbarui')
                                    ->body('Tidak ada proyek yang dapat diperbarui. Pastikan Anda memiliki akses ke proyek yang dipilih.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Assign PIC ke Multiple Projects')
                        ->modalDescription('Pilih Person in Charge yang akan ditugaskan ke semua proyek yang dipilih.')
                        ->modalSubmitActionLabel('Assign PIC'),
                    Tables\Actions\BulkAction::make('ubah_sop')
                        ->label('Ubah SOP')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->form([
                            Select::make('sop_id')
                                ->label('Pilih SOP Baru')
                                ->options(Sop::query()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->native(false)
                                ->helperText('Pilih SOP yang akan diterapkan ke proyek yang dipilih.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Ubah SOP Proyek')
                        ->modalDescription('⚠️ PERINGATAN: Tindakan ini akan menghapus semua langkah, tugas, dan dokumen yang ada pada proyek yang dipilih dan menggantinya dengan template dari SOP baru. Jika ada progress yang sudah berjalan, progress tersebut akan direset ke 0. Tindakan ini tidak dapat dibatalkan!')
                        ->modalSubmitActionLabel('Ubah SOP')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $updatedCount = 0;
                            $projectsWithProgress = [];

                            // Find the selected SOP
                            $targetSop = Sop::with(['steps.tasks', 'steps.requiredDocuments'])
                                ->find($data['sop_id']);

                            if (!$targetSop) {
                                Notification::make()
                                    ->title('SOP Tidak Ditemukan')
                                    ->body('SOP yang dipilih tidak ditemukan di sistem.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \DB::transaction(function () use ($records, $targetSop, &$updatedCount, &$projectsWithProgress) {
                                foreach ($records as $project) {
                                    // Check if project has any progress
                                    $hasProgress = $project->steps()->whereHas('tasks', function ($q) {
                                        $q->where('status', 'completed');
                                    })->exists() || $project->steps()->whereHas('requiredDocuments', function ($q) {
                                        $q->whereIn('status', ['approved', 'uploaded', 'pending_review']);
                                    })->exists();

                                    if ($hasProgress) {
                                        $projectsWithProgress[] = $project->name;
                                    }

                                    // Update SOP
                                    $project->update([
                                        'sop_id' => $targetSop->id,
                                    ]);

                                    // Delete existing steps (cascades to tasks and documents)
                                    $project->steps()->delete();

                                    // Copy steps from the target SOP
                                    foreach ($targetSop->steps as $sopStep) {
                                        $projectStep = $project->steps()->create([
                                            'name' => $sopStep->name,
                                            'description' => $sopStep->description,
                                            'order' => $sopStep->order,
                                            'status' => 'pending',
                                        ]);

                                        // Copy tasks from SOP step
                                        foreach ($sopStep->tasks as $sopTask) {
                                            $projectStep->tasks()->create([
                                                'title' => $sopTask->title,
                                                'description' => $sopTask->description,
                                                'status' => 'pending',
                                            ]);
                                        }

                                        // Copy required documents from SOP step
                                        foreach ($sopStep->requiredDocuments as $sopDoc) {
                                            $projectStep->requiredDocuments()->create([
                                                'name' => $sopDoc->name,
                                                'description' => $sopDoc->description,
                                                'is_required' => $sopDoc->is_required ?? true,
                                                'status' => 'draft',
                                            ]);
                                        }
                                    }

                                    $updatedCount++;
                                }
                            });

                            if ($updatedCount > 0) {
                                $message = "Berhasil mengubah SOP untuk {$updatedCount} proyek ke '{$targetSop->name}'.";

                                if (!empty($projectsWithProgress)) {
                                    $message .= " Progress pada " . count($projectsWithProgress) . " proyek telah direset.";
                                }

                                Notification::make()
                                    ->title('SOP Berhasil Diubah')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Tidak Ada Proyek yang Diperbarui')
                                    ->body('Tidak ada proyek yang berhasil diubah.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    Tables\Actions\BulkAction::make('export_projects')
                        ->label('Ekspor Proyek')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Select::make('group_by')
                                ->label('Kelompokkan Sheet Berdasarkan')
                                ->options([
                                    'none' => 'Tanpa Pengelompokan (1 Sheet)',
                                    'pic' => 'PIC (Person In Charge)',
                                    'status' => 'Status Proyek',
                                    'priority' => 'Prioritas',
                                    'sop' => 'SOP',
                                    'client' => 'Klien',
                                    'type' => 'Tipe Proyek',
                                ])
                                ->default('none')
                                ->native(false)
                                ->helperText('Pilih cara pengelompokan proyek menjadi sheet terpisah dalam file Excel.'),
                        ])
                        ->modalHeading('Ekspor Proyek')
                        ->modalDescription('Pilih opsi pengelompokan untuk ekspor data proyek.')
                        ->modalSubmitActionLabel('Ekspor')
                        ->modalIcon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records, array $data) {
                            $selectedIds = $records->pluck('id')->toArray();
                            $groupBy = $data['group_by'] ?? 'none';
                            $fileName = 'proyek-' . now()->format('Y-m-d-His') . '.xlsx';

                            return Excel::download(new ProjectMultiSheetExport($selectedIds, $groupBy), $fileName);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
                //
            StepsRelationManager::class,
            ClientRelationManager::class
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Client' => $record->client->name,
            'Status' => $record->status,
            'PIC' => $record->pic?->name ?? 'No PIC assigned', // Add PIC to search results
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::openProjectsQuery()->count();
    }

    /** Projects counted in the sidebar badges: not completed, for active clients. */
    protected static function openProjectsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::getModel()::query()
            ->whereHas('client', fn ($query) => $query->where('status', 'Active'))
            ->where('status', '!=', 'completed');
    }

    /**
     * "Projects" plus one child item per department (and "Tanpa Departemen"),
     * each linking to the list pre-filtered with ?dept=. Filament shows the
     * children while the Projects section is the active one.
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();
        $parent = $items[0];

        $counts = static::openProjectsQuery()
            ->selectRaw('department_id, COUNT(*) AS total')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        $isIndex = fn () => request()->routeIs(static::getRouteBaseName() . '.index');
        $baseUrl = static::getUrl('index');

        $children = \App\Models\Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($dept) => \Filament\Navigation\NavigationItem::make($dept->name)
                ->url($baseUrl . '?dept=' . $dept->id)
                ->badge(($counts[$dept->id] ?? 0) > 0 ? (string) $counts[$dept->id] : null, color: 'gray')
                ->isActiveWhen(fn () => $isIndex() && request()->query('dept') === (string) $dept->id))
            ->all();

        if (($counts[null] ?? $counts[''] ?? 0) > 0) {
            $children[] = \Filament\Navigation\NavigationItem::make('Tanpa Departemen')
                ->url($baseUrl . '?dept=none')
                ->badge((string) ($counts[null] ?? $counts['']), color: 'gray')
                ->isActiveWhen(fn () => $isIndex() && request()->query('dept') === 'none');
        }

        $parent->childItems($children);

        return $items;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'pic.name']; // Add PIC name to searchable attributes
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return ProjectResource::getUrl('view', ['record' => $record]);
    }


    public static function sendProjectNotifications(string $title, string $body, $project, string $type = 'info', ?string $action = null): void
    {
        // Create the notification template
        $notificationTemplate = Notification::make()
            ->title($title)
            ->body($body)
            ->icon(match ($type) {
                'success' => 'heroicon-o-check-circle',
                'danger' => 'heroicon-o-x-circle',
                'warning' => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-information-circle',
            })
            ->persistent();

        // Add action if provided
        if ($action) {
            $notificationTemplate->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label($action)
                    ->url(static::getUrl('view', ['record' => $project->id])),
                \Filament\Notifications\Actions\Action::make('Mark As Read')
                    ->markAsRead(),
            ]);
        }

        // Optimize recipient query - use a single query with joins instead of eager loading
        $recipients = \App\Models\User::select('users.*')
            ->join('user_projects', 'users.id', '=', 'user_projects.user_id')
            ->where('user_projects.project_id', $project->id)
            ->where('users.id', '!=', auth()->id()) // Exclude current user
            ->distinct()
            ->get();

        // Also notify the PIC if they exist and aren't already in the recipients
        if ($project->pic && !$recipients->contains('id', $project->pic->id) && $project->pic->id !== auth()->id()) {
            $recipients->push($project->pic);
        }

        // Use database transaction to ensure all notifications are created or none
        \DB::transaction(function () use ($recipients, $notificationTemplate, $type) {
            // Process in chunks for large recipient lists
            foreach ($recipients->chunk(50) as $chunk) {
                foreach ($chunk as $user) {
                    $notificationTemplate->sendToDatabase($user)->broadcast($user);
                }
            }
        });

        // Send UI notification to current user
        Notification::make()
                    ->title($title)
                    ->body($body)
            ->{$type}()
                ->send();
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'activity' => Pages\ViewProjectActivity::route('/{record}/activity'),
        ];
    }
}