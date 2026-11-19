<?php

namespace App\Livewire\Client\Panel;

use App\Support\ClientPortal\ClientContext;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Company picker of the client portal: a card at the top of the sidebar
 * (variant "sidebar") and a compact button in the topbar on phones
 * (variant "topbar"). Switching reloads the page, so every tab mounts again
 * with the new company from ClientContext instead of listening for an event
 * that carries a client id.
 */
class ClientSwitcher extends Component
{
    #[Locked]
    public string $variant = 'sidebar';

    public function switchClient(int $clientId): void
    {
        $context = app(ClientContext::class);

        if ($clientId === $context->id() || ! $context->switchTo($clientId)) {
            return;
        }

        $panel = filament()->getPanel('klien');
        $back = url()->previous();

        // Stay on the same section; only follow the referer when it points into the portal itself.
        $this->redirect(str_starts_with($back, url($panel->getPath()) . '/') ? $back : $panel->getUrl());
    }

    public function render()
    {
        $context = app(ClientContext::class);

        return view('livewire.client.panel.client-switcher', [
            'clients' => $context->clients(),
            'selected' => $context->client(),
        ]);
    }
}
