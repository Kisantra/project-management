{{-- Requested-document checklist, rendered inside the Filament form via Forms\Components\View.
     Variables come from viewData(): $letter, $key, $field, $editable, $canManage, $docOptions --}}
<div class="fi-fo-field-wrp">
    <label class="mb-1 block text-sm font-medium leading-6 text-gray-950 dark:text-white">
        {{ $field['label'] }}
        @if ($field['required']) <sup class="text-danger-600 dark:text-danger-400 font-medium">*</sup> @endif
    </label>
    @php $docs = $letter->checklist($key); $received = $docs->where('is_received', true)->count(); @endphp
    <div class="overflow-visible rounded-lg bg-white ring-1 ring-gray-300 dark:bg-gray-900 dark:ring-white/10">
        {{-- Items --}}
        @if ($docs->isEmpty())
            <p class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400">Belum ada dokumen. Cari dan pilih di bawah.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($docs as $i => $doc)
                    <li class="group px-3 py-2" wire:key="doc-{{ $doc->id }}" x-data="{ editNote: false }">
                        <div class="flex items-center gap-2.5">
                            <input type="checkbox" @checked($doc->is_received) @disabled(! $canManage)
                                   wire:click="toggleReceived({{ $doc->id }})"
                                   title="{{ $doc->is_received ? 'Diterima ' . $doc->received_at?->locale('id')->translatedFormat('d M Y') . ($doc->receiver ? ' oleh ' . $doc->receiver->name : '') : 'Tandai sudah diterima' }}"
                                   class="h-4 w-4 shrink-0 rounded border-gray-300 text-success-600 focus:ring-success-500 dark:border-gray-600 dark:bg-gray-800">
                            <span class="w-5 shrink-0 text-xs tabular-nums text-gray-400">{{ $i + 1 }}.</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm {{ $doc->is_received ? 'text-gray-400 line-through dark:text-gray-500' : 'text-gray-900 dark:text-gray-100' }}" title="{{ $doc->name }}">
                                    {{ $doc->name }}
                                    @if (! $doc->type)
                                        <span class="ml-1 rounded bg-gray-100 px-1 py-px text-[10px] font-medium text-gray-500 dark:bg-white/10 dark:text-gray-400">kustom</span>
                                    @endif
                                </span>
                                @if ($editable && $canManage)
                                    <span x-show="!editNote">
                                        @if (filled($doc->note))
                                            <button type="button" x-on:click="editNote = true; $nextTick(() => $refs.note.focus())"
                                                    class="text-xs text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">{{ $doc->note }}</button>
                                        @else
                                            <button type="button" x-on:click="editNote = true; $nextTick(() => $refs.note.focus())"
                                                    class="text-[11px] text-gray-400 opacity-0 transition hover:text-primary-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:text-primary-400">+ keterangan (mis. Jan–Des 2024)</button>
                                        @endif
                                    </span>
                                    <input x-show="editNote" x-cloak x-ref="note" type="text" value="{{ $doc->note }}" placeholder="mis. Januari–Desember 2024"
                                           x-on:keydown.enter.prevent="$wire.updateDocumentNote({{ $doc->id }}, $el.value); editNote = false"
                                           x-on:keydown.escape="editNote = false"
                                           x-on:blur="$wire.updateDocumentNote({{ $doc->id }}, $el.value); editNote = false"
                                           class="mt-1 block w-full rounded border-0 bg-gray-50 px-2 py-1 text-xs text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10">
                                @elseif (filled($doc->note))
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $doc->note }}</span>
                                @endif
                            </span>
                            @if ($doc->is_received)
                                <span class="shrink-0 text-[11px] font-medium text-success-600 dark:text-success-400">diterima</span>
                            @endif
                            @if ($editable && $canManage)
                                <button type="button" wire:click="removeDocument({{ $doc->id }})" aria-label="Hapus {{ $doc->name }}"
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-danger-600 focus-visible:opacity-100 group-hover:opacity-100 dark:hover:bg-white/5">
                                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Picker: searchable, grouped, keyboard-driven; free text becomes a custom item --}}
        @if ($editable && $canManage)
            <div class="relative border-t border-gray-100 p-2 dark:border-white/5"
                 x-data="docPicker(@js($docOptions), @js($key))"
                 x-on:keydown.escape.window="close()"
                 x-on:letter-doc-removed.window="unflag($event.detail.typeId)"
                 wire:ignore.self>
                <div class="relative">
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input type="text" x-ref="input" x-model="query"
                           x-on:focus="open = true" x-on:click="open = true"
                           x-on:input="open = true; active = 0"
                           x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
                           x-on:keydown.enter.prevent="choose()"
                           placeholder="Cari atau ketik nama dokumen…" autocomplete="off"
                           role="combobox" :aria-expanded="open" aria-autocomplete="list"
                           class="block w-full rounded-md border-0 bg-gray-50 py-1.5 pl-8 pr-3 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:bg-gray-900">
                </div>

                <div x-show="open" x-cloak x-on:click.outside="close()"
                     x-transition:enter="transition duration-100 ease-out motion-reduce:transition-none"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute inset-x-2 z-20 mt-1 max-h-72 overflow-y-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10"
                     role="listbox">
                    <template x-for="group in filtered" :key="group.group">
                        <div>
                            <div class="sticky top-0 bg-white/95 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400 backdrop-blur dark:bg-gray-800/95 dark:text-gray-500" x-text="group.group"></div>
                            <template x-for="item in group.items" :key="item.id">
                                <button type="button" role="option"
                                        :aria-selected="flat[active]?.id === item.id"
                                        :disabled="item.used"
                                        x-on:mouseenter="active = indexOf(item)"
                                        x-on:click="pick(item)"
                                        :class="{
                                            'bg-primary-50 dark:bg-primary-400/10': flat[active]?.id === item.id && !item.used,
                                            'cursor-default': item.used,
                                        }"
                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-800 dark:text-gray-100">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center">
                                        <template x-if="item.used"><x-heroicon-m-check class="h-4 w-4 text-success-500" /></template>
                                    </span>
                                    <span class="min-w-0 flex-1 truncate" :class="item.used && 'text-gray-400 dark:text-gray-500'" x-text="item.name"></span>
                                    <span x-show="item.used" class="shrink-0 text-[11px] text-gray-400">sudah ada</span>
                                </button>
                            </template>
                        </div>
                    </template>

                    <template x-if="query.trim().length > 1 && !exactMatch">
                        <button type="button" x-on:click="pickCustom()" x-on:mouseenter="active = flat.length"
                                :class="active === flat.length && 'bg-primary-50 dark:bg-primary-400/10'"
                                class="flex w-full items-center gap-2 border-t border-gray-100 px-3 py-2 text-left text-sm text-gray-800 dark:border-white/5 dark:text-gray-100">
                            <x-heroicon-m-plus class="h-4 w-4 shrink-0 text-primary-500" />
                            <span class="min-w-0 truncate">Tambah "<span class="font-medium" x-text="query.trim()"></span>" sebagai dokumen kustom</span>
                        </button>
                    </template>

                    <template x-if="flat.length === 0 && query.trim().length <= 1">
                        <p class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400">Tidak ada jenis dokumen. Ketik nama untuk menambah dokumen kustom.</p>
                    </template>
                </div>
            </div>
        @endif
    </div>
    @if ($docs->isNotEmpty())
        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            {{ $received }}/{{ $docs->count() }} diterima.
            @if ($editable) Klik nama untuk menambah keterangan, mis. periode. @else Centang saat dokumen masuk. @endif
        </p>
    @endif
</div>
