{{--
    Main dashboard (docs/mockups/dashboard-redesign.html). Top to bottom:
    what needs doing today (brief, action cards, agenda), how the office is
    doing (tax compliance, project flow and status, team, trends), what changed
    (activity). Blocks below the cards load lazily and reload when the scope changes.
--}}
@php
    use App\Livewire\Dashboard\Home\Section;
@endphp

<x-filament-panels::page class="w-full">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
        <div class="min-w-0">
            <div class="text-[12.5px] font-bold text-gray-500 dark:text-gray-400">{{ $dateLabel }}</div>
            <h1 class="mb-1.5 mt-0.5 text-[1.75rem] font-extrabold leading-tight tracking-tight text-gray-950 dark:text-white">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="max-w-3xl text-[14.5px] text-gray-700 dark:text-gray-300">
                @if ($home['brief']['lead'])
                    {{ $home['brief']['lead'] }}@foreach ($home['brief']['items'] as $item)<b @class(['font-extrabold', 'text-danger-700 dark:text-danger-300' => $item['bad'], 'text-gray-950 dark:text-white' => ! $item['bad']])>{{ $item['text'] }}</b>{{ $item['tail'] }}{{ $loop->last ? '.' : ($loop->remaining === 1 ? ', dan ' : ', ') }}@endforeach
                @else
                    Tidak ada yang mendesak hari ini.
                @endif
            </p>
        </div>
        <div class="kis-scope" role="group" aria-label="Lingkup dashboard">
            <button type="button" wire:click="setScope('kantor')" aria-pressed="{{ $this->scope === 'kantor' ? 'true' : 'false' }}">Kantor</button>
            <button type="button" wire:click="setScope('saya')" aria-pressed="{{ $this->scope === 'saya' ? 'true' : 'false' }}">Saya</button>
        </div>
    </div>

    {{-- 1. Needs attention --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="setScope">
        @foreach ($home['cards'] as $card)
            @php $sum = max(1, array_sum(array_column($card['bar'], 0))); @endphp
            <a href="{{ $card['url'] }}" wire:key="card-{{ $card['key'] }}-{{ $this->scope }}" @class(['kis-card kis-act', 'is-alert' => $card['alert']])>
                <div class="kis-act__label">
                    <span class="kis-act__icon"><x-filament::icon :icon="$card['icon']" class="h-4 w-4" /></span>
                    {{ $card['label'] }}
                </div>
                <span class="kis-act__go" aria-hidden="true">→</span>
                <div class="kis-act__value"><b>{{ number_format($card['value'], 0, ',', '.') }}</b><span>{{ $card['unit'] }}</span></div>
                <div class="kis-act__sub" title="{{ $card['sub'] }}">{{ $card['sub'] }}</div>
                <div class="kis-act__bar">
                    @foreach ($card['bar'] as [$n, $color])
                        @if ($n > 0)
                            <i style="width: {{ $n / $sum * 100 }}%; background: {{ $color }}"></i>
                        @endif
                    @endforeach
                </div>
            </a>
        @endforeach
    </div>

    {{-- 2. Tax compliance + projects per status (status takes the flow card's place without tax access) --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
        @if ($canSeeTax)
            <div class="lg:col-span-8">@livewire(Section::class, ['section' => 'compliance', 'scope' => $this->scope], key('compliance-' . $this->scope))</div>
        @else
            <div class="lg:col-span-8">@livewire(Section::class, ['section' => 'flow', 'scope' => $this->scope], key('flow-' . $this->scope))</div>
        @endif
        <div class="lg:col-span-4">@livewire(Section::class, ['section' => 'status', 'scope' => $this->scope], key('status-' . $this->scope))</div>
    </div>

    {{-- 3. Project flow + the next 14 days --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
        @if ($canSeeTax)
            <div class="lg:col-span-8">@livewire(Section::class, ['section' => 'flow', 'scope' => $this->scope], key('flow-' . $this->scope))</div>
        @endif
        <div @class(['lg:col-span-4' => $canSeeTax, 'lg:col-span-12' => ! $canSeeTax])>@livewire(Section::class, ['section' => 'agenda', 'scope' => $this->scope], key('agenda-' . $this->scope))</div>
    </div>

    {{-- 4. Team + on-time trend --}}
    @if ($canSeeTeam || $canSeeTax)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            @if ($canSeeTeam)
                <div @class(['lg:col-span-7' => $canSeeTax, 'lg:col-span-12' => ! $canSeeTax])>@livewire(Section::class, ['section' => 'workload', 'scope' => $this->scope], key('workload-' . $this->scope))</div>
            @endif
            @if ($canSeeTax)
                <div @class(['lg:col-span-5' => $canSeeTeam, 'lg:col-span-12' => ! $canSeeTeam])>@livewire(Section::class, ['section' => 'ontime', 'scope' => $this->scope], key('ontime-' . $this->scope))</div>
            @endif
        </div>
    @endif

    {{-- 5. Value: PPN position + biggest clients --}}
    @if ($canSeeTax)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="lg:col-span-7">@livewire(Section::class, ['section' => 'ppn', 'scope' => $this->scope], key('ppn-' . $this->scope))</div>
            <div class="lg:col-span-5">@livewire(Section::class, ['section' => 'clients', 'scope' => $this->scope], key('clients-' . $this->scope))</div>
        </div>
    @endif

    {{-- 6. What changed --}}
    @livewire(Section::class, ['section' => 'activity', 'scope' => $this->scope], key('activity-' . $this->scope))
</x-filament-panels::page>
