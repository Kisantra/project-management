{{-- KPI row: four stat tiles. Left: label, 30-day value, "dari N (30 hari sebelumnya)".
     Top-right: signed delta pill. Right: a 30-point area sparkline whose hue follows the
     delta direction (same signal as the pill, never the only one). The tile is the link. --}}
@php
    $tone = [
        'up'   => ['pill' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400', 'stroke' => 'rgb(var(--success-500))', 'icon' => 'heroicon-m-arrow-trending-up'],
        'down' => ['pill' => 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400',   'stroke' => 'rgb(var(--danger-500))',  'icon' => 'heroicon-m-arrow-trending-down'],
        'flat' => ['pill' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',               'stroke' => 'rgb(var(--gray-400))',    'icon' => 'heroicon-m-minus'],
    ];
    $w = 120; $h = 40;
@endphp

<section aria-label="Ringkasan 30 hari" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($tiles as $i => $tile)
        @php
            $t = $tone[$tile['delta']['tone']];
            $spark = \App\Livewire\Dashboard\Widget\ProjectStatsOverview::sparkline($tile['series'], $w, $h);
            $gradId = 'spark-' . $i;
            $n = count($tile['series']);
            $colW = $w / max($n - 1, 1);
        @endphp
        <a href="{{ $tile['href'] }}"
           class="group flex items-stretch justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition duration-150 ease-out hover:shadow-md hover:ring-gray-950/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-white/20">
            <div class="flex min-w-0 flex-col justify-between">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $tile['label'] }}</span>
                <span class="mt-2 text-[1.75rem] font-semibold leading-none tracking-tight text-gray-950 dark:text-white">
                    {{ number_format($tile['value'], 0, ',', '.') }}
                </span>
                <span class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    dari {{ number_format($tile['previous'], 0, ',', '.') }} <span class="text-gray-400 dark:text-gray-500">(30 hari sebelumnya)</span>
                </span>
            </div>

            <div class="flex shrink-0 flex-col items-end justify-between">
                <span class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-medium {{ $t['pill'] }}"
                      title="Dibanding 30 hari sebelumnya">
                    <x-filament::icon :icon="$t['icon']" class="h-3 w-3" />
                    {{ $tile['delta']['text'] }}
                </span>

                <svg viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" class="mt-2 overflow-visible"
                     role="img" aria-label="{{ $tile['label'] }} per hari, 30 hari terakhir">
                    <defs>
                        <linearGradient id="{{ $gradId }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="{{ $t['stroke'] }}" stop-opacity="0.22" />
                            <stop offset="1" stop-color="{{ $t['stroke'] }}" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <path d="{{ $spark['area'] }}" fill="url(#{{ $gradId }})" />
                    <path d="{{ $spark['line'] }}" fill="none" stroke="{{ $t['stroke'] }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="{{ $spark['last']['x'] }}" cy="{{ $spark['last']['y'] }}" r="2.5" class="fill-white dark:fill-gray-900" stroke="{{ $t['stroke'] }}" stroke-width="1.75" />
                    {{-- Hover layer: one hit column per day with a native tooltip --}}
                    @foreach ($tile['series'] as $d => $v)
                        <rect x="{{ round($d * $colW - $colW / 2, 2) }}" y="0" width="{{ round($colW, 2) }}" height="{{ $h }}" fill="transparent">
                            <title>{{ $tile['days'][$d] }} · {{ $v }} {{ $tile['unit'] }}</title>
                        </rect>
                    @endforeach
                </svg>
            </div>
        </a>
    @endforeach
</section>
