@php
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp

<div class="w-full px-2">
    <div class="flex items-center gap-x-3">
        <!-- Avatar: inisial di belakang, foto di atasnya; foto yang gagal dimuat dibuang. -->
        <div class="relative h-9 w-9 shrink-0">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-gray-950 text-[11px] font-extrabold text-white dark:bg-white dark:text-gray-950">
                {{ $initials }}
            </span>
            <img
                src="{{ $user->avatar }}"
                alt=""
                class="absolute inset-0 h-9 w-9 rounded-full object-cover"
                onerror="this.remove()"
            >
            <!-- Status Dot -->
            <span class="absolute -bottom-0.5 -end-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-gray-50 dark:ring-gray-950 {{ $user->status === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
        </div>

        <!-- User Info -->
        <div class="min-w-0 flex-1">
            <h3 class="truncate text-[13px] font-bold text-gray-950 dark:text-white">
                {{ $user->name }}
            </h3>
            <p class="truncate text-xs font-semibold text-gray-500 dark:text-gray-400">
                @if($user->position)
                    {{ $user->position }}
                @elseif($user->department)
                    {{ $user->department->name }}
                @else
                    {{ $user->email }}
                @endif
            </p>
        </div>
    </div>
</div>
