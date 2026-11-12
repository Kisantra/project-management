{{-- Skeleton while a lazy dashboard block loads: same card chrome, no spinner. --}}
@php
    $height = match ($section) {
        'compliance', 'agenda' => 'h-[27rem]',
        'flow', 'status' => 'h-[23rem]',
        'workload', 'ontime' => 'h-[25rem]',
        'ppn', 'clients' => 'h-[23rem]',
        default => 'h-56',
    };
@endphp
<div class="kis-card {{ $height }} p-5" aria-busy="true">
    <div class="h-4 w-40 animate-pulse rounded bg-gray-100 dark:bg-white/5"></div>
    <div class="mt-2 h-3 w-64 animate-pulse rounded bg-gray-100 dark:bg-white/5"></div>
    <div class="mt-8 h-[60%] animate-pulse rounded-[11px] bg-gray-50 dark:bg-white/[.03]"></div>
</div>
