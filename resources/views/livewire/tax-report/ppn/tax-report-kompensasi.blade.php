{{--
    PPN compensation (Kompensasi tab). Summary strip from the stored PPN summary,
    the carry-over to the next period, and the history of compensations received
    and given. Modals below are opened by the methods of TaxReportKompensasi.
--}}
@php
    use App\Support\TaxPeriod;
    use App\Support\Ui\Tone;

    $rp = fn ($amount) => 'Rp ' . number_format(abs((float) $amount), 0, ',', '.');
    $period = fn ($report) => $report ? TaxPeriod::label($report->month, $report->year ?? $report->created_at?->year) : '–';
    $statusText = fn (?string $status) => $status ? ucfirst(mb_strtolower($status)) : 'Belum dihitung';
    $here = TaxPeriod::label($taxReport->month, $taxReport->year ?? $taxReport->created_at?->year);
    $nextIndex = TaxPeriod::index($taxReport->month) % 12 + 1;
    $nextLabel = TaxPeriod::label($nextIndex, (int) ($taxReport->year ?? $taxReport->created_at?->year) + ($nextIndex === 1 ? 1 : 0));
    $compStatus = [
        'pending' => ['Menunggu', Tone::OUTLINE],
        'approved' => ['Disetujui', Tone::BLUE],
        'rejected' => ['Ditolak', Tone::RED],
    ];
@endphp

<div class="kis-komp">

    {{-- Summary: one card split into cells --}}
    <div class="kis-sumstrip">
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Selisih sebelum kompensasi</span>
            <b class="kis-sumstrip__value">{{ $rp($saldoSebelumKompensasi) }}</b>
            <span class="kis-sumstrip__meta">{{ $statusText($statusSebelum) }}</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Kompensasi diterima</span>
            <b class="kis-sumstrip__value">{{ $rp($kompensasiDiterima) }}</b>
            <span class="kis-sumstrip__meta">{{ $receivedCompensations->count() }} transaksi</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">
                Kompensasi manual
                <button type="button" wire:click="openManualKompensasiForm" class="kis-ledger__edit">Ubah</button>
            </span>
            <b class="kis-sumstrip__value">{{ $rp($manualKompensasi) }}</b>
            <span class="kis-sumstrip__meta" title="{{ $manualKompensasiNotes }}">{{ $manualKompensasiNotes ? \Illuminate\Support\Str::limit($manualKompensasiNotes, 48) : 'Tanpa catatan' }}</span>
        </div>
        <div class="kis-sumstrip__cell">
            <span class="kis-sumstrip__label">Saldo akhir</span>
            <b @class(['kis-sumstrip__value', 'is-bad' => $statusSetelah === 'Kurang Bayar'])>{{ $rp($saldoSetelahKompensasi) }}</b>
            <span class="kis-sumstrip__meta"><x-kis.badge :tone="Tone::forPaymentStatus($statusSetelah)" dot size="sm">{{ $statusText($statusSetelah) }}</x-kis.badge></span>
        </div>
    </div>

    {{-- Approved compensation no longer matches the surplus (invoices changed afterwards) --}}
    @if ($this->staleGivenCompensations->isNotEmpty())
        <div @class(['kis-callout', 'is-red' => $this->isOverCompensated]) role="alert">
            <x-filament::icon :icon="$this->isOverCompensated ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-information-circle'" class="kis-callout__icon" />
            <div class="kis-callout__body">
                <p class="kis-callout__title">{{ $this->isOverCompensated ? 'Kompensasi melebihi lebih bayar terbaru' : 'Kompensasi tidak sama dengan lebih bayar terbaru' }}</p>
                <p>
                    @if ($this->isOverCompensated)
                        Faktur yang berubah mengurangi lebih bayar, sehingga kompensasi yang sudah disetujui <b>melebihi saldo tersedia</b>. Revisi supaya datanya konsisten.
                    @else
                        Faktur yang berubah mengubah lebih bayar periode ini. Kompensasi yang disetujui berbeda dengan angka terbaru; pertimbangkan merevisinya.
                    @endif
                </p>
                <div class="kis-callout__actions">
                    @foreach ($this->staleGivenCompensations as $staleComp)
                        <button type="button" wire:click="openReviseModal({{ $staleComp->id }})" class="kis-btn kis-btn--sm kis-btn--outline">
                            Revisi ke {{ $period($staleComp->targetTaxReport) }} ({{ $rp($staleComp->amount_compensated) }})
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Carry-over to the next period --}}
    <section class="kis-card kis-carry">
        <div class="kis-carry__main">
            <span class="kis-carry__eyebrow">Kompensasi ke periode berikutnya</span>
            @if ($statusSetelah === 'Lebih Bayar' && $kompensasiTersedia > 0)
                @if ($nextMonthExists)
                    <h3 class="kis-carry__title">{{ $rp($maxCompensationAmount) }} bisa dikompensasikan ke {{ $nextLabel }}</h3>
                    <p class="kis-carry__text">{{ $this->compensationTypeDescription }}.</p>
                    <div class="kis-carry__flow">
                        <span class="kis-carry__box"><b>{{ $here }}</b><small>Lebih bayar {{ $rp($saldoSetelahKompensasi) }} · sisa {{ $rp($kompensasiTersedia) }}</small></span>
                        <x-heroicon-m-arrow-right class="kis-carry__arrow" />
                        <span class="kis-carry__box"><b>{{ $nextLabel }}</b><small>{{ $statusText($this->nextMonthInfo['status']) }} {{ $this->nextMonthInfo['formatted_saldo'] }}</small></span>
                    </div>
                @else
                    <h3 class="kis-carry__title">Laporan {{ $nextLabel }} belum dibuat</h3>
                    <p class="kis-carry__text">Lebih bayar {{ $rp($kompensasiTersedia) }} baru bisa dikompensasikan setelah laporan pajak {{ $nextLabel }} dibuat di menu Laporan Pajak.</p>
                @endif
            @else
                <h3 class="kis-carry__title">Tidak ada lebih bayar untuk dikompensasikan</h3>
                <p class="kis-carry__text">
                    Kompensasi hanya bisa dibuat saat saldo akhir lebih bayar.
                    @if ($statusSetelah === 'Lebih Bayar')
                        Seluruh lebih bayar {{ $here }} sudah dikompensasikan.
                    @else
                        Saldo {{ $here }}: {{ mb_strtolower($statusText($statusSetelah)) }}{{ $statusSetelah === 'Nihil' ? '' : ' ' . $rp($saldoSetelahKompensasi) }}.
                    @endif
                </p>
            @endif
        </div>
        @if ($this->canCreateCompensation)
            <x-filament::button wire:click="openCompensationModal" icon="heroicon-m-arrow-right">
                Buat kompensasi ke {{ TaxPeriod::name($nextIndex) }}
            </x-filament::button>
        @elseif ($statusSetelah === 'Lebih Bayar' && $kompensasiTersedia > 0 && $nextMonthExists)
            <span class="kis-carry__hint">Masih ada kompensasi yang menunggu persetujuan</span>
        @endif
    </section>

    {{-- History --}}
    <section x-data="{ list: @js($givenCompensations->where('status', 'pending')->isNotEmpty() ? 'given' : 'received') }">
        <div class="kis-komp__head">
            <h3 class="kis-card__title">Riwayat kompensasi</h3>
            <div class="kis-rail__wrap kis-komp__seg" role="tablist" aria-label="Riwayat kompensasi">
                <button type="button" role="tab" class="kis-rail__chip" x-bind:class="list === 'received' && 'is-on'" x-bind:aria-selected="list === 'received'" x-on:click="list = 'received'">
                    Diterima <em>{{ $receivedCompensations->count() }}</em>
                </button>
                <button type="button" role="tab" class="kis-rail__chip" x-bind:class="list === 'given' && 'is-on'" x-bind:aria-selected="list === 'given'" x-on:click="list = 'given'">
                    Diberikan <em>{{ $givenCompensations->count() }}</em>
                </button>
            </div>
        </div>

        {{-- Received --}}
        <div class="kis-card kis-tbl" x-show="list === 'received'" @style(['display: none' => $givenCompensations->where('status', 'pending')->isNotEmpty()])>
            @if ($receivedCompensations->isEmpty())
                <p class="kis-tbl__empty"><b>Belum ada kompensasi diterima.</b> {{ $here }} belum menerima kompensasi dari periode sebelumnya.</p>
            @else
                <table>
                    <thead>
                        <tr><th>Dari periode</th><th class="is-num">Jumlah</th><th>Status</th><th>Catatan</th><th>Disetujui</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($receivedCompensations as $compensation)
                            <tr wire:key="komp-in-{{ $compensation->id }}">
                                <td><span class="kis-tbl__primary">{{ $period($compensation->sourceTaxReport) }}</span></td>
                                <td class="is-num"><b>{{ $rp($compensation->amount_compensated) }}</b></td>
                                <td><x-kis.badge tone="blue" dot size="sm">Disetujui</x-kis.badge></td>
                                <td class="kis-tbl__wrap">{{ $compensation->notes ?: '–' }}</td>
                                <td>
                                    <span class="kis-tbl__primary">{{ $compensation->approvedBy?->name ?? '–' }}</span>
                                    <span class="kis-tbl__meta">{{ TaxPeriod::date($compensation->created_at) }}, {{ $compensation->created_at->format('H:i') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Given --}}
        <div class="kis-card kis-tbl" x-show="list === 'given'" @style(['display: none' => $givenCompensations->where('status', 'pending')->isEmpty()])>
            @if ($givenCompensations->isEmpty())
                <p class="kis-tbl__empty"><b>Belum ada kompensasi diberikan.</b> {{ $here }} belum memberi kompensasi ke periode berikutnya.</p>
            @else
                <table>
                    <thead>
                        <tr><th>Ke periode</th><th class="is-num">Jumlah</th><th>Status</th><th>Catatan</th><th>Dibuat</th><th><span class="sr-only">Aksi</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($givenCompensations as $compensation)
                            @php
                                [$label, $tone] = $compStatus[$compensation->status] ?? [$compensation->status, Tone::NEUTRAL];
                                $target = $compensation->targetTaxReport;
                            @endphp
                            <tr wire:key="komp-out-{{ $compensation->id }}">
                                <td>
                                    <span class="kis-tbl__primary">{{ $period($target) }}</span>
                                    @if ($target?->ppnSummary)
                                        <span class="kis-tbl__meta">{{ $statusText($target->ppnSummary->status_final) }} {{ $rp($target->ppnSummary->saldo_final) }}</span>
                                    @endif
                                </td>
                                <td class="is-num"><b>{{ $rp($compensation->amount_compensated) }}</b></td>
                                <td>
                                    <x-kis.badge :tone="$tone" dot size="sm">{{ $label }}</x-kis.badge>
                                    @if ($compensation->status === 'rejected' && $compensation->rejection_reason)
                                        <span class="kis-tbl__meta is-bad">{{ $compensation->rejection_reason }}</span>
                                    @endif
                                </td>
                                <td class="kis-tbl__wrap">{{ $compensation->notes ?: '–' }}</td>
                                <td>
                                    <span class="kis-tbl__primary">{{ TaxPeriod::date($compensation->created_at) }}</span>
                                    <span class="kis-tbl__meta">{{ $compensation->approvedBy?->name ? 'Disetujui ' . $compensation->approvedBy->name : $compensation->createdBy?->name }}</span>
                                </td>
                                <td class="kis-tbl__actions">
                                    @if ($compensation->status === 'pending')
                                        <x-filament::button size="sm" wire:click="openApproveModal({{ $compensation->id }})" icon="heroicon-m-check">Setujui</x-filament::button>
                                        <x-filament::button size="sm" color="gray" wire:click="openRejectModal({{ $compensation->id }})">Tolak</x-filament::button>
                                        <x-filament::button size="sm" color="gray" wire:click="cancelCompensation({{ $compensation->id }})" wire:confirm="Batalkan kompensasi ini?">Batal</x-filament::button>
                                    @elseif ($compensation->status === 'approved')
                                        <x-filament::button size="sm" color="gray" wire:click="openReviseModal({{ $compensation->id }})" icon="heroicon-m-pencil-square">Revisi</x-filament::button>
                                        <x-filament::button size="sm" color="gray" wire:click="cancelCompensation({{ $compensation->id }})" wire:confirm="Batalkan kompensasi yang sudah disetujui ini?">Batal</x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    {{-- ============================================
    MODALS
    ============================================ --}}

    {{-- Manual Kompensasi Modal --}}
    <x-filament::modal id="manual-kompensasi-modal" width="2xl" :close-by-clicking-away="false">
        <x-slot name="heading">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-500/20">
                    <x-filament::icon icon="heroicon-o-pencil-square"
                        class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <span>Ubah kompensasi manual</span>
            </div>
        </x-slot>

        <div class="space-y-5">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-500/10">
                <div class="flex gap-3">
                    <x-filament::icon icon="heroicon-o-information-circle"
                        class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <p class="font-semibold mb-1">Tentang Kompensasi Manual:</p>
                        <ul class="space-y-1 list-disc list-inside text-xs">
                            <li>Digunakan untuk kompensasi yang tidak tercatat di sistem</li>
                            <li>Akan mengurangi saldo pajak terutang periode ini</li>
                            <li>Masukkan jumlah 0 untuk menghapus kompensasi manual</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Jumlah Kompensasi Manual <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">Rp</span>
                    <input type="number" wire:model.live="tempManualKompensasi"
                        class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="0" min="0" step="1000" x-data x-on:focus="$el.type = 'number'" x-on:blur="
                            if ($el.value) {
                                let val = parseInt($el.value);
                                $el.type = 'text';
                                $el.value = new Intl.NumberFormat('id-ID').format(val);
                                setTimeout(() => { $el.type = 'number'; $el.value = val; }, 100);
                            }
                        ">
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Nilai saat ini: Rp {{ number_format($manualKompensasi, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                    @if($tempManualKompensasi > 0)
                    Preview: Rp {{ number_format($tempManualKompensasi, 0, ',', '.') }}
                    @endif
                </p>
                @error('tempManualKompensasi')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Catatan <span class="text-xs text-gray-500">(Opsional)</span>
                </label>
                <textarea wire:model="tempManualKompensasiNotes" rows="4"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="Jelaskan alasan atau sumber kompensasi manual ini..."></textarea>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Catatan ini akan membantu untuk audit dan pelacakan
                </p>
                @error('tempManualKompensasiNotes')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if($tempManualKompensasi > 0)
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Preview Perhitungan:</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Saldo Awal:</span>
                        <span>Rp {{ number_format(abs($saldoSebelumKompensasi), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600 dark:text-gray-400">
                        <span>Kompensasi Diterima:</span>
                        <span>- Rp {{ number_format($kompensasiDiterima, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-blue-600 dark:text-blue-400">
                        <span>Kompensasi Manual:</span>
                        <span>- Rp {{ number_format($tempManualKompensasi, 0, ',', '.') }}</span>
                    </div>
                    <div class="border-t border-gray-300 dark:border-gray-600 pt-2 mt-2"></div>

                    @php
                    $predictedSaldo = $saldoSebelumKompensasi - $kompensasiDiterima - $tempManualKompensasi;
                    $predictedStatus = $predictedSaldo > 0 ? 'Kurang Bayar' : ($predictedSaldo < 0 ? 'Lebih Bayar'
                        : 'Nihil' ); $statusColor=$predictedStatus==='Lebih Bayar'
                        ? 'text-primary-700 dark:text-primary-300' : ($predictedStatus==='Kurang Bayar'
                        ? 'text-danger-700 dark:text-danger-300' : 'text-gray-600 dark:text-gray-400' );
                        $bgColor=$predictedStatus==='Lebih Bayar' ? 'bg-primary-50 dark:bg-primary-500/10' :
                        ($predictedStatus==='Kurang Bayar' ? 'bg-danger-50 dark:bg-danger-500/10'
                        : 'bg-gray-50 dark:bg-gray-800' ); $borderColor=$predictedStatus==='Lebih Bayar'
                        ? 'border-primary-200 dark:border-primary-800' : ($predictedStatus==='Kurang Bayar'
                        ? 'border-danger-200 dark:border-danger-800' : 'border-gray-200 dark:border-gray-700' ); @endphp
                        <div class="rounded-lg border p-3 {{ $borderColor }} {{ $bgColor }}">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white">Saldo Akhir:</span>
                                <div class="flex items-center gap-1.5">
                                    <div
                                        class="h-1.5 w-1.5 rounded-full {{ $predictedStatus === 'Lebih Bayar' ? 'bg-primary-500' : ($predictedStatus === 'Kurang Bayar' ? 'bg-danger-500' : 'bg-gray-400') }}">
                                    </div>
                                    <span class="text-xs font-medium {{ $statusColor }}">{{ $predictedStatus }}</span>
                                </div>
                            </div>
                            <span class="font-bold {{ $statusColor }}">
                                Rp {{ number_format(abs($predictedSaldo), 0, ',', '.') }}
                            </span>
                        </div>
                </div>

                {{-- Status Change Indicator --}}
                @if($statusSetelah !== $predictedStatus)
                <div
                    class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-500/10">
                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-o-arrow-path"
                            class="h-4 w-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="text-xs text-blue-900 dark:text-blue-200">
                            <span class="font-semibold">Status akan berubah:</span>
                            <span class="text-blue-700 dark:text-blue-300">{{ $statusSetelah }}</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="inline h-3 w-3 mx-1" />
                            <span class="font-semibold {{ $statusColor }}">{{ $predictedStatus }}</span>
                        </div>
                    </div>
                </div>
                @else
                <div class="mt-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-o-check-circle"
                            class="h-4 w-4 text-gray-600 dark:text-gray-400 flex-shrink-0 mt-0.5" />
                        <p class="text-xs text-gray-700 dark:text-gray-300">
                            Status tetap: <span class="font-semibold">{{ $statusSetelah }}</span>
                        </p>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
</div>

<x-slot name="footerActions">
    <x-filament::button color="gray" wire:click="cancelManualKompensasiEdit">
        Batal
    </x-filament::button>
    <x-filament::button color="primary" wire:click="saveManualKompensasi" icon="heroicon-m-check">
        Simpan
    </x-filament::button>
</x-slot>
</x-filament::modal>

{{-- Create Compensation Modal --}}
<x-filament::modal id="compensation-modal" width="2xl" :close-by-clicking-away="false">
    <x-slot name="heading">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                <x-filament::icon icon="heroicon-o-arrow-right-circle"
                    class="h-6 w-6 text-gray-600 dark:text-gray-400" />
            </div>
            <span>{{ $this->compensationTypeLabel }}</span>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Periode Saat Ini</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $taxReport->month }}</p>
                    <span class="mt-1 text-xs text-primary-700 dark:text-primary-300">{{ $statusSetelah }}</span>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Periode Target</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $this->nextMonthInfo['month'] ?? '-' }}
                    </p>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->nextMonthInfo['status'] ?? '-' }}
                    </span>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Jumlah Kompensasi <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">Rp</span>
                <input type="number" wire:model.live="compensationAmount"
                    class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    placeholder="0" min="0" max="{{ $maxCompensationAmount }}">
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Maksimal: Rp {{ number_format($maxCompensationAmount, 0, ',', '.') }}
            </p>
            @error('compensationAmount')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Catatan <span class="text-xs text-gray-500">(Opsional)</span>
            </label>
            <textarea wire:model="compensationNotes" rows="3"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Tambahkan catatan jika diperlukan..."></textarea>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex gap-3">
                <x-filament::icon icon="heroicon-o-information-circle"
                    class="h-5 w-5 text-gray-600 dark:text-gray-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-semibold mb-1">Ringkasan:</p>
                    <ul class="space-y-1 text-xs">
                        <li>{{ $this->compensationTypeDescription }}</li>
                        <li>Jumlah: <strong>Rp {{ number_format($compensationAmount, 0, ',', '.') }}</strong></li>
                        <li>Target: <strong>{{ $this->nextMonthInfo['month'] ?? '-' }}</strong></li>
                        <li>Memerlukan approval sebelum berlaku</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'compensation-modal' })">
            Batal
        </x-filament::button>
        <x-filament::button wire:click="createCompensation" :disabled="$compensationAmount <= 0"
            icon="heroicon-m-arrow-right-circle">
            Buat Kompensasi
        </x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- Approve Modal --}}
<x-filament::modal id="approve-compensation-modal" width="md">
    <x-slot name="heading">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-primary-100 p-2 dark:bg-primary-500/20">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-primary-700 dark:text-primary-300" />
            </div>
            <span>Setujui Kompensasi</span>
        </div>
    </x-slot>

    <div class="py-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Apakah Anda yakin ingin menyetujui kompensasi ini? Saldo pajak akan diperbarui secara otomatis.
        </p>
    </div>

    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'approve-compensation-modal' })">
            Batal
        </x-filament::button>
        <x-filament::button color="primary" wire:click="approveCompensation">
            Ya, Setujui
        </x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- Reject Modal --}}
<x-filament::modal id="reject-compensation-modal" width="md">
    <x-slot name="heading">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-red-100 p-2 dark:bg-red-500/20">
                <x-filament::icon icon="heroicon-o-x-circle" class="h-6 w-6 text-red-600 dark:text-red-400" />
            </div>
            <span>Tolak Kompensasi</span>
        </div>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Berikan alasan penolakan untuk kompensasi ini:
        </p>
        <div>
            <textarea wire:model="rejectionReason" rows="4"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                placeholder="Masukkan alasan penolakan (minimal 10 karakter)..."></textarea>
            @error('rejectionReason')
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <x-slot name="footerActions">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'reject-compensation-modal' })">
            Batal
        </x-filament::button>
        <x-filament::button color="danger" wire:click="rejectCompensation">
            Tolak Kompensasi
        </x-filament::button>
    </x-slot>
</x-filament::modal>

{{-- Revise Compensation Modal --}}
<x-filament::modal id="revise-compensation-modal" width="lg" :close-by-clicking-away="false">
    <x-slot name="heading">Revisi Kompensasi</x-slot>
    <x-slot name="description">
        Kompensasi lama akan dibatalkan dan digantikan dengan nilai baru.
        Semua bulan setelah bulan target akan dikalkulasi ulang secara otomatis.
    </x-slot>

    <div class="space-y-5">
        {{-- Current vs New --}}
        @if($reviseCompensationId)
        @php $rc = $givenCompensations->firstWhere('id', $reviseCompensationId); @endphp
        @if($rc)
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Jumlah Saat Ini</p>
                <p class="text-lg font-bold text-gray-500 line-through dark:text-gray-400">
                    Rp {{ number_format($rc->amount_compensated, 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    ke {{ $rc->targetTaxReport->month }}
                </p>
            </div>
            <div class="rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/40">
                <p class="text-xs text-primary-700 dark:text-primary-300 mb-1">Surplus Terkini</p>
                <p class="text-lg font-bold text-primary-700 dark:text-primary-300">
                    Rp {{ number_format($this->taxReport->ppnSummary?->kompensasi_tersedia ?? 0, 0, ',', '.') }}
                </p>
                <p class="text-xs text-primary-700 dark:text-primary-300 mt-1">
                    tersedia untuk dikompensasi
                </p>
            </div>
        </div>
        @endif
        @endif

        {{-- New Amount Input --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Jumlah Revisi <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500 dark:text-gray-400">Rp</span>
                <input wire:model="reviseAmount" type="number" min="1"
                    :max="{{ $this->taxReport->ppnSummary?->kompensasi_tersedia ?? 0 }}"
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
            </div>
            @error('reviseAmount')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Catatan Revisi
            </label>
            <textarea wire:model="reviseNotes" rows="2"
                placeholder="Alasan revisi (opsional)..."
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
            @error('reviseNotes')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Info note --}}
        <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30">
            <div class="flex gap-2">
                <x-filament::icon icon="heroicon-o-information-circle"
                    class="h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" />
                <p class="text-xs text-blue-800 dark:text-blue-200">
                    Kompensasi lama akan disimpan dengan status <strong>Dibatalkan</strong> sebagai riwayat.
                    Kompensasi baru akan langsung berstatus <strong>Disetujui</strong> dan semua bulan setelah
                    periode target akan dihitung ulang secara otomatis.
                </p>
            </div>
        </div>
    </div>

    <x-slot name="footerActions">
        <x-filament::button wire:click="confirmRevise" color="primary" icon="heroicon-m-pencil-square">
            Konfirmasi Revisi
        </x-filament::button>
        <x-filament::button color="gray"
            x-on:click="$dispatch('close-modal', { id: 'revise-compensation-modal' })">
            Batal
        </x-filament::button>
    </x-slot>
</x-filament::modal>

</div>