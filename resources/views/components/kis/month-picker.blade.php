{{--
    Month multi-select (DESIGN.md, Month multi-select). The model value is
    ['year' => 2026, 'months' => [9, 10, 11]]; an empty list means all months.
    `counts` is [year => [month => count]] and decides which years can be
    browsed and which months can be picked. Changes apply immediately.

    <x-kis.month-picker wire:model.live="period" :counts="$this->monthCounts" />
--}}
@props([
    'counts' => [],
    'label' => 'Periode',
    'unit' => 'laporan',
    'now' => null,
])

@php
    $now ??= now();
    $monthsShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $presets = ['1' => 'Q1', '2' => 'Q2', '3' => 'Q3', '4' => 'Q4', 'all' => 'Setahun'];
@endphp

<div
    x-data="kisMonthPicker({ counts: @js((object) $counts), nowYear: {{ $now->year }}, nowMonth: {{ $now->month }} })"
    x-modelable="value"
    x-on:keydown.escape.window="close(true)"
    x-on:click.outside="close()"
    {{ $attributes->class(['kis-mp']) }}
>
    <button
        type="button"
        x-ref="trigger"
        class="kis-mp__trigger"
        x-on:click="toggle()"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
    >
        <svg class="kis-mp__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
        <span class="kis-mp__key">{{ $label }}</span>
        <span class="kis-mp__value" x-text="summary()"></span>
        <span class="kis-mp__n" x-show="months.length > 1" x-cloak x-text="months.length"></span>
        <svg class="kis-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
    </button>

    <div
        class="kis-pop kis-mp__pop"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="dialog"
        aria-label="Pilih bulan"
    >
        <div class="kis-mp__head">
            <button type="button" class="kis-mp__step" x-on:click="stepYear(-1)" x-bind:disabled="!hasYear(year - 1)" aria-label="Tahun sebelumnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </button>
            <div class="kis-mp__year" aria-live="polite">
                <b x-text="year"></b>
                <span x-text="total() + ' {{ $unit }}'"></span>
            </div>
            <button type="button" class="kis-mp__step" x-on:click="stepYear(1)" x-bind:disabled="!hasYear(year + 1)" aria-label="Tahun berikutnya">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>

        <div class="kis-mp__presets">
            @foreach ($presets as $preset => $presetLabel)
                <button
                    type="button"
                    x-on:click="applyPreset(@js($preset))"
                    x-bind:class="{ 'is-on': isPresetActive(@js($preset)) }"
                    x-bind:aria-pressed="isPresetActive(@js($preset))"
                    x-bind:disabled="presetMonths(@js($preset)).length === 0"
                >{{ $presetLabel }}</button>
            @endforeach
        </div>

        <div class="kis-mp__grid" x-ref="grid" role="group" aria-label="Bulan" x-on:keydown="onGridKeydown($event)">
            @foreach ($monthsShort as $index => $short)
                @php $month = $index + 1; @endphp
                <button
                    type="button"
                    class="kis-mp__cell"
                    x-on:click="toggleMonth({{ $month }}, $event)"
                    x-bind:class="{ 'is-on': isSelected({{ $month }}), 'is-now': isNow({{ $month }}) }"
                    x-bind:aria-pressed="isSelected({{ $month }})"
                    x-bind:disabled="count({{ $month }}) === 0"
                    x-bind:title="monthTitle({{ $month }})"
                >
                    <span class="kis-mp__cell-name">{{ $short }}</span>
                    <span class="kis-mp__cell-count" x-text="count({{ $month }}) ? count({{ $month }}) + ' {{ $unit }}' : 'Tidak ada'"></span>
                    <span class="kis-mp__check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                </button>
            @endforeach
        </div>

        <p class="kis-mp__tip"><kbd>Shift</kbd> + klik untuk memilih rentang bulan.</p>

        <div class="kis-pop__foot kis-mp__foot">
            <span x-show="months.length > 0"><b x-text="months.length"></b> bulan dipilih</span>
            <span x-show="months.length === 0">Semua bulan</span>
            <button type="button" class="kis-btn kis-btn--outline kis-btn--sm" x-on:click="reset()">Reset</button>
            <button type="button" class="kis-btn kis-btn--primary kis-btn--sm" x-on:click="close(true)">Selesai</button>
        </div>
    </div>
</div>
