<?php

namespace App\Livewire\Clients;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientListClickup extends Component
{
    public const HARD_CAP = 500;
    public const DEFAULT_GROUP_LIMIT = 10;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public array $statusFilter = ['Active']; // default: hanya klien Aktif saat halaman dibuka

    #[Url(as: 'type')]
    public array $typeFilter = [];

    #[Url(as: 'pkp')]
    public array $pkpFilter = [];

    #[Url(as: 'contract')]
    public array $contractFilter = [];

    #[Url(as: 'group')]
    public string $groupBy = 'type';

    #[Url(as: 'sort')]
    public string $sortField = 'name';

    #[Url(as: 'dir')]
    public string $sortDirection = 'asc';

    #[Url(as: 'cols')]
    public array $visibleColumns = ['type', 'pkp', 'group'];

    public array $expandedGroups = [];

    public const TOGGLEABLE_COLUMNS = [
        'type'  => 'Type',
        'pkp'   => 'PKP',
        'group' => 'Group',
    ];

    /** Credential modal state */
    public ?int $viewingCredentialsClientId = null;

    public const STATUSES = [
        'Active'   => ['label' => 'Active',   'color' => '#16a34a', 'bg' => '#dcfce7'],
        'Inactive' => ['label' => 'Inactive', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    ];

    public const TYPES = [
        'Pribadi'                       => ['label' => 'Pribadi',         'color' => '#0e7490', 'bg' => '#cffafe'],
        'Badan'                         => ['label' => 'Badan',           'color' => '#9333ea', 'bg' => '#f3e8ff'],
        'Pemerintah'                    => ['label' => 'Pemerintah',      'color' => '#ca8a04', 'bg' => '#fef9c3'],
        'Pemungut PPN PMSE Luar Negeri' => ['label' => 'PMSE Luar Negeri','color' => '#ea580c', 'bg' => '#ffedd5'],
    ];

    public const PKP_STATUSES = [
        'PKP'     => ['label' => 'PKP',     'color' => '#16a34a', 'bg' => '#dcfce7'],
        'Non-PKP' => ['label' => 'Non-PKP', 'color' => '#64748b', 'bg' => '#f1f5f9'],
    ];

    public const CONTRACTS = [
        'ppn_contract'       => 'PPN',
        'pph_contract'       => 'PPh 21',
        'bupot_contract'     => 'PPh Unifikasi',
        'pph_badan_contract' => 'PPh Badan',
    ];

    public function updating($name): void
    {
        if (\in_array($name, ['search', 'statusFilter', 'typeFilter', 'pkpFilter', 'contractFilter', 'groupBy'], true)) {
            // Reset expanded groups when grouping changes
            if ($name === 'groupBy') {
                $this->expandedGroups = [];
            }
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'pkpFilter', 'contractFilter']);
    }

    public function removeStatus(string $key): void
    {
        $this->statusFilter = array_values(array_diff($this->statusFilter, [$key]));
    }

    public function removeType(string $key): void
    {
        $this->typeFilter = array_values(array_diff($this->typeFilter, [$key]));
    }

    public function removePkp(string $key): void
    {
        $this->pkpFilter = array_values(array_diff($this->pkpFilter, [$key]));
    }

    public function removeContract(string $key): void
    {
        $this->contractFilter = array_values(array_diff($this->contractFilter, [$key]));
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || !empty($this->statusFilter)
            || !empty($this->typeFilter)
            || !empty($this->pkpFilter)
            || !empty($this->contractFilter);
    }

    public function getActiveFilterCountProperty(): int
    {
        return \count($this->statusFilter) + \count($this->typeFilter) + \count($this->pkpFilter) + \count($this->contractFilter);
    }

    /** Label kombinasi kontrak yang dimiliki klien (untuk grouping). */
    protected function contractLabel(Client $client): string
    {
        $types = [];
        foreach (self::CONTRACTS as $col => $label) {
            if ($client->{$col}) {
                $types[] = $label;
            }
        }

        return empty($types) ? 'Tanpa Kontrak' : implode(', ', $types);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleGroupExpand(string $groupKey): void
    {
        if (\in_array($groupKey, $this->expandedGroups, true)) {
            $this->expandedGroups = array_values(array_diff($this->expandedGroups, [$groupKey]));
        } else {
            $this->expandedGroups[] = $groupKey;
        }
    }

    public function toggleColumn(string $key): void
    {
        if (!\array_key_exists($key, self::TOGGLEABLE_COLUMNS)) {
            return;
        }
        if (\in_array($key, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$key]));
        } else {
            $this->visibleColumns[] = $key;
        }
    }

    public function isColumnVisible(string $key): bool
    {
        return \in_array($key, $this->visibleColumns, true);
    }

    public function getGridTemplateProperty(): string
    {
        $cols = ['36px', 'minmax(220px, 2fr)'];                    // logo (circle), name
        $cols[] = '120px';                                          // status (always)
        if ($this->isColumnVisible('type'))  $cols[] = '140px';     // type
        if ($this->isColumnVisible('pkp'))   $cols[] = '110px';     // pkp
        if ($this->isColumnVisible('group')) $cols[] = '140px';     // group
        $cols[] = '160px';                                          // actions (wider — fits credential pill)
        return implode(' ', $cols);
    }

    public function openCredentials(int $clientId): void
    {
        $this->viewingCredentialsClientId = $clientId;
    }

    public function closeCredentials(): void
    {
        $this->viewingCredentialsClientId = null;
    }

    public function getCredentialClientProperty()
    {
        if (!$this->viewingCredentialsClientId) return null;

        return Client::with([
            'applicationCredentials.application',
            'clientCredential',
            'pic',
        ])->find($this->viewingCredentialsClientId);
    }

    protected function filteredQuery(): Builder
    {
        $query = Client::query()
            ->with(['pic:id,name', 'group:id,name', 'accountRepresentative:id,name', 'latestIntake']);

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('NPWP', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        if (!empty($this->statusFilter)) $query->whereIn('status', $this->statusFilter);
        if (!empty($this->typeFilter))   $query->whereIn('client_type', $this->typeFilter);
        if (!empty($this->pkpFilter))    $query->whereIn('pkp_status', $this->pkpFilter);

        // Filter kontrak: klien yang memiliki salah satu kontrak terpilih
        if (!empty($this->contractFilter)) {
            $query->where(function ($q) {
                foreach ($this->contractFilter as $col) {
                    if (\array_key_exists($col, self::CONTRACTS)) {
                        $q->orWhere($col, true);
                    }
                }
            });
        }

        return $query->orderBy($this->sortField, $this->sortDirection);
    }

    public function getTotalCountProperty(): int
    {
        return $this->filteredQuery()->count();
    }

    public function getClientsProperty()
    {
        return $this->filteredQuery()->limit(self::HARD_CAP)->get();
    }

    /**
     * Returns groups as: [groupKey => ['all' => Collection, 'visible' => Collection, 'hasMore' => bool, 'hidden' => int, 'expanded' => bool, 'total' => int]]
     */
    public function getGroupedClientsProperty(): array
    {
        $clients = $this->clients;

        if ($this->groupBy === 'none') {
            return ['' => $this->wrapGroup($clients)];
        }

        $grouped = [];
        foreach ($clients as $client) {
            $key = match ($this->groupBy) {
                'status' => $client->status ?: 'unknown',
                'type'   => $client->client_type ?: 'unknown',
                'pkp'    => $client->pkp_status ?: 'unknown',
                'pic'    => $client->pic?->name ?? 'Tidak Ada PIC',
                'group'  => $client->group?->name ?? 'Tidak Ada Group',
                'contract' => $this->contractLabel($client),
                default  => '',
            };
            $grouped[$key] ??= collect();
            $grouped[$key]->push($client);
        }

        if ($this->groupBy === 'status') {
            $grouped = $this->reorder($grouped, array_keys(self::STATUSES));
        } elseif ($this->groupBy === 'type') {
            $grouped = $this->reorder($grouped, array_keys(self::TYPES));
        } elseif ($this->groupBy === 'pkp') {
            $grouped = $this->reorder($grouped, array_keys(self::PKP_STATUSES));
        } else {
            ksort($grouped);
        }

        $result = [];
        foreach ($grouped as $key => $items) {
            $result[$key] = $this->wrapGroup($items, (string) $key);
        }

        return $result;
    }

    protected function reorder(array $grouped, array $order): array
    {
        $sorted = [];
        foreach ($order as $key) {
            if (isset($grouped[$key])) $sorted[$key] = $grouped[$key];
        }
        foreach ($grouped as $k => $v) {
            if (!isset($sorted[$k])) $sorted[$k] = $v;
        }
        return $sorted;
    }

    protected function wrapGroup($items, string $groupKey = ''): array
    {
        $count = $items->count();
        $isExpanded = \in_array($groupKey, $this->expandedGroups, true);
        $limit = self::DEFAULT_GROUP_LIMIT;

        $visible = ($isExpanded || $count <= $limit) ? $items : $items->take($limit);

        return [
            'all'      => $items,
            'visible'  => $visible,
            'total'    => $count,
            'shown'    => $visible->count(),
            'hasMore'  => $count > $limit,
            'hidden'   => max(0, $count - $visible->count()),
            'expanded' => $isExpanded,
        ];
    }

    public function getGroupLabel(string $key): string
    {
        return match ($this->groupBy) {
            'status' => self::STATUSES[$key]['label'] ?? ucfirst($key),
            'type'   => self::TYPES[$key]['label'] ?? ucfirst($key),
            'pkp'    => self::PKP_STATUSES[$key]['label'] ?? ucfirst($key),
            default  => $key ?: 'Other',
        };
    }

    public function getGroupColor(string $key): array
    {
        return match ($this->groupBy) {
            'status' => self::STATUSES[$key] ?? ['color' => '#64748b', 'bg' => '#f1f5f9'],
            'type'   => self::TYPES[$key] ?? ['color' => '#64748b', 'bg' => '#f1f5f9'],
            'pkp'    => self::PKP_STATUSES[$key] ?? ['color' => '#64748b', 'bg' => '#f1f5f9'],
            default  => ['color' => '#64748b', 'bg' => '#f1f5f9'],
        };
    }

    public function viewUrl(Client $client): string
    {
        return ClientResource::getUrl('view', ['record' => $client]);
    }

    public function editUrl(Client $client): string
    {
        return ClientResource::getUrl('edit', ['record' => $client]);
    }

    public function render()
    {
        return view('livewire.clients.client-list-clickup', [
            'grouped'    => $this->groupedClients,
            'totalCount' => $this->totalCount,
            'isCapped'   => $this->totalCount > self::HARD_CAP,
        ]);
    }
}
