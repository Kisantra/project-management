{{--
    Cek & proses: a summary of the deal on top (stays visible on every wizard
    step), the cancellation banner, then the wizard form.
--}}
@php
    use App\Filament\Resources\ClientResource;
    use App\Models\ClientIntake;
    use App\Support\Ui\ClientAvatar;

    $intake = $this->getIntake()->loadMissing('suggestedClient', 'client');
    $date = fn ($value) => $value?->locale('id')->translatedFormat('j M Y');
    $waited = $intake->waitingDays();
    $status = match (true) {
        $intake->isWithdrawn() => ['Batal di CM', 'red'],
        $intake->isCompleted() => ['Selesai', 'blue'],
        $intake->isOverdue() => ['Menunggu ' . $waited . ' hari', 'red'],
        default => ['Menunggu dicek', 'outline'],
    };
    $line = collect([
        $intake->deal_at ? 'Deal ' . $date($intake->deal_at) . (filled($intake->marketing_owner) ? ' oleh ' . $intake->marketing_owner : '') : (filled($intake->marketing_owner) ? 'Deal oleh ' . $intake->marketing_owner : null),
        'diterima ' . ($waited === 0 ? 'hari ini' : $waited . ' hari lalu'),
    ])->filter()->implode(' · ');
@endphp

<x-filament-panels::page>
    <section class="kis-card kis-intake-sum" aria-label="Ringkasan deal">
        <div class="kis-intake-sum__bar">
            <span class="kis-gtile" aria-hidden="true">{{ ClientAvatar::initials($intake->name) }}</span>
            <div class="kis-intake-sum__who">
                <b>{{ $intake->name }}</b>
                <span>{{ $line }}</span>
            </div>
            <x-kis.badge :tone="$status[1]" dot>{{ $status[0] }}</x-kis.badge>
            @if (filled($intake->lead_url))
                <a href="{{ $intake->lead_url }}" target="_blank" rel="noopener" class="kis-btn kis-btn--sm kis-btn--outline">
                    Lihat lead di CM <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                </a>
            @endif
        </div>
        <dl class="kis-intake-sum__facts">
            <div><dt>Layanan</dt><dd>{{ $intake->service ?: '–' }}</dd></div>
            <div><dt>Nilai deal</dt><dd>{{ ClientIntake::formatRupiah($intake->deal_value) ?? '–' }}</dd></div>
            <div><dt>Bentuk usaha</dt><dd>{{ collect([$intake->entity, $intake->sent('client_type')])->filter()->implode(' · ') ?: '–' }}</dd></div>
            <div>
                <dt>Di PM</dt>
                <dd>
                    @if ($intake->client)
                        <a href="{{ ClientResource::getUrl('view', ['record' => $intake->client]) }}">{{ $intake->client->name }}</a>
                    @elseif ($intake->suggestedClient)
                        Mungkin klien lama: {{ $intake->suggestedClient->name }}
                    @else
                        Klien baru
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    {{-- Cancelled in CM: before processing it cannot be processed; afterwards the client and project stay. --}}
    @if ($intake->isWithdrawn())
        <div role="alert" class="kis-callout is-red kis-intake-alert">
            <x-heroicon-m-x-circle class="kis-callout__icon" />
            <div class="kis-callout__body">
                <p class="kis-callout__title">
                    Deal dibatalkan di Client Management{{ filled($intake->withdrawn_detail) ? ': ' . $intake->withdrawn_detail : '' }}
                </p>
                <p>
                    Dibatalkan {{ $intake->withdrawn_at->locale('id')->translatedFormat('j M Y H.i') }}.
                    @if (filled($intake->withdrawn_note))
                        Catatan: {{ $intake->withdrawn_note }}
                    @endif
                </p>
                @if ($intake->isCompleted())
                    <p>Klien dan proyeknya sudah dibuat dan tidak dihapus. Tinjau apakah proyek perlu dihentikan atau status klien diubah.</p>
                @endif
            </div>
        </div>
    @endif

    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.data'"
        wire:submit="process"
    >
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
