{{-- Quick start-time picks under the time pickers; one click sets the hour. --}}
<div class="-mt-1 flex flex-wrap items-center gap-x-3 gap-y-2" x-data="{ path: @js($path) }">
    <p class="flex flex-wrap gap-1.5" role="group" aria-label="Jam cepat">
        @foreach (['08:00', '09:00', '10:00', '13:00', '14:00', '15:00', '16:00'] as $slot)
            <button type="button"
                    x-on:click="$wire.set(path + '.start_time', '{{ $slot }}')"
                    :aria-pressed="$wire.get(path + '.start_time') === '{{ $slot }}'"
                    :class="$wire.get(path + '.start_time') === '{{ $slot }}' ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-400 hover:text-primary-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300'"
                    class="rounded-md border px-2 py-1 text-[11px] font-semibold tabular-nums transition-colors">
                {{ str_replace(':', '.', $slot) }}
            </button>
        @endforeach
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400">Jam dipakai untuk mengurutkan acara di hari yang sama dan menghitung pengingat.</p>
</div>
