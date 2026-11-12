<?php

namespace App\Filament\Resources\ClientGroupResource\RelationManagers;

use App\Filament\Resources\ClientResource;
use App\Livewire\Clients\ClientListClickup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Companies in a client group. Editable on the group detail page as well as on
 * the edit page; adding only offers clients that are in no group yet, like the
 * "Tambah client" modal on the groups list.
 */
class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Perusahaan dalam grup';

    /** Client::group(); Filament would guess clientGroup() and "Tambah client" failed. */
    protected static ?string $inverseRelationship = 'group';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama Client')
                ->required()
                ->maxLength(255),
        ]);
    }

    /** The heading counts on the group page follow the table. */
    public function membersChanged(): void
    {
        $this->dispatch('group-members-changed');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Perusahaan dalam grup')
            ->description('Klik baris untuk membuka detail klien')
            ->columns([
                Tables\Columns\ViewColumn::make('tile')
                    ->label('')
                    ->view('filament.tables.columns.client-tile')
                    ->width('1%'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => implode(' · ', array_filter([
                        filled(trim((string) $record->NPWP, "- \t")) ? ClientListClickup::formatNpwp($record->NPWP) : 'NPWP belum diisi',
                        $record->email,
                    ])))
                    ->wrap(),

                Tables\Columns\TextColumn::make('client_type')
                    ->label('Tipe')
                    ->description(fn ($record) => $record->client_subtype)
                    ->placeholder('–'),

                // Kept as columns so search reaches them; shown under the name.
                Tables\Columns\TextColumn::make('NPWP')
                    ->label('NPWP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'Active' => 'Aktif',
                        'Inactive' => 'Nonaktif',
                        default => $state,
                    })
                    ->color(fn (?string $state): string => $state === 'Active' ? 'primary' : 'gray'),
            ])
            ->recordUrl(fn ($record) => ClientResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Tables\Actions\AssociateAction::make()
                    ->label('Tambah client')
                    ->icon('heroicon-m-plus')
                    ->color('primary')
                    ->modalHeading('Tambah client ke grup')
                    ->modalSubmitActionLabel('Tambahkan')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email', 'NPWP'])
                    // A client in another group is moved from its own group page or the client form, not silently here.
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereNull('group_id'))
                    ->recordSelect(fn (Forms\Components\Select $select) => $select
                        ->label('Client')
                        ->helperText('Hanya client yang belum tergabung dalam grup mana pun.'))
                    ->after(fn () => $this->membersChanged()),
            ])
            ->actions([
                Tables\Actions\DissociateAction::make()
                    ->label('Keluarkan')
                    ->iconButton()
                    ->icon('heroicon-m-arrow-right-start-on-rectangle')
                    ->tooltip('Keluarkan dari grup')
                    ->color('gray')
                    ->modalHeading('Keluarkan dari grup?')
                    ->modalDescription('Client tetap ada, hanya tidak lagi tergabung dalam grup ini.')
                    ->modalSubmitActionLabel('Keluarkan')
                    ->after(fn () => $this->membersChanged()),
            ])
            ->bulkActions([
                Tables\Actions\DissociateBulkAction::make()
                    ->label('Keluarkan terpilih')
                    ->modalHeading('Keluarkan client terpilih dari grup?')
                    ->after(fn () => $this->membersChanged()),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada perusahaan')
            ->emptyStateDescription('Tambahkan client yang satu grup usaha.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
