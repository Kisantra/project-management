@php
    use App\Services\InvoiceJsonImportService;

    $rupiah = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
@endphp

<div>

    {{-- ══════════════════════════════════════════════════════
         STEP 1 — PROMPT, JSON, FILES
    ══════════════════════════════════════════════════════ --}}
    @if ($step === 'input')

        <div class="px-6 py-6 space-y-8">

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Untuk <strong class="text-gray-800 dark:text-gray-200">{{ $clientName }}</strong>, masa {{ $periodLabel }}. Semua data diperiksa dulu sebelum disimpan.
            </p>

            @if ($inputError)
                <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                    <p class="text-sm text-red-700 dark:text-red-300">{{ $inputError }}</p>
                </div>
            @endif

            {{-- 1. Prompt --}}
            <section
                x-data="{
                    open: false,
                    copied: false,
                    copy() {
                        const text = this.$refs.prompt.value;
                        const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(done);
                            return;
                        }
                        // Fallback for plain-http hosts; appended inside the modal so its focus trap allows select().
                        const area = document.createElement('textarea');
                        area.value = text;
                        area.style.position = 'fixed';
                        area.style.opacity = '0';
                        this.$root.appendChild(area);
                        area.select();
                        document.execCommand('copy');
                        area.remove();
                        done();
                    },
                }"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-semibold flex items-center justify-center shrink-0">1</span>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Salin prompt, lalu kirim ke AI bersama file fakturnya</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Bisa dipakai di ChatGPT, Claude, atau Gemini. Prompt sudah berisi nama klien, NPWP, dan masa pajak laporan ini.
                            </p>
                        </div>
                    </div>
                    <button type="button" x-on:click="copy()"
                        class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition-colors shadow-sm">
                        <x-heroicon-o-clipboard-document class="w-4 h-4" x-show="!copied" />
                        <x-heroicon-o-check class="w-4 h-4" x-show="copied" x-cloak />
                        <span x-text="copied ? 'Tersalin' : 'Salin prompt'">Salin prompt</span>
                    </button>
                </div>

                <div class="mt-3 pl-10">
                    <button type="button" x-on:click="open = !open"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
                        <span x-text="open ? 'Sembunyikan isi prompt' : 'Lihat isi prompt'">Lihat isi prompt</span>
                    </button>
                    <textarea x-ref="prompt" x-show="open" x-cloak readonly rows="12"
                        class="mt-3 w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 text-xs font-mono text-gray-700 dark:text-gray-300 p-4">{{ $prompt }}</textarea>
                </div>
            </section>

            {{-- 2. JSON --}}
            <section>
                <div class="flex items-start gap-3">
                    <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-semibold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Tempel jawaban JSON dari AI</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Salin seluruh jawabannya. Pembungkus ```json dan kalimat tambahan dari AI dibersihkan otomatis.
                        </p>
                    </div>
                </div>
                <div class="mt-3 pl-10">
                    <textarea wire:model="json" rows="10" spellcheck="false"
                        placeholder='{ "masa": "...", "faktur": [ ... ] }'
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-mono text-gray-800 dark:text-gray-200 p-4 focus:border-primary-500 focus:ring-primary-500"></textarea>
                </div>
            </section>

            {{-- 3. Files --}}
            <section>
                <div class="flex items-start gap-3">
                    <span class="w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-semibold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Unggah file fakturnya</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            File dicocokkan lewat field "file" di JSON, atau lewat nama file yang sama dengan nomor faktur.
                        </p>
                    </div>
                </div>

                <div class="mt-3 pl-10 space-y-3"
                    x-data="{
                        isDragging: false,
                        handleDrop(e) {
                            this.isDragging = false;
                            const input = $refs.fileInput;
                            const dt = new DataTransfer();
                            [...e.dataTransfer.files].forEach(f => dt.items.add(f));
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }"
                >
                    <label class="block cursor-pointer"
                        x-on:dragover.prevent="isDragging = true"
                        x-on:dragleave.prevent="isDragging = false"
                        x-on:drop.prevent="handleDrop($event)">
                        <div
                            :class="isDragging
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60 hover:border-primary-400 hover:bg-primary-50/50 dark:hover:border-primary-600'"
                            class="border-2 border-dashed rounded-2xl p-8 text-center transition-all duration-150">
                            <x-heroicon-o-cloud-arrow-up class="w-7 h-7 mx-auto text-gray-400" />
                            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Klik untuk memilih file, atau seret ke sini</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                                PDF, JPG, PNG, atau ZIP · maks. 12 MB per file · lebih dari 20 file? masukkan ke satu ZIP
                            </p>
                            <input x-ref="fileInput" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.zip"
                                class="sr-only" wire:model="newUploads">
                        </div>
                    </label>

                    <div wire:loading wire:target="newUploads" class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Mengunggah ke server…
                    </div>

                    @error('newUploads.*')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    @if (count($uploads) > 0)
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ count($uploads) }} file diunggah</p>
                            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                @foreach ($uploads as $index => $upload)
                                    @php $ext = strtolower($upload->getClientOriginalExtension()); @endphp
                                    <div wire:key="upload-{{ $index }}-{{ $upload->getFilename() }}"
                                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                        @if ($ext === 'zip')
                                            <x-heroicon-o-archive-box class="w-5 h-5 text-amber-500 shrink-0" />
                                        @elseif ($ext === 'pdf')
                                            <x-heroicon-o-document class="w-5 h-5 text-red-500 shrink-0" />
                                        @else
                                            <x-heroicon-o-photo class="w-5 h-5 text-blue-500 shrink-0" />
                                        @endif
                                        <p class="flex-1 min-w-0 text-sm text-gray-800 dark:text-gray-200 truncate">{{ $upload->getClientOriginalName() }}</p>
                                        <button type="button" wire:click="removeUpload({{ $index }})" title="Hapus file"
                                            class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-500 transition-colors">
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/80 flex items-center justify-end gap-4">
            <button type="button" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze,newUploads"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 disabled:opacity-60 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors shadow-sm">
                <span wire:loading.remove wire:target="analyze" class="inline-flex items-center gap-2">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                    Periksa data
                </span>
                <span wire:loading wire:target="analyze">Memeriksa…</span>
            </button>
        </div>

    @endif


    {{-- ══════════════════════════════════════════════════════
         STEP 2 — REVIEW
    ══════════════════════════════════════════════════════ --}}
    @if ($step === 'review' && $analysis)

        @php
            $summary = $analysis['summary'];
            $severity = [InvoiceJsonImportService::STATUS_ERROR => 0, InvoiceJsonImportService::STATUS_WARNING => 1, InvoiceJsonImportService::STATUS_OK => 2];
            $rows = collect($analysis['rows'])->sortBy(fn ($row) => [$severity[$row['status']], $row['line']])->values();
            $badges = [
                InvoiceJsonImportService::STATUS_OK => ['bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300', 'Siap'],
                InvoiceJsonImportService::STATUS_WARNING => ['bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300', 'Periksa'],
                InvoiceJsonImportService::STATUS_ERROR => ['bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300', 'Gagal'],
            ];
        @endphp

        <div class="px-6 pt-6 pb-5 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Periksa sebelum menyimpan</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $summary['total'] }} faktur di JSON untuk {{ $clientName }}, masa {{ $analysis['period_label'] }}.
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($summary['ok'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">
                            <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> {{ $summary['ok'] }} siap
                        </span>
                    @endif
                    @if ($summary['warning'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" /> {{ $summary['warning'] }} perlu dicek
                        </span>
                    @endif
                    @if ($summary['error'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">
                            <x-heroicon-o-x-circle class="w-3.5 h-3.5" /> {{ $summary['error'] }} gagal
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">

            @if ($analysis['global_errors'])
                <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-red-700 dark:text-red-300 space-y-1">
                        <p class="font-semibold">Tidak ada yang bisa disimpan</p>
                        @foreach ($analysis['global_errors'] as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($analysis['global_warnings'])
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-700 dark:text-amber-300 space-y-1">
                        @foreach ($analysis['global_warnings'] as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Totals of what will be saved --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (['keluaran' => 'Faktur Keluaran', 'masukan' => 'Faktur Masukan'] as $key => $label)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $label }} yang akan disimpan</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $summary[$key]['count'] }} faktur</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            DPP {{ $rupiah($summary[$key]['dpp']) }} · PPN {{ $rupiah($summary[$key]['ppn']) }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Rows --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60 text-left text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Baris</th>
                                <th class="px-4 py-3 font-semibold">Faktur</th>
                                <th class="px-4 py-3 font-semibold">Jenis</th>
                                <th class="px-4 py-3 font-semibold text-right">DPP</th>
                                <th class="px-4 py-3 font-semibold text-right">PPN</th>
                                <th class="px-4 py-3 font-semibold">File</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        @foreach ($rows as $row)
                            @php $data = $row['data']; @endphp
                            {{-- One tbody per invoice keeps its messages visually attached to it. --}}
                            <tbody wire:key="row-{{ $row['line'] }}" class="border-t border-gray-100 dark:border-gray-800">
                                <tr class="align-top">
                                    <td class="px-4 pt-4 pb-3 text-gray-500 dark:text-gray-400">{{ $row['line'] }}</td>
                                    <td class="px-4 pt-4 pb-3">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ ($data['company_name'] ?? '') ?: '—' }}</p>
                                        <p class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-1">
                                            {{ ($data['invoice_number'] ?? '') ?: 'tanpa nomor' }}
                                            @if (!empty($data['invoice_date']))
                                                · {{ \Illuminate\Support\Carbon::parse($data['invoice_date'])->format('d/m/Y') }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 pt-4 pb-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        {{ match ($data['type'] ?? null) { 'Faktur Keluaran' => 'Keluaran', 'Faktur Masuk' => 'Masukan', default => '—' } }}
                                    </td>
                                    <td class="px-4 pt-4 pb-3 text-right whitespace-nowrap text-gray-900 dark:text-white">
                                        {{ isset($data['dpp']) ? $rupiah($data['dpp']) : '—' }}
                                    </td>
                                    <td class="px-4 pt-4 pb-3 text-right whitespace-nowrap text-gray-900 dark:text-white">
                                        {{ isset($data['ppn']) ? $rupiah($data['ppn']) : '—' }}
                                    </td>
                                    <td class="px-4 pt-4 pb-3 text-gray-600 dark:text-gray-400 break-all">
                                        {{ $row['file'] ?? '—' }}
                                    </td>
                                    <td class="px-4 pt-4 pb-3">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $badges[$row['status']][0] }}">
                                            {{ $badges[$row['status']][1] }}
                                        </span>
                                    </td>
                                </tr>
                                @if ($row['errors'] || $row['warnings'])
                                    <tr>
                                        <td></td>
                                        <td colspan="6" class="px-4 pb-4">
                                            <ul class="space-y-1.5 text-sm">
                                                @foreach ($row['errors'] as $message)
                                                    <li class="flex items-start gap-2 text-red-600 dark:text-red-400">
                                                        <x-heroicon-o-x-circle class="w-4 h-4 shrink-0 mt-0.5" />
                                                        <span>{{ $message }}</span>
                                                    </li>
                                                @endforeach
                                                @foreach ($row['warnings'] as $message)
                                                    <li class="flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                                                        <span>{{ $message }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>

            @if ($analysis['unused_files'])
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ count($analysis['unused_files']) }} file tidak dipakai oleh faktur mana pun
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 break-words">{{ implode(', ', $analysis['unused_files']) }}</p>
                </div>
            @endif
        </div>

        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/80 flex items-center justify-between gap-4">
            <button type="button" wire:click="backToInput"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Kembali
            </button>

            <div class="flex items-center gap-4">
                <p class="text-sm text-gray-500 dark:text-gray-400 hidden sm:block">
                    @if ($summary['saveable'] > 0)
                        <strong class="text-gray-800 dark:text-gray-200">{{ $summary['saveable'] }}</strong> faktur akan disimpan{{ $summary['error'] ? ', baris yang gagal dilewati' : '' }}
                    @else
                        Belum ada faktur yang bisa disimpan
                    @endif
                </p>
                <button type="button" wire:click="save" wire:loading.attr="disabled"
                    @disabled($summary['saveable'] === 0)
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-sm transition-colors shadow-sm
                        {{ $summary['saveable'] > 0
                            ? 'bg-primary-600 hover:bg-primary-700 text-white'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed' }}">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                        <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                        Simpan {{ $summary['saveable'] }} faktur
                    </span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
            </div>
        </div>

    @endif

</div>
