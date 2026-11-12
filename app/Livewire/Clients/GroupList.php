<?php

namespace App\Livewire\Clients;

use App\Filament\Resources\ClientGroupResource;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\ClientGroup;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Client groups list (docs/mockups/client-groups-redesign.html): one card per
 * group with up to MEMBER_PREVIEW companies, a status rail, search that also
 * matches member names, and the "Tambah client" modal.
 */
class GroupList extends Component
{
    /** Companies listed on a card before "+N lainnya". */
    public const MEMBER_PREVIEW = 4;

    public const STATUSES = ['' => 'Semua', 'active' => 'Aktif', 'inactive' => 'Nonaktif'];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    // ── Modal "Tambah Client ke grup" ──
    public ?int $addGroupId = null;
    public array $newClientIds = [];
    public string $clientSearch = '';

    public function setStatus(string $status): void
    {
        $this->statusFilter = array_key_exists($status, self::STATUSES) ? $status : '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
    }

    public function openAddClient(int $groupId): void
    {
        $this->addGroupId = $groupId;
        $this->newClientIds = [];
        $this->clientSearch = '';
        $this->dispatch('open-modal', id: 'add-client-to-group');
    }

    public function saveAddClient(): void
    {
        if (! $this->addGroupId || empty($this->newClientIds)) {
            return;
        }

        $group = ClientGroup::find($this->addGroupId);
        if (! $group) {
            return;
        }

        // Hanya pindahkan client yang memang belum punya grup (cegah race / manipulasi).
        $count = Client::whereIn('id', $this->newClientIds)
            ->whereNull('group_id')
            ->update(['group_id' => $group->id]);

        Notification::make()
            ->success()
            ->title("{$count} client ditambahkan")
            ->body("Berhasil menambahkan ke grup \"{$group->name}\".")
            ->send();

        $this->newClientIds = [];
        $this->clientSearch = '';
        $this->dispatch('close-modal', id: 'add-client-to-group');
    }

    /** Grup yang sedang dibuka modalnya. */
    public function getAddGroupProperty(): ?ClientGroup
    {
        return $this->addGroupId ? ClientGroup::find($this->addGroupId) : null;
    }

    /** Client yang belum tergabung grup mana pun (kandidat untuk ditambahkan). */
    public function getAvailableClientsProperty(): Collection
    {
        return Client::query()
            ->whereNull('group_id')
            ->when($this->clientSearch !== '', function ($q) {
                $term = '%' . $this->clientSearch . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('NPWP', 'like', $term));
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'client_type', 'NPWP']);
    }

    public function getGroupsProperty(): Collection
    {
        return ClientGroup::query()
            ->with(['clients' => fn ($q) => $q->orderBy('name')])
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhereHas('clients', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->get();
    }

    /** Groups per status for the rail (ignores the search, like the other lists). */
    public function getStatusCountsProperty(): array
    {
        $counts = ClientGroup::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            '' => (int) $counts->sum(),
            'active' => (int) ($counts['active'] ?? 0),
            'inactive' => (int) ($counts['inactive'] ?? 0),
        ];
    }

    /** Clients that belong to no group yet: the "Buat grup baru" card points at them. */
    public function getUngroupedCountProperty(): int
    {
        return Client::whereNull('group_id')->count();
    }

    public function viewUrl(ClientGroup $group): string
    {
        return ClientGroupResource::getUrl('view', ['record' => $group]);
    }

    public function editUrl(ClientGroup $group): string
    {
        return ClientGroupResource::getUrl('edit', ['record' => $group]);
    }

    public function clientUrl(Client $client): string
    {
        return ClientResource::getUrl('view', ['record' => $client]);
    }

    public function render()
    {
        return view('livewire.clients.group-list', [
            'groups'     => $this->groups,
            'hasFilters' => $this->search !== '' || $this->statusFilter !== '',
        ]);
    }
}
