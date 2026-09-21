<?php

namespace App\Livewire\Client\Management;

use App\Filament\Pages\Letters\Show;
use App\Models\Client;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\LetterService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Surat" tab on the admin client detail page: every letter and berita acara
 * issued for this client, plus a shortcut to start a new one.
 */
class SuratTab extends Component
{
    use WithPagination;

    public Client $client;

    #[Url(as: 'surat_status')]
    public string $statusFilter = '';

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getLettersProperty()
    {
        return Letter::query()
            ->where('client_id', $this->client->id)
            ->with(['template:id,name,category,icon,signers', 'creator:id,name', 'signatures.user', 'project:id,name', 'requestedDocuments'])
            ->status($this->statusFilter ?: null)
            ->latest()
            ->paginate(10, pageName: 'suratPage');
    }

    public function getCountsProperty(): array
    {
        $counts = Letter::query()
            ->where('client_id', $this->client->id)
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
        $counts[''] = array_sum($counts);

        return $counts;
    }

    public function getTemplatesProperty()
    {
        return LetterTemplate::active()->ordered()->get()->groupBy('category');
    }

    public function getCanCreateProperty(): bool
    {
        return auth()->user()->can('surat.*') && $this->client->status === 'Active';
    }

    public function createDraft(int $templateId, LetterService $service): void
    {
        if (! $this->canCreate) {
            Notification::make()->title('Klien tidak aktif atau Anda tidak punya akses surat')->warning()->send();

            return;
        }

        $template = LetterTemplate::active()->find($templateId);
        if (! $template) {
            return;
        }

        $letter = $service->createDraft($template, $this->client, auth()->user());

        $this->dispatch('close-modal', id: 'buat-surat-klien');
        $this->redirect(Show::getUrl(['record' => $letter]));
    }

    public function render()
    {
        return view('livewire.client.management.surat-tab', [
            'letters'   => $this->letters,
            'counts'    => $this->counts,
            'templates' => $this->templates,
        ]);
    }
}
