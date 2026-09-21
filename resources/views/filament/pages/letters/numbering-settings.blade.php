<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3">
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\Letters\Index::getUrl()">Kembali</x-filament::button>
            <x-filament::button type="submit" wire:loading.attr="disabled">Simpan pengaturan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
