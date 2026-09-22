<div>
    {{-- Content --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
        
        {{-- Left Section: Greeting & Quote --}}
        <div class="flex-1 min-w-0">
            {{-- Date & Time Badge --}}
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium mb-4 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-default">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <span>{{ $this->currentDate }}</span>
                <span class="w-1 h-1 rounded-full bg-gray-400 dark:bg-gray-600"></span>
                <span wire:poll.60s class="tabular-nums">{{ $this->currentTime }}</span>
            </div>

            {{-- Main Greeting with Animated Emoji --}}
            <div class="flex items-center gap-3 mb-4">
                <span class="text-3xl sm:text-4xl select-none" style="animation: bounce 3s ease-in-out infinite;">{{ $this->greetingEmoji }}</span>
                <div>
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                        {{ $this->greeting }}, <span class="text-primary-600 dark:text-primary-400">{{ $this->userName }}</span>!
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Siap untuk hari yang produktif?
                    </p>
                </div>
            </div>

            {{-- Motivational Quote - Single Line --}}
            <div class="mt-4">
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 text-gray-300 dark:text-gray-700 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                    </svg>
                    <p class="text-gray-600 dark:text-gray-400 text-sm italic truncate">
                        {{ $this->motivationalQuote['quote'] }}
                    </p>
                    <span class="text-gray-400 dark:text-gray-600 text-xs font-medium whitespace-nowrap">
                        — {{ $this->motivationalQuote['author'] }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
    </style>
</div>
