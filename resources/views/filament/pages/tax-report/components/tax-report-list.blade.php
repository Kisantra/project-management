@php
    use App\Filament\Resources\TaxReportResource;
    use App\Filament\Resources\TaxReportResource\Pages\ListTaxReports;
    use App\Support\Ui\Tone;

    $sortCol = $this->getTableSortColumn();
    $sortDir = $this->getTableSortDirection();
    // Rows are grouped per month only while they are ordered by period.
    $grouped = $sortCol === null || $sortCol === 'month';
    $summary = $grouped ? $this->monthSummary : [];
    $previousPeriod = null;
    $th = 'px-3 py-2.5 text-[11px] font-extrabold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400';
    $iconLink = 'grid h-8 w-8 place-items-center rounded-[9px] text-gray-500 transition hover:bg-gray-100 hover:text-gray-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white';
@endphp

<div class="tax-report-list">
    @if ($records->isEmpty())
        <div class="rounded-[14px] border border-gray-200 bg-white px-6 py-14 text-center dark:border-white/10 dark:bg-gray-900">
            <x-filament::icon icon="heroicon-o-document-text" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm font-bold text-gray-950 dark:text-white">Tidak ada laporan pajak di periode ini</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ganti periode atau filter, atau buat laporan baru.</p>
            <div class="mt-5 flex justify-center">
                {{ $this->getTable()->getEmptyStateActions() }}
            </div>
        </div>
    @else
        <div class="overflow-x-auto rounded-[14px] border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="w-full min-w-[60rem] table-fixed text-sm">
                <colgroup>
                    <col class="w-[26%]">
                    <col class="w-[9.5%]"><col class="w-[9.5%]"><col class="w-[9.5%]"><col class="w-[9.5%]">
                    <col class="w-[13%]">
                    <col class="w-[13%]">
                    <col class="w-[10%]">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2" scope="col" class="{{ $th }} ps-5 text-left">
                            <button type="button"
                                wire:click="sortTable('client.name', @js($sortCol === 'client.name' && $sortDir === 'asc' ? 'desc' : 'asc'))"
                                @class(['inline-flex items-center gap-1 uppercase tracking-[0.08em] hover:text-gray-950 dark:hover:text-white', 'text-primary-700 dark:text-primary-300' => $sortCol === 'client.name'])>
                                Client
                                @if ($sortCol === 'client.name')
                                    <x-filament::icon :icon="$sortDir === 'asc' ? 'heroicon-m-arrow-up' : 'heroicon-m-arrow-down'" class="h-3.5 w-3.5" />
                                @endif
                            </button>
                        </th>
                        <th colspan="4" scope="colgroup" class="{{ $th }} pb-1 text-center">
                            <span class="block border-b border-gray-300 pb-1.5 dark:border-white/15">Status pajak</span>
                        </th>
                        <th rowspan="2" scope="col" class="{{ $th }} text-right">Saldo PPN</th>
                        <th rowspan="2" scope="col" class="{{ $th }} ps-5 text-left">Status bayar</th>
                        <th rowspan="2" scope="col" class="{{ $th }} pe-5 text-right"><span class="sr-only">Aksi</span></th>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        @foreach (ListTaxReports::TAX_TYPES as $type)
                            <th scope="col" class="{{ $th }} pt-1 text-center text-[10.5px]">{{ $type['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        @php
                            $periodKey = ListTaxReports::periodKey($record);
                            $slots = ListTaxReports::slots($record);
                            $ppn = $record->taxCalculationSummaries->firstWhere('tax_type', 'ppn');
                            $paymentStatus = $ppn?->status_final ?? 'N/A';
                            $saldo = abs((float) ($ppn?->saldo_final ?? 0));
                            $contracts = collect(ListTaxReports::TAX_TYPES)->filter(fn ($type) => (bool) ($record->client?->{$type['contract']} ?? false));
                        @endphp

                        @if ($grouped && $periodKey !== $previousPeriod)
                            @php
                                $previousPeriod = $periodKey;
                                $group = $summary[$periodKey] ?? ['total' => null, 'pending' => 0];
                            @endphp
                            <x-kis.group-row :colspan="8" :label="ListTaxReports::periodLabel($record)" :count="$group['total']" count-label="laporan" wire:key="period-{{ $periodKey }}">
                                @if (ListTaxReports::isCurrentPeriod($record))
                                    <x-kis.badge tone="blue" size="sm">Bulan ini</x-kis.badge>
                                @endif
                                <x-slot name="aside">
                                    @if ($group['pending'] > 0)
                                        <span class="kis-group-row__alert">{{ $group['pending'] }} belum lapor</span>
                                    @elseif (! is_null($group['total']))
                                        <span>Semua sudah lapor</span>
                                    @endif
                                </x-slot>
                            </x-kis.group-row>
                        @endif

                        <tr wire:key="{{ $this->getId() }}.table.records.{{ $record->getKey() }}" class="border-b border-gray-100 transition last:border-0 hover:bg-gray-50/70 dark:border-white/5 dark:hover:bg-white/[.02]">
                            <td class="truncate px-3 py-2.5 ps-5">
                                <a href="{{ TaxReportResource::getUrl('view', ['record' => $record]) }}" class="font-bold text-gray-950 hover:text-primary-700 dark:text-white dark:hover:text-primary-300" title="{{ $record->client?->name }}">{{ $record->client?->name ?? 'Tanpa client' }}</a>
                                @unless ($grouped)
                                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ ListTaxReports::periodLabel($record) }}</span>
                                @endunless
                            </td>

                            @foreach ($slots as $slot)
                                <td class="px-1 py-2.5 text-center">
                                    @if ($slot['state'] === 'none')
                                        <span class="text-gray-300 dark:text-gray-600" title="{{ $slot['title'] }}">–</span>
                                    @elseif ($slot['state'] === 'pending')
                                        <span title="{{ $slot['title'] }}"><x-kis.badge tone="red" size="sm">Belum</x-kis.badge></span>
                                    @else
                                        <span class="inline-flex items-center gap-1" title="{{ $slot['title'] }}">
                                            <x-kis.badge tone="blue" size="sm">
                                                <x-filament::icon icon="heroicon-m-check" class="h-3 w-3" />{{ $slot['date'] ?? 'Lapor' }}
                                            </x-kis.badge>
                                            @if ($slot['paid'])
                                                <span class="grid h-5 w-5 place-items-center rounded-[6px] bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                                    <x-filament::icon icon="heroicon-m-banknotes" class="h-3 w-3" />
                                                    <span class="sr-only">Sudah bayar</span>
                                                </span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                            @endforeach

                            <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">
                                <span @class(['font-extrabold text-gray-950 dark:text-white' => $saldo > 0, 'font-semibold text-gray-400 dark:text-gray-500' => $saldo == 0])>Rp {{ number_format($saldo, 0, ',', '.') }}</span>
                            </td>

                            <td class="px-3 py-2.5 ps-5">
                                <x-kis.badge :tone="Tone::forPaymentStatus($paymentStatus)" dot>{{ $paymentStatus }}</x-kis.badge>
                            </td>

                            <td class="px-3 py-2 pe-4">
                                <div class="flex items-center justify-end gap-0.5">
                                    <a href="{{ TaxReportResource::getUrl('view', ['record' => $record]) }}" class="{{ $iconLink }}" title="Lihat">
                                        <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
                                        <span class="sr-only">Lihat {{ $record->client?->name }}</span>
                                    </a>
                                    <a href="{{ TaxReportResource::getUrl('edit', ['record' => $record]) }}" class="{{ $iconLink }}" title="Edit">
                                        <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
                                        <span class="sr-only">Edit {{ $record->client?->name }}</span>
                                    </a>
                                    <x-filament::dropdown placement="bottom-end" teleport width="xs">
                                        <x-slot name="trigger">
                                            <button type="button" class="{{ $iconLink }}" title="Aksi lain">
                                                <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-4 w-4" />
                                                <span class="sr-only">Aksi lain untuk {{ $record->client?->name }}</span>
                                            </button>
                                        </x-slot>
                                        @if ($contracts->isNotEmpty())
                                            <x-filament::dropdown.list>
                                                @foreach ($contracts as $type)
                                                    <x-filament::dropdown.list.item x-on:click="$el.closest('.fi-dropdown-panel')?.close?.()" icon="heroicon-m-document-check" wire:click="mountTableAction('{{ $type['action'] }}', '{{ $record->getKey() }}')">
                                                        Update status {{ $type['label'] === 'Bupot' ? 'PPh Unifikasi' : $type['label'] }}
                                                    </x-filament::dropdown.list.item>
                                                @endforeach
                                            </x-filament::dropdown.list>
                                        @endif
                                        <x-filament::dropdown.list>
                                            <x-filament::dropdown.list.item x-on:click="$el.closest('.fi-dropdown-panel')?.close?.()" icon="heroicon-m-banknotes" wire:click="mountTableAction('update_bayar_status', '{{ $record->getKey() }}')">
                                                Update status bayar
                                            </x-filament::dropdown.list.item>
                                        </x-filament::dropdown.list>
                                        <x-filament::dropdown.list>
                                            <x-filament::dropdown.list.item x-on:click="$el.closest('.fi-dropdown-panel')?.close?.()" icon="heroicon-m-trash" color="danger" wire:click="mountTableAction('delete', '{{ $record->getKey() }}')">
                                                Hapus laporan
                                            </x-filament::dropdown.list.item>
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-gray-200 px-5 py-3 text-xs font-semibold text-gray-500 dark:border-white/10 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><x-kis.badge tone="blue" size="sm"><x-filament::icon icon="heroicon-m-check" class="h-3 w-3" />28 Okt</x-kis.badge> Sudah lapor</span>
                <span class="inline-flex items-center gap-1.5"><span class="grid h-5 w-5 place-items-center rounded-[6px] bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"><x-filament::icon icon="heroicon-m-banknotes" class="h-3 w-3" /></span> Sudah bayar</span>
                <span class="inline-flex items-center gap-1.5"><x-kis.badge tone="red" size="sm">Belum</x-kis.badge> Belum lapor</span>
                <span class="inline-flex items-center gap-1.5"><span class="text-gray-300 dark:text-gray-600">–</span> Tidak ada kontrak</span>
            </div>
        </div>
    @endif
</div>
