<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LetterTemplateResource\Pages;
use App\Models\LetterTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Master data for the letter library: fields, signers and body copy.
 */
class LetterTemplateResource extends Resource
{
    protected static ?string $model = LetterTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Penyuratan';
    protected static ?string $navigationLabel = 'Template Surat';
    protected static ?string $modelLabel = 'Template Surat';
    protected static ?string $pluralModelLabel = 'Template Surat';
    protected static ?string $slug = 'template-surat';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'direktur']);
    }

    public static function form(Form $form): Form
    {
        $roleOptions = \Spatie\Permission\Models\Role::query()
            ->whereNotIn('name', ['client'])
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        return $form->schema([
            Forms\Components\Section::make('Identitas')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('Nama template')->required()->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, ?string $old, $get) => blank($get('code')) ? $set('code', Str::slug($state)) : null),
                Forms\Components\TextInput::make('code')->label('Kode (unik)')->required()->alphaDash()->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('description')->label('Deskripsi singkat')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('category')->label('Kategori')->options(LetterTemplate::CATEGORIES)->required(),
                Forms\Components\TextInput::make('number_prefix')->label('Awalan nomor')->default('S')->maxLength(8)->helperText('S untuk surat, BA untuk berita acara.'),
                Forms\Components\TextInput::make('icon')->label('Ikon (nama heroicon)')->placeholder('heroicon-o-envelope'),
                Forms\Components\TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                Forms\Components\Toggle::make('is_critical')->label('Tandai kritis')->helperText('Ditampilkan dengan penanda merah di pustaka.'),
                Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                Forms\Components\Toggle::make('checklist_as_attachment')
                    ->label('Daftar dokumen sebagai lampiran')
                    ->helperText('Isian bertipe checklist dicetak sebagai tabel di halaman "LAMPIRAN", bukan di badan surat.')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Penandatangan (berurutan)')->schema([
                Forms\Components\Repeater::make('signers')->label('')->schema([
                    Forms\Components\TextInput::make('label')->label('Jabatan di surat')->required()->placeholder('Manajer'),
                    Forms\Components\Select::make('roles')->label('Role yang boleh menandatangani')->multiple()->options($roleOptions)->required(),
                ])->columns(2)->reorderable()->addActionLabel('Tambah penandatangan')->defaultItems(1),
            ]),

            Forms\Components\Section::make('Isian')
                ->description('Setiap isian bisa dipakai di naskah dengan {{kunci}}. Isian bertipe daftar menjadi daftar bernomor.')
                ->schema([
                    Forms\Components\Repeater::make('fields')->label('')->schema([
                        Forms\Components\TextInput::make('key')->label('Kunci')->required()->regex('/^[a-z0-9_]+$/')->helperText('huruf kecil, angka, garis bawah'),
                        Forms\Components\TextInput::make('label')->label('Label')->required(),
                        Forms\Components\Select::make('type')->label('Tipe')->options(LetterTemplate::FIELD_TYPES)->default('text')->required()->live(),
                        Forms\Components\Toggle::make('required')->label('Wajib')->default(true)->inline(false),
                        Forms\Components\TextInput::make('placeholder')->label('Contoh isian')->columnSpan(2),
                        Forms\Components\Textarea::make('default')->label('Nilai awal')->rows(2)->columnSpan(2),
                        Forms\Components\KeyValue::make('options')->label('Pilihan (kunci → label)')
                            ->visible(fn ($get) => $get('type') === 'select')->columnSpanFull(),
                    ])->columns(4)->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => ($state['label'] ?? null) ?: ($state['key'] ?? null))
                        ->addActionLabel('Tambah isian'),
                ]),

            Forms\Components\Section::make('Naskah')
                ->description('Paragraf dipisah baris kosong. Baris berawalan "- " menjadi butir. Placeholder bawaan: ' . collect(LetterTemplate::BUILTIN_PLACEHOLDERS)->keys()->map(fn ($k) => '{{' . $k . '}}')->join(', ') . '.')
                ->schema([
                    Forms\Components\Textarea::make('body')->label('')->rows(18)->required()
                        ->extraInputAttributes(['class' => 'font-mono text-sm']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable()->width('4rem'),
                Tables\Columns\TextColumn::make('name')->label('Template')->searchable()->description(fn (LetterTemplate $r) => Str::limit($r->description, 80)),
                Tables\Columns\TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(fn ($state) => LetterTemplate::CATEGORIES[$state] ?? $state),
                Tables\Columns\TextColumn::make('signers')->label('Penandatangan')->formatStateUsing(fn ($state, LetterTemplate $r) => collect($r->signers)->pluck('label')->join(' → ')),
                Tables\Columns\TextColumn::make('letters_count')->label('Dipakai')->counts('letters')->sortable(),
                Tables\Columns\IconColumn::make('is_critical')->label('Kritis')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLetterTemplates::route('/'),
            'create' => Pages\CreateLetterTemplate::route('/create'),
            'edit'   => Pages\EditLetterTemplate::route('/{record}/edit'),
        ];
    }
}
