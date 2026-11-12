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

    /** Only clients without a PIC (summary tile "Tanpa PIC"). */
    #[Url(as: 'tanpa_pic')]
    public bool $noPic = false;

    #[Url(as: 'group')]
    public string $groupBy = 'type';

    #[Url(as: 'sort')]
    public string $sortField = 'name';

    #[Url(as: 'dir')]
    public string $sortDirection = 'asc';

    // Type is hidden by default: it is the default grouping, so the group header already says it.
    #[Url(as: 'cols')]
    public array $visibleColumns = ['contract', 'pic', 'pkp', 'group'];

    public array $expandedGroups = [];

    public const TOGGLEABLE_COLUMNS = [
        'contract' => 'Kontrak',
        'pic'      => 'PIC',
        'type'     => 'Tipe',
        'pkp'      => 'PKP',
        'group'    => 'Grup',
    ];

    public const GROUP_BY = [
        'type'     => 'Tipe',
        'status'   => 'Status',
        'pkp'      => 'PKP',
        'contract' => 'Kontrak',
        'pic'      => 'PIC',
        'group'    => 'Grup',
        'none'     => 'Tanpa kelompok',
    ];

    /** "field:direction" => label. Anything else in ?sort= falls back to name A–Z. */
    public const SORTS = [
        'name:asc'        => 'Nama A–Z',
        'name:desc'       => 'Nama Z–A',
        'created_at:desc' => 'Terbaru ditambahkan',
        'created_at:asc'  => 'Terlama ditambahkan',
        'status:asc'      => 'Status (Active dulu)',
        'status:desc'     => 'Status (Inactive dulu)',
    ];

    /** Credential modal state */
    public ?int $viewingCredentialsClientId = null;

    // Tones from DESIGN.md (App\Support\Ui\Tone); color/bg are the matching
    // Tone::swatch() values for markup that needs a literal colour.
    public const STATUSES = [
        'Active'   => ['label' => 'Active',   'tone' => 'blue',    'color' => '#10657f', 'bg' => '#dff4fe'],
        'Inactive' => ['label' => 'Inactive', 'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
    ];

    // Client type is a category, not a state: neutral, told apart by the label.
    public const TYPES = [
        'Pribadi'                       => ['label' => 'Pribadi',          'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
        'Badan'                         => ['label' => 'Badan',            'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
        'Pemerintah'                    => ['label' => 'Pemerintah',       'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
        'Pemungut PPN PMSE Luar Negeri' => ['label' => 'PMSE Luar Negeri', 'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
    ];

    public const PKP_STATUSES = [
        'PKP'     => ['label' => 'PKP',     'tone' => 'outline', 'color' => '#10657f', 'bg' => 'transparent'],
        'Non-PKP' => ['label' => 'Non-PKP', 'tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'],
    ];

    private const NEUTRAL_GROUP = ['tone' => 'neutral', 'color' => '#485156', 'bg' => '#ebf2f4'];

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
        $this->reset(['search', 'statusFilter', 'typeFilter', 'pkpFilter', 'contractFilter', 'noPic']);
    }

    /** Summary tiles double as quick filters: each one starts from the defaults (active clients). */
    public function quickFilter(string $tile): void
    {
        $this->clearFilters();

        match ($tile) {
            'pkp' => $this->pkpFilter = ['PKP'],
            'contract' => $this->contractFilter = array_keys(self::CONTRACTS),
            'nopic' => $this->noPic = true,
            default => null,
        };
    }

    /** Which summary tile matches the current filters exactly, if any. */
    public function activeTile(): ?string
    {
        $base = $this->search === '' && $this->statusFilter === ['Active'] && ! $this->typeFilter;
        if (! $base) {
            return null;
        }

        return match (true) {
            ! $this->pkpFilter && ! $this->contractFilter && ! $this->noPic => 'all',
            $this->pkpFilter === ['PKP'] && ! $this->contractFilter && ! $this->noPic => 'pkp',
            ! $this->pkpFilter && count($this->contractFilter) === count(self::CONTRACTS) && ! $this->noPic => 'contract',
            ! $this->pkpFilter && ! $this->contractFilter && $this->noPic => 'nopic',
            default => null,
        };
    }

    /** Office-wide snapshot of active clients for the summary strip (one query). */
    public function getSummaryProperty(): array
    {
        $row = Client::query()
            ->where('status', 'Active')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(client_type = 'Badan') AS badan,
                SUM(client_type = 'Pribadi') AS pribadi,
                SUM(pkp_status = 'PKP') AS pkp,
                SUM(ppn_contract = 1 OR pph_contract = 1 OR bupot_contract = 1 OR pph_badan_contract = 1) AS contracted,
                SUM(ppn_contract = 1) AS ppn_contract,
                SUM(pph_contract = 1) AS pph_contract,
                SUM(bupot_contract = 1) AS bupot_contract,
                SUM(pph_badan_contract = 1) AS pph_badan_contract,
                SUM(pic_id IS NULL) AS no_pic
            ")
            ->first();

        return [
            'total' => (int) $row->total,
            'inactive' => Client::query()->where('status', 'Inactive')->count(),
            'badan' => (int) $row->badan,
            'pribadi' => (int) $row->pribadi,
            'pkp' => (int) $row->pkp,
            'contracted' => (int) $row->contracted,
            'contracts' => collect(self::CONTRACTS)->mapWithKeys(fn ($label, $col) => [$label => (int) $row->{$col}])->all(),
            'noPic' => (int) $row->no_pic,
        ];
    }

    public function setSort(string $sort): void
    {
        if (array_key_exists($sort, self::SORTS)) {
            [$this->sortField, $this->sortDirection] = explode(':', $sort);
        }
    }

    public function currentSort(): string
    {
        $key = $this->sortField . ':' . $this->sortDirection;

        return array_key_exists($key, self::SORTS) ? $key : 'name:asc';
    }

    public function removeNoPic(): void
    {
        $this->noPic = false;
    }

    /** 15-digit NPWP as 01.234.567.8-901.000, 16-digit (NIK) in groups of four; anything else as stored. */
    public static function formatNpwp(?string $npwp): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $npwp);

        return match (strlen($digits)) {
            15 => sprintf('%s.%s.%s.%s-%s.%s', substr($digits, 0, 2), substr($digits, 2, 3), substr($digits, 5, 3), substr($digits, 8, 1), substr($digits, 9, 3), substr($digits, 12, 3)),
            16 => trim(chunk_split($digits, 4, ' ')),
            default => $npwp ?: null,
        };
    }

    /** Labels of the client's tax contracts, in the fixed order of CONTRACTS. */
    public static function contractLabels(Client $client): array
    {
        return array_values(array_filter(self::CONTRACTS, fn ($label, $col) => (bool) $client->{$col}, ARRAY_FILTER_USE_BOTH));
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
            || !empty($this->contractFilter)
            || $this->noPic;
    }

    public function getActiveFilterCountProperty(): int
    {
        // All four contract types read as one filter ("Berkontrak pajak"), matching its single chip.
        $contracts = \count($this->contractFilter) === \count(self::CONTRACTS) ? 1 : \count($this->contractFilter);

        return \count($this->statusFilter) + \count($this->typeFilter) + \count($this->pkpFilter) + $contracts + (int) $this->noPic;
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
        $cols = ['36px', 'minmax(220px, 2.2fr)'];                       // logo tile, name
        if ($this->isColumnVisible('contract')) $cols[] = 'minmax(150px, 1fr)';
        if ($this->isColumnVisible('pic'))      $cols[] = 'minmax(140px, 1fr)';
        $cols[] = '100px';                                               // status (always)
        if ($this->isColumnVisible('type'))     $cols[] = '110px';
        if ($this->isColumnVisible('pkp'))      $cols[] = '90px';
        if ($this->isColumnVisible('group'))    $cols[] = 'minmax(100px, .8fr)';
        $cols[] = '150px';                                               // actions (credential button + menu)
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

    /** The client whose credentials are open; the credential manager loads its own data. */
    public function getCredentialClientProperty(): ?Client
    {
        if (!$this->viewingCredentialsClientId) return null;

        return Client::find($this->viewingCredentialsClientId);
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
        if ($this->noPic)                $query->whereNull('pic_id');

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

        [$field, $direction] = explode(':', $this->currentSort());

        return $query->orderBy($field, $direction)->orderBy('id');
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
            'pkp'      => $items->where('pkp_status', 'PKP')->count(),
            'contracted' => $items->filter(fn (Client $client) => self::contractLabels($client) !== [])->count(),
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
            'status' => self::STATUSES[$key] ?? self::NEUTRAL_GROUP,
            'type'   => self::TYPES[$key] ?? self::NEUTRAL_GROUP,
            'pkp'    => self::PKP_STATUSES[$key] ?? self::NEUTRAL_GROUP,
            default  => self::NEUTRAL_GROUP,
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
