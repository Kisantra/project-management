{{-- Skeleton shown while a lazy chart widget loads: same panel chrome, no spinner. --}}
<div class="{{ $height ?? 'h-72' }} rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm dark:border-gray-800/80 dark:bg-gray-950">
    <div class="h-4 w-40 animate-pulse rounded bg-gray-100 dark:bg-gray-800"></div>
    <div class="mt-2 h-3 w-64 animate-pulse rounded bg-gray-100 dark:bg-gray-800"></div>
    <div class="mt-8 flex h-40 items-end gap-2">
        @foreach ([40, 70, 55, 30, 80, 60, 45, 65] as $h)
            <div class="flex-1 animate-pulse rounded-t bg-gray-100 dark:bg-gray-800" style="height: {{ $h }}%"></div>
        @endforeach
    </div>
</div>
