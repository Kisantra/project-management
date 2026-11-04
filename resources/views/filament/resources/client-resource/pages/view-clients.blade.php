<x-filament-panels::page>
    <div class="client-management-page space-y-6">
        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                @foreach($this->getTabs() as $tabKey => $tabLabel)
                <button wire:click="$set('activeTab', '{{ $tabKey }}')"
                    @class([ 'whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors'
                    , 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'=> $activeTab === $tabKey,
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-100' => $activeTab !==
                    $tabKey,
                    ])
                    type="button"
                    >
                    {{ $tabLabel }}
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Tab Content --}}
        <div class="relative">
            @switch($activeTab)
            @case('identitas')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)" class="">
                @livewire('client.management.identitas-tab', ['client' => $record])
            </div>
            @break

            @case('relasi')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)" class="">
                @livewire('client.management.relasi-tab', ['client' => $record], key('relasi-tab-'.$record->id))
            </div>
            @break

            @case('kontrak')
            <div x-data class="py-6 transition-opacity duration-300">
                @livewire('client.management.kontrak-tab', ['client' => $record], key('kontrak-tab-'.$record->id))
            </div>
            @break

            @case('dokumen')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)"
                class="">
                @livewire('client.management.dokumen-tab', ['client'=> $record])
            </div>
            @break

            @case('komunikasi')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)" class="">
                @livewire('client.management.komunikasi-tab', ['client' => $record])
            </div>
            @break

            @case('compliance')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)" class="">
                @livewire('client.management.compliance-tab', ['client' => $record], key('compliance-tab-'.$record->id))
            </div>
            @break

            @case('karyawan')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)"
                class="">
                @livewire('client.management.karyawan-tab', ['client' => $record], key('karyawan-tab-'.$record->id))
            </div>
            @break

            @case('tim')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)" class="">
                @livewire('client.management.tim-tab', ['client' => $record], key('tim-tab-'.$record->id))
            </div>
            @break

            @case('projek')
            <div x-data x-init="$el.style.opacity = 0; setTimeout(() => $el.style.opacity = 1, 10)"
                class="">
                @livewire('client.management.projek-tab', ['client' => $record], key('projek-tab-'.$record->id))
            </div>
            @break
            @endswitch
        </div>
    </div>

    <style>
        .client-management-page label + .rounded-lg.bg-gray-50 {
            min-height: 2.5rem;
            border-bottom: 1px solid rgb(209 213 219) !important;
            border-radius: 0 !important;
            background: transparent !important;
            padding: 0.25rem 0 0.75rem !important;
        }

        .dark .client-management-page label + .rounded-lg.bg-gray-50 {
            border-color: rgb(75 85 99) !important;
        }
    </style>
</x-filament-panels::page>
