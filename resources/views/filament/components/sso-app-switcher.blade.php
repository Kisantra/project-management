{{--
    Menu pindah aplikasi Kisantra (ikon grid di sebelah avatar). Isinya dari
    Kisantra Platform lewat kisantra/sso-laravel; hanya muncul untuk sesi yang
    masuk lewat SSO. Logo aplikasi: tanda K putih di atas warna dasarnya.
--}}
@php($sso = \Kisantra\Sso\Sso::switcher())

@if ($sso)
    @php($here = rtrim((string) config('app.url'), '/'))
    <x-filament::dropdown placement="bottom-end" teleport width="xs">
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-o-squares-2x2"
                color="gray"
                size="lg"
                label="Aplikasi Kisantra"
                tooltip="Aplikasi Kisantra"
            />
        </x-slot>

        <div class="px-3 pb-1 pt-2 text-xs font-semibold text-gray-500 dark:text-gray-400">Aplikasi Kisantra</div>

        <x-filament::dropdown.list>
            @foreach ($sso['apps'] as $app)
                @php($current = rtrim($app['url'], '/') === $here)
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="$current ? url('/') : rtrim($app['url'], '/').'/sso/login'"
                >
                    <span class="flex items-center gap-3">
                        <span class="inline-block h-7 w-7 shrink-0 overflow-hidden rounded-lg" style="background: {{ $app['tile'] }}">
                            <svg viewBox="0 0 160 160" class="block h-full w-full" aria-hidden="true">
                                <path fill="#fff" d="M40 40.1A6.5 6.5 0 0 1 46.5 33.6L62.4 33.6A6.5 6.5 0 0 1 68.9 40.1L68.9 60.6A23.4 23.4 0 0 0 92.3 84L116 84A4 4 0 0 1 120 88L120 122.04A4.4 4.4 0 0 1 115.6 126.44L82.4 126.44A4.4 4.4 0 0 1 78 122.04L78 97A9.1 12.3 0 0 0 68.9 84.7L68.9 101.7A25.5 25.5 0 0 1 49.58 126.44L42.5 126.44A2.5 2.5 0 0 1 40 123.94Z"/>
                                <path fill="{{ $app['arm'] }}" d="M81.33 37.1A3.5 3.5 0 0 1 84.83 33.6L114.12 33.6Q118.12 33.6 118.06 37.6A37.5 37.5 0 0 1 86.33 72.17Q81.33 72.55 81.33 67.55Z"/>
                            </svg>
                        </span>
                        <span class="flex-1 truncate">{{ $app['name'] }}</span>
                        @if ($current)
                            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400">Di sini</span>
                        @endif
                    </span>
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item tag="a" :href="$sso['launcher']" icon="heroicon-m-home">
                Semua aplikasi
            </x-filament::dropdown.list.item>
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
