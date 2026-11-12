<?php

namespace App\Filament\Resources\ClientIntakeResource\Pages;

use App\Filament\Resources\ClientIntakeResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ProjectResource;
use App\Livewire\Clients\IntakeContractPanel;
use App\Models\AccountRepresentative;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientManagement\ClientIntakeService;
use App\Services\ClientManagement\ClientManagementClient;
use App\Services\ClientManagement\IntakeAlreadyProcessedException;
use App\Services\ClientManagement\IntakeDocumentException;
use App\Services\ClientManagement\IntakeDocumentImporter;
use App\Services\Clients\DefaultClientTeam;
use App\Services\Projects\ProjectCreator;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Field;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cek & proses satu klien masuk dari Client Management (CM): pilih klien
 * baru/lama, rapikan data klien, buat proyek, dan salin dokumen dari CM.
 * Semua disimpan dalam satu transaksi; CM dikabari lewat antrean sesudahnya.
 */
class ProsesClientIntake extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ClientIntakeResource::class;

    protected static string $view = 'filament.resources.client-intake-resource.pages.proses-client-intake';

    protected static ?string $breadcrumb = 'Cek & proses';

    public ?array $data = [];

    /** Lampiran CM untuk langkah Dokumen; diambil saat masuk langkah itu. */
    public array $cmDocuments = [];

    public ?string $cmDocumentsError = null;

    public bool $cmDocumentsLoaded = false;

    /** Group form proyek; relasinya (tahapan, anggota) disimpan setelah proyek dibuat. */
    protected ?Forms\Components\Group $projectFormGroup = null;

    protected ?Client $linkedClientCache = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if ($this->getIntake()->isPending()) {
            $state = $this->initialState();

            // Form dibangun saat pertama diakses dan petunjuk "Di CM" membaca
            // pilihan klien dari $data, jadi isi $data sebelum form dibangun.
            $this->data = $state;
            $this->form->fill($state);
        }
    }

    public function getIntake(): ClientIntake
    {
        return $this->getRecord();
    }

    public function getTitle(): string | Htmlable
    {
        return $this->getIntake()->isPending() ? 'Cek & proses klien masuk' : 'Klien masuk';
    }

    /** What CM is, in one line (the deal itself is in the summary card below). */
    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(
            '<span class="kis-lead">Data deal dari <b>Client Management (CM)</b>, aplikasi tim marketing Kisantra. '
            . 'Isian bertanda CM berasal dari sana; ubah bila perlu sebelum diproses.</span>'
        );
    }

    // ---------------------------------------------------------------------
    // Form
    // ---------------------------------------------------------------------

    public function form(Form $form): Form
    {
        $form->statePath('data');

        if (! $this->getIntake()->isPending()) {
            return $form->schema([
                $this->resultSection(),
                ...$this->summarySections(),
            ]);
        }

        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Cek')
                    ->description('Data dari Client Management')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        ...$this->summarySections(),
                        $this->clientChoiceSection(),
                    ]),

                Forms\Components\Wizard\Step::make('Data klien')
                    ->description('Rapikan sebelum disimpan')
                    ->icon('heroicon-o-building-office-2')
                    ->schema($this->clientSchema()),

                Forms\Components\Wizard\Step::make('Proyek')
                    ->description('Proyek untuk layanan ini')
                    ->icon('heroicon-o-clipboard-document')
                    ->schema([$this->projectFormGroup = $this->projectGroup()])
                    // Daftar lampiran baru diambil saat menuju langkah Dokumen.
                    ->afterValidation(fn () => $this->loadCmDocuments()),

                Forms\Components\Wizard\Step::make('Dokumen')
                    ->description('Salin dari Client Management')
                    ->icon('heroicon-o-paper-clip')
                    ->schema($this->documentSchema()),
            ])
                ->previousAction(fn (Forms\Components\Actions\Action $action) => $action->label('Kembali'))
                ->nextAction(fn (Forms\Components\Actions\Action $action) => $action->label('Lanjut'))
                ->submitAction($this->submitButton()),
        ]);
    }

    /** @return array<Forms\Components\Section> */
    protected function summarySections(): array
    {
        $intake = $this->getIntake();
        $value = fn (mixed $value) => filled($value) ? (string) $value : '—';

        $contact = collect([
            $intake->sent('contact.name'),
            $intake->sent('contact.position'),
            $intake->sent('contact.phone'),
            $intake->sent('contact.email'),
        ])->filter()->implode(' · ');

        return [
            Forms\Components\Section::make('Data dari Client Management')
                ->description('Hanya dibaca. Datanya bisa diubah di langkah Data klien.')
                ->icon('heroicon-o-inbox-arrow-down')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\Placeholder::make('cm_name')->label('Nama')->content($intake->name),
                    Forms\Components\Placeholder::make('cm_entity')->label('Bentuk usaha')
                        ->content($value(collect([$intake->entity, $intake->sent('client_type')])->filter()->implode(' · '))),
                    Forms\Components\Placeholder::make('cm_npwp')->label('NPWP')->content($value($intake->sent('npwp'))),
                    Forms\Components\Placeholder::make('cm_email')->label('Email')->content($value($intake->sent('email'))),
                    Forms\Components\Placeholder::make('cm_contact')->label('Kontak')->content($value($contact)),
                    Forms\Components\Placeholder::make('cm_address')->label('Alamat')->content($value($intake->sent('address'))),
                    Forms\Components\Placeholder::make('cm_service')->label('Layanan')->content($value($intake->service)),
                    Forms\Components\Placeholder::make('cm_value')->label('Nilai deal')->content($value(ClientIntake::formatRupiah($intake->deal_value))),
                    Forms\Components\Placeholder::make('cm_owner')->label('PJ marketing')->content($value($intake->marketing_owner)),
                    Forms\Components\Placeholder::make('cm_deal_at')->label('Tanggal Deal')
                        ->content($value($intake->deal_at?->locale('id')->translatedFormat('j M Y'))),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('open_lead')
                            ->label('Lihat lead di Client Management')
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->url($intake->lead_url, shouldOpenInNewTab: true)
                            ->link(),
                    ])->visible(filled($intake->lead_url))->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Kontrak & lampiran dari Client Management')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    // Komponen terpisah + lazy: CM yang lambat tidak menahan halaman,
                    // dan signed URL (5 menit) diambil ulang setiap kali dibuka.
                    Forms\Components\Livewire::make(IntakeContractPanel::class, ['cmLeadId' => $intake->cm_lead_id])
                        ->key('cm-contract-' . $intake->id)
                        ->lazy(),
                ]),
        ];
    }

    protected function clientChoiceSection(): Forms\Components\Section
    {
        $intake = $this->getIntake();
        $suggested = $intake->suggestedClient;

        return Forms\Components\Section::make('Klien di PM')
            ->icon('heroicon-o-users')
            ->schema([
                Forms\Components\Placeholder::make('suggestion')
                    ->label('Saran pencocokan')
                    ->hiddenLabel()
                    ->visible($suggested !== null)
                    ->content(fn () => $suggested
                        ? "Mungkin klien lama: {$suggested->name} (cocok dari " . ($intake->suggested_by === 'npwp' ? 'NPWP' : 'email') . ').'
                        : null),

                Forms\Components\Radio::make('mode')
                    ->label('Proses deal ini sebagai')
                    ->options([
                        'new' => 'Buat klien baru',
                        'link' => 'Tautkan ke klien yang sudah ada',
                    ])
                    ->descriptions([
                        'new' => 'Data dari Client Management menjadi klien baru di PM.',
                        'link' => 'Deal ini untuk klien yang sudah ada; datanya bisa diperbarui di langkah berikutnya.',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->applyClientChoice($get, $set)),

                Forms\Components\Select::make('existing_client_id')
                    ->label('Klien yang sudah ada')
                    ->placeholder('Cari nama, NPWP, atau email')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Client::query()
                        ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                            ->orWhere('NPWP', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => Client::find($value)?->name)
                    ->visible(fn (Get $get) => $get('mode') === 'link')
                    ->required(fn (Get $get) => $get('mode') === 'link')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->applyClientChoice($get, $set)),
            ]);
    }

    /** @return array<Forms\Components\Component> */
    protected function clientSchema(): array
    {
        $linked = $this->linkedClient();

        return [
            Forms\Components\Placeholder::make('client_intro')
                ->label('Keterangan')
                ->hiddenLabel()
                ->content($linked
                    ? "Memperbarui data klien {$linked->name}. Isian bertanda \"Di CM\" berbeda dengan data dari Client Management."
                    : 'Klien baru, terisi dari Client Management. Isian bertanda CM berasal dari sana dan masih bisa diubah.'),

            Forms\Components\Group::make($this->clientSections())
                ->statePath('client')
                // Klien lama sebagai model: aturan unik nama/email mengabaikan klien itu sendiri.
                ->model(fn () => $this->linkedClient() ?? Client::class),

            $this->contactSection(),
        ];
    }

    /** @return array<Forms\Components\Section> */
    protected function clientSections(): array
    {
        $linked = $this->linkedClient();
        $entitySubtype = ClientIntakeService::subtypeForEntity($this->getIntake()->entity);

        // PIC wajib untuk Badan di sini (di form biasa tidak).
        $picFields = array_map(function ($component) {
            if ($component instanceof Forms\Components\Select && $component->getName() === 'pic_id') {
                $component->required(fn (Get $get) => ($get('client_type') === 'Pribadi' && $get('pic_is_self') === 'yes')
                    || $get('client_type') === 'Badan');
            }

            return $component;
        }, ClientResource::picFields());

        $contractLabels = [
            'ppn_contract' => 'PPN',
            'pph_contract' => 'PPh 21',
            'bupot_contract' => 'PPh Unifikasi',
            'pph_badan_contract' => 'PPh Badan',
        ];

        $service = $this->getIntake()->service;

        return [
            Forms\Components\Section::make('Identitas')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([
                    $this->cmHint(
                        ClientResource::clientTypeField()
                            ->label('Jenis klien')
                            // Setelah reset bawaan, isi lagi saran sub tipe dari bentuk usaha di CM.
                            ->afterStateUpdated(function (Set $set, ?string $state) use ($entitySubtype) {
                                if ($state === 'Badan' && $entitySubtype) {
                                    $set('client_subtype', $entitySubtype);
                                }
                            }),
                        'client_type',
                        $linked?->client_type,
                    ),
                    ClientResource::clientSubtypeField(),
                    $this->cmHint(ClientResource::nameField()->label('Nama klien'), 'name', $linked?->name),
                    $this->cmHint(
                        ClientResource::npwpField()->required(),
                        'npwp',
                        $linked?->NPWP,
                        fn ($value) => ClientIntakeService::normalizeNpwp($value),
                    ),
                    $this->cmHint(ClientResource::emailField()->label('Email'), 'email', $linked?->email),
                    ClientResource::statusField()->label('Status klien'),
                    $this->cmHint(ClientResource::addressField()->label('Alamat'), 'address', $linked?->adress),
                ]),

            Forms\Components\Section::make('Data pajak')
                ->description('Tidak ada di Client Management.')
                ->icon('heroicon-o-document-text')
                ->columns(2)
                ->schema([
                    ClientResource::pkpStatusField(),
                    ClientResource::kluField(),
                    ClientResource::accountRepresentativeField()
                        ->label('Account Representative (AR)')
                        ->helperText('KPP klien mengikuti AR yang dipilih.')
                        ->required()
                        ->live(),
                    Forms\Components\Placeholder::make('kpp_display')
                        ->label('KPP')
                        ->content(fn (Get $get) => filled($get('ar_id'))
                            ? (AccountRepresentative::find($get('ar_id'))?->kpp ?: 'AR ini belum punya KPP')
                            : 'Pilih AR lebih dulu'),
                ]),

            Forms\Components\Section::make('Penanggung jawab')
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->schema([
                    ...$picFields,
                    ClientResource::groupField()->label('Grup klien'),
                ]),

            Forms\Components\Section::make('Kontrak layanan')
                ->description(filled($service)
                    ? "Layanan yang di-deal: {$service}. " . (ClientIntakeService::suggestedContracts($service)
                        ? 'Toggle yang sesuai sudah dinyalakan sebagai saran.'
                        : 'Tidak ada toggle yang cocok otomatis; pilih manual.')
                    : null)
                ->icon('heroicon-o-document-check')
                ->schema([
                    Forms\Components\Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema(array_map(
                        fn (Forms\Components\Toggle $toggle) => $toggle->label($contractLabels[$toggle->getName()] ?? $toggle->getLabel()),
                        ClientResource::contractToggles(),
                    )),
                ]),
        ];
    }

    protected function contactSection(): Forms\Components\Section
    {
        $primary = $this->linkedClient()?->contacts()->where('type', 'primary')->orderByDesc('is_active')->orderBy('id')->first();
        $phoneTarget = ClientIntakeService::splitPhone($this->getIntake()->sent('contact.phone'))['mobile'] ? 'mobile' : 'phone';
        $digits = fn ($value) => preg_replace('/\D+/', '', (string) $value);

        $phoneField = fn (string $name, string $label) => $name === $phoneTarget
            ? $this->cmHint(Forms\Components\TextInput::make($name)->label($label)->tel()->maxLength(50), 'contact.phone', $primary?->{$name}, $digits)
            : Forms\Components\TextInput::make($name)->label($label)->tel()->maxLength(50);

        return Forms\Components\Section::make('Kontak utama')
            ->description('Disimpan sebagai kontak bertipe utama. Nomor seluler dari Client Management masuk ke HP/WhatsApp.')
            ->icon('heroicon-o-user')
            ->statePath('contact')
            ->columns(2)
            ->schema([
                $this->cmHint(Forms\Components\TextInput::make('name')->label('Nama')->required()->maxLength(255), 'contact.name', $primary?->name),
                $this->cmHint(Forms\Components\TextInput::make('position')->label('Jabatan')->maxLength(255), 'contact.position', $primary?->position),
                $this->cmHint(Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(255), 'contact.email', $primary?->email),
                $phoneField('phone', 'Telepon'),
                $phoneField('mobile', 'HP/WhatsApp'),
            ]);
    }

    protected function projectGroup(): Forms\Components\Group
    {
        $users = fn () => $this->projectUsers();

        $labels = [
            'name' => 'Nama proyek',
            'pic_id' => 'PIC proyek',
            'type' => 'Jenis proyek',
            'priority' => 'Prioritas',
            'sop_id' => 'SOP',
            'due_date' => 'Tenggat',
            'description' => 'Deskripsi',
        ];

        $details = array_map(
            fn (Field $field) => isset($labels[$field->getName()]) ? $field->label($labels[$field->getName()]) : $field,
            [ProjectResource::projectNameField(), ...ProjectResource::projectDetailFields($users)],
        );

        foreach ($details as $field) {
            if ($field->getName() === 'pic_id') {
                $field->helperText('Project Manager, Direktur, atau Super Admin dari tim klien.');
            }
        }

        return Forms\Components\Group::make([
            Forms\Components\Section::make('Proyek')
                ->description('Field yang sama dengan form Create Project. Nilai deal dari Client Management dicatat di deskripsi karena proyek tidak punya kolom nilai.')
                ->icon('heroicon-o-clipboard-document')
                ->columns(4)
                ->schema($details),

            Forms\Components\Section::make('Tahapan proyek')
                ->description('Terisi otomatis dari SOP yang dipilih, sama seperti form Create Project.')
                ->icon('heroicon-o-squares-plus')
                ->collapsible()
                ->schema([ProjectResource::projectStepsRepeater()->label('Tahapan')->addActionLabel('Tambah tahapan')]),

            Forms\Components\Section::make('Anggota proyek')
                ->description('Direktur dan super-admin selalu ditambahkan otomatis, sama seperti membuat proyek manual.')
                ->icon('heroicon-o-users')
                ->collapsible()
                ->schema([ProjectResource::projectMembersRepeater($users)->addActionLabel('Tambah anggota')]),
        ])
            ->statePath('project')
            // Belum ada proyek; relasinya disimpan setelah proyek dibuat (createProject()).
            ->model(Project::class);
    }

    /** @return array<Forms\Components\Component> */
    protected function documentSchema(): array
    {
        $submitLabel = $this->submitLabel();

        return [
            Forms\Components\Placeholder::make('documents_intro')
                ->label('Keterangan')
                ->hiddenLabel()
                ->content("Dokumen yang dicentang diunduh dari Client Management saat tombol \"{$submitLabel}\" ditekan, lalu disimpan sebagai dokumen tambahan klien di PM. Kontrak juga dipasang sebagai dokumen kontrak klien bila klien belum punya."),

            Forms\Components\Placeholder::make('documents_error')
                ->label('Status dokumen')
                ->hiddenLabel()
                ->visible(fn () => $this->cmDocumentsError !== null)
                ->content(fn () => $this->cmDocumentsError),

            Forms\Components\Placeholder::make('documents_empty')
                ->label('Status dokumen')
                ->hiddenLabel()
                ->visible(fn () => $this->cmDocumentsLoaded && $this->cmDocumentsError === null && $this->cmDocuments === [])
                ->content('Lead ini tidak punya lampiran di Client Management.'),

            Forms\Components\CheckboxList::make('documents')
                ->label('Dokumen yang disalin')
                ->options(fn () => collect($this->cmDocuments)->mapWithKeys(fn (array $doc) => [$doc['id'] => $doc['name']])->all())
                ->descriptions(fn () => collect($this->cmDocuments)->mapWithKeys(fn (array $doc) => [
                    $doc['id'] => collect([
                        IntakeDocumentImporter::KIND_LABELS[$doc['kind']] ?? null,
                        IntakeContractPanel::formatSize($doc['size']),
                        IntakeContractPanel::formatDate($doc['uploaded_at']),
                    ])->filter()->implode(' · '),
                ])->all())
                ->bulkToggleable()
                ->visible(fn () => $this->cmDocuments !== []),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('reload_documents')
                    ->label('Muat ulang daftar')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->action(fn () => $this->loadCmDocuments()),
            ]),
        ];
    }

    protected function resultSection(): Forms\Components\Section
    {
        $intake = $this->getIntake()->loadMissing('client', 'project', 'completedBy');
        $link = fn (string $url, string $label) => new HtmlString('<a href="' . e($url) . '" class="font-medium text-primary-600 hover:underline dark:text-primary-400">' . e($label) . '</a>');
        $time = fn ($value) => $value?->locale('id')->translatedFormat('j M Y H.i');

        $handoff = match (true) {
            $intake->handoff_sent_at !== null => 'Terkirim ' . $time($intake->handoff_sent_at),
            filled($intake->handoff_error) => 'Belum terkirim. ' . $intake->handoff_error,
            default => 'Dalam antrean',
        };

        return Forms\Components\Section::make($intake->isCompleted() ? 'Sudah diproses' : 'Tidak diproses')
            ->icon($intake->isCompleted() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->columns(['default' => 1, 'md' => 2])
            ->schema($intake->isCompleted() ? [
                Forms\Components\Placeholder::make('result_by')->label('Diproses oleh')
                    ->content(($intake->completedBy?->name ?? 'Pengguna terhapus') . ', ' . $time($intake->completed_at)),
                Forms\Components\Placeholder::make('result_mode')->label('Cara')
                    ->content($intake->matched ? 'Ditautkan ke klien yang sudah ada' : 'Klien baru dibuat'),
                Forms\Components\Placeholder::make('result_client')->label('Klien')
                    ->content($intake->client
                        ? $link(ClientResource::getUrl('view', ['record' => $intake->client]), $intake->client->name)
                        : 'Klien sudah dihapus'),
                Forms\Components\Placeholder::make('result_project')->label('Proyek')
                    ->content($intake->project
                        ? $link(ProjectResource::getUrl('view', ['record' => $intake->project]), $intake->project->name)
                        : 'Proyek sudah dihapus'),
                Forms\Components\Placeholder::make('result_handoff')->label('Kabar ke Client Management')
                    ->content($handoff),
            ] : [
                Forms\Components\Placeholder::make('result_withdrawn')->label('Keterangan')->hiddenLabel()
                    ->content('Deal ini dibatalkan di Client Management sebelum diproses, jadi tidak ada klien atau proyek yang dibuat. Bila lead kembali ke Deal, data ini muncul lagi di tab Menunggu dicek.'),
            ]);
    }

    // ---------------------------------------------------------------------
    // State
    // ---------------------------------------------------------------------

    protected function initialState(): array
    {
        $suggested = $this->getIntake()->suggestedClient;

        return [
            'mode' => $suggested ? 'link' : 'new',
            'existing_client_id' => $suggested?->id,
            'client' => $this->clientState($suggested),
            'contact' => $this->contactState($suggested),
            'project' => $this->projectState(),
            'documents' => [],
        ];
    }

    /** Klien lama: nilai tersimpan. Klien baru: dari CM. Lalu saran dari layanan & bentuk usaha. */
    protected function clientState(?Client $client): array
    {
        $intake = $this->getIntake();

        $state = $client
            ? $client->only([
                'client_type', 'client_subtype', 'name', 'NPWP', 'email', 'status', 'adress', 'pkp_status',
                'klu', 'ar_id', 'pic_id', 'group_id', 'ppn_contract', 'pph_contract', 'bupot_contract', 'pph_badan_contract',
            ])
            : [
                'client_type' => $intake->sent('client_type') ?: 'Badan',
                'client_subtype' => null,
                'name' => $intake->name,
                'NPWP' => $intake->sent('npwp'),
                'email' => $intake->sent('email'),
                'status' => 'Active',
                'adress' => filled($intake->sent('address')) ? mb_substr(trim($intake->sent('address')), 0, 255) : null,
                'pkp_status' => 'Non-PKP',
                'klu' => null,
                'ar_id' => null,
                'pic_id' => null,
                'group_id' => null,
                'ppn_contract' => false,
                'pph_contract' => false,
                'bupot_contract' => false,
                'pph_badan_contract' => false,
            ];

        foreach (['ppn_contract', 'pph_contract', 'bupot_contract', 'pph_badan_contract'] as $field) {
            $state[$field] = (bool) $state[$field];
        }

        // Saran hanya menyalakan, tidak pernah mematikan kontrak yang sudah ada.
        $suggested = ClientIntakeService::suggestedContracts($intake->service);

        foreach ($suggested as $field) {
            $state[$field] = true;
        }

        // Toggle PPN terkunci untuk Non-PKP; layanan PPN berarti klien PKP.
        if (in_array('ppn_contract', $suggested, true)) {
            $state['pkp_status'] = 'PKP';
        }

        if (blank($state['client_subtype']) && $state['client_type'] === 'Badan') {
            $state['client_subtype'] = ClientIntakeService::subtypeForEntity($intake->entity);
        }

        $state['pic_is_self'] = $state['client_type'] === 'Pribadi' && filled($state['pic_id']) ? 'yes' : 'no';

        return $state;
    }

    protected function contactState(?Client $client): array
    {
        $existing = $client?->contacts()->where('type', 'primary')->orderByDesc('is_active')->orderBy('id')->first();

        if ($existing) {
            return $existing->only(['name', 'position', 'email', 'phone', 'mobile']);
        }

        $intake = $this->getIntake();

        return [
            'name' => $intake->sent('contact.name'),
            'position' => $intake->sent('contact.position'),
            'email' => $intake->sent('contact.email'),
            ...ClientIntakeService::splitPhone($intake->sent('contact.phone')),
        ];
    }

    protected function projectState(): array
    {
        $intake = $this->getIntake();
        $year = ($intake->deal_at ?? now())->year;

        $details = collect([
            filled($intake->service) ? 'layanan <strong>' . e($intake->service) . '</strong>' : null,
            $intake->deal_value !== null ? 'nilai deal ' . e(ClientIntake::formatRupiah($intake->deal_value)) : null,
            filled($intake->marketing_owner) ? 'PJ marketing ' . e($intake->marketing_owner) : null,
            $intake->deal_at ? 'deal ' . $intake->deal_at->locale('id')->translatedFormat('j M Y') : null,
        ])->filter()->implode(', ');

        return [
            'name' => trim(($intake->service ?: $intake->name) . ' ' . $year),
            'pic_id' => null,
            'department_id' => null,
            'type' => $this->suggestedProjectType($intake->service),
            'priority' => 'normal',
            'sop_id' => null,
            'due_date' => null,
            'description' => $details ? "<p>Dari Client Management: {$details}.</p>" : null,
            'steps' => [],
            'userProject' => [],
        ];
    }

    /** PPh Badan dilaporkan tahunan; PPN, PPh 21, dan Unifikasi bulanan. */
    protected function suggestedProjectType(?string $service): ?string
    {
        $contracts = ClientIntakeService::suggestedContracts($service);

        return match (true) {
            in_array('pph_badan_contract', $contracts, true) => 'yearly',
            $contracts !== [] => 'monthly',
            default => null,
        };
    }

    /** Ganti klien baru/lama: isi ulang data klien, kontak, dan pilihan orang di proyek. */
    protected function applyClientChoice(Get $get, Set $set): void
    {
        $this->linkedClientCache = null;

        $client = $get('mode') === 'link' && filled($get('existing_client_id'))
            ? Client::find($get('existing_client_id'))
            : null;

        $set('client', $this->clientState($client));
        $set('contact', $this->contactState($client));
        $set('project.pic_id', null);
        $set('project.userProject', []);
    }

    protected function linkedClient(): ?Client
    {
        $id = ($this->data['mode'] ?? null) === 'link' ? ($this->data['existing_client_id'] ?? null) : null;

        if (blank($id)) {
            return null;
        }

        if ($this->linkedClientCache?->getKey() !== (int) $id) {
            $this->linkedClientCache = Client::find($id);
        }

        return $this->linkedClientCache;
    }

    /**
     * Calon PIC/anggota proyek. Klien lama: pengguna yang terhubung ke klien.
     * Klien baru: tim default yang akan ditugaskan saat klien dibuat.
     */
    protected function projectUsers(): Builder
    {
        $client = $this->linkedClient();

        return $client
            ? ProjectResource::usersOfClient($client->id)
            : User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', DefaultClientTeam::ROLES));
    }

    /**
     * Tandai isian dari CM. Klien baru: tag "CM" (dengan tooltip). Klien lama:
     * "Di CM: …" hanya bila nilai CM berbeda dengan data tersimpan.
     */
    protected function cmHint(Field $field, string $payloadKey, mixed $current, ?\Closure $normalize = null): Field
    {
        $sent = $this->getIntake()->sent($payloadKey);

        if (blank($sent) || ! is_scalar($sent)) {
            return $field;
        }

        if (! $this->linkedClient()) {
            return $field->hint('CM')
                ->hintColor('info')
                ->hintIcon('heroicon-m-information-circle', tooltip: 'Terisi dari Client Management, aplikasi tim marketing. Boleh diubah.');
        }

        $normalize ??= fn ($value) => mb_strtolower(trim((string) $value));

        if ($normalize($sent) === $normalize($current)) {
            return $field;
        }

        return $field->hint('Di CM: ' . Str::limit((string) $sent, 40))
            ->hintColor('warning')
            ->hintIcon('heroicon-m-arrows-right-left', tooltip: "Client Management (aplikasi tim marketing) mencatat: {$sent}");
    }

    public function loadCmDocuments(): void
    {
        $this->cmDocumentsError = null;
        $cm = app(ClientManagementClient::class);

        if (! $cm->isConfigured()) {
            $this->cmDocuments = [];
            $this->cmDocumentsError = 'Integrasi dengan Client Management belum diatur, jadi dokumen tidak bisa disalin. Klien dan proyek tetap bisa dibuat.';
            $this->cmDocumentsLoaded = true;

            return;
        }

        try {
            $documents = $cm->documents($this->getIntake()->cm_lead_id);
        } catch (Throwable $e) {
            report($e);
            $this->cmDocuments = [];
            $this->cmDocumentsError = 'Daftar dokumen tidak bisa diambil dari Client Management. Coba "Muat ulang daftar", atau lanjutkan tanpa dokumen.';
            $this->cmDocumentsLoaded = true;

            return;
        }

        // Cukup id/nama/jenis/ukuran untuk dipilih; URL unduhan diambil ulang saat menyimpan.
        $this->cmDocuments = array_map(
            fn (array $doc) => collect($doc)->only(['id', 'kind', 'name', 'size', 'uploaded_at'])->all(),
            $documents,
        );

        $ids = array_column($this->cmDocuments, 'id');

        // Pertama kali: semua dicentang. Sesudahnya: pertahankan pilihan staf.
        $this->data['documents'] = $this->cmDocumentsLoaded
            ? array_values(array_intersect($this->data['documents'] ?? [], $ids))
            : $ids;

        $this->cmDocumentsLoaded = true;
    }

    // ---------------------------------------------------------------------
    // Proses
    // ---------------------------------------------------------------------

    protected function submitLabel(): string
    {
        return ($this->data['mode'] ?? 'new') === 'link' ? 'Tautkan & buat proyek' : 'Buat klien & proyek';
    }

    protected function submitButton(): Htmlable
    {
        return new HtmlString(Blade::render(
            '<x-filament::button type="submit" form="process">{{ $label }}</x-filament::button>',
            ['label' => $this->submitLabel()],
        ));
    }

    public function process(): void
    {
        if (! $this->getIntake()->isPending()) {
            $this->notifyNotPending();

            return;
        }

        $state = $this->form->getState();
        $linked = $state['mode'] === 'link' ? Client::find($state['existing_client_id'] ?? null) : null;

        if ($state['mode'] === 'link' && ! $linked) {
            $this->addError('data.existing_client_id', 'Klien yang dipilih tidak ditemukan.');

            return;
        }

        $importer = app(IntakeDocumentImporter::class);

        // Unduh dulu, di luar transaksi: kalau gagal, belum ada yang tersimpan.
        try {
            $downloads = $importer->download($this->getIntake()->cm_lead_id, $state['documents'] ?? []);
        } catch (IntakeDocumentException $e) {
            report($e);

            $message = $e->fileName
                ? "\"{$e->fileName}\" tidak bisa diunduh dari Client Management ({$e->reason()}) Hapus centang dokumen itu lalu simpan lagi, atau coba beberapa saat lagi."
                : $e->reason() . ' Muat ulang daftar dokumen lalu coba lagi.';

            $this->addError('data.documents', $message);

            Notification::make()
                ->title('Dokumen gagal diambil, belum ada yang disimpan')
                ->body($message)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $written = [];

        try {
            [$client, $project, $intake] = DB::transaction(function () use ($state, $linked, $downloads, $importer, &$written) {
                // Kunci baris: dua staf yang memproses bersamaan tidak boleh membuat dua klien.
                $intake = ClientIntake::query()->lockForUpdate()->findOrFail($this->getIntake()->getKey());

                if (! $intake->isPending()) {
                    throw new IntakeAlreadyProcessedException();
                }

                $service = app(ClientIntakeService::class);

                $client = $service->saveClient(
                    $linked ? Client::query()->lockForUpdate()->findOrFail($linked->getKey()) : null,
                    $state['client'],
                    $state['contact'] ?? [],
                );

                $project = $this->createProject($client, $state['project']);

                $importer->store($client, $intake, $downloads['files'], $written);

                $service->complete($intake, $client, $project, $linked !== null, auth()->user());

                return [$client, $project, $intake];
            });
        } catch (IntakeAlreadyProcessedException) {
            $importer->cleanup($downloads['dir']);
            $this->notifyNotPending();

            return;
        } catch (Throwable $e) {
            $importer->discard($written);
            $importer->cleanup($downloads['dir']);

            throw $e;
        }

        $importer->cleanup($downloads['dir']);

        // Di luar transaksi: CM yang mati tidak boleh membatalkan data yang tersimpan.
        $queued = $this->dispatchHandoff($intake);

        $documents = count($downloads['files']);

        Notification::make()
            ->title($linked ? 'Klien ditautkan dan proyek dibuat' : 'Klien dan proyek dibuat')
            ->body("Klien {$client->name}, proyek {$project->name}"
                . ($documents ? ", {$documents} dokumen disalin." : '.')
                . ($queued ? ' Client Management akan diberi tahu otomatis.' : ' Kabar ke Client Management gagal dikirim; kirim ulang dari halaman Klien Masuk.'))
            ->status($queued ? 'success' : 'warning')
            ->send();

        $this->redirect(ClientResource::getUrl('view', ['record' => $client]));
    }

    /**
     * Sama seperti CreateProject: kolom proyek, lalu relasi form (departemen,
     * tahapan SOP + tugas + dokumen wajib, anggota) disimpan oleh Filament,
     * lalu langkah sesudahnya (anggota wajib, notifikasi, DailyTask).
     */
    protected function createProject(Client $client, array $data): Project
    {
        $data['client_id'] = $client->id;

        $project = Project::create(ProjectCreator::attributes($data));

        $this->projectFormGroup->model($project);
        $this->projectFormGroup->getChildComponentContainer()->saveRelationships();

        app(ProjectCreator::class)->afterCreate($project, $data['userProject'] ?? []);

        return $project;
    }

    protected function dispatchHandoff(ClientIntake $intake): bool
    {
        try {
            app(ClientIntakeService::class)->dispatchHandoff($intake);

            return true;
        } catch (Throwable $e) {
            // Dengan QUEUE_CONNECTION=sync, kegagalan CM muncul di sini.
            report($e);

            return false;
        }
    }

    protected function notifyNotPending(): void
    {
        Notification::make()
            ->title('Klien masuk ini sudah tidak menunggu dicek')
            ->body('Sudah diproses oleh orang lain atau dibatalkan di Client Management.')
            ->warning()
            ->send();

        $this->redirect($this->getIntake()->reviewUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend_handoff')
                ->label('Kirim ulang ke Client Management')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => ($intake = $this->getIntake())->isCompleted()
                    && ! $intake->isWithdrawn()
                    && $intake->handoff_sent_at === null)
                ->action(function () {
                    $queued = $this->dispatchHandoff($this->getIntake());

                    Notification::make()
                        ->title($queued ? 'Dikirim ulang ke Client Management' : 'Gagal mengirim ke Client Management')
                        ->body($queued ? null : 'Coba lagi beberapa saat lagi.')
                        ->status($queued ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }
}
