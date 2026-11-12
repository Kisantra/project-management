<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SopResource\Pages;
use App\Filament\Resources\SopResource\RelationManagers;
use App\Models\Sop;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section as FormSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
class SopResource extends Resource
{
    protected static ?string $model = Sop::class;
    protected static ?string $navigationGroup = 'Standard Operating Procedures';

    protected static ?string $navigationLabel = 'Prosedur Proyek';
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $modelLabel = 'Prosedur Proyek';
    protected static ?string $pluralModelLabel = 'Prosedur Proyek';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('sop.*');
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()->can('sop.*');
    }
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SOP Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->unique(ignoreRecord:True)
                            ->columnSpanFull()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Steps')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                FormSection::make('Tasks')
                                    ->description('Add and manage tasks for this SOP step')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('tasks')
                                            ->label('Project Task')
                                            ->relationship('tasks')
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Task Title')
                                                    ->required(),
                                            ])
                                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                                            ->addActionLabel('Add New Task'),
                                    ]),
                                FormSection::make('Required Documents')
                                    ->description('Specify required documents for this SOP step')
                                    ->collapsible()
                                    ->schema([
                                        Repeater::make('requiredDocuments')
                                            ->label('Required Documents')
                                            ->relationship('requiredDocuments')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Documents Name')
                                                    ->required(),
                                            ])
                                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                                            ->addActionLabel('Add New Document')
                                    ])
                            ])
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                            ->orderColumn('order')
                            ->columnSpanFull()
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('SOP Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => "Created " . $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('steps_count')
                    ->label('Steps')
                    ->counts('steps')
                    ->sortable(),

                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Project Count')
                    ->counts('projects')
                    ->color('success')
                    ->sortable(),

                // Last project info using a relationship and callback
                Tables\Columns\TextColumn::make('latest_project_name')
                    ->label('Latest Project')
                    ->getStateUsing(function ($record) {
                        $latestProject = $record->projects()
                            ->latest('created_at')
                            ->first();

                        return $latestProject ? $latestProject->name : '-';
                    })
                    ->description(function ($record) {
                        $latestProject = $record->projects()
                            ->latest('created_at')
                            ->first();

                        return $latestProject
                            ? 'Created ' . $latestProject->created_at->diffForHumans()
                            : 'No projects yet';
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            // The order here is the order of the SOP filter on the Projects page.
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(fn (Tables\Actions\Action $action, bool $isReordering) => $action
                ->button()
                ->label($isReordering ? 'Selesai mengurutkan' : 'Atur urutan filter')
                ->tooltip($isReordering ? null : 'Urutan ini dipakai filter SOP di halaman Projects'))
            ->filters([
                Tables\Filters\SelectFilter::make('project_status')
                    ->relationship('projects', 'status')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn($record) => view('filament.resources.sops.view', ['record' => $record])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSops::route('/'),
            'create' => Pages\CreateSop::route('/create'),
            'edit' => Pages\EditSop::route('/{record}/edit'),
        ];
    }
}
