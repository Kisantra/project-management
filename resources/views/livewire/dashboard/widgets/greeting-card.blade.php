<div>
    {{-- Greeting only: the first line sits on a soft primary highlight, the prompt below it is quiet gray. --}}
    <div class="py-2">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">
            <span class="rounded-lg bg-primary-50 px-2 py-1 text-primary-700 [box-decoration-break:clone] dark:bg-primary-500/15 dark:text-primary-300">{{ $this->greeting }}, {{ $this->userName }}!</span>
            <span class="ml-1 select-none align-middle text-[0.85em]" aria-hidden="true">👋</span>
        </h1>
        <p class="mt-2 text-3xl font-medium leading-tight tracking-tight text-gray-400 sm:text-4xl dark:text-gray-500">
            Siap untuk hari yang produktif?
        </p>
    </div>
</div>
