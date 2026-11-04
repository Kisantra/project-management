<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatchNoteResource\Pages;
use App\Models\PatchNote;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PatchNoteResource extends Resource
{
    protected static ?string $model = PatchNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Patch Notes';

    protected static ?string $modelLabel = 'Patch Note';

    protected static ?string $pluralModelLabel = 'Patch Notes';

    protected static ?int $navigationSort = 98;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'direktur']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'direktur']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Rilis')
                ->icon('heroicon-o-rocket-launch')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('version')
                        ->label('Versi')
                        ->required()
                        ->placeholder('mis. 1.5.0')
                        ->maxLength(50),
                    Forms\Components\DatePicker::make('released_at')
                        ->label('Tanggal Rilis')
                        ->native(false)
                        ->default(now()),
                    Forms\Components\TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->placeholder('Ringkasan singkat rilis ini')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi (opsional)')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Terbitkan')
                        ->helperText('Hanya patch terbit yang dimunculkan ke user.')
                        ->default(false),
                ]),

            Forms\Components\Section::make('Daftar Perubahan')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Forms\Components\Repeater::make('changes')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Tipe')
                                ->options(PatchNote::CHANGE_TYPES)
                                ->default('feature')
                                ->required()
                                ->native(false),
                            Forms\Components\Select::make('area')
                                ->label('Area / Modul')
                                ->options(PatchNote::AREAS)
                                ->required()
                                ->native(false)
                                ->placeholder('Di bagian mana?'),
                            Forms\Components\TextInput::make('title')
                                ->label('Judul singkat (opsional)')
                                ->placeholder('mis. Garis waktu tenggat')
                                ->helperText('Tampil sebagai judul slide bila item ini punya gambar.')
                                ->maxLength(80)
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('text')
                                ->label('Perubahan')
                                ->required()
                                ->rows(2)
                                ->autosize()
                                ->placeholder('Tulis dengan bahasa sederhana yang mudah dimengerti pengguna…')
                                ->columnSpanFull(),

                            Forms\Components\Fieldset::make('Letak & tampilan (opsional)')
                                ->columns(2)
                                ->schema([
                                    Forms\Components\Select::make('location_url')
                                        ->label('Halaman')
                                        ->options(fn () => static::locationOptions())
                                        ->searchable()
                                        ->native(false)
                                        ->placeholder('Pilih menu tempat perubahan ini berada')
                                        ->live()
                                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('location', static::locationOptions()[$state] ?? null)),
                                    Forms\Components\TextInput::make('location')
                                        ->label('Teks letak')
                                        ->placeholder('Tax Management › Dashboard')
                                        ->helperText('Terisi otomatis dari halaman. Pisahkan tingkat dengan "›", mis. tambahkan "› Tab SPT".')
                                        ->maxLength(120),
                                    Forms\Components\FileUpload::make('image_before')
                                        ->label('Gambar sebelum')
                                        ->image()
                                        ->disk(PatchNote::IMAGE_DISK)
                                        ->directory(PatchNote::IMAGE_DIRECTORY)
                                        ->visibility('public')
                                        ->maxSize(4096)
                                        ->helperText('Kosongkan untuk fitur yang benar-benar baru.'),
                                    Forms\Components\FileUpload::make('image_after')
                                        ->label('Gambar sesudah')
                                        ->image()
                                        ->disk(PatchNote::IMAGE_DISK)
                                        ->directory(PatchNote::IMAGE_DIRECTORY)
                                        ->visibility('public')
                                        ->maxSize(4096)
                                        ->helperText('Item bergambar tampil sebagai slide sorotan di popup.'),
                                ]),
                        ])
                        ->columns(2)
                        ->defaultItems(1)
                        ->addActionLabel('Tambah Perubahan')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => ($state['title'] ?? null) ?: ($state['text'] ?? null) ?: 'Perubahan baru'),
                ]),

            Forms\Components\Hidden::make('created_by')
                ->default(fn () => auth()->id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('released_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label('Versi')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('changes')
                    ->label('Perubahan')
                    ->badge()
                    ->getStateUsing(fn (PatchNote $record) => count($record->changes ?? []) . ' item'),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Terbit')
                    ->boolean(),
                Tables\Columns\TextColumn::make('released_at')
                    ->label('Rilis')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status Terbit'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Pilihan "Letak" diambil dari menu panel admin: path relatif => "Grup › Menu".
     * Path (bukan URL penuh) yang disimpan supaya tetap benar di domain lain.
     *
     * @return array<string, string>
     */
    protected static function locationOptions(): array
    {
        $options = [];

        foreach (Filament::getNavigation() as $group) {
            foreach (collect($group->getItems()) as $item) {
                foreach (collect($item->getChildItems())->prepend($item) as $entry) {
                    $path = parse_url((string) $entry->getUrl(), PHP_URL_PATH) ?: '/';

                    $options[$path] ??= collect([$group->getLabel(), $entry->getLabel()])
                        ->filter()
                        ->implode(' › ');
                }
            }
        }

        return $options;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPatchNotes::route('/'),
            'create' => Pages\CreatePatchNote::route('/create'),
            'edit'   => Pages\EditPatchNote::route('/{record}/edit'),
        ];
    }
}
