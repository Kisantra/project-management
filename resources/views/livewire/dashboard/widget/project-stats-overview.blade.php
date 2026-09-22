{{-- One quiet strip, four cells separated by hairlines. Each cell: label, the number,
     a status line (dot + text) and a muted secondary fact. The whole cell is the link. --}}
@php
    $dot = [
        'success' => 'bg-success-500',
        'danger'  => 'bg-danger-500',
        'warning' => 'bg-warning-500',
        'gray'    => 'bg-gray-300 dark:bg-gray-600',
    ];
    $text = [
        'success' => 'text-success-700 dark:text-success-400',
        'danger'  => 'text-danger-700 dark:text-danger-400',
        'warning' => 'text-warning-700 dark:text-warning-400',
        'gray'    => 'text-gray-600 dark:text-gray-400',
    ];
@endphp

<section aria-label="Ringkasan"
         class="grid grid-cols-1 gap-px overflow-hidden rounded-xl bg-gray-200 shadow-sm ring-1 ring-gray-950/5 sm:grid-cols-2 xl:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
    @foreach ($cards as $card)
        @php $tone = $card['pill']['tone']; @endphp
        <a href="{{ $card['href'] }}"
           class="group relative block bg-white px-5 py-4 transition-colors duration-150 ease-out hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 motion-reduce:transition-none dark:bg-gray-900 dark:hover:bg-white/5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
                <x-filament::icon icon="heroicon-m-chevron-right"
                    class="h-4 w-4 text-gray-400 opacity-0 transition duration-150 ease-out group-hover:opacity-100 group-focus-visible:opacity-100 motion-reduce:transition-none" />
            </div>

            <p class="mt-2 text-[2rem] font-semibold leading-none tracking-tight tabular-nums text-gray-950 dark:text-white">
                {{ number_format($card['value'], 0, ',', '.') }}
            </p>

            <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs leading-4">
                <span class="inline-flex items-center gap-1.5 font-medium {{ $text[$tone] ?? $text['gray'] }}">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dot[$tone] ?? $dot['gray'] }}"></span>
                    {{ $card['pill']['text'] }}
                </span>
                <span class="text-gray-500 dark:text-gray-400">{{ $card['note'] }}</span>
            </p>
        </a>
    @endforeach
</section>
