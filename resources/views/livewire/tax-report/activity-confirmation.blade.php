{{--
    Was there any activity this period? PPh Unifikasi and PPh Badan are only due
    when there was; without an explicit answer the period keeps counting as
    outstanding. Marking "no activity" sets Sudah Lapor without a document, so
    who and when stay visible.
--}}
@php
    $summary = $this->summary;
    $isNihil = $this->isNoActivity;
    $sudahLapor = $summary && $summary->report_status === 'Sudah Lapor' && ! $isNihil;
    $what = $this->type === 'bupot' ? 'pemotongan ' . $this->label : 'aktivitas ' . $this->label;
@endphp

<div>
    @if ($isNihil)
        <section class="kis-card kis-ask is-settled">
            <x-heroicon-m-minus-circle class="kis-ask__icon" aria-hidden="true" />
            <div class="kis-ask__body">
                <h3 class="kis-ask__title">Tidak ada {{ $what }} pada {{ $this->periodLabel() }}</h3>
                <p class="kis-ask__text">
                    Tidak ada SPT yang wajib dilapor, jadi masa ini tidak dihitung sebagai tunggakan.
                    @if ($summary?->no_activity_at)
                        Ditandai{{ $summary->noActivityBy ? ' oleh ' . $summary->noActivityBy->name : '' }} pada {{ \App\Support\TaxPeriod::date($summary->no_activity_at) }}, {{ $summary->no_activity_at->format('H:i') }}.
                    @endif
                </p>
            </div>
            <div class="kis-ask__acts">{{ $this->clearNoActivityAction }}</div>
        </section>
    @elseif ($sudahLapor)
        <section class="kis-card kis-ask is-settled">
            <x-heroicon-m-check-circle class="kis-ask__icon is-done" aria-hidden="true" />
            <div class="kis-ask__body">
                <h3 class="kis-ask__title">SPT {{ $this->label }} {{ $this->periodLabel() }} sudah dilapor</h3>
                <p class="kis-ask__text">Ada {{ $what }} pada masa ini dan SPT-nya sudah diunggah.</p>
            </div>
        </section>
    @else
        <section class="kis-card kis-ask">
            <x-heroicon-m-question-mark-circle class="kis-ask__icon" aria-hidden="true" />
            <div class="kis-ask__body">
                <h3 class="kis-ask__title">Ada {{ $what }} pada {{ $this->periodLabel() }}?</h3>
                <p class="kis-ask__text">
                    {{ $this->label }} hanya wajib dilapor bila ada {{ $this->type === 'bupot' ? 'pemotongan' : 'aktivitas' }}.
                    Kalau ada, unggah SPT-nya setelah dilapor di Coretax. Kalau tidak, tandai supaya masa ini tidak terus dihitung sebagai tunggakan.
                </p>
            </div>
            <div class="kis-ask__acts">
                {{ $this->markNoActivityAction }}
                <x-filament::button icon="heroicon-m-arrow-up-tray" x-on:click="$wire.dispatch('open-spt-upload', { type: '{{ $type }}' })">
                    Ada, unggah SPT
                </x-filament::button>
            </div>
        </section>
    @endif

    <x-filament-actions::modals />
</div>
