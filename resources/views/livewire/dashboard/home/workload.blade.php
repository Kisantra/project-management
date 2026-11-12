{{-- Open projects per PIC split into on time and overdue, plus open daily tasks. --}}
<x-kis.card title="Beban kerja tim" :subtitle="$mine ? 'Proyek berjalan dan tugas harian Anda' : 'Proyek berjalan per PIC, dan tugas harian yang masih terbuka'" class="h-full">
    <x-slot name="aside">
        <div class="kis-legend">
            <span><i style="background: var(--kis-ramp-b3)"></i>Sesuai jadwal</span>
            <span><i style="background: var(--kis-bar-red)"></i>Lewat tenggat</span>
        </div>
    </x-slot>

    @if (empty($data['rows']))
        <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada proyek berjalan.</p>
    @else
        <div class="grid gap-2.5">
            <div class="grid grid-cols-[28px_9.5rem_1fr_3.5rem_3.5rem] items-center gap-3 text-[11px] font-extrabold uppercase tracking-[0.06em] text-gray-500 dark:text-gray-400">
                <span></span><span>PIC</span><span>Proyek berjalan</span><span class="text-right">Lewat</span><span class="text-right">Tugas</span>
            </div>
            @foreach ($data['rows'] as $row)
                @php $onTime = $row['open'] - $row['late']; @endphp
                <div class="grid grid-cols-[28px_9.5rem_1fr_3.5rem_3.5rem] items-center gap-3 text-[13px]">
                    <span @class(['grid h-7 w-7 place-items-center rounded-full text-[11px] font-extrabold',
                        'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $row['unassigned'],
                        'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300' => ! $row['unassigned']])>{{ $row['initials'] }}</span>
                    <span @class(['truncate font-bold', 'italic text-danger-700 dark:text-danger-300' => $row['unassigned'], 'text-gray-950 dark:text-white' => ! $row['unassigned']]) title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                    <span class="flex h-2.5 gap-0.5 overflow-hidden rounded-full" style="width: {{ $row['open'] / $data['max'] * 100 }}%">
                        @if ($onTime > 0)
                            <i class="block h-full" style="flex: {{ $onTime }}; background: var(--kis-ramp-b3)" data-kis-tip="{{ json_encode(['t' => $row['name'], 'r' => [['var(--kis-ramp-b3)', 'Sesuai jadwal', $onTime . ' proyek']]]) }}"></i>
                        @endif
                        @if ($row['late'] > 0)
                            <i class="block h-full" style="flex: {{ $row['late'] }}; background: var(--kis-bar-red)" data-kis-tip="{{ json_encode(['t' => $row['name'], 'r' => [['var(--kis-bar-red)', 'Lewat tenggat', $row['late'] . ' proyek']]]) }}"></i>
                        @endif
                    </span>
                    <span @class(['text-right font-extrabold tabular-nums', 'text-danger-700 dark:text-danger-300' => $row['late'] > 0, 'text-gray-400' => $row['late'] === 0])>{{ $row['late'] ?: '–' }}</span>
                    <span class="text-right font-bold tabular-nums text-gray-500 dark:text-gray-400">{{ $row['tasks'] ?? '–' }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <x-slot name="footer">
        <span>Diurutkan dari beban terbesar.</span>
        <a href="{{ route('filament.admin.pages.daily-task-dashboard') }}" class="kis-card__link">Lihat Tugas Harian →</a>
    </x-slot>
</x-kis.card>
