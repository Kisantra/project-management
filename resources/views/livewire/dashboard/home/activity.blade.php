{{-- Latest activity, two columns. --}}
<x-kis.card title="Aktivitas terbaru" :subtitle="$mine ? 'Aktivitas Anda' : 'Seluruh tim'">
    <x-slot name="aside">
        <a href="{{ route('filament.admin.resources.user-activities.index') }}" class="kis-card__link">Log lengkap →</a>
    </x-slot>

    @if (empty($data))
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas.</p>
    @else
        <ul class="grid gap-x-7 lg:grid-cols-2">
            @foreach ($data as $item)
                <li class="grid grid-cols-[26px_1fr_auto] items-start gap-2.5 border-t border-gray-100 py-2 text-[13px] dark:border-white/5 lg:[&:nth-child(-n+2)]:border-0 [&:first-child]:border-0">
                    <span class="grid h-[26px] w-[26px] place-items-center rounded-full bg-gray-100 text-[10.5px] font-extrabold text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $item['initials'] }}</span>
                    <span class="line-clamp-2 font-semibold text-gray-700 dark:text-gray-300" title="{{ $item['text'] }}">{{ $item['text'] }}</span>
                    <span class="whitespace-nowrap text-xs font-semibold text-gray-400">{{ $item['when'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-kis.card>
