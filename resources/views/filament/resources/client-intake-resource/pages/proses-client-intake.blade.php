<x-filament-panels::page>
    @php($intake = $this->getIntake())

    @include('filament.resources.client-intake-resource.partials.cm-callout')

    {{-- Dibatalkan di CM: sebelum diproses tidak bisa diproses; sesudahnya klien & proyek tetap ada. --}}
    @if ($intake->isWithdrawn())
        <div role="alert"
             class="flex gap-3 rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:ring-danger-400/30">
            <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
            <div class="space-y-1 text-sm">
                <p class="font-semibold text-danger-700 dark:text-danger-300">
                    Deal dibatalkan di Client Management
                    @if (filled($intake->withdrawn_detail))
                        — {{ $intake->withdrawn_detail }}
                    @endif
                </p>
                <p class="text-gray-700 dark:text-gray-300">
                    Dibatalkan {{ $intake->withdrawn_at->locale('id')->translatedFormat('j M Y H.i') }}.
                    @if (filled($intake->withdrawn_note))
                        Catatan: {{ $intake->withdrawn_note }}
                    @endif
                </p>
                @if ($intake->isCompleted())
                    <p class="text-gray-600 dark:text-gray-400">
                        Klien dan proyeknya sudah dibuat dan tidak dihapus. Tinjau apakah proyek perlu dihentikan atau status klien diubah.
                    </p>
                @endif
            </div>
        </div>
    @endif

    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.data'"
        wire:submit="process"
    >
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
