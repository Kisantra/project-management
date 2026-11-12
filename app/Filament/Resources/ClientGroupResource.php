<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientGroupResource\Pages;
use App\Filament\Resources\ClientGroupResource\RelationManagers\ClientsRelationManager;
use App\Models\ClientGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;

class ClientGroupResource extends Resource
{
    protected static ?string $model = ClientGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Client Management';

    protected static ?string $navigationLabel = 'Grup Client';

    protected static ?string $modelLabel = 'Grup Client';

    protected static ?string $pluralModelLabel = 'Grup Client';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('clients.*');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('clients.*');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Grup')
                ->icon('heroicon-o-building-library')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Grup')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Grup Harita, Grup Adaro'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active'   => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->default('active')
                            ->required(),
                    ]),

                    Forms\Components\FileUpload::make('logo')
                        ->label('Logo Grup')
                        ->image()
                        ->imageEditor()
                        ->directory('client-groups/logos')
                        ->disk('public')
                        ->maxSize(2048)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Kontak & Alamat')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Forms\Components\Textarea::make('address')
                        ->label('Alamat')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Nama Kontak')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email Kontak')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),
                    ]),
                ]),

            Forms\Components\Section::make('Catatan')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Internal')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }

    /*
     * No table() or infolist(): the list page is the clients.group-list Livewire
     * component and the view page has its own view (pages/view-group.blade.php).
     */

    public static function getRelations(): array
    {
        return [
            ClientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClientGroups::route('/'),
            'create' => Pages\CreateClientGroup::route('/create'),
            'view'   => Pages\ViewClientGroup::route('/{record}'),
            'edit'   => Pages\EditClientGroup::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'contact_name', 'contact_email'];
    }
}
