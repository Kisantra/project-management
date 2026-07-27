@php
    // Skala bar relatif terhadap bruto tertinggi di daftar, supaya perbandingan
    // antar klien langsung terbaca tanpa membandingkan angka satu per satu.
    $peak = $rows->max('bruto') ?: 1;
@endphp

<section class="tp-panel" aria-labelledby="tp-topclients-heading">

    <div class="tp-panel-header flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-3.5">
        <h2 id="tp-topclients-heading" class="tp-panel-title">Klien terbesar</h2>
        <div class="flex items-center gap-3">
            <p style="font-size: var(--tp-size-xs); color: var(--tp-text-muted); white-space: nowrap;">
                Peredaran bruto {{ $periodLabel }}
            </p>
            {{-- Toggle rentang: akumulasi setahun vs hanya masa terpilih. --}}
            <div class="tp-seg tp-seg--text" role="group" aria-label="Rentang peredaran bruto">
                <button type="button" wire:click="setScope('year')"
                        aria-pressed="{{ $scope === 'year' ? 'true' : 'false' }}"
                        title="Akumulasi setahun">Tahunan</button>
                <button type="button" wire:click="setScope('month')"
                        aria-pressed="{{ $scope === 'month' ? 'true' : 'false' }}"
                        title="Hanya masa terpilih">Per masa</button>
            </div>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="px-5 py-10 text-center">
            <x-heroicon-o-banknotes class="mx-auto h-6 w-6" style="color: var(--tp-text-faint);" aria-hidden="true" />
            <p class="mt-3 font-medium" style="font-size: var(--tp-size-sm); color: var(--tp-text);">
                Belum ada peredaran bruto tercatat untuk {{ $scope === 'month' ? 'masa' : 'tahun' }} {{ $periodLabel }}
            </p>
            <p class="mx-auto mt-1 max-w-md" style="font-size: var(--tp-size-xs); color: var(--tp-text-muted);">
                Angka ini terisi dari Faktur Keluaran yang diinput pada laporan pajak {{ $scope === 'month' ? 'masa' : 'tahun' }} tersebut.
            </p>
        </div>
    @else
        {{-- Kepala kolom, hanya layar lebar. --}}
        <div class="hidden lg:grid grid-cols-[1.5rem_minmax(0,1fr)_9rem_minmax(0,10rem)] gap-4 px-5 py-2"
             style="border-bottom: 1px solid var(--tp-border); background: var(--tp-surface-sunken);"
             aria-hidden="true">
            <span class="tp-label tp-label-quiet text-right">#</span>
            <span class="tp-label tp-label-quiet">Klien</span>
            <span class="tp-label tp-label-quiet text-right">Peredaran bruto</span>
            <span class="tp-label tp-label-quiet">Penanggung jawab</span>
        </div>

        <ul role="list">
            @foreach ($rows as $row)
                @php $h = $row['handler']; @endphp

                <li style="border-bottom: 1px solid var(--tp-border);">
                    <a href="{{ $row['url'] }}"
                       class="tp-row tp-focus grid grid-cols-[1.5rem_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-5 py-3
                              lg:grid-cols-[1.5rem_minmax(0,1fr)_9rem_minmax(0,10rem)]">

                        <span class="tp-num text-right font-semibold"
                              style="font-size: var(--tp-size-sm); color: var(--tp-text-muted);">
                            {{ $loop->iteration }}
                        </span>

                        <span class="min-w-0">
                            <span class="block truncate font-medium"
                                  style="font-size: var(--tp-size-base); color: var(--tp-text);"
                                  title="{{ $row['name'] }}">
                                {{ $row['name'] }}
                            </span>
                            {{-- Bar bruto relatif, langsung di bawah nama supaya
                                 skala antar klien terbaca sekali pandang. --}}
                            <span class="mt-1 flex items-center gap-2">
                                <span class="h-1 min-w-0 max-w-[9rem] flex-1 overflow-hidden rounded-full"
                                      style="background: var(--tp-surface-sunken); box-shadow: inset 0 0 0 1px var(--tp-border);"
                                      aria-hidden="true">
                                    <span class="block h-full rounded-full"
                                          style="width: {{ max(round($row['bruto'] / $peak * 100, 1), 2) }}%; background: var(--tp-accent-solid);"></span>
                                </span>
                                <span class="tp-num shrink-0" style="font-size: var(--tp-size-2xs); color: var(--tp-text-muted);">
                                    {{ $row['faktur'] }} faktur
                                </span>
                            </span>
                        </span>

                        <span class="tp-num col-start-3 row-start-1 text-right font-semibold lg:col-start-3"
                              style="font-size: var(--tp-size-sm); color: var(--tp-text);"
                              title="{{ $this->formatBrutoFull($row['bruto']) }}">
                            {{ $this->formatBruto($row['bruto']) }}
                        </span>

                        {{-- Penanggung jawab: siapa yang paling banyak menginput
                             faktur klien ini pada masa tersebut. --}}
                        <span class="col-start-2 row-start-2 flex items-center gap-2 lg:col-start-4 lg:row-start-1">
                            @if ($h)
                                @if ($h['avatar'])
                                    <img src="/{{ ltrim($h['avatar'], '/') }}"
                                         alt=""
                                         class="h-6 w-6 shrink-0 rounded-full object-cover"
                                         style="box-shadow: 0 0 0 1px var(--tp-border);"
                                         aria-hidden="true">
                                @else
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
                                          style="background: var(--tp-accent-bg); color: var(--tp-accent-text); font-size: 0.625rem; font-weight: 600;"
                                          aria-hidden="true">
                                        {{ $this->initials($h['name']) }}
                                    </span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate" style="font-size: var(--tp-size-sm); color: var(--tp-text);">
                                        {{ $h['name'] }}
                                    </span>
                                    @if ($h['others'] > 0)
                                        <span style="font-size: var(--tp-size-2xs); color: var(--tp-text-muted);">
                                            +{{ $h['others'] }} lainnya
                                        </span>
                                    @endif
                                </span>
                            @else
                                <span style="font-size: var(--tp-size-xs); color: var(--tp-text-faint);">
                                    Belum tercatat
                                </span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($hasMore || $showAll)
            <div class="px-5 py-2.5">
                <button type="button" wire:click="toggleShowAll" class="tp-btn tp-btn-ghost w-full">
                    {{ $showAll ? 'Ringkas jadi ' . \App\Livewire\TaxReport\Dashboard\TopClientsRevenue::PREVIEW_LIMIT . ' teratas' : 'Tampilkan lebih banyak' }}
                </button>
            </div>
        @endif

        <p class="px-5 pb-3.5 pt-1" style="font-size: var(--tp-size-xs); color: var(--tp-text-muted);">
            @if ($scope === 'month')
                Peredaran bruto masa {{ $periodLabel }} dari Faktur Keluaran. Penanggung jawab diambil dari siapa yang paling banyak menginput faktur klien tersebut pada masa itu.
            @else
                Peredaran bruto akumulatif setahun {{ $periodLabel }} dari Faktur Keluaran. Penanggung jawab diambil dari siapa yang paling banyak menginput faktur klien tersebut sepanjang tahun.
            @endif
        </p>
    @endif
</section>
