{{--
    Klien Masuk list: the tabs as one summary strip (each cell sets activeTab),
    then the table. The CM explanation is in the page subheading.
--}}
@php
    use App\Models\ClientIntake;

    $q = $this->getQueueSummary();
    $rp = fn (int $value) => 'Rp ' . number_format($value, 0, ',', '.');
    $active = $this->activeTab ?? 'menunggu';
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="kis-intake">
        <div class="kis-intake__strip" role="tablist" aria-label="Status klien masuk">
            <button type="button" role="tab" wire:click="$set('activeTab', 'menunggu')" aria-selected="{{ $active === 'menunggu' ? 'true' : 'false' }}"
                    @class(['kis-intake__cell', 'is-on' => $active === 'menunggu'])>
                <span class="kis-intake__top">
                    Menunggu dicek
                    @if ($q['menunggu']['overdue'] > 0)
                        <x-kis.badge tone="red" dot size="sm">{{ $q['menunggu']['overdue'] }} lewat {{ ClientIntake::OVERDUE_DAYS }} hari</x-kis.badge>
                    @endif
                </span>
                <b class="kis-intake__value">{{ $q['menunggu']['count'] }}</b>
                <span class="kis-intake__context">
                    @if ($q['menunggu']['count'] > 0)
                        Nilai deal <b>{{ $rp($q['menunggu']['value']) }}</b> · tertua
                        <span @class(['is-bad' => $q['menunggu']['oldest'] > ClientIntake::OVERDUE_DAYS])>{{ $q['menunggu']['oldest'] === 0 ? 'hari ini' : $q['menunggu']['oldest'] . ' hari' }}</span>
                    @else
                        Tidak ada yang menunggu
                    @endif
                </span>
            </button>
            <button type="button" role="tab" wire:click="$set('activeTab', 'selesai')" aria-selected="{{ $active === 'selesai' ? 'true' : 'false' }}"
                    @class(['kis-intake__cell', 'is-on' => $active === 'selesai'])>
                <span class="kis-intake__top">Selesai</span>
                <b class="kis-intake__value">{{ $q['selesai']['count'] }}</b>
                <span class="kis-intake__context">
                    {{ $q['selesai']['thisMonth'] }} diproses bulan ini
                    @if ($q['selesai']['failed'] > 0)
                        · <span class="is-bad">{{ $q['selesai']['failed'] }} kabar ke CM gagal</span>
                    @endif
                </span>
            </button>
            <button type="button" role="tab" wire:click="$set('activeTab', 'batal')" aria-selected="{{ $active === 'batal' ? 'true' : 'false' }}"
                    @class(['kis-intake__cell', 'is-on' => $active === 'batal'])>
                <span class="kis-intake__top">Batal di Client Management</span>
                <b class="kis-intake__value">{{ $q['batal']['count'] }}</b>
                <span class="kis-intake__context">
                    @if ($q['batal']['processed'] > 0)
                        <span class="is-bad">{{ $q['batal']['processed'] }} kliennya telanjur dibuat</span>
                    @else
                        Belum ada yang telanjur diproses
                    @endif
                </span>
            </button>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
