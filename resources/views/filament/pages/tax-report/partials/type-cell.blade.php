{{-- One cell of the tax-type strip (ReportOverview::cards()). --}}
<span class="kis-trs__top">
    <span class="kis-trs__label">{{ $card['label'] }}</span>
    <x-kis.badge :tone="$card['tone']" :dot="! $card['locked']" size="sm">
        @if ($card['locked'])
            <x-heroicon-m-lock-closed class="h-3 w-3" />
        @endif
        {{ $card['statusLabel'] }}
    </x-kis.badge>
</span>
<span class="kis-trs__value">
    <b>{{ $card['value'] }}</b>
    @if ($card['note'])
        <span @class(['is-bad' => $card['noteTone'] === 'red', 'is-good' => $card['noteTone'] === 'blue'])>{{ $card['note'] }}</span>
    @endif
</span>
<span class="kis-trs__context">{{ $card['context'] }}</span>
