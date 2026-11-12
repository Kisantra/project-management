@php
    use App\Livewire\ProjectStatuses\StatusManager;
    use App\Support\Ui\Tone;
    $categoryLabels = StatusManager::CATEGORY_LABELS;
    $shapes = StatusManager::SHAPES;
    $formSwatch = Tone::swatch($form_tone);
@endphp

<div class="ps-root"
     x-data="{ formOpen: false }"
     x-on:open-status-form.window="formOpen = true"
     x-on:close-status-form.window="formOpen = false">

    {{-- ============== HEADER ============== --}}
    <div class="ps-header">
        <div>
            <p class="ps-subtitle">Status yang tersedia untuk semua proyek. Seret untuk mengubah urutan. Status sistem bisa diganti nama, nada, dan ikonnya, tetapi tidak bisa dihapus.</p>
        </div>
    </div>

    {{-- ============== CATEGORY SECTIONS ============== --}}
    <div class="ps-categories">
        @foreach ($categoryLabels as $catKey => $catLabel)
            <section class="ps-cat">
                <header class="ps-cat-head">
                    <span class="ps-cat-label">{{ $catLabel }}</span>
                    <button type="button" wire:click="startCreate('{{ $catKey }}')" class="ps-cat-add" title="Tambah status">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    </button>
                </header>

                <div class="ps-rows"
                     x-data="{}"
                     x-init="
                        const root = $el;
                        Sortable.create(root, {
                            handle: '.ps-drag',
                            animation: 150,
                            ghostClass: 'ps-row-ghost',
                            onEnd: () => {
                                const ids = Array.from(root.children).map(el => el.dataset.id).filter(Boolean);
                                $wire.reorder('{{ $catKey }}', ids);
                            }
                        });
                     ">
                    @foreach ($grouped[$catKey] as $status)
                        <div class="ps-row" data-id="{{ $status->id }}" wire:key="status-{{ $status->id }}">
                            <button type="button" class="ps-drag" title="Seret untuk mengubah urutan">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                            </button>

                            <span class="ps-row-icon">
                                @include('livewire.projects.partials.status-shape', ['shape' => $status->shape, 'color' => $status->tone_swatch['color'], 'tone' => \App\Support\Ui\Tone::forProjectStatus($status), 'size' => 18])
                            </span>

                            <span class="ps-row-label">{{ $status->label }}</span>

                            @if ($status->is_system)
                                <span class="ps-locked">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                    Sistem
                                </span>
                            @endif

                            <div x-data="{ open: false }" class="ps-actions">
                                <button type="button" @click="open = !open" class="ps-act-btn" aria-label="Actions">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak class="ps-act-menu">
                                    <button type="button" wire:click="startEdit({{ $status->id }})" @click="open = false" class="ps-act-item">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4z"/></svg>
                                        Ubah
                                    </button>
                                    @if (! $status->is_system)
                                        <button type="button"
                                                wire:click="delete({{ $status->id }})"
                                                wire:confirm="Hapus status '{{ $status->label }}'?"
                                                @click="open = false"
                                                class="ps-act-item ps-act-danger">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                            Hapus
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($grouped[$catKey]->isEmpty())
                    <button type="button" wire:click="startCreate('{{ $catKey }}')" class="ps-empty-add">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        Tambah status
                    </button>
                @else
                    <button type="button" wire:click="startCreate('{{ $catKey }}')" class="ps-row-add">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        Tambah status
                    </button>
                @endif
            </section>
        @endforeach
    </div>

    {{-- ============== ADD/EDIT MODAL ============== --}}
    <div x-show="formOpen" x-cloak class="ps-modal-backdrop" @click.self="$wire.cancel()">
        <div class="ps-modal" @keydown.escape.window="$wire.cancel()">
            <header class="ps-modal-head">
                <h3>{{ $editingId ? 'Ubah status' : 'Status baru' }}</h3>
                <button type="button" wire:click="cancel" class="ps-modal-close" aria-label="Tutup">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </header>

            <div class="ps-modal-body">
                {{-- Label --}}
                <div class="ps-field">
                    <label class="ps-field-label">Nama</label>
                    <input type="text" wire:model.live.debounce.300ms="form_label" maxlength="60" class="ps-input" placeholder="mis. Menunggu Klien">
                    @error('form_label') <span class="ps-field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Category (locked for system statuses) --}}
                <div class="ps-field">
                    <label class="ps-field-label">Kategori</label>
                    @php
                        $catLocked = $editingId && optional(\App\Models\ProjectStatus::find($editingId))->is_system;
                    @endphp
                    <select wire:model="editingCategory" class="ps-input" {{ $catLocked ? 'disabled' : '' }}>
                        @foreach ($categoryLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($catLocked)
                        <span class="ps-field-hint">Kategori status sistem tidak bisa diubah.</span>
                    @endif
                    @error('editingCategory') <span class="ps-field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Tone: one of the design-system voices instead of a free colour --}}
                <div class="ps-field">
                    <span class="ps-field-label" id="ps-tone-label">Nada</span>
                    <div class="ps-tones" role="radiogroup" aria-labelledby="ps-tone-label">
                        @foreach (Tone::LABELS as $tone => $meta)
                            <label @class(['ps-tone', 'is-active' => $form_tone === $tone])>
                                <input type="radio" class="sr-only" name="ps-tone" value="{{ $tone }}" wire:model.live="form_tone">
                                <x-kis.badge :tone="$tone" dot>{{ $meta['label'] }}</x-kis.badge>
                                <span class="ps-tone-hint">{{ $meta['hint'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form_tone') <span class="ps-field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Shape --}}
                <div class="ps-field">
                    <label class="ps-field-label">Ikon</label>
                    <div class="ps-shapes">
                        @foreach ($shapes as $shape)
                            <button type="button"
                                    wire:click="$set('form_shape', '{{ $shape }}')"
                                    class="ps-shape {{ $form_shape === $shape ? 'is-active' : '' }}"
                                    title="{{ $shape }}">
                                @include('livewire.projects.partials.status-shape', ['shape' => $shape, 'color' => $formSwatch['color'], 'tone' => $form_tone, 'size' => 20])
                            </button>
                        @endforeach
                    </div>
                    @error('form_shape') <span class="ps-field-error">{{ $message }}</span> @enderror
                </div>

                {{-- Preview --}}
                <div class="ps-field">
                    <label class="ps-field-label">Pratinjau</label>
                    <div class="ps-preview">
                        <span class="ps-row-icon">
                            @include('livewire.projects.partials.status-shape', ['shape' => $form_shape, 'color' => $formSwatch['color'], 'tone' => $form_tone, 'size' => 18])
                        </span>
                        <span class="ps-preview-label">{{ $form_label ?: '—' }}</span>
                        <x-kis.badge :tone="$form_tone" dot>{{ $form_label ?: 'Status' }}</x-kis.badge>
                    </div>
                </div>
            </div>

            <footer class="ps-modal-foot">
                <button type="button" wire:click="cancel" class="ps-btn ps-btn-ghost">Batal</button>
                <button type="button" wire:click="save" class="ps-btn ps-btn-primary">
                    {{ $editingId ? 'Simpan' : 'Buat status' }}
                </button>
            </footer>
        </div>
    </div>

    {{-- ============== STYLES ============== --}}
    @once
        <style>
            [x-cloak] { display: none !important; }

            .ps-root {
                --ps-ink: rgb(var(--gray-950));
                --ps-muted: rgb(var(--gray-500));
                --ps-subtle: rgb(var(--gray-400));
                --ps-line: rgb(var(--gray-200));
                --ps-line-soft: rgb(var(--gray-200));
                --ps-bg: #ffffff;
                --ps-bg-soft: rgb(var(--gray-50));
                --ps-bg-hover: rgb(var(--gray-100));
                --ps-accent: rgb(var(--primary-600));
                --ps-accent-ink: rgb(var(--primary-700));
                --ps-accent-soft: rgb(var(--primary-50));
                --ps-danger: rgb(var(--danger-700));
                --ps-danger-soft: rgb(var(--danger-50));
                font-family: inherit;
                color: var(--ps-ink);
                font-size: 13.5px;
            }

            .dark .ps-root {
                --ps-ink: #ffffff;
                --ps-muted: rgb(var(--gray-400));
                --ps-subtle: rgb(var(--gray-500));
                --ps-line: rgba(255, 255, 255, .1);
                --ps-line-soft: rgba(255, 255, 255, .08);
                --ps-bg: rgb(var(--gray-900));
                --ps-bg-soft: rgba(255, 255, 255, .03);
                --ps-bg-hover: rgba(255, 255, 255, .05);
                --ps-accent: rgb(var(--primary-400));
                --ps-accent-ink: rgb(var(--primary-300));
                --ps-accent-soft: rgba(var(--primary-500), .14);
                --ps-danger: rgb(var(--danger-300));
                --ps-danger-soft: rgba(var(--danger-500), .14);
            }

            .ps-header { margin-bottom: 24px; }
            .ps-title { font-size: 20px; font-weight: 700; color: var(--ps-ink); margin: 0 0 4px; }
            .ps-subtitle { font-size: 13px; color: var(--ps-muted); margin: 0; max-width: 640px; line-height: 1.5; }

            /* ─── Category sections ─── */
            .ps-categories {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 16px;
            }
            .ps-cat {
                background: var(--ps-bg);
                border-radius: 12px;
                padding: 14px;
                box-shadow: 0 0 0 1px var(--ps-line-soft);
            }
            .ps-cat-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 0 4px;
            }
            .ps-cat-label {
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: var(--ps-muted);
            }
            .ps-cat-add {
                width: 26px; height: 26px;
                display: flex; align-items: center; justify-content: center;
                background: transparent;
                border: 0;
                border-radius: 6px;
                color: var(--ps-muted);
                cursor: pointer;
                transition: background .12s, color .12s;
            }
            .ps-cat-add:hover { background: var(--ps-accent-soft); color: var(--ps-accent-ink); }

            /* ─── Rows ─── */
            .ps-rows {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-height: 4px;
            }
            .ps-row {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 10px;
                background: var(--ps-bg);
                border: 1px solid var(--ps-line);
                border-radius: 8px;
                transition: background .12s;
            }
            .ps-row:hover { background: var(--ps-bg-hover); }
            .ps-row-ghost { opacity: .4; }

            .ps-drag {
                background: transparent;
                border: 0;
                padding: 0 2px;
                cursor: grab;
                color: var(--ps-subtle);
                display: flex; align-items: center;
            }
            .ps-drag:hover { color: var(--ps-ink); }
            .ps-drag:active { cursor: grabbing; }
            .ps-row-icon { display: inline-flex; align-items: center; flex-shrink: 0; }
            .ps-row-label {
                flex: 1;
                font-size: 12.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .ps-locked {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 7px;
                background: var(--ps-bg-soft);
                color: var(--ps-muted);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                border-radius: 99px;
            }

            /* ─── Row actions menu ─── */
            .ps-actions { position: relative; }
            .ps-act-btn {
                width: 24px; height: 24px;
                background: transparent;
                border: 0;
                border-radius: 5px;
                color: var(--ps-subtle);
                cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                transition: background .12s, color .12s;
            }
            .ps-act-btn:hover { background: var(--ps-line); color: var(--ps-ink); }
            .ps-act-menu {
                position: absolute;
                top: calc(100% + 4px);
                right: 0;
                z-index: 20;
                background: var(--ps-bg);
                border-radius: 10px;
                padding: 4px;
                min-width: 140px;
                box-shadow:
                    0 0 0 1px rgba(15, 23, 42, .04),
                    0 10px 28px rgba(15, 23, 42, .12);
            }
            .ps-act-item {
                display: flex; align-items: center; gap: 8px;
                width: 100%;
                padding: 7px 10px;
                background: transparent;
                border: 0;
                border-radius: 6px;
                font: inherit;
                font-size: 12.5px;
                color: var(--ps-ink);
                text-align: left;
                cursor: pointer;
                transition: background .1s;
            }
            .ps-act-item:hover { background: var(--ps-bg-hover); }
            .ps-act-danger { color: var(--ps-danger); }
            .ps-act-danger:hover { background: var(--ps-danger-soft); }

            /* ─── Add buttons ─── */
            .ps-row-add, .ps-empty-add {
                display: flex; align-items: center; gap: 6px;
                width: 100%;
                margin-top: 4px;
                padding: 9px 10px;
                background: transparent;
                border: 1.5px dashed var(--ps-line);
                border-radius: 8px;
                font: inherit;
                font-size: 12px;
                font-weight: 500;
                color: var(--ps-muted);
                cursor: pointer;
                transition: border-color .12s, color .12s, background .12s;
                justify-content: center;
            }
            .ps-row-add:hover, .ps-empty-add:hover {
                border-color: var(--ps-accent);
                color: var(--ps-accent-ink);
                background: var(--ps-accent-soft);
            }

            /* ─── Modal ─── */
            .ps-modal-backdrop {
                position: fixed;
                inset: 0;
                z-index: 60;
                background: rgba(15, 23, 42, .45);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                animation: psFade .15s ease-out;
            }
            @keyframes psFade { from { opacity: 0; } to { opacity: 1; } }

            .ps-modal {
                width: 100%;
                max-width: 460px;
                background: var(--ps-bg);
                border-radius: 14px;
                box-shadow: 0 24px 60px rgba(15, 23, 42, .3);
                overflow: hidden;
                animation: psSlide .2s ease-out;
            }
            @keyframes psSlide { from { transform: translateY(6px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

            .ps-modal-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid var(--ps-line-soft);
            }
            .ps-modal-head h3 {
                margin: 0;
                font-size: 15px;
                font-weight: 700;
                color: var(--ps-ink);
            }
            .ps-modal-close {
                width: 28px; height: 28px;
                background: transparent;
                border: 0;
                border-radius: 6px;
                color: var(--ps-muted);
                cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                transition: background .12s, color .12s;
            }
            .ps-modal-close:hover { background: var(--ps-bg-hover); color: var(--ps-ink); }

            .ps-modal-body {
                padding: 18px 20px;
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .ps-field { display: flex; flex-direction: column; gap: 6px; }
            .ps-field-label {
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--ps-muted);
            }
            .ps-field-hint {
                font-size: 11px;
                color: var(--ps-subtle);
            }
            .ps-field-error {
                font-size: 11.5px;
                color: var(--ps-danger);
                font-weight: 500;
            }
            .ps-input {
                width: 100%;
                height: 38px;
                padding: 0 12px;
                background-color: var(--ps-bg);
                border: 1px solid var(--ps-line);
                border-radius: 11px;
                font: inherit;
                font-size: 13px;
                color: var(--ps-ink);
                transition: border-color .12s, box-shadow .12s;
            }
            /* Keep the panel's single select chevron (a `background` shorthand tiled it). */
            select.ps-input {
                padding-right: 34px;
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 16px 16px;
            }
            .ps-input:focus {
                outline: 0;
                border-color: var(--ps-accent);
                box-shadow: 0 0 0 3px rgba(var(--primary-500), .2);
            }
            .ps-input:disabled { background: var(--ps-bg-soft); color: var(--ps-muted); cursor: not-allowed; }

            .ps-tones {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 6px;
            }
            .ps-tone {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
                padding: 10px;
                border-radius: 11px;
                cursor: pointer;
                box-shadow: inset 0 0 0 1px var(--ps-line);
                transition: box-shadow .12s, background .12s;
            }
            .ps-tone:hover { box-shadow: inset 0 0 0 1px var(--ps-muted); }
            .ps-tone.is-active {
                background: var(--ps-accent-soft);
                box-shadow: inset 0 0 0 1.5px var(--ps-accent);
            }
            .ps-tone:has(:focus-visible) {
                outline: 2px solid var(--ps-accent);
                outline-offset: 2px;
            }
            .ps-tone-hint {
                font-size: 12px;
                line-height: 1.35;
                color: var(--ps-muted);
            }

            .ps-shapes {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .ps-shape {
                width: 36px; height: 36px;
                display: flex; align-items: center; justify-content: center;
                background: var(--ps-bg);
                border: 1px solid var(--ps-line);
                border-radius: 8px;
                cursor: pointer;
                transition: border-color .12s, transform .1s, background .12s;
            }
            .ps-shape:hover { border-color: var(--ps-muted); }
            .ps-shape.is-active {
                border-color: var(--ps-accent);
                background: var(--ps-accent-soft);
                transform: scale(1.05);
            }

            .ps-preview {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                background: var(--ps-bg-soft);
                border-radius: 8px;
                border: 1px solid var(--ps-line-soft);
            }
            .ps-preview-label {
                font-size: 12.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--ps-ink);
            }

            .ps-modal-foot {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                padding: 14px 20px;
                border-top: 1px solid var(--ps-line-soft);
                background: var(--ps-bg-soft);
            }
            .ps-btn {
                padding: 8px 16px;
                border-radius: 8px;
                border: 0;
                font: inherit;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: background .12s, color .12s;
            }
            .ps-btn-ghost {
                background: transparent;
                color: var(--ps-muted);
            }
            .ps-btn-ghost:hover {
                background: var(--ps-bg-hover);
                color: var(--ps-ink);
            }
            .ps-btn-primary {
                background: var(--ps-accent);
                color: white;
            }
            .ps-btn-primary:hover { background: var(--ps-accent-ink); }
        </style>
    @endonce

    {{-- SortableJS — loaded once. CDN for now, can be bundled later. --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endonce
</div>
