{{--
    Tax report header: who (with a switcher to other clients that have a report
    for this period), what is still open, and the period stepper.
    Links keep ?jenis=&tab= through kisFollow() (resources/js/kis-ui.js).
--}}
@php
    $client = $overview->client();
    $progress = $overview->progress();
    $around = $overview->neighbours();
    $switcher = $overview->switcher();
@endphp

<header class="kis-trh">
    <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />

    <div class="kis-trh__row">
        <div class="kis-trh__who">
            <span class="kis-trh__tile" aria-hidden="true">
                <span>{{ $client['initials'] }}</span>
                @if ($client['logo'])
                    <img src="{{ $client['logo'] }}" alt="" onerror="this.remove()">
                @endif
            </span>

            <div class="kis-trh__id">
                <div class="kis-trh__title">
                    <h1 class="kis-trh__name" title="{{ $client['name'] }}">{{ $client['name'] }}</h1>

                    @if (count($switcher) > 1)
                        <div class="kis-trh__switch"
                             x-data="{ open: false, q: '', match(name) { return name.toLowerCase().includes(this.q.trim().toLowerCase()) } }"
                             x-on:keydown.escape.window="open = false"
                             x-on:click.outside="open = false">
                            <button type="button" class="kis-trh__switch-btn" x-on:click="open = ! open; if (open) $nextTick(() => $refs.q.focus())"
                                    x-bind:aria-expanded="open" aria-haspopup="true" aria-label="Ganti klien" title="Ganti klien">
                                <x-heroicon-m-chevron-down class="kis-chevron" />
                            </button>
                            <div class="kis-pop kis-trh__pop" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                                <label class="kis-pop__search">
                                    <x-heroicon-m-magnifying-glass class="h-4 w-4 shrink-0" />
                                    <input type="search" x-ref="q" x-model="q" placeholder="Cari klien" aria-label="Cari klien">
                                </label>
                                <ul class="kis-trh__list">
                                    @foreach ($switcher as $item)
                                        <li x-show="match(@js($item['name']))">
                                            @if ($item['current'])
                                                <span class="kis-trh__opt is-current" aria-current="true">
                                                    <span>{{ $item['name'] }}</span>
                                                    <x-heroicon-m-check class="h-4 w-4 shrink-0" />
                                                </span>
                                            @else
                                                <a href="{{ $item['url'] }}" class="kis-trh__opt" x-on:click="kisFollow($event, $el)">{{ $item['name'] }}</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="kis-pop__foot"><b>{{ count($switcher) }}</b> klien punya laporan {{ $overview->periodLabel() }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <p class="kis-trh__meta">
                    @if ($client['npwp'])
                        <span>NPWP {{ $client['npwp'] }}</span>
                    @endif
                    <span>{{ $client['contracts'] ? 'Kontrak ' . implode(', ', $client['contracts']) : 'Belum ada kontrak pajak' }}</span>
                    @if ($progress['total'] > 0)
                        <span @class(['is-bad' => $progress['done'] < $progress['total'], 'is-done' => $progress['done'] === $progress['total']])>
                            @if ($progress['done'] === $progress['total'])
                                Semua jenis sudah lapor
                            @else
                                {{ $progress['done'] }} dari {{ $progress['total'] }} jenis sudah lapor
                            @endif
                        </span>
                    @endif
                </p>
            </div>
        </div>

        <div class="kis-trh__acts">
            <nav class="kis-trh__stepper" aria-label="Pindah periode">
                @foreach (['prev' => 'chevron-left', 'next' => 'chevron-right'] as $dir => $icon)
                    @if ($dir === 'next')
                        <span class="kis-trh__period">{{ $overview->periodLabel() }}</span>
                    @endif
                    @if ($around[$dir])
                        <a href="{{ $around[$dir]['url'] }}" class="kis-trh__step" x-data x-on:click="kisFollow($event, $el)"
                           title="{{ $around[$dir]['label'] }}" aria-label="{{ $dir === 'prev' ? 'Sebelumnya' : 'Berikutnya' }}: {{ $around[$dir]['label'] }}">
                            <x-filament::icon :icon="'heroicon-m-' . $icon" class="h-4 w-4" />
                        </a>
                    @else
                        <span class="kis-trh__step is-off" aria-hidden="true"><x-filament::icon :icon="'heroicon-m-' . $icon" class="h-4 w-4" /></span>
                    @endif
                @endforeach
            </nav>
            @if ($editUrl)
                <a href="{{ $editUrl }}" class="kis-btn kis-btn--outline">
                    <x-heroicon-m-pencil-square class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    Ubah laporan
                </a>
            @endif
        </div>
    </div>
</header>
