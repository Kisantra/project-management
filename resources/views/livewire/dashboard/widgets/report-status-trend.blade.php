{{-- Reported vs not-yet-reported obligations per masa pajak, stacked bars. "Sudah lapor" is
     the primary hue at the base; the remainder is neutral until the deadline has passed,
     then it turns to the danger hue. Click a bar to pin it; the caption follows. --}}
<div x-data="{
        b: @js($periods),
        pinned: null,
        latest: {{ $latestIndex }},
        get i() { return this.pinned ?? this.latest },
        get a() { return this.b[this.i] },
     }"
     class="flex flex-col rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800/80 dark:bg-gray-950">

    <style>
        @keyframes rs-grow { from { transform: scaleY(0); } to { transform: scaleY(1); } }
        .rs-bar { transform-origin: bottom; animation: rs-grow .6s cubic-bezier(.22, 1, .36, 1) both; }
        @media (prefers-reduced-motion: reduce) { .rs-bar { animation: none; } }
    </style>

    {{-- Header --}}
    <div class="flex flex-col gap-3 px-6 pt-6 sm:flex-row sm:items-start sm:justify-between sm:px-8 sm:pt-8">
        <div>
            <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-gray-100">Status pelaporan pajak</h2>
            <p class="mt-0.5 text-xs text-gray-500">
                12 masa terakhir &middot;
                @if ($sumPct !== null)
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($sumDone, 0, ',', '.') }}</span> dari {{ number_format($sumTotal, 0, ',', '.') }} kewajiban sudah lapor ({{ $sumPct }}%)
                    @if ($sumLate) &middot; <span class="font-medium text-danger-600 dark:text-danger-400">{{ number_format($sumLate, 0, ',', '.') }} lewat tenggat</span> @endif
                @else
                    belum ada kewajiban tercatat
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-x-3 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-500"></span>Sudah lapor</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-gray-300 dark:bg-gray-600"></span>Belum lapor</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-danger-400"></span>Lewat tenggat</span>
            </div>
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-[11px] font-medium dark:bg-gray-800" role="tablist" aria-label="Jenis pajak">
                @foreach ($types as $key => $t)
                    <button type="button" wire:click="setType('{{ $key }}')" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}"
                            class="rounded-md px-2.5 py-1 transition-colors duration-150 motion-reduce:transition-none {{ $type === $key ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Caption follows the pinned (or latest) period --}}
    <div class="mt-5 flex min-h-8 flex-wrap items-baseline gap-x-3 gap-y-1 px-6 text-xs text-gray-500 sm:px-8 dark:text-gray-400" aria-live="polite">
        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="'Masa ' + a.title"></span>
        <template x-if="a.total > 0">
            <span><span class="font-semibold text-gray-900 dark:text-gray-100" x-text="a.done + ' dari ' + a.total"></span> sudah lapor (<span x-text="a.pct + '%'"></span>)</span>
        </template>
        <template x-if="a.total === 0">
            <span>tidak ada kewajiban tercatat</span>
        </template>
        <template x-if="a.pending > 0 && a.late">
            <span class="rounded-full bg-danger-50 px-2 py-0.5 text-[11px] font-semibold text-danger-700 dark:bg-danger-500/10 dark:text-danger-400" x-text="a.pending + ' lewat tenggat ' + a.deadline"></span>
        </template>
        <template x-if="a.pending > 0 && !a.late">
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300" x-text="a.pending + ' belum lapor · tenggat ' + a.deadline"></span>
        </template>
        <template x-if="a.total > 0 && a.pending === 0">
            <span class="rounded-full bg-success-50 px-2 py-0.5 text-[11px] font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-400">semua sudah lapor</span>
        </template>
        <template x-if="pinned !== null">
            <button type="button" @click="pinned = null" class="font-semibold text-primary-600 underline decoration-primary-600/40 hover:decoration-primary-600 dark:text-primary-400">kembali ke masa terbaru</button>
        </template>
    </div>

    {{-- Plot --}}
    <div class="px-6 pb-2 pt-7 sm:px-8" wire:loading.class="opacity-60" wire:target="setType">
        <div class="flex gap-3">
            <div class="relative h-56 w-8 shrink-0 text-right" aria-hidden="true">
                @foreach ($gridlines as $k => $g)
                    <span class="absolute right-0 text-[11px] font-medium tabular-nums text-gray-400 {{ $k === 0 ? '' : ($loop->last ? '-translate-y-full' : '-translate-y-1/2') }}"
                          style="top: {{ (1 - $g / $ceiling) * 100 }}%">{{ $g }}</span>
                @endforeach
            </div>

            <div class="relative h-56 min-w-0 flex-1">
                @foreach ($gridlines as $g)
                    <span class="absolute inset-x-0 border-t border-gray-100 dark:border-gray-800" style="top: {{ (1 - $g / $ceiling) * 100 }}%" aria-hidden="true"></span>
                @endforeach

                <div class="absolute inset-0 flex items-stretch gap-1.5">
                    @foreach ($periods as $i => $p)
                        @php
                            $doneH = $p['done'] / $ceiling * 100;
                            $pendH = $p['pending'] / $ceiling * 100;
                            $pendClass = $p['late'] ? 'bg-danger-400 group-hover/bar:bg-danger-500' : 'bg-gray-300 group-hover/bar:bg-gray-400 dark:bg-gray-600 dark:group-hover/bar:bg-gray-500';
                        @endphp
                        <button type="button"
                                @click="pinned = (pinned === {{ $i }} ? null : {{ $i }})"
                                :aria-pressed="i === {{ $i }}"
                                :class="i === {{ $i }} ? 'bg-primary-50 dark:bg-primary-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[.03]'"
                                class="group/bar relative flex flex-1 flex-col-reverse items-stretch rounded-md transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 motion-reduce:transition-none"
                                aria-label="Masa {{ $p['title'] }}: {{ $p['done'] }} sudah lapor, {{ $p['pending'] }} belum{{ $p['late'] ? ', lewat tenggat' : '' }}">
                            @if ($p['done'] > 0)
                                <span class="rs-bar mx-1 block {{ $p['pending'] > 0 ? 'rounded-t-[2px]' : 'rounded-t-[3px]' }} transition-colors duration-150"
                                      :class="i === {{ $i }} ? 'bg-primary-600' : 'bg-primary-500 group-hover/bar:bg-primary-600'"
                                      style="height: {{ $doneH }}%; animation-delay: {{ $i * 40 }}ms"></span>
                            @endif
                            @if ($p['pending'] > 0)
                                <span class="rs-bar mx-1 block rounded-t-[3px] {{ $p['done'] > 0 ? 'mb-0.5' : '' }} {{ $pendClass }} transition-colors duration-150"
                                      style="height: {{ $pendH }}%; animation-delay: {{ $i * 40 + 60 }}ms"></span>
                            @endif
                            @if ($p['total'] === 0)
                                <span class="mx-1 block h-0.5 rounded bg-gray-200 dark:bg-gray-700"></span>
                            @endif

                            {{-- pct label: pinned for the active bar, on hover for the rest --}}
                            <span class="pointer-events-none absolute inset-x-0 bottom-full z-20 mx-auto mb-1 w-max rounded-md px-1.5 py-0.5 text-[11px] font-semibold shadow-sm transition-opacity duration-150"
                                  :class="i === {{ $i }} ? 'bg-primary-600 text-white opacity-100' : 'bg-gray-900 text-white opacity-0 group-hover/bar:opacity-100 dark:bg-white dark:text-gray-900'">
                                {{ $p['pct'] === null ? '–' : $p['pct'] . '%' }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-2 flex gap-1.5 pl-11">
            @foreach ($periods as $i => $p)
                <span class="flex-1 text-center text-[11px] transition-colors"
                      :class="i === {{ $i }} ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-medium text-gray-400'"
                      title="Masa {{ $p['title'] }}">{{ $p['label'] }}</span>
            @endforeach
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between border-t border-gray-100 px-6 py-3 text-[11px] sm:px-8 dark:border-gray-800">
        <span class="text-gray-400">Satu kewajiban = satu klien aktif per masa per jenis pajak. Tenggat PPN tanggal 20, PPh tanggal 10 bulan berikutnya.</span>
        <a href="{{ \App\Filament\Pages\DashboardTaxReport::getUrl() }}"
           class="inline-flex items-center gap-1 font-medium text-gray-500 transition-colors hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
            Dashboard laporan pajak
            <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3" />
        </a>
    </div>
</div>
