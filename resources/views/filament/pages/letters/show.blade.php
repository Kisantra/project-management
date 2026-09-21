<x-filament-panels::page>
    @include('letters.document-style')
    @php
        $letter = $record;
        $editable = $letter->isEditable();
        $fields = $this->fields;
        $missing = $this->missing;
        $slot = $letter->currentSignature();
        $canSign = $this->canSign;
        $canManage = $this->canManage;
        $inputClass = 'block w-full rounded-lg border-0 bg-white py-2 px-3 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:bg-gray-900 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500';
    @endphp

    {{-- ================= HEADER STRIP ================= --}}
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$letter->status_color" size="lg">{{ $letter->status_label }}</x-filament::badge>
        <span class="inline-flex h-8 items-center gap-1.5 rounded-full bg-white px-3 text-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
            <span class="text-gray-500 dark:text-gray-400">Nomor</span>
            <span class="font-mono font-semibold text-gray-900 dark:text-white">{{ $letter->display_number }}</span>
        </span>
        <span class="inline-flex h-8 items-center gap-1.5 rounded-full bg-white px-3 text-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
            <span class="text-gray-500 dark:text-gray-400">Klien</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ $letter->client?->name }}</span>
        </span>
        <span class="inline-flex h-8 items-center gap-1.5 rounded-full bg-white px-3 text-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
            <span class="text-gray-500 dark:text-gray-400">Template</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ $letter->template->name }}</span>
        </span>
        @if ($slot)
            <span class="inline-flex h-8 items-center gap-1.5 rounded-full bg-warning-50 px-3 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300">
                <x-heroicon-m-clock class="h-3.5 w-3.5" /> Menunggu {{ $slot->label }}
            </span>
        @endif

        <div class="ml-auto flex flex-wrap items-center gap-2">
            @if ($letter->status === \App\Models\Letter::STATUS_SIGNED)
                <x-filament::button size="sm" icon="heroicon-m-arrow-down-tray" wire:click="downloadPdf">Unduh PDF</x-filament::button>
            @elseif (! $editable)
                <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="downloadPdf">Pratinjau PDF</x-filament::button>
            @endif
            @if ($editable && $canManage)
                <x-filament::button size="sm" icon="heroicon-m-paper-airplane" wire:click="submit" wire:loading.attr="disabled"
                                    :disabled="count($missing) > 0"
                                    :tooltip="count($missing) ? 'Lengkapi: ' . implode(', ', $missing) : null">
                    Ajukan untuk tanda tangan
                </x-filament::button>
            @endif
            @if ($canManage && $letter->status !== \App\Models\Letter::STATUS_SIGNED && $letter->status !== \App\Models\Letter::STATUS_CANCELED)
                <x-filament::button size="sm" color="gray" x-on:click="$dispatch('open-modal', { id: 'batal-surat' })">Batalkan</x-filament::button>
            @endif
            @if ($canManage && $letter->canBeDeleted())
                <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-trash" x-on:click="$dispatch('open-modal', { id: 'hapus-surat' })">Hapus</x-filament::button>
            @endif
        </div>
    </div>

    {{-- ================= APPROVAL BOX ================= --}}
    @if ($letter->isAwaitingSignature() || $letter->signatures->isNotEmpty())
        <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <ol class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    @foreach ($letter->signatures as $sig)
                        <li class="flex items-center gap-2 text-sm">
                            <span @class([
                                'flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold ring-1',
                                'bg-success-500 text-white ring-success-500' => $sig->isSigned(),
                                'bg-danger-500 text-white ring-danger-500' => $sig->status === 'rejected',
                                'bg-warning-50 text-warning-700 ring-warning-500' => $sig->isPending() && $slot?->id === $sig->id,
                                'bg-white text-gray-400 ring-gray-300 dark:bg-gray-900 dark:ring-gray-600' => $sig->isPending() && $slot?->id !== $sig->id,
                            ])>
                                @if ($sig->isSigned()) <x-heroicon-m-check class="h-3.5 w-3.5" /> @elseif ($sig->status === 'rejected') <x-heroicon-m-x-mark class="h-3.5 w-3.5" /> @else {{ $sig->order }} @endif
                            </span>
                            <span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $sig->label }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    @if ($sig->isSigned()) {{ $sig->user?->name }} · {{ $sig->signed_at?->locale('id')->translatedFormat('d M Y H:i') }}
                                    @elseif ($sig->status === 'rejected') ditolak oleh {{ $sig->user?->name }}
                                    @elseif ($sig->user) menunggu {{ $sig->user->name }}
                                    @else menunggu @endif
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                @if ($canSign)
                    <div class="flex shrink-0 items-center gap-2">
                        <x-filament::button color="danger" outlined size="sm" x-on:click="$dispatch('open-modal', { id: 'tolak-surat' })">Tolak</x-filament::button>
                        <x-filament::button color="success" size="sm" icon="heroicon-m-pencil-square"
                                            x-on:click="$dispatch('open-modal', { id: 'ttd-surat' })">
                            Tandatangani sebagai {{ $slot?->label }}
                        </x-filament::button>
                    </div>
                @endif
            </div>
            @if ($letter->status === \App\Models\Letter::STATUS_REJECTED)
                @php $rej = $letter->signatures->firstWhere('status', 'rejected'); @endphp
                <div class="border-t border-danger-100 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-400/20 dark:bg-danger-400/10 dark:text-danger-200 sm:px-5">
                    <span class="font-semibold">Alasan penolakan:</span> {{ $rej?->note }} — perbaiki isian lalu ajukan kembali.
                </div>
            @endif
        </section>
    @endif

    {{-- ================= EDITOR + PREVIEW ================= --}}
    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Fields --}}
        <aside class="space-y-4 lg:col-span-4">
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/5">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Isi poin-poinnya</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-gray-600 dark:bg-white/5 dark:text-gray-400">{{ count($fields) }} isian</span>
                </div>
                <div class="p-4">
                    {{ $this->form }}
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="font-medium text-gray-900 dark:text-white">
                    Blok tanda tangan:
                    <span class="font-normal text-gray-600 dark:text-gray-300">{{ collect($letter->template->signers)->pluck('label')->join(' · ') ?: 'tidak ada' }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($editable)
                        Draft tersimpan otomatis. Setelah diajukan, nomor surat diberikan dan isian terkunci.
                    @else
                        Isian terkunci karena surat sudah diajukan. Tanda tangan dilakukan dari kotak persetujuan di atas.
                    @endif
                </p>
                @if ($editable && count($missing))
                    <p class="mt-2 text-xs text-warning-700 dark:text-warning-300">Belum lengkap: {{ implode(', ', $missing) }}.</p>
                @endif
            </div>
        </aside>

        {{-- Paper preview --}}
        <section class="lg:col-span-8">
            <div class="mx-auto w-[210mm] max-w-full overflow-hidden rounded-sm shadow-lg ring-1 ring-gray-950/10 [&_.ltr-page]:mx-auto" wire:loading.class="opacity-60" wire:target="data">
                {!! $this->preview !!}
            </div>
        </section>
    </div>

    @once
        <script>
            // Plain factory on window: x-data evaluates it when Alpine initialises the
            // element, so it does not depend on the alpine:init timing.
            window.docPicker = (groups, fieldKey) => ({
                    groups, fieldKey,
                    query: '', open: false, active: 0,
                    get filtered() {
                        const q = this.query.trim().toLowerCase();
                        return this.groups
                            .map(g => ({ group: g.group, items: g.items.filter(i => !q || i.name.toLowerCase().includes(q)) }))
                            .filter(g => g.items.length);
                    },
                    get flat() { return this.filtered.flatMap(g => g.items); },
                    get exactMatch() {
                        const q = this.query.trim().toLowerCase();
                        return this.flat.some(i => i.name.toLowerCase() === q);
                    },
                    indexOf(item) { return this.flat.findIndex(i => i.id === item.id); },
                    move(step) {
                        const max = this.flat.length + (this.query.trim().length > 1 && !this.exactMatch ? 1 : 0);
                        if (!max) return;
                        this.open = true;
                        this.active = (this.active + step + max) % max;
                    },
                    choose() {
                        const item = this.flat[this.active];
                        if (item) { if (!item.used) this.pick(item); return; }
                        if (this.query.trim().length > 1) this.pickCustom();
                    },
                    pick(item) {
                        if (item.used) return;
                        item.used = true;
                        this.$wire.addDocument(this.fieldKey, item.id);
                        this.reset();
                    },
                    pickCustom() {
                        const name = this.query.trim();
                        if (name.length < 2) return;
                        this.$wire.addCustomDocument(this.fieldKey, name);
                        this.reset();
                    },
                    reset() { this.query = ''; this.active = 0; this.open = false; this.$refs.input?.focus(); },
                    close() { this.open = false; },
                    unflag(id) {
                        if (!id) return;
                        this.groups.forEach(g => g.items.forEach(i => { if (i.id === id) i.used = false; }));
                    },
            });
        </script>
    @endonce

    {{-- ================= SIGN MODAL ================= --}}
    @if ($canSign)
        <x-filament::modal id="ttd-surat" width="md" icon="heroicon-o-pencil-square" icon-color="success">
            <x-slot name="heading">Tandatangani sebagai {{ $slot?->label }}</x-slot>
            <x-slot name="description">Tanda tangan elektronik atas nama Anda akan dicetak pada surat ini.</x-slot>

            <dl class="divide-y divide-gray-100 rounded-lg bg-gray-50 text-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-white/5 dark:ring-white/10">
                <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Nomor</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $letter->display_number }}</dd></div>
                <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Perihal</dt><dd class="text-gray-900 dark:text-white">{{ $letter->subject }}</dd></div>
                <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Klien</dt><dd class="text-gray-900 dark:text-white">{{ $letter->client?->name }}</dd></div>
                <div class="flex gap-3 px-3 py-2"><dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Sebagai</dt><dd class="text-gray-900 dark:text-white">{{ $slot?->label }} &middot; {{ auth()->user()->name }}</dd></div>
            </dl>
            @php $mySignature = auth()->user()->signature_path; @endphp
            <div class="mt-4 rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                <div class="flex items-center justify-between px-3 py-2">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Gambar tanda tangan Anda</span>
                    @if ($mySignature)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($mySignature) }}" alt="Tanda tangan" class="h-10 w-auto">
                    @else
                        <span class="text-xs text-warning-700 dark:text-warning-300">Belum ada, surat akan memakai tanda tangan elektronik teks</span>
                    @endif
                </div>
                <div x-data="{ open: {{ $mySignature ? 'false' : 'true' }} }" class="border-t border-gray-100 dark:border-white/5">
                    <button type="button" x-on:click="open = !open" class="w-full px-3 py-1.5 text-left text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" x-text="open ? 'Tutup' : '{{ $mySignature ? 'Ganti gambar' : 'Unggah gambar tanda tangan' }}'"></button>
                    <div x-show="open" x-cloak class="space-y-2 px-3 pb-3">
                        {{ $this->signatureForm }}
                        <x-filament::button size="xs" color="gray" wire:click="saveSignature" wire:loading.attr="disabled">Simpan tanda tangan</x-filament::button>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Nama, jabatan, dan gambar tanda tangan akan tercetak di blok tanda tangan; tanpa gambar, dipakai keterangan tanda tangan elektronik. Tindakan ini tercatat di log aktivitas dan tidak bisa dibatalkan.
                @php $after = $letter->signatures->where('order', '>', $slot?->order ?? 0)->first(); @endphp
                @if ($after) Setelah ini surat diteruskan ke {{ $after->label }}. @else Setelah ini surat dinyatakan selesai. @endif
            </p>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'ttd-surat' })">Batal</x-filament::button>
                <x-filament::button color="success" icon="heroicon-m-pencil-square" wire:click="sign" wire:loading.attr="disabled" wire:target="sign">
                    Tandatangani
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif

    {{-- ================= DELETE MODAL (unnumbered letters only) ================= --}}
    @if ($canManage && $letter->canBeDeleted())
        <x-filament::modal id="hapus-surat" width="md" icon="heroicon-o-trash" icon-color="danger">
            <x-slot name="heading">Hapus draft</x-slot>
            <x-slot name="description">{{ $letter->subject }} &middot; {{ $letter->client?->name }}</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Draft ini belum bernomor, jadi tidak ada urutan yang terpakai. Isian, daftar dokumen yang diminta, dan slot
                tanda tangannya ikut terhapus dan tidak bisa dikembalikan. Jejak penghapusan tetap tercatat di aktivitas klien.
            </p>
            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'hapus-surat' })">Kembali</x-filament::button>
                <x-filament::button color="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Hapus draft</x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif

    {{-- ================= CANCEL MODAL ================= --}}
    @if ($canManage && $letter->status !== \App\Models\Letter::STATUS_SIGNED && $letter->status !== \App\Models\Letter::STATUS_CANCELED)
        <x-filament::modal id="batal-surat" width="md" icon="heroicon-o-x-circle" icon-color="danger">
            <x-slot name="heading">Batalkan surat</x-slot>
            <x-slot name="description">
                {{ $letter->display_number }} &middot; {{ $letter->subject }}
            </x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Surat akan berstatus dibatalkan dan tidak bisa diajukan lagi.
                @if ($letter->number) Nomor {{ $letter->number }} tetap tercatat dan tidak dipakai surat lain. @endif
                Bila hanya perlu diperbaiki, gunakan "Tolak" dari penandatangan atau biarkan sebagai draft.
            </p>
            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'batal-surat' })">Kembali</x-filament::button>
                <x-filament::button color="danger" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">Batalkan surat</x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif

    {{-- ================= REJECT MODAL ================= --}}
    <x-filament::modal id="tolak-surat" width="md">
        <x-slot name="heading">Tolak dan kembalikan surat</x-slot>
        <x-slot name="description">Pembuat surat akan menerima alasan ini dan bisa memperbaiki lalu mengajukan ulang.</x-slot>
        <textarea rows="4" wire:model="rejectNote" class="{{ $inputClass }}" placeholder="Tulis alasan penolakan…"></textarea>
        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'tolak-surat' })">Batal</x-filament::button>
            <x-filament::button color="danger" wire:click="reject" wire:loading.attr="disabled">Tolak surat</x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
