<?php

namespace App\Filament\Pages\Letters;

use App\Models\Client;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\LetterService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Surat & Berita Acara — template library + list of letters.
 */
class Index extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Penyuratan';
    protected static ?string $navigationLabel = 'Surat & Berita Acara';
    protected static ?string $title = 'Surat & Berita Acara';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'surat';
    protected static string $view = 'filament.pages.letters.index';

    #[Url(as: 'tab')]
    public string $tab = 'template';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** Template picked from the library; opens the "pilih klien" dialog. */
    public ?int $pickedTemplateId = null;

    /**
     * State of the pick-client form. Initialised with its keys on purpose: an
     * empty [] reaches the browser as a JS array, and nested keys set on an
     * array are dropped when Livewire serialises it back.
     */
    public ?array $pick = ['client_id' => null, 'project_id' => null];

    public function mount(): void
    {
        $this->pickForm->fill();
    }

    protected function getForms(): array
    {
        return ['pickForm'];
    }

    public function pickForm(Form $form): Form
    {
        return $form->statePath('pick')->schema([
            Forms\Components\Select::make('client_id')->label('Klien')
                ->options(fn () => $this->clientOptions)
                ->searchable()->native(false)->required()
                ->placeholder('Cari nama klien…')
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('project_id', null)),
            Forms\Components\Select::make('project_id')->label('Proyek terkait')
                ->options(fn (Forms\Get $get) => $this->projectOptionsFor($get('client_id')))
                ->searchable()->native(false)
                ->placeholder('Tidak terkait proyek')
                ->helperText('Opsional. Surat akan muncul di riwayat proyek yang dipilih.')
                ->disabled(fn (Forms\Get $get) => blank($get('client_id'))),
        ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('surat.*');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('surat.*');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /* ------------------------------------------------------------------ */

    public function getTemplatesProperty()
    {
        return LetterTemplate::active()->ordered()
            ->when($this->search !== '' && $this->tab === 'template', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->withCount('letters')
            ->get()
            ->groupBy('category');
    }

    public function getLettersProperty(): LengthAwarePaginator
    {
        return Letter::query()
            ->with(['template:id,name,category,icon,signers', 'client:id,name', 'creator:id,name', 'signatures', 'requestedDocuments'])
            ->status($this->statusFilter ?: null)
            ->when($this->search !== '' && $this->tab === 'surat', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$this->search}%")
                ->orWhere('subject', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->search}%"))))
            ->latest()
            ->paginate(15);
    }

    public function getStatusCountsProperty(): array
    {
        $counts = Letter::query()->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status')->all();
        $counts[''] = array_sum($counts);

        return $counts;
    }

    /**
     * Letters that need something from the current user: their turn to sign,
     * or their own draft that came back rejected. Shown above the list.
     */
    public function getActionItemsProperty()
    {
        $user = auth()->user();

        return Letter::query()
            ->with(['template:id,name,category,icon', 'client:id,name', 'signatures.user'])
            ->where(fn ($q) => $q
                ->where('status', Letter::STATUS_SUBMITTED)
                ->orWhere(fn ($w) => $w->where('status', Letter::STATUS_REJECTED)->where('created_by', $user->id)))
            ->latest('updated_at')
            ->get()
            ->filter(fn (Letter $l) => $l->status === Letter::STATUS_REJECTED || $l->canBeSignedBy($user))
            ->values();
    }

    /** Letters waiting for the current user's signature. */
    public function getAwaitingMeProperty(): int
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(config('letter.signer_roles', []))) {
            return 0;
        }

        return Letter::query()->status(Letter::STATUS_SUBMITTED)->with('signatures')->get()
            ->filter(fn (Letter $l) => $l->canBeSignedBy($user))
            ->count();
    }

    /** Preview of the next number per prefix, for the header chip. */
    public function getNextNumbersProperty(): array
    {
        $service = app(LetterService::class);
        $setting = \App\Models\LetterNumberingSetting::current();

        return LetterTemplate::query()->distinct()->orderBy('number_prefix')->pluck('number_prefix')
            ->map(fn ($prefix) => $setting->format($prefix, $service->peekNextSequence($prefix, now(), $setting), now()))
            ->all();
    }

    public function getClientOptionsProperty(): array
    {
        return Client::query()->where('status', 'Active')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function projectOptionsFor(?int $clientId): array
    {
        if (! $clientId) {
            return [];
        }

        return \App\Models\Project::query()
            ->where('client_id', $clientId)
            ->orderByDesc('created_at')
            ->pluck('name', 'id')
            ->all();
    }

    /* ------------------------------------------------------------------ */

    public function pickTemplate(int $templateId): void
    {
        $this->pickedTemplateId = $templateId;
        $this->pickForm->fill([]);
        $this->dispatch('open-modal', id: 'pilih-klien');
    }

    public function createDraft(LetterService $service): void
    {
        $state = $this->pickForm->getState();
        $template = LetterTemplate::active()->find($this->pickedTemplateId);
        $client = Client::find($state['client_id'] ?? null);

        if (! $template || ! $client) {
            Notification::make()->title('Pilih template dan klien terlebih dahulu')->warning()->send();

            return;
        }

        $letter = $service->createDraft($template, $client, auth()->user(), $state['project_id'] ?? null);

        $this->dispatch('close-modal', id: 'pilih-klien');
        $this->redirect(Show::getUrl(['record' => $letter]));
    }
}
