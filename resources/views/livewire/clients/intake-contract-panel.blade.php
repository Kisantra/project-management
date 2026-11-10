@php
    use App\Livewire\Clients\IntakeContractPanel;

    $messages = [
        'not-configured' => 'Integrasi dengan Client Management belum diatur, jadi kontrak belum bisa ditampilkan di sini. Hubungi admin sistem.',
        'unreachable' => 'Client Management tidak dapat dihubungi. Coba muat ulang, atau buka kontrak langsung dari lead di Client Management.',
        'not-found' => 'Client Management tidak menemukan lead ini. Kemungkinan lead sudah dihapus di sana.',
    ];
@endphp

<div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($error)
                Lampiran tidak tersedia
            @else
                {{ count($documents) }} lampiran dari lead
            @endif
        </p>

        @if ($error !== 'not-configured')
            <x-filament::button
                type="button"
                size="xs"
                color="gray"
                icon="heroicon-m-arrow-path"
                wire:click="reload"
                wire:target="reload,preview"
            >
                Muat ulang
            </x-filament::button>
        @endif
    </div>

    @if ($error)
        <div class="flex gap-2 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/30">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>{{ $messages[$error] ?? $messages['unreachable'] }}</p>
        </div>
    @elseif (empty($documents))
        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada kontrak atau proposal yang dilampirkan di lead ini.</p>
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:divide-white/5 dark:ring-white/10">
            @foreach ($documents as $document)
                @php
                    $isOpen = $preview && $preview['id'] === $document['id'];
                    $meta = array_filter([
                        $document['label'],
                        IntakeContractPanel::formatSize($document['size']),
                        IntakeContractPanel::formatDate($document['uploaded_at']),
                    ]);
                @endphp
                <li wire:key="cm-doc-{{ $document['id'] }}">
                    <button
                        type="button"
                        wire:click="preview({{ $document['id'] }})"
                        wire:loading.attr="disabled"
                        @disabled(! $document['preview_url'])
                        @class([
                            'flex w-full items-start gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-white/5',
                            'bg-primary-50 dark:bg-primary-400/10' => $isOpen,
                        ])
                        aria-pressed="{{ $isOpen ? 'true' : 'false' }}"
                    >
                        <x-filament::icon
                            :icon="str_starts_with((string) $document['mime'], 'image/') ? 'heroicon-o-photo' : 'heroicon-o-document-text'"
                            class="mt-0.5 h-5 w-5 shrink-0 text-gray-400"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $document['name'] }}</span>
                            @if ($meta)
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ implode(' · ', $meta) }}</span>
                            @endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($preview && $preview['preview_url'])
        @php
            $mime = (string) $preview['mime'];
            $isPdf = $mime === 'application/pdf' || str_ends_with(strtolower($preview['name']), '.pdf');
            $isImage = str_starts_with($mime, 'image/');
        @endphp

        <div class="space-y-2" wire:key="cm-preview-{{ $preview['id'] }}-{{ md5($preview['preview_url']) }}">
            <div class="flex items-center justify-between gap-2">
                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $preview['name'] }}</p>
                <div class="flex shrink-0 items-center gap-3">
                    <x-filament::link :href="$preview['preview_url']" target="_blank" size="sm" icon="heroicon-m-arrow-top-right-on-square">
                        Buka penuh
                    </x-filament::link>
                    <x-filament::icon-button
                        icon="heroicon-m-x-mark"
                        color="gray"
                        size="sm"
                        label="Tutup pratinjau"
                        wire:click="closePreview"
                    />
                </div>
            </div>

            @if ($isPdf)
                <iframe
                    src="{{ $preview['preview_url'] }}"
                    title="Pratinjau {{ $preview['name'] }}"
                    class="h-[32rem] w-full rounded-lg bg-gray-100 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10"
                ></iframe>
            @elseif ($isImage)
                <img
                    src="{{ $preview['preview_url'] }}"
                    alt="Pratinjau {{ $preview['name'] }}"
                    class="max-h-[32rem] w-full rounded-lg object-contain ring-1 ring-gray-950/5 dark:ring-white/10"
                >
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Pratinjau tidak tersedia untuk jenis berkas ini. Gunakan "Buka penuh".</p>
            @endif

            <p class="text-xs text-gray-500 dark:text-gray-400">Tautan pratinjau berlaku 5 menit. Bila tidak tampil, tekan Muat ulang.</p>
        </div>
    @endif
</div>
