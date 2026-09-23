{{-- Page header, drawn by the page itself: title and counts on the left, the view
     toggle, month stepper and the one primary action on the right. --}}
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-[1.625rem]">Kalender</h1>
        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
            <span><span class="font-semibold text-gray-900 dark:text-gray-100">{{ $total }}</span> acara{{ $hasFilters ? ' cocok' : '' }} di {{ $rangeLabel }}</span>
            <span>· <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $done }}</span> selesai</span>
            @if ($late > 0)
                <span class="font-semibold text-danger-600 dark:text-danger-400">· {{ $late }} terlewat</span>
            @endif
        </p>
    </div>

    <div class="flex w-full flex-wrap items-center gap-2.5 sm:w-auto">
        {{-- view toggle --}}
        <div class="flex rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10" role="tablist" aria-label="Tampilan">
            @foreach (['month' => ['Bulan', 'heroicon-m-calendar-days'], 'week' => ['Minggu', 'heroicon-m-view-columns']] as $key => [$label, $icon])
                <button type="button" wire:click="setMode('{{ $key }}')" role="tab" aria-selected="{{ $this->mode === $key ? 'true' : 'false' }}" title="{{ $label }}"
                        class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-[0.8438rem] font-semibold transition-colors {{ $this->mode === $key ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' }}">
                    <x-filament::icon :icon="$icon" class="h-4 w-4" />
                    <span class="hidden sm:inline">{{ $label }}</span>
                </button>
            @endforeach
        </div>

        {{-- month / week stepper --}}
        <div class="flex flex-1 items-center gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10 sm:flex-none">
            <button type="button" wire:click="previous" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-primary-700 dark:text-gray-300 dark:hover:bg-white/5" aria-label="Sebelumnya">
                <x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" />
            </button>
            <span class="min-w-0 flex-1 truncate px-2 text-center text-[0.8438rem] font-semibold text-gray-900 dark:text-gray-100 sm:min-w-[9rem]" wire:loading.class="opacity-50" wire:target="previous,next,today">{{ $rangeLabel }}</span>
            <button type="button" wire:click="next" class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-primary-700 dark:text-gray-300 dark:hover:bg-white/5" aria-label="Berikutnya">
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" />
            </button>
            @unless ($isCurrent)
                <button type="button" wire:click="today" class="rounded-md px-2 py-1.5 text-xs font-semibold text-primary-700 transition-colors hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-500/15">
                    {{ $this->mode === 'week' ? 'Minggu ini' : 'Bulan ini' }}
                </button>
            @endunless
        </div>

        <x-filament::button size="lg" icon="heroicon-m-plus" wire:click="openCreate" class="w-full sm:w-auto">Tambah Acara</x-filament::button>
    </div>
</div>
