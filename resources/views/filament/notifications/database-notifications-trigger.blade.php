@php
$isMobile = preg_match('/(android|iphone|ipad|mobile)/i', request()->header('User-Agent'));
@endphp

@if(!$isMobile)
<button type="button"
    title="Notifikasi"
    aria-label="Notifikasi{{ $unreadNotificationsCount > 0 ? ', ' . $unreadNotificationsCount . ' belum dibaca' : '' }}"
    class="relative grid h-9 w-9 place-items-center rounded-full bg-gray-100 text-gray-600 transition-colors duration-150 hover:bg-gray-200 hover:text-gray-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
    </svg>
    @if($unreadNotificationsCount > 0)
        <span class="absolute -end-1 -top-1 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-danger-600 px-1 text-[10.5px] font-extrabold leading-none text-white ring-2 ring-white dark:ring-gray-900">
            {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
        </span>
    @endif
</button>
@endif
