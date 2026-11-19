<?php

namespace App\Livewire\Client\Panel\Concerns;

use App\Models\Client;
use App\Support\ClientPortal\ClientContext;
use Livewire\Attributes\Locked;

/**
 * For client-portal components: the company comes from ClientContext (the
 * session), never from the browser. The id is locked so it can't be set from
 * the client, and it is re-checked on every request in case the user lost
 * access to the company in between.
 */
trait UsesCurrentClient
{
    #[Locked]
    public ?int $selectedClientId = null;

    /** Runs before the component's own mount(), so mount() can already use the id. */
    public function initializeUsesCurrentClient(): void
    {
        $this->selectedClientId ??= $this->clientContext()->id();
    }

    public function hydrateUsesCurrentClient(): void
    {
        if ($this->selectedClientId !== null) {
            $this->clientContext()->authorize($this->selectedClientId);
        }
    }

    protected function clientContext(): ClientContext
    {
        return app(ClientContext::class);
    }

    protected function currentClient(): ?Client
    {
        return $this->selectedClientId
            ? $this->clientContext()->clients()->firstWhere('id', $this->selectedClientId)
            : null;
    }
}
