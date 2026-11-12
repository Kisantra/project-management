@props([
    'code' => '403',
    'errorMessage' => 'Forbidden'
])

<x-filament::page class="filament-error-page">
    <div class="relative flex items-center justify-center min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full text-center">
            <!-- Error Code and Message -->
            <div class="mb-8">
                <h1 class="text-8xl font-bold text-gray-300 dark:text-gray-700 mb-2">
                    {{ $code }}
                </h1>
                <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200 mb-4">
                    {{ $errorMessage }}
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-2">
                    Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-500">
                    Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
                </p>
            </div>

            <!-- Information Cards -->
            <div class="grid md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/20 mx-auto mb-2">
                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-gray-800 dark:text-gray-200 text-sm mb-1">Akses Terbatas</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Halaman ini memerlukan izin khusus</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/20 mx-auto mb-2">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-gray-800 dark:text-gray-200 text-sm mb-1">Peran Pengguna</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Periksa role dan permission Anda</p>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/20 mx-auto mb-2">
                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-gray-800 dark:text-gray-200 text-sm mb-1">Bantuan</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Hubungi tim support untuk bantuan</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-center gap-3 mb-8">
                <button 
                    onclick="window.history.back()" 
                    class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </button>
                
                <a 
                    href="{{ filament()->getHomeUrl() }}" 
                    class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors duration-200"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Dashboard
                </a>
            </div>

            <!-- Additional Information -->
            <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3 text-left">
                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-1">
                            Membutuhkan Akses?
                        </h3>
                        <p class="text-sm text-amber-700 dark:text-amber-300">
                            Jika Anda perlu mengakses halaman ini, silakan hubungi administrator sistem atau tim IT untuk meminta izin yang diperlukan.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-500">
                    Error Code: {{ $code }} | 
                    <span class="ml-1">{{ now()->format('Y-m-d H:i:s') }}</span>
                </p>
            </div>
        </div>
    </div>
</x-filament::page>