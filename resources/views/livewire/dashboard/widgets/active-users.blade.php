{{-- Most active users. Emphasis fades with rank: #1 sits on a tinted row with the
     strongest bar, #2 and #3 keep a filled rank badge, the rest are quiet. --}}
@php
    $rankStyle = [
        1 => ['row' => 'bg-gradient-to-r from-amber-50/80 via-primary-50/70 to-primary-50/40 ring-1 ring-amber-200/70 dark:from-amber-500/10 dark:via-primary-500/10 dark:to-transparent dark:ring-amber-400/20', 'badge' => 'bg-primary-600 text-white', 'bar' => 'bg-primary-600', 'avatar' => 'h-11 w-11 ring-2 ring-amber-400 shadow-[0_0_0_4px_rgba(251,191,36,.18)]', 'name' => 'text-[15px] font-semibold text-gray-950 dark:text-white'],
        2 => ['row' => '', 'badge' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300', 'bar' => 'bg-primary-400', 'avatar' => 'h-9 w-9 ring-1 ring-gray-950/5', 'name' => 'text-sm font-semibold text-gray-900 dark:text-gray-100'],
        3 => ['row' => '', 'badge' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300', 'bar' => 'bg-primary-300', 'avatar' => 'h-9 w-9 ring-1 ring-gray-950/5', 'name' => 'text-sm font-semibold text-gray-900 dark:text-gray-100'],
    ];
    $rest = ['row' => '', 'badge' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400', 'bar' => 'bg-gray-300 dark:bg-gray-600', 'avatar' => 'h-8 w-8 ring-1 ring-gray-950/5', 'name' => 'text-sm font-medium text-gray-700 dark:text-gray-300'];
@endphp

<div class="flex h-full flex-col rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800/80 dark:bg-gray-950">
    <style>
        @keyframes au-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @keyframes au-grow { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        .au-row { animation: au-in .45s cubic-bezier(.22, 1, .36, 1) both; }
        .au-bar { transform-origin: left; animation: au-grow .7s cubic-bezier(.22, 1, .36, 1) both; }
        /* Medals pop in; the crown lands tilted on the rim and keeps a slow, gentle sway. */
        @keyframes au-pop { from { opacity: 0; transform: scale(.3) rotate(-30deg); } to { opacity: 1; transform: none; } }
        .au-medal { transform-origin: center; animation: au-pop .5s cubic-bezier(.34, 1.56, .64, 1) both; }
        @keyframes au-crown-in { from { opacity: 0; transform: translateY(-8px) scale(.4) rotate(-60deg); } to { opacity: 1; transform: translateY(0) scale(1) rotate(-24deg); } }
        @keyframes au-crown-sway { 0%, 100% { transform: rotate(-24deg); } 50% { transform: rotate(-16deg) translateY(-1px); } }
        .au-crown { transform-origin: bottom right; animation: au-crown-in .55s cubic-bezier(.34, 1.56, .64, 1) both, au-crown-sway 3.2s ease-in-out .6s infinite; filter: drop-shadow(0 2px 3px rgba(217, 119, 6, .35)); }
        @keyframes au-twinkle { 0%, 100% { opacity: 0; transform: scale(.5); } 50% { opacity: 1; transform: scale(1); } }
        .au-sparkle { animation: au-twinkle 2.4s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) { .au-row, .au-bar, .au-medal, .au-crown { animation: none; } .au-crown { transform: rotate(-24deg); } .au-sparkle { animation: none; opacity: .9; transform: none; } }
    </style>

    <div class="flex flex-col gap-3 px-6 pt-6 sm:px-8 sm:pt-8">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-gray-100">Pengguna paling aktif</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ number_format($total, 0, ',', '.') }} aktivitas tercatat</p>
            </div>
        </div>
        {{-- Range switch --}}
        <div class="inline-flex w-fit rounded-lg bg-gray-100 p-0.5 text-[11px] font-medium dark:bg-gray-800" role="tablist" aria-label="Rentang waktu">
            @foreach ($ranges as $key => $label)
                <button type="button" wire:click="setRange('{{ $key }}')" role="tab" aria-selected="{{ $range === $key ? 'true' : 'false' }}"
                        class="rounded-md px-2.5 py-1 transition-colors duration-150 motion-reduce:transition-none {{ $range === $key ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <ol class="mt-4 flex flex-1 flex-col gap-1 px-3 sm:px-5" wire:loading.class="opacity-50" wire:target="setRange">
        @forelse ($rows as $i => $r)
            @php $s = $rankStyle[$r['rank']] ?? $rest; @endphp
            <li class="au-row rounded-xl px-3 py-2.5 {{ $s['row'] }}" style="animation-delay: {{ $i * 70 }}ms" wire:key="au-{{ $range }}-{{ $r['rank'] }}">
                <div class="flex items-center gap-3">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold tabular-nums {{ $s['badge'] }}">{{ $r['rank'] }}</span>
                    {{-- Medal image by rank, shown at the right edge at avatar size:
                         MVP (1), gold (2–3), silver (4–5), bronze (rest). Rank 1 keeps the crown. --}}
                    @php
                        $medal = match (true) {
                            $r['rank'] === 1 => ['file' => 'mvp',    'size' => 'h-12 w-12', 'alt' => 'MVP'],
                            $r['rank'] <= 3  => ['file' => 'gold',   'size' => 'h-10 w-10', 'alt' => 'Medali emas'],
                            $r['rank'] <= 5  => ['file' => 'silver', 'size' => 'h-9 w-9',   'alt' => 'Medali perak'],
                            default          => ['file' => 'bronze', 'size' => 'h-9 w-9',   'alt' => 'Medali perunggu'],
                        };
                    @endphp
                    <span class="relative shrink-0">
                        @if ($r['avatar'])
                            <img src="{{ $r['avatar'] }}" alt="" class="block rounded-full object-cover {{ $s['avatar'] }}">
                        @else
                            <span class="flex items-center justify-center rounded-full bg-gray-100 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300 {{ $s['avatar'] }}">{{ $r['initials'] }}</span>
                        @endif
                        @if ($r['rank'] === 1)
                            {{-- Crown perched on the rim, tilted; two sparkles twinkle around the winner --}}
                            <span class="au-crown absolute -left-2.5 -top-3.5 select-none text-xl leading-none"
                                  style="animation-delay: {{ $i * 70 + 250 }}ms, 0s" aria-label="Peringkat 1" role="img">👑</span>
                            <span class="au-sparkle absolute -right-2 -top-1 select-none text-[11px] leading-none" style="animation-delay: .2s" aria-hidden="true">✨</span>
                            <span class="au-sparkle absolute -bottom-1 -right-2.5 select-none text-[9px] leading-none" style="animation-delay: 1.3s" aria-hidden="true">✨</span>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate {{ $s['name'] }}">{{ $r['name'] }}</span>
                        <span class="block truncate text-[11px] text-gray-500 dark:text-gray-400">{{ $r['title'] ?: 'terakhir ' . $r['last'] }}</span>
                    </span>
                    <span class="text-right">
                        <span class="block text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($r['total'], 0, ',', '.') }}</span>
                        <span class="block text-[11px] text-gray-400">{{ $r['share'] }}%</span>
                    </span>
                    <img src="{{ asset('images/medal/optimized/' . $medal['file'] . '.webp') }}" alt="{{ $medal['alt'] }}"
                         class="au-medal {{ $medal['size'] }} shrink-0 select-none object-contain drop-shadow-sm"
                         style="animation-delay: {{ $i * 70 + 250 }}ms" title="{{ $medal['alt'] }} · peringkat {{ $r['rank'] }}">
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 pl-0 dark:bg-gray-800" aria-hidden="true">
                    <span class="au-bar block h-full rounded-full {{ $s['bar'] }}" style="width: {{ $r['total'] / $max * 100 }}%; animation-delay: {{ $i * 70 + 120 }}ms"></span>
                </div>
            </li>
        @empty
            <li class="flex flex-1 flex-col items-center justify-center py-12 text-center">
                <p class="text-sm text-gray-500">Belum ada aktivitas dalam rentang ini.</p>
            </li>
        @endforelse
    </ol>

    <div class="mt-4 border-t border-gray-100 px-6 py-3 text-[11px] text-gray-400 sm:px-8 dark:border-gray-800">
        Dihitung dari log aktivitas: proyek, dokumen, laporan pajak, faktur, dan surat.
    </div>
</div>
