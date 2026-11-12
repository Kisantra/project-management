{{--
    One credential value inside the credential table (credential-manager).
    The label is hidden on wide screens, where the column header names it,
    unless show-label is set (secondary fields such as "Kode aktivasi").
--}}
@props([
    'label',
    'wire' => null,
    'value' => null,
    'type' => 'text',
    'mode' => 'view',
    'placeholder' => '',
    'error' => null,
    // Service name for screen readers, e.g. "Kata sandi Core Tax".
    'context' => null,
    'showLabel' => false,
])

@php
    $isPassword = $type === 'password';
    $isEdit = $mode === 'edit';
    $id = 'cf-' . str_replace('.', '-', (string) $wire);
    $spoken = trim(mb_strtolower($label) . ' ' . $context);
    // A new key per value: Alpine state (the value to copy) is rebuilt after a save.
    $key = $id . '-' . $mode . ($isEdit ? '' : '-' . crc32((string) $value));
@endphp

@if ($isEdit)
    <div @class(['kis-cf', 'has-label' => $showLabel]) wire:key="{{ $key }}" x-data="{ show: false }">
        <label class="kis-cf__label" for="{{ $id }}">{{ $label }}@if ($context)<span class="sr-only"> {{ $context }}</span>@endif</label>
        <div class="kis-cf__control">
            <input id="{{ $id }}"
                   @if ($isPassword) type="password" x-bind:type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
                   wire:model="{{ $wire }}"
                   placeholder="{{ $placeholder }}"
                   autocomplete="{{ $isPassword ? 'new-password' : 'off' }}"
                   data-1p-ignore data-lpignore="true" data-form-type="other"
                   @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-err" @endif
                   @class(['kis-cf__input', 'has-eye' => $isPassword, 'is-invalid' => $error]) />
            @if ($isPassword)
                <button type="button" class="kis-cf__eye" x-on:click="show = ! show" x-bind:aria-pressed="show"
                        aria-label="Tampilkan {{ $spoken }}" x-bind:title="show ? 'Sembunyikan' : 'Tampilkan'">
                    <x-heroicon-m-eye x-show="! show" class="h-4 w-4" />
                    <x-heroicon-m-eye-slash x-show="show" x-cloak class="h-4 w-4" />
                </button>
            @endif
        </div>
        @if ($error)
            <p class="kis-cf__error" id="{{ $id }}-err">{{ $error }}</p>
        @endif
    </div>
@else
    <div @class(['kis-cf', 'has-label' => $showLabel]) wire:key="{{ $key }}"
         x-data="{ show: false, copied: false, timer: null, val: @js((string) $value) }">
        <div class="kis-cf__label">{{ $label }}@if ($context)<span class="sr-only"> {{ $context }}</span>@endif</div>
        @if (filled($value))
            <div class="kis-cf__box">
                @if ($isPassword)
                    <span class="kis-cf__value is-secret" x-bind:class="show && 'is-shown'" x-text="show ? val : '••••••••••'">••••••••••</span>
                @else
                    <span class="kis-cf__value" title="{{ $value }}">{{ $value }}</span>
                @endif
                <span class="kis-cf__actions">
                    @if ($isPassword)
                        <button type="button" class="kis-cf__btn" x-on:click="show = ! show" x-bind:aria-pressed="show"
                                aria-label="Tampilkan {{ $spoken }}" x-bind:title="show ? 'Sembunyikan' : 'Tampilkan'">
                            <x-heroicon-m-eye x-show="! show" class="h-4 w-4" />
                            <x-heroicon-m-eye-slash x-show="show" x-cloak class="h-4 w-4" />
                        </button>
                    @endif
                    <button type="button" class="kis-cf__btn" x-bind:class="copied && 'is-done'" title="Salin" aria-label="Salin {{ $spoken }}"
                            x-on:click="kisCopy(val).then(() => { copied = true; clearTimeout(timer); timer = setTimeout(() => copied = false, 1600) })">
                        <x-heroicon-m-document-duplicate x-show="! copied" class="h-4 w-4" />
                        <x-heroicon-m-check x-show="copied" x-cloak class="h-4 w-4" />
                    </button>
                    <span class="kis-cf__toast" x-show="copied" x-transition.opacity.duration.150ms x-cloak aria-hidden="true">Tersalin</span>
                    <span class="sr-only" aria-live="polite" x-text="copied ? 'Tersalin' : ''"></span>
                </span>
            </div>
        @else
            <div class="kis-cf__empty">Belum diisi</div>
        @endif
    </div>
@endif
