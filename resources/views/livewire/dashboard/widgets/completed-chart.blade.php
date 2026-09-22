{{-- Projects completed per month, last 12 months. Click a bar to pin it (the caption
     follows), hover for the value; the dashed line is the 12-month average. One hue:
     bars above average are stronger, below average lighter, the active one full. --}}
<div x-data="{
        b: @js($months),
        avg: {{ $average }},
        last: {{ count($months) - 1 }},
        pinned: null,
        get i() { return this.pinned ?? this.last },
        get a() { return this.b[this.i] },
        get prev() { return this.i > 0 ? this.b[this.i - 1] : null },
        pct(n, d) { return d ? Math.round((n - d) / d * 100) : null },
     }"
     class="flex h-full flex-col rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800/80 dark:bg-gray-950">

    <style>
        @keyframes cc-grow { from { transform: scaleY(0); } to { transform: scaleY(1); } }
        .cc-bar { transform-origin: bottom; animation: cc-grow .6s cubic-bezier(.22, 1, .36, 1) both; }
        @media (prefers-reduced-motion: reduce) { .cc-bar { animation: none; } }
    </style>

    {{-- Header: no icon, just the words --}}
    <div class="flex flex-col gap-3 px-6 pt-6 sm:flex-row sm:items-start sm:justify-between sm:px-8 sm:pt-8">
        <div>
            <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-gray-100">Proyek selesai</h2>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ number_format($sum, 0, ',', '.') }} selesai dalam 12 bulan terakhir &middot; {{ number_format($allTime, 0, ',', '.') }} sepanjang waktu
            </p>
        </div>
        <span class="text-[11px] font-medium text-gray-400">{{ $months[0]['title'] }} – {{ end($months)['title'] }}</span>
    </div>

    {{-- Caption follows the active (pinned or latest) month --}}
    <div class="mt-5 flex min-h-8 flex-wrap items-baseline gap-x-2 gap-y-1 px-6 text-xs text-gray-500 sm:px-8 dark:text-gray-400" aria-live="polite">
        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="a.title"></span>
        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="a.total + ' proyek'"></span>
        <template x-if="prev && pct(a.total, prev.total) !== null">
            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                  :class="pct(a.total, prev.total) >= 0 ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400'"
                  x-text="(pct(a.total, prev.total) >= 0 ? '+' : '') + pct(a.total, prev.total) + '% dari ' + prev.label"></span>
        </template>
        <template x-if="prev && pct(a.total, prev.total) === null && a.total > 0">
            <span class="rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-400" x-text="'+' + a.total + ' dari ' + prev.label"></span>
        </template>
        <template x-if="avg > 0">
            <span x-text="(a.total >= avg ? 'di atas' : 'di bawah') + ' rata-rata ' + avg + ' (' + (a.total - avg >= 0 ? '+' : '') + (a.total - avg) + ')'"></span>
        </template>
        <template x-if="pinned !== null">
            <button type="button" @click="pinned = null" class="font-semibold text-primary-600 underline decoration-primary-600/40 hover:decoration-primary-600 dark:text-primary-400" x-text="'kembali ke ' + b[last].label"></button>
        </template>
    </div>

    {{-- Plot: grows with the panel so the chart never leaves dead space under it --}}
    <div class="flex flex-1 flex-col px-6 pb-2 pt-7 sm:px-8">
        <div class="flex min-h-[14rem] flex-1 gap-3">
            <div class="relative w-7 shrink-0 text-right" aria-hidden="true">
                @foreach ($gridlines as $k => $g)
                    <span class="absolute right-0 text-[11px] font-medium text-gray-400 {{ $k === 0 ? '' : ($loop->last ? '-translate-y-full' : '-translate-y-1/2') }}"
                          style="top: {{ (1 - $g / $ceiling) * 100 }}%">{{ $g }}</span>
                @endforeach
            </div>

            <div class="relative min-w-0 flex-1">
                @foreach ($gridlines as $g)
                    <span class="absolute inset-x-0 border-t border-gray-100 dark:border-gray-800" style="top: {{ (1 - $g / $ceiling) * 100 }}%" aria-hidden="true"></span>
                @endforeach

                @if ($average > 0)
                    <span class="absolute inset-x-0 z-10 border-t border-dashed border-primary-500/50" style="top: {{ (1 - $average / $ceiling) * 100 }}%" aria-hidden="true">
                        <span class="absolute -top-2.5 right-0 rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">rata-rata {{ $average }}</span>
                    </span>
                @endif

                <div class="absolute inset-0 flex items-stretch gap-1.5">
                    @foreach ($months as $i => $m)
                        <button type="button"
                                @click="pinned = (pinned === {{ $i }} ? null : {{ $i }})"
                                :aria-pressed="i === {{ $i }}"
                                :class="i === {{ $i }} ? 'bg-primary-50 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[.03]'"
                                class="group/bar relative flex flex-1 items-end rounded-md transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none"
                                aria-label="{{ $m['title'] }}: {{ $m['total'] }} proyek selesai">
                            @if ($m['total'] > 0)
                                <span class="cc-bar mx-1 block w-full rounded-t-[3px] transition-colors duration-150"
                                      :class="i === {{ $i }} ? 'bg-primary-600' : '{{ $m['total'] >= $average ? 'bg-primary-400 group-hover/bar:bg-primary-500' : 'bg-primary-200 group-hover/bar:bg-primary-300 dark:bg-primary-500/30 dark:group-hover/bar:bg-primary-500/50' }}'"
                                      style="height: {{ $m['total'] / $ceiling * 100 }}%; animation-delay: {{ $i * 45 }}ms"></span>
                            @else
                                <span class="mx-1 block h-0.5 w-full rounded bg-gray-200 dark:bg-gray-700"></span>
                            @endif

                            <span class="pointer-events-none absolute inset-x-0 bottom-full z-20 mx-auto mb-1 w-max rounded-md px-1.5 py-0.5 text-[11px] font-semibold shadow-sm transition-opacity duration-150"
                                  :class="i === {{ $i }} ? 'bg-primary-600 text-white opacity-100' : 'bg-gray-900 text-white opacity-0 group-hover/bar:opacity-100 dark:bg-white dark:text-gray-900'">
                                {{ $m['total'] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-2 flex gap-1.5 pl-10">
            @foreach ($months as $i => $m)
                <span class="flex-1 text-center text-[11px] transition-colors"
                      :class="i === {{ $i }} ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-medium text-gray-400'"
                      title="{{ $m['title'] }}">{{ $m['label'] }}</span>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-gray-100 px-6 py-3 text-[11px] sm:px-8 dark:border-gray-800">
        <span class="text-gray-400">
            @if ($bestIndex !== null)
                Bulan terbaik: <span class="font-medium text-gray-600 dark:text-gray-300">{{ $months[$bestIndex]['title'] }}</span> ({{ $months[$bestIndex]['total'] }} proyek)
            @else
                Belum ada proyek selesai dalam 12 bulan terakhir.
            @endif
        </span>
        <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('index') }}?status[0]=completed"
           class="inline-flex items-center gap-1 font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            Lihat proyek selesai
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3" />
        </a>
    </div>
</div>
