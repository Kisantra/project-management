<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestedDocumentTypeResource\Pages;
use App\Models\RequestedDocumentType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master data for the documents that letters can request from clients.
 */
class RequestedDocumentTypeResource extends Resource
{
    protected static ?string $model = RequestedDocumentType::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Penyuratan';
    protected static ?string $navigationLabel = 'Jenis Dokumen';
    protected static ?string $modelLabel = 'Jenis Dokumen';
    protected static ?string $pluralModelLabel = 'Jenis Dokumen';
    protected static ?string $slug = 'jenis-dokumen';
    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur', 'project-manager']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur', 'project-manager']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama dokumen')->required()->maxLength(150)->unique(ignoreRecord: true),
            Forms\Components\Select::make('category')->label('Kategori')->options(RequestedDocumentType::CATEGORIES)->default('lainnya')->required(),
            Forms\Components\TextInput::make('description')->label('Keterangan')->maxLength(200)->placeholder('mis. periode Januari–Desember'),
            Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->defaultGroup(
                Tables\Grouping\Group::make('category')
                    ->label('Kategori')
                    ->getTitleFromRecordUsing(fn (RequestedDocumentType $r) => $r->category_label)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama dokumen')->searchable()->sortable()->description(fn (RequestedDocumentType $r) => $r->description),
                Tables\Columns\TextColumn::make('requested_documents_count')->label('Dipakai')->counts('requestedDocuments')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->label('Kategori')->options(RequestedDocumentType::CATEGORIES),
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (RequestedDocumentType $r) => $r->requestedDocuments()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRequestedDocumentTypes::route('/'),
        ];
    }
}
