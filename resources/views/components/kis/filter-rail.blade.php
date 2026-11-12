{{--
    One-row filter rail with a searchable overflow (DESIGN.md, SOP filter and
    The One-Row Filter Rule). Options keep the order they are given in; pass
    them already sorted (SOP: fixed order set by admins).

    <x-kis.filter-rail
        wire:model.live="sop"
        label="SOP"
        :all-count="139"
        :options="[['value' => 3, 'label' => 'Laporan Keuangan', 'count' => 25], ...]"
        search-placeholder="Cari SOP…"
    />

    Add `multiple` to let several options be ticked: the model is then a list
    and "Semua" clears it.

    Options are read when the component starts. If they can change between
    Livewire renders, give the tag a wire:key that changes with them.
--}}
@props([
    'options' => [],
    'label' => null,
    'allLabel' => 'Semua',
    'allCount' => null,
    'searchPlaceholder' => 'Cari…',
    'emptyText' => 'Tidak ada yang cocok',
    'multiple' => false,
])

@php
    $options = collect($options)
        ->map(fn ($option) => [
            'value' => $option['value'],
            'label' => (string) $option['label'],
            'count' => $option['count'] ?? null,
        ])
        ->values();
    $maxCount = max(1, (int) $options->max('count'));
    $labelId = 'kis-rail-' . \Illuminate\Support\Str::random(8);
    // Single choice behaves as a radio group; multiple choice as toggle buttons.
    $chipRole = $multiple ? null : 'radio';
    $chipState = $multiple ? 'aria-pressed' : 'aria-checked';
@endphp

<div
    x-data="kisFilterRail({ options: @js($options), allLabel: @js($allLabel), multiple: @js((bool) $multiple) })"
    x-modelable="value"
    x-on:keydown.escape.window="close(true)"
    x-on:click.outside="close()"
    {{ $attributes->class(['kis-rail']) }}
>
    @if ($label)
        <span class="kis-rail__label" id="{{ $labelId }}">{{ $label }}</span>
    @endif

    <div class="kis-rail__wrap" x-ref="wrap">
        <div class="kis-rail__row" x-ref="row" role="{{ $multiple ? 'group' : 'radiogroup' }}" @if ($label) aria-labelledby="{{ $labelId }}" @endif>
            <button
                type="button"
                @if ($chipRole) role="{{ $chipRole }}" @endif
                data-key=""
                class="kis-rail__chip"
                x-bind:class="{ 'is-on': isSelected(null), 'is-hidden': !isShown('') }"
                x-bind:{{ $chipState }}="isSelected(null)"
                x-on:click="select(null)"
            >{{ $allLabel }}@if (filled($allCount))<em>{{ $allCount }}</em>@endif</button>

            @foreach ($options as $option)
                @php $key = (string) $option['value']; @endphp
                <button
                    type="button"
                    @if ($chipRole) role="{{ $chipRole }}" @endif
                    data-key="{{ $key }}"
                    class="kis-rail__chip"
                    x-bind:class="{ 'is-on': isSelected(@js($option['value'])), 'is-pinned': isPinned(@js($key)), 'is-hidden': !isShown(@js($key)) }"
                    x-bind:{{ $chipState }}="isSelected(@js($option['value']))"
                    x-on:click="select(@js($option['value']))"
                >{{ $option['label'] }}@if (filled($option['count']))<em>{{ $option['count'] }}</em>@endif</button>
            @endforeach
        </div>

        <div class="kis-rail__more">
            <button
                type="button"
                x-ref="more"
                class="kis-rail__more-btn"
                x-show="hiddenCount > 0"
                x-cloak
                x-on:click="toggle()"
                x-bind:aria-expanded="open"
                aria-haspopup="dialog"
            >
                <span x-text="'+' + hiddenCount + ' lainnya'"></span>
                <svg class="kis-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>

            <div
                class="kis-pop kis-pop--end kis-rail__pop"
                x-show="open"
                x-cloak
                x-transition.opacity.duration.150ms
                role="dialog"
                @if ($label) aria-label="Semua {{ $label }}" @endif
            >
                <label class="kis-pop__search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <input
                        type="text"
                        x-ref="search"
                        x-model="query"
                        x-on:input="highlighted = 0"
                        x-on:keydown="onSearchKeydown($event)"
                        placeholder="{{ $searchPlaceholder }}"
                        autocomplete="off"
                        aria-label="{{ $searchPlaceholder }}"
                    >
                </label>

                <ul class="kis-rail__list" x-ref="list" role="listbox" @if ($multiple) aria-multiselectable="true" @endif>
                    <li class="kis-rail__section" style="order: 0" x-show="hasSection(false)">Belum tampil di baris</li>
                    <li class="kis-rail__section" style="order: 999" x-show="hasSection(true)">Sudah di baris</li>

                    @foreach ($options as $option)
                        @php $key = (string) $option['value']; @endphp
                        <li
                            role="option"
                            class="kis-rail__option"
                            x-show="matches({ label: @js($option['label']) })"
                            x-bind:style="{ order: listOrder(@js($key)) }"
                            x-bind:class="{ 'is-on': isSelected(@js($option['value'])) }"
                            x-bind:aria-selected="isSelected(@js($option['value']))"
                            x-bind:data-highlighted="isHighlighted(@js($key))"
                            x-on:mousemove="highlighted = listKeys().indexOf(@js($key))"
                            x-on:click="pick(@js($option['value']))"
                        >
                            <span @class(['kis-rail__radio', 'kis-rail__radio--check' => $multiple]) aria-hidden="true"></span>
                            <span class="kis-rail__name"><span x-text="parts(@js($option['label']))[0]"></span><mark x-show="parts(@js($option['label']))[1]" x-text="parts(@js($option['label']))[1]"></mark><span x-text="parts(@js($option['label']))[2]"></span></span>
                            @if (filled($option['count']))
                                <span class="kis-rail__bar" aria-hidden="true"><i style="width: {{ max(6, round($option['count'] / $maxCount * 100)) }}%"></i></span>
                                <span class="kis-rail__count">{{ $option['count'] }}</span>
                            @endif
                        </li>
                    @endforeach

                    <li class="kis-rail__empty" style="order: 2000" x-show="listKeys().length === 0">{{ $emptyText }}</li>
                </ul>

                <div class="kis-pop__foot">
                    <span>{{ $options->count() }} pilihan</span>
                    <span><kbd>↑</kbd> <kbd>↓</kbd> pilih · <kbd>Enter</kbd></span>
                </div>
            </div>
        </div>
    </div>
</div>
