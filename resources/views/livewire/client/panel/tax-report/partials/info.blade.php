{{-- ⓘ with an explanation that opens on hover and on focus (keyboard, tap). --}}
<span class="kpt-info" tabindex="0" role="button" aria-label="{{ $text }}">
    <x-heroicon-m-information-circle class="h-4 w-4" />
    <span class="kpt-info__pop" role="tooltip">{{ $text }}</span>
</span>
