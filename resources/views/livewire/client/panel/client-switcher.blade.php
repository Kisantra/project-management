{{--
    Company picker (ClientSwitcher). Sidebar: a card at the top of the nav that
    unfolds the list in place (the sidebar scrolls, so a floating popover would
    be clipped). Topbar (phones only): a compact button with a popover.
--}}
@php
    use App\Support\ClientPortal\ClientContext;
    use Illuminate\Support\Facades\Storage;

    $many = $clients->count() > 1;
    $position = $selected ? $clients->search(fn ($c) => $c->id === $selected->id) + 1 : 0;
@endphp

<div @class(['kp-switch', 'kp-switch--' . $variant]) x-data="{ open: false }" x-on:keydown.escape="open = false">
    @if ($selected)
        <button type="button" class="kp-switch__card"
                @if ($many) x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="listbox" @else disabled @endif
                title="{{ $selected->name }}">
            <span class="kp-switch__tile">
                @if ($selected->logo)
                    <img src="{{ Storage::url($selected->logo) }}" alt="" onerror="this.remove()">
                @endif
                {{ ClientContext::initials($selected->name) }}
            </span>
            <span class="kp-switch__text">
                <b>{{ $selected->name }}</b>
                <small>{{ $many ? $position . ' dari ' . $clients->count() . ' perusahaan' : 'Perusahaan Anda' }}</small>
            </span>
            @if ($many)
                <x-heroicon-m-chevron-up-down class="kp-switch__chev" />
            @endif
        </button>

        @if ($many)
            <div class="kp-switch__menu" x-show="open" x-cloak x-on:click.outside="open = false"
                 x-transition.opacity.duration.150ms role="listbox" aria-label="Ganti perusahaan">
                <p class="kp-switch__label">Ganti perusahaan</p>
                @foreach ($clients as $client)
                    <button type="button" role="option" @class(['kp-switch__opt', 'is-on' => $client->id === $selected->id])
                            aria-selected="{{ $client->id === $selected->id ? 'true' : 'false' }}"
                            wire:click="switchClient({{ $client->id }})" wire:loading.attr="disabled">
                        <span class="kp-switch__tile kp-switch__tile--sm">{{ ClientContext::initials($client->name) }}</span>
                        <span class="kp-switch__name">{{ $client->name }}</span>
                        @if ($client->id === $selected->id)
                            <x-heroicon-m-check class="kp-switch__check" />
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    @endif
</div>
