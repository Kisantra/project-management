<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\ApplicationsRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\ClientDocumentsRelationManager;
use App\Filament\Resources\ClientResource\RelationManagers\ProgressRelationManager;
use Filament\Forms\Components\Section;
use App\Models\Client;
use App\Models\Pic;
use App\Models\ClientCredential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Fieldset;
use Guava\FilamentModalRelationManagers\Actions\Table\RelationManagerAction;
use Filament\Forms\Components\Select;
use App\Filament\Imports\ClientImporter;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\ViewEntry;
use App\Exports\Clients\ClientsExport;
use App\Exports\Clients\ClientsDetailedExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\AccountRepresentative;


class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Client Management';
    protected static ?int $navigationSort = 1;

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
        return $form
            ->schema([
                Section::make('Client Profile')
                    ->description('Detail dari Client')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Forms\Components\Select::make('client_type')
                            ->label('Tipe Klien')
                            ->options([
                                'Pribadi' => 'Pribadi/Perorangan',
                                'Badan' => 'Badan/Perusahaan',
                                'Pemerintah' => 'Pemerintah',
                                'Pemungut PPN PMSE Luar Negeri' => 'Pemungut PPN PMSE Luar Negeri',
                            ])
                            ->default('Pribadi')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                // Reset subtype when client type changes
                                $set('client_subtype', null);

                                // Reset pilihan "individu ini adalah PIC" pada setiap perubahan tipe
                                $set('pic_is_self', 'no');

                                // Reset PIC for Pribadi (dipilih ulang lewat dropdown bila perlu)
                                if ($state === 'Pribadi') {
                                    $set('pic_id', null);
                                }
                            })
                            ->native(false)
                            ->helperText(function (Forms\Get $get) {
                                $type = $get('client_type');
                                return match($type) {
                                    'Badan' => '✅ Klien Badan memerlukan PIC (Person In Charge)',
                                    'Pemerintah' => '🏛️ Klien Pemerintahan memerlukan informasi instansi',
                                    'Pemungut PPN PMSE Luar Negeri' => '🌐 Pemungut PPN untuk platform digital luar negeri',
                                    default => 'ℹ️ Klien Pribadi tidak memerlukan PIC',
                                };
                            })
                            ->columnSpanFull(),

                        // Dynamic Subtype Field - Shows different options based on client_type
                        Forms\Components\Select::make('client_subtype')
                            ->label(function (Forms\Get $get) {
                                return match($get('client_type')) {
                                    'Badan' => 'Jenis Badan Usaha',
                                    'Pemerintah' => 'Jenis Instansi Pemerintah',
                                    default => 'Sub Tipe',
                                };
                            })
                            
                            ->options(function (Forms\Get $get) {
                                $clientType = $get('client_type');
                                
                                if ($clientType === 'Badan') {
                                    return [
                                        'Badan Internasional' => 'Badan Internasional',
                                        'Badan Usaha Milik Desa' => 'Badan Usaha Milik Desa',
                                        'Bentuk Usaha Tetap (BUT)' => 'Bentuk Usaha Tetap (BUT)',
                                        'Dana Pensiun' => 'Dana Pensiun',
                                        'Firma' => 'Firma',
                                        'Kantor Perwakilan Perusahaan Asing (KPPA)' => 'Kantor Perwakilan Perusahaan Asing (KPPA)',
                                        'Kerja Sama Operasi (KSO/JO)' => 'Kerja Sama Operasi (KSO/JO)',
                                        'Kongsi' => 'Kongsi',
                                        'Kontrak Investasi Kolektif' => 'Kontrak Investasi Kolektif',
                                        'Koperasi' => 'Koperasi',
                                        'Lembaga dan bentuk badan lainnya' => 'Lembaga dan bentuk badan lainnya',
                                        'Organisasi Lainnya' => 'Organisasi Lainnya',
                                        'Organisasi Massa' => 'Organisasi Massa',
                                        'Organisasi sosial politik' => 'Organisasi sosial politik',
                                        'PT Perorangan' => 'PT Perorangan',
                                        'Penyelenggara Kegiatan' => 'Penyelenggara Kegiatan',
                                        'Perkumpulan' => 'Perkumpulan',
                                        'Persekutuan Perdata' => 'Persekutuan Perdata',
                                        'Perseroan Komanditer (CV)' => 'Perseroan Komanditer (CV)',
                                        'Perseroan Lainnya' => 'Perseroan Lainnya',
                                        'Perseroan Terbatas (PT)' => 'Perseroan Terbatas (PT)',
                                        'Perusahaan Umum' => 'Perusahaan Umum',
                                        'Perwakilan Negara Asing' => 'Perwakilan Negara Asing',
                                        'Yayasan' => 'Yayasan',
                                    ];
                                } elseif ($clientType === 'Pemerintah') {
                                    return [
                                        'Institusi Pemerintah Pusat' => 'Institusi Pemerintah Pusat',
                                        'Institusi Pemerintah Daerah' => 'Institusi Pemerintah Daerah',
                                        'Lembaga Pemerintah Desa' => 'Lembaga Pemerintah Desa',
                                        'Badan Layanan Umum Pusat' => 'Badan Layanan Umum Pusat',
                                        'Badan Layanan Umum Daerah' => 'Badan Layanan Umum Daerah',
                                    ];
                                }
                                
                                return [];
                            })
                            ->visible(fn (Forms\Get $get) => in_array($get('client_type'), ['Badan', 'Pemerintah']))
                            ->required(fn (Forms\Get $get) => in_array($get('client_type'), ['Badan', 'Pemerintah']))
                            ->searchable()
                            ->native(false)
                            ->helperText(function (Forms\Get $get) {
                                return match($get('client_type')) {
                                    'Badan' => 'Pilih jenis badan usaha sesuai dengan dokumen legal',
                                    'Pemerintah' => 'Pilih jenis instansi pemerintahan',
                                    default => '',
                                };
                            })
                            ->columnSpanFull()
                            ->placeholder(function (Forms\Get $get) {
                                return match($get('client_type')) {
                                    'Badan' => 'Pilih jenis badan usaha...',
                                    'Pemerintah' => 'Pilih jenis instansi...',
                                    default => 'Pilih sub tipe...',
                                };
                            }),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Client Name')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->label('Client Email')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('adress')
                            ->label('Address')
                            ->columnSpanFull(),

                        // ===== PIC (khusus klien Pribadi) =====
                        // Gerbang Select: apakah individu ini juga terdaftar / akan didaftarkan
                        // sebagai PIC. Field bantuan — tidak disimpan ke kolom.
                        Forms\Components\Select::make('pic_is_self')
                            ->label('Individu ini terdaftar sebagai PIC?')
                            ->options([
                                'no'  => 'Tidak',
                                'yes' => 'Ya, terdaftar sebagai PIC',
                            ])
                            ->default('no')
                            ->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('client_type') === 'Pribadi')
                            ->helperText('Pilih "Ya" jika orang ini sudah ada di daftar PIC atau ingin didaftarkan sebagai PIC baru. Nama, NIK & kredensialnya akan terisi otomatis.')
                            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) {
                                // Saat edit: set "Ya" bila klien Pribadi sudah ter-link ke PIC
                                if ($get('client_type') === 'Pribadi' && filled($get('pic_id'))) {
                                    $set('pic_is_self', 'yes');
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                // Bersihkan pilihan PIC saat diubah ke "Tidak"
                                if ($state !== 'yes') {
                                    $set('pic_id', null);
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('pic_id')
                            ->label('Person In Charge (PIC)')
                            ->searchable()
                            ->live()
                            ->options(function (Forms\Get $get) {
                                // Untuk Badan: kelompokkan PIC berdasarkan sumbernya —
                                // record Pic (master) vs klien Pribadi yang terdaftar sebagai PIC.
                                if ($get('client_type') === 'Badan') {
                                    $pribadiClients = \App\Models\Client::query()
                                        ->where('client_type', 'Pribadi')
                                        ->whereNotNull('pic_id')
                                        ->orderBy('name')
                                        ->get(['id', 'name', 'pic_id']);

                                    $linkedPicIds = $pribadiClients->pluck('pic_id')->unique();

                                    $masterPics = \App\Models\Pic::query()
                                        ->when($linkedPicIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $linkedPicIds))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();

                                    $pribadiOptions = $pribadiClients
                                        ->mapWithKeys(fn ($client) => [$client->pic_id => $client->name])
                                        ->toArray();

                                    $options = [];
                                    if (! empty($masterPics)) {
                                        $options['PIC (Master)'] = $masterPics;
                                    }
                                    if (! empty($pribadiOptions)) {
                                        $options['Klien Pribadi'] = $pribadiOptions;
                                    }

                                    return $options;
                                }

                                // Tipe lain (Pribadi): daftar PIC biasa (flat)
                                return \App\Models\Pic::orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->visible(fn (Forms\Get $get) => $get('client_type') === 'Badan'
                                || ($get('client_type') === 'Pribadi' && ($get('pic_is_self') === 'yes' || filled($get('pic_id')))))
                            ->disabled(fn (Forms\Get $get) => ! ($get('client_type') === 'Badan'
                                || ($get('client_type') === 'Pribadi' && ($get('pic_is_self') === 'yes' || filled($get('pic_id'))))))
                            ->required(fn (Forms\Get $get) => $get('client_type') === 'Pribadi' && $get('pic_is_self') === 'yes')
                            // Untuk Pribadi: tetap dehydrate walau hidden, agar unlink (pic_id => null)
                            // saat diubah ke "Tidak" benar-benar tersimpan ke database.
                            ->dehydratedWhenHidden(fn (Forms\Get $get) => $get('client_type') === 'Pribadi')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('nik')
                                    ->label('NIK')
                                    ->required()
                                    ->unique(table: \App\Models\Pic::class)
                                    ->length(16)
                                    ->numeric(),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->minLength(8),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])
                            // Tanpa relationship(), createOptionForm butuh createOptionUsing untuk
                            // membuat record Pic dan mengembalikan id-nya sebagai value select.
                            ->createOptionUsing(fn (array $data) => \App\Models\Pic::create([
                                'name'     => $data['name'],
                                'nik'      => $data['nik'],
                                'password' => $data['password'],
                                'status'   => $data['status'] ?? 'active',
                            ])->getKey())
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                // Untuk klien Pribadi: autofill nama klien dari nama PIC yang dipilih/dibuat
                                if ($get('client_type') === 'Pribadi' && filled($state)) {
                                    if ($pic = \App\Models\Pic::find($state)) {
                                        $set('name', $pic->name);
                                    }
                                }
                            })
                            ->helperText(function (Forms\Get $get) {
                                return match (true) {
                                    $get('client_type') === 'Badan' => 'Pilih PIC (master) atau klien Pribadi yang terdaftar sebagai PIC, atau buat PIC baru',
                                    $get('client_type') === 'Pribadi' => 'Pilih orang dari daftar PIC, atau buat PIC baru — nama, NIK & kredensial akan terisi otomatis',
                                    default => 'PIC tidak diperlukan untuk klien ini',
                                };
                            })
                            ->columnSpanFull(),

                        // Read-only: NIK & password PIC yang dipilih (berdampingan)
                        Forms\Components\Placeholder::make('pic_nik_display')
                            ->label('NIK PIC')
                            ->visible(fn (Forms\Get $get) => $get('client_type') === 'Pribadi' && filled($get('pic_id')))
                            ->content(fn (Forms\Get $get) => optional(\App\Models\Pic::find($get('pic_id')))->nik ?? '—'),

                        Forms\Components\Placeholder::make('pic_password_display')
                            ->label('Password PIC')
                            ->visible(fn (Forms\Get $get) => $get('client_type') === 'Pribadi' && filled($get('pic_id')))
                            ->content(fn (Forms\Get $get) => optional(\App\Models\Pic::find($get('pic_id')))->password ?? '—'),

                        Select::make('ar_id')
                            ->label('Account Representative (AR)')
                            ->relationship('accountRepresentative', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('AR Name'),
                                Forms\Components\TextInput::make('phone_number')
                                    ->tel()
                                    ->label('Phone Number')
                                    ->placeholder('+62 XXX XXXX XXXX'),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->unique()
                                    ->label('Email'),
                                Forms\Components\Select::make('kpp')
                                    ->label('KPP')
                                    ->options(\App\Services\Clients\KppService::getKppOptions())
                                    ->searchable()
                                    ->placeholder('Pilih atau cari KPP...')
                                    ->helperText('Pilih KPP tempat AR bertugas'),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(2),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Create New Account Representative')
                                    ->modalSubmitActionLabel('Create AR')
                                    ->modalWidth('lg');
                            })
                            ->helperText('Select or create a new Account Representative for this client'),
                        Select::make('status')
                            ->label('Client Status')
                            ->options([
                                'Active' => 'Active',
                                'Inactive' => 'Inactive',
                            ])
                            ->default('Active')
                            ->required()
                            ->native(false),
                        Select::make('group_id')
                            ->label('Grup Client')
                            ->relationship('group', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Tidak ada grup')
                            ->helperText('Opsional — pilih jika client ini terafiliasi ke suatu grup')
                            ->columnSpanFull(),
                        FileUpload::make('logo')
                            ->label('Client Logo')
                            ->openable()
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('avatars')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('300')
                            ->imageResizeTargetHeight('300')
                            ->maxSize(5120)
                            ->downloadable()
                            ->columnSpanFull()
                    ])
                    ->columns(2),

                // SECTION BARU: CONTACT PERSON
                Section::make('Contact Person')
                    ->description('Kontak person yang dapat dihubungi untuk klien ini')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Forms\Components\Repeater::make('contacts')
                            ->relationship('contacts')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Kontak')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Contoh: Budi Santoso')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('position')
                                            ->label('Jabatan')
                                            ->maxLength(255)
                                            ->placeholder('Contoh: Direktur Utama')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255)
                                            ->placeholder('contoh@email.com')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('phone')
                                            ->label('Telepon')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('081234567890')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\TextInput::make('mobile')
                                            ->label('HP/WhatsApp')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('081234567890')
                                            ->helperText('Nomor yang bisa dihubungi via WhatsApp')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Select::make('type')
                                            ->label('Tipe Kontak')
                                            ->options([
                                                'primary' => 'Utama',
                                                'secondary' => 'Sekunder',
                                                'billing' => 'Billing/Keuangan',
                                                'technical' => 'Teknis',
                                            ])
                                            ->default('primary')
                                            ->required()
                                            ->native(false)
                                            ->helperText('Tentukan peran kontak person ini')
                                            ->columnSpan(1),
                                        
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Status Aktif')
                                            ->default(true)
                                            ->inline(false)
                                            ->helperText('Nonaktifkan jika kontak sudah tidak digunakan')
                                            ->columnSpan(2),
                                        
                                        Forms\Components\Textarea::make('notes')
                                            ->label('Catatan')
                                            ->rows(2)
                                            ->placeholder('Catatan tambahan tentang kontak ini...')
                                            ->columnSpan(2),
                                    ])
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Kontak Person')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Kontak Baru')
                            ->collapsed(false)
                            ->cloneable()
                            ->columnSpanFull()
                            ->maxItems(10)
                    ])
                    ->collapsible()
                    ->collapsed(false),
        
                Section::make('Client Tax Information')
                    ->description('Tax registration and compliance details')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Forms\Components\TextInput::make('NPWP')
                            ->label('NPWP')
                            ->placeholder('XX.XXX.XXX.X-XXX.XXX'),
                        // KLU (Klasifikasi Lapangan Usaha) — khusus klien Badan.
                        Forms\Components\TextInput::make('klu')
                            ->label('KLU')
                            ->placeholder('mis. 62019')
                            ->maxLength(255)
                            ->helperText('Klasifikasi Lapangan Usaha — kode bidang usaha sesuai NPWP/DJP.')
                            ->visible(fn (Forms\Get $get) => $get('client_type') === 'Badan'),
                        Forms\Components\TextInput::make('EFIN')
                            ->label('EFIN')
                            ->placeholder('Electronic Filing Identification Number'),                       
                        Select::make('pkp_status')
                            ->label('Status PKP')
                            ->options([
                                'Non-PKP' => 'Non-PKP',
                                'PKP' => 'PKP (Pengusaha Kena Pajak)',
                            ])
                            ->default('Non-PKP')
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                                if ($state === 'Non-PKP') {
                                    $set('ppn_contract', false);
                                }
                            })
                            ->helperText(function (Forms\Get $get) {
                                $status = $get('pkp_status');
                                if ($status === 'PKP') {
                                    return '✅ Client dapat membuat faktur pajak dan memungut PPN';
                                } else {
                                    return 'ℹ️ Client tidak dapat membuat faktur pajak (otomatis menonaktifkan kontrak PPN)';
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Kredensial utama klien (ClientCredential) — disimpan via relasi belongsTo
                // (clients.credential_id). Hanya dibuat bila minimal satu field terisi.
                Section::make('Kredensial Aplikasi Pajak')
                    ->description('Akun & kata sandi aplikasi pajak (Core Tax, DJP Online, Email). Opsional — bisa juga diisi nanti lewat menu Kelola Kredensial.')
                    ->icon('heroicon-o-key')
                    ->collapsible()
                    ->relationship('clientCredential', condition: fn (?array $state): bool =>
                        filled($state['core_tax_user_id'] ?? null)
                        || filled($state['core_tax_password'] ?? null)
                        || filled($state['djp_account'] ?? null)
                        || filled($state['djp_password'] ?? null)
                        || filled($state['email'] ?? null)
                        || filled($state['email_password'] ?? null)
                        || filled($state['notes'] ?? null)
                    )
                    ->schema([
                        Forms\Components\Hidden::make('credential_type')->default('general'),
                        Forms\Components\Hidden::make('is_active')->default(true),

                        Forms\Components\Fieldset::make('Core Tax')
                            ->schema([
                                Forms\Components\TextInput::make('core_tax_user_id')
                                    ->label('Core Tax User ID')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('core_tax_password')
                                    ->label('Core Tax Password')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                            ])->columns(2),

                        Forms\Components\Fieldset::make('DJP Online')
                            ->schema([
                                Forms\Components\TextInput::make('djp_account')
                                    ->label('Akun DJP')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('djp_password')
                                    ->label('Password DJP')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                            ])->columns(2),

                        Forms\Components\Fieldset::make('Email')
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email_password')
                                    ->label('Password Email')
                                    ->password()
                                    ->revealable()
                                    ->maxLength(255),
                            ])->columns(2),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contract Management')
                    ->description('Tax service contracts and agreements')
                    ->icon('heroicon-o-document-check')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\Toggle::make('ppn_contract')
                                    ->label('PPN Contract')
                                    ->reactive()
                                    ->disabled(fn (Forms\Get $get) => $get('pkp_status') === 'Non-PKP')
                                    ->helperText(function (Forms\Get $get) {
                                        if ($get('pkp_status') === 'Non-PKP') {
                                            return 'Tidak tersedia untuk Non-PKP';
                                        }
                                        return 'Kontrak untuk pengelolaan PPN';
                                    })
                                    ->columnSpan(1),
                                    
                                Forms\Components\Toggle::make('pph_contract')
                                    ->label('PPh 21 Contract')
                                    ->reactive()
                                    ->helperText('Kontrak untuk pengelolaan PPh 21')
                                    ->columnSpan(1),

                                Forms\Components\Toggle::make('bupot_contract')
                                    ->label('PPh Unifikasi Contract')
                                    ->reactive()
                                    ->helperText('Kontrak untuk PPh Unifikasi (bukti potong)')
                                    ->columnSpan(1),
                                
                                Forms\Components\Toggle::make('pph_badan_contract')
                                    ->label('PPh Badan Contract')
                                    ->reactive()
                                    ->helperText('Kontrak untuk PPh Badan')
                                    ->columnSpan(1),
                                
                                Forms\Components\FileUpload::make('contract_file')
                                    ->label('Contract Document')
                                    ->visible(function (callable $get) {
                                        return $get('ppn_contract') || $get('pph_contract') || $get('bupot_contract') || $get('pph_badan_contract');
                                    })
                                    ->preserveFilenames()
                                    ->openable()
                                    ->downloadable()
                                    ->directory('client-contracts')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->helperText('Upload signed contract document (PDF or image)')
                                    ->columnSpan(4),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                    
                Tables\Columns\TextColumn::make('pic.name')
                    ->label('PIC')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->placeholder('No PIC assigned'),
                    
                Tables\Columns\TextColumn::make('NPWP')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('NPWP copied!')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('klu')
                    ->label('KLU')
                    ->searchable()
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'Active' => 'heroicon-o-check-circle',
                        'Inactive' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Grup')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-library')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('client_type')
                    ->label('Tipe')
                    ->colors([
                        'primary' => 'Badan',
                        'warning' => 'Pribadi',
                        'success' => 'Pemerintah',
                        'info' => 'Pemungut PPN PMSE Luar Negeri',
                    ])
                    ->icons([
                        'heroicon-o-building-office' => 'Badan',
                        'heroicon-o-user' => 'Pribadi',
                        'heroicon-o-building-library' => 'Pemerintah',
                        'heroicon-o-globe-alt' => 'Pemungut PPN PMSE Luar Negeri',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('client_subtype')
                    ->label('Sub Tipe')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(),
                  
                // PKP STATUS COLUMN
                Tables\Columns\BadgeColumn::make('pkp_status')
                    ->label('PKP Status')
                    ->colors([
                        'success' => 'PKP',
                        'warning' => 'Non-PKP',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'PKP',
                        'heroicon-o-x-circle' => 'Non-PKP',
                    ])
                    ->sortable(),
                                     
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pic_id')
                    ->label('Filter by PIC')
                    ->relationship('pic', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('client_type')
                    ->label('Tipe Klien')
                    ->options([
                        'Pribadi' => 'Pribadi/Perorangan',
                        'Badan' => 'Badan/Perusahaan',
                        'Pemerintah' => 'Pemerintah',
                        'Pemungut PPN PMSE Luar Negeri' => 'Pemungut PPN PMSE Luar Negeri',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('client_subtype')
                    ->label('Sub Tipe')
                    ->options(function () {
                        return \App\Models\Client::whereNotNull('client_subtype')
                            ->distinct()
                            ->pluck('client_subtype', 'client_subtype')
                            ->toArray();
                    })
                    ->searchable()
                    ->multiple(),
         
                Tables\Filters\SelectFilter::make('status')
                    ->label('Client Status')
                    ->native(false)
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                    ])
                    ->default('Active'),
                    
                // PKP STATUS FILTER
                Tables\Filters\SelectFilter::make('pkp_status')
                    ->label('Status PKP')
                    ->options([
                        'PKP' => 'PKP',
                        'Non-PKP' => 'Non-PKP',
                    ]),                    
          
                // CONTRACT FILTERS
                Tables\Filters\Filter::make('has_ppn_contract')
                    ->label('Memiliki Kontrak PPN')
                    ->query(fn (Builder $query): Builder => $query->where('ppn_contract', true)),

                Tables\Filters\Filter::make('active_contracts')
                    ->label('Memiliki Kontrak Aktif')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->where('ppn_contract', true)
                          ->orWhere('pph_contract', true)
                          ->orWhere('bupot_contract', true);
                    })),
            ])
            ->headerActions([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\Action::make('export_excel_simple')
                            ->label('Simple Export')
                            ->icon('heroicon-o-document-arrow-down')
                            ->color('success')
                            ->action(function () {
                                return Excel::download(new \App\Exports\Clients\ClientsExport(), 'clients-' . now()->format('Y-m-d-H-i') . '.xlsx');
                            }),
                            
                        Tables\Actions\Action::make('export_excel_detailed')
                            ->label('Detailed Export (Multi-Sheet)')
                            ->icon('heroicon-o-document-chart-bar')
                            ->color('info')
                            ->action(function () {
                                return Excel::download(new \App\Exports\Clients\ClientsDetailedExport(), 'clients-detailed-' . now()->format('Y-m-d-H-i') . '.xlsx');
                            }),
                    ])
                    ->label('Excel Export')
                    ->icon('heroicon-o-table-cells')
                    ->color('primary'),
                ])
            ->actions([
            // Existing Core Tax action
            Tables\Actions\Action::make('view_all_credentials')
                ->label('')
                ->icon('heroicon-o-key')
                ->color('info')
                ->tooltip('Kelola Kredensial')
                ->modalHeading(fn (Client $record) => 'Kredensial — ' . $record->name)
                ->modalIcon('heroicon-o-key')
                ->modalWidth('4xl')
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn (Client $record) => view(
                    'filament.modals.clients.client-all-credentials',
                    ['clientId' => $record->id],
                )),


            // PIC Management Actions Group
            Tables\Actions\ActionGroup::make([
                // Assign PIC (when no PIC assigned)
                Tables\Actions\Action::make('assign_pic')
                    ->label('Assign PIC')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn ($record) => !$record->pic_id)
                    ->form([
                        Select::make('pic_id')
                            ->label('Select PIC')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter PIC name'),
                                Forms\Components\TextInput::make('nik')
                                    ->label('NIK')
                                    ->required()
                                    ->unique()
                                    ->length(16)
                                    ->numeric()
                                    ->placeholder('16-digit NIK'),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->placeholder('Minimum 8 characters'),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Create New PIC')
                                    ->modalSubmitActionLabel('Create PIC')
                                    ->modalWidth('lg');
                            })
                            ->helperText('Select an existing PIC or create a new one'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['pic_id' => $data['pic_id']]);
                        
                        $pic = \App\Models\Pic::find($data['pic_id']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('PIC Assigned Successfully')
                            ->body("PIC '{$pic->name}' has been assigned to client '{$record->name}'.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Assign PIC to Client')
                    ->modalSubmitActionLabel('Assign PIC')
                    ->modalWidth('md'),

                // Change PIC (when PIC is assigned)
                Tables\Actions\Action::make('change_pic')
                    ->label('Change PIC')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->pic_id)
                    ->form([
                        Forms\Components\Placeholder::make('current_pic')
                            ->label('Current PIC')
                            ->content(fn ($record) => $record->pic ? $record->pic->name : 'None assigned'),
                        
                        Select::make('pic_id')
                            ->label('New PIC')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Enter PIC name'),
                                Forms\Components\TextInput::make('nik')
                                    ->label('NIK')
                                    ->required()
                                    ->unique()
                                    ->length(16)
                                    ->numeric()
                                    ->placeholder('16-digit NIK'),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->placeholder('Minimum 8 characters'),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Create New PIC')
                                    ->modalSubmitActionLabel('Create PIC')
                                    ->modalWidth('lg');
                            })
                            ->helperText('Select a different PIC or create a new one'),
                    ])
                    ->action(function ($record, array $data) {
                        $oldPic = $record->pic;
                        $record->update(['pic_id' => $data['pic_id']]);
                        
                        $newPic = \App\Models\Pic::find($data['pic_id']);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('PIC Changed Successfully')
                            ->body("Client '{$record->name}' has been reassigned from '{$oldPic?->name}' to '{$newPic->name}'.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Change PIC Assignment')
                    ->modalSubmitActionLabel('Change PIC')
                    ->modalWidth('md'),

                // View PIC Details (when PIC is assigned)
                Tables\Actions\Action::make('view_pic_details')
                    ->label('PIC Details')
                    ->icon('heroicon-o-identification')
                    ->color('info')
                    ->visible(fn ($record) => $record->pic_id)
                    ->modalHeading('PIC Information')
                    ->modalContent(fn ($record) => view('filament.modals.pic-details', ['record' => $record]))
                    ->modalActions([
                        Tables\Actions\Action::make('close')
                            ->label('Close')
                            ->color('gray')
                            ->close(),
                    ])
                    ->modalWidth('md'),

                // Unassign PIC (when PIC is assigned)
                Tables\Actions\Action::make('unassign_pic')
                    ->label('Unassign PIC')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn ($record) => $record->pic_id)
                    ->requiresConfirmation()
                    ->modalHeading('Unassign PIC from Client')
                    ->modalDescription(fn ($record) => "Are you sure you want to unassign PIC '{$record->pic?->name}' from client '{$record->name}'? This action can be reversed later.")
                    ->modalSubmitActionLabel('Yes, Unassign PIC')
                    ->action(function ($record) {
                        $picName = $record->pic?->name;
                        $record->update(['pic_id' => null]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('PIC Unassigned Successfully')
                            ->body("PIC '{$picName}' has been unassigned from client '{$record->name}'.")
                            ->success()
                            ->send();
                    }),
            ])
                ->label('PIC Management')
                ->icon('heroicon-o-users')
                ->color('primary'),


            RelationManagerAction::make('document-relation-manager')
                ->label('')
                ->icon('heroicon-o-folder')
                ->color('warning')
                ->relationManager(ClientDocumentsRelationManager::make())
                ->tooltip('Kelola Legal Documents')
                ->modalWidth('7xl')
                ->modalHeading(fn ($record) => 'Legal Documents - ' . $record->name)
                ->modalDescription('Kelola dokumen legal klien berdasarkan SOP atau upload dokumen custom')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Quick Export Actions
                    Tables\Actions\BulkAction::make('export_selected_simple')
                        ->label('Export Selected (Simple)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($records) {
                            $clientIds = $records->pluck('id')->toArray();
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Export Started')
                                ->body('Exporting ' . count($clientIds) . ' selected client(s)...')
                                ->info()
                                ->send();
                            
                            return Excel::download(
                                new \App\Exports\Clients\ClientsExport([], false, $clientIds), 
                                'selected-clients-simple-' . now()->format('Y-m-d-H-i') . '.xlsx'
                            );
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Export Selected Clients')
                        ->modalDescription(fn ($records) => 'Export ' . $records->count() . ' selected client(s) to a simple Excel file.')
                        ->modalSubmitActionLabel('Export Now')
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('export_selected_detailed')
                        ->label('Export Selected (Multi-Sheet)')
                        ->icon('heroicon-o-document-chart-bar')
                        ->color('info')
                        ->action(function ($records) {
                            $clientIds = $records->pluck('id')->toArray();
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Detailed Export Started')
                                ->body('Creating multi-sheet export for ' . count($clientIds) . ' client(s)...')
                                ->info()
                                ->send();
                            
                            return Excel::download(
                                new \App\Exports\Clients\ClientsDetailedExport([], $clientIds), 
                                'selected-clients-detailed-' . now()->format('Y-m-d-H-i') . '.xlsx'
                            );
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Export Selected Clients (Detailed)')
                        ->modalDescription(fn ($records) => 'Export ' . $records->count() . ' selected client(s) to a comprehensive multi-sheet Excel file with separate tabs for different data categories.')
                        ->modalSubmitActionLabel('Export Detailed')
                        ->deselectRecordsAfterCompletion(),
                    
                    Tables\Actions\BulkAction::make('bulk_deactivate_clients')
                        ->label('Nonaktifkan Klien Terpilih')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan Klien Terpilih')
                        ->modalDescription(fn ($records) => 
                            "Apakah Anda yakin ingin menonaktifkan {$records->count()} klien yang dipilih? Status mereka akan diubah menjadi 'Inactive'."
                        )
                        ->modalSubmitActionLabel('Ya, Nonaktifkan Klien')
                        ->action(function ($records) {
                            $count = 0;
                            
                            \DB::transaction(function () use ($records, &$count) {
                                foreach ($records as $record) {
                                    if ($record->status !== 'Inactive') {
                                        $record->update(['status' => 'Inactive']);
                                        $count++;
                                    }
                                }
                            });
                            
                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Klien Berhasil Dinonaktifkan')
                                    ->body("Berhasil menonaktifkan {$count} klien.")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak Ada Perubahan')
                                    ->body('Klien yang dipilih sudah dalam status tidak aktif.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('bulk_activate_clients')
                        ->label('Aktifkan Klien Terpilih')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aktifkan Klien Terpilih')
                        ->modalDescription(fn ($records) => 
                            "Apakah Anda yakin ingin mengaktifkan {$records->count()} klien yang dipilih? Status mereka akan diubah menjadi 'Active'."
                        )
                        ->modalSubmitActionLabel('Ya, Aktifkan Klien')
                        ->action(function ($records) {
                            $count = 0;
                            
                            \DB::transaction(function () use ($records, &$count) {
                                foreach ($records as $record) {
                                    if ($record->status !== 'Active') {
                                        $record->update(['status' => 'Active']);
                                        $count++;
                                    }
                                }
                            });
                            
                            if ($count > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Klien Berhasil Diaktifkan')
                                    ->body("Berhasil mengaktifkan {$count} klien.")
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Tidak Ada Perubahan')
                                    ->body('Klien yang dipilih sudah dalam status aktif.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Standard bulk actions
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('bulk_assign_pic')
                        ->label('Assign PIC to Selected')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Select::make('pic_id')
                                ->label('Select PIC')
                                ->relationship('pic', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required(),
                                    Forms\Components\TextInput::make('nik')->required()->length(16),
                                    Forms\Components\TextInput::make('email')->email(),
                                    Forms\Components\TextInput::make('password')->password()->required(),
                                    Forms\Components\Select::make('status')
                                        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                                        ->default('active'),
                                ]),
                        ])
                        ->action(function (array $data, $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['pic_id' => $data['pic_id']]);
                                $count++;
                            }
                            
                            $pic = \App\Models\Pic::find($data['pic_id']);
                            
                            \Filament\Notifications\Notification::make()
                                ->title('PIC Assigned Successfully')
                                ->body("Assigned PIC '{$pic->name}' to {$count} client(s).")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Assign PIC to Selected Clients')
                        ->modalSubmitActionLabel('Assign PIC')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordClasses(function (Client $record) {
                if ($record->status === 'Inactive') {
                    return 'border-l-4 border-l-red-500 dark:border-l-red-400 opacity-70 hover:bg-red-50 dark:hover:bg-red-900/10 bg-red-50/30 dark:bg-red-900/10';
                }
                // Default hover effect for active clients with complete info
                return 'hover:bg-gray-50 dark:hover:bg-gray-800/10';
            })
            ->deferLoading();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'Active')->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'NPWP'];
    }

    public static function getRelations(): array
    {
        return [
            ProgressRelationManager::class,
            ApplicationsRelationManager::class,
            ClientDocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}