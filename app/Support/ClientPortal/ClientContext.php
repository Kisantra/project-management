<?php

namespace App\Support\ClientPortal;

use App\Models\Client;
use App\Models\UserClient;
use Illuminate\Support\Collection;

/**
 * The company a client-portal user is looking at (/klien).
 *
 * A user can be linked to several companies through user_clients. The choice
 * lives in the session and is only ever written by switchTo(), which checks
 * ownership, so every tab reads the same company and none of them takes a
 * client id from the browser. Bound as a scoped singleton: the lookups run
 * once per request.
 */
final class ClientContext
{
    public const SESSION_KEY = 'client_panel_selected_client_id';

    private ?Collection $ids = null;

    private ?Collection $clients = null;

    /** @return Collection<int, int> */
    public function clientIds(): Collection
    {
        return $this->ids ??= auth()->check()
            ? UserClient::query()
                ->where('user_id', auth()->id())
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();
    }

    /** Companies of the signed-in user, by name (for the switcher). */
    public function clients(): Collection
    {
        return $this->clients ??= $this->clientIds()->isEmpty()
            ? collect()
            : Client::query()
                ->whereIn('id', $this->clientIds())
                ->select(['id', 'name', 'logo', 'status', 'client_type'])
                ->orderBy('name')
                ->get();
    }

    public function owns(int|string|null $clientId): bool
    {
        return filled($clientId) && $this->clientIds()->contains((int) $clientId);
    }

    /** The selected company, falling back to the first one by name. */
    public function id(): ?int
    {
        $stored = session(self::SESSION_KEY);

        if ($this->owns($stored)) {
            return (int) $stored;
        }

        $first = $this->clients()->first()?->id;

        if ($first) {
            session([self::SESSION_KEY => $first]);
        }

        return $first;
    }

    public function client(): ?Client
    {
        $id = $this->id();

        return $id ? $this->clients()->firstWhere('id', $id) : null;
    }

    public function switchTo(int $clientId): bool
    {
        if (! $this->owns($clientId)) {
            return false;
        }

        session([self::SESSION_KEY => $clientId]);

        return true;
    }

    /** Two letters for a company tile: "PT. Daya Taka Kreasi Bersama" -> "DT". */
    public static function initials(?string $name): string
    {
        $words = collect(preg_split('/[\s.,]+/', strtoupper((string) $name)))
            ->filter()
            ->reject(fn (string $word) => in_array($word, ['PT', 'CV', 'TBK', 'UD', 'KOPERASI', 'YAYASAN', 'PERSERO'], true))
            ->values();

        return $words->take(2)->map(fn (string $word) => mb_substr($word, 0, 1))->implode('') ?: 'K';
    }

    /** Stops the request when the id is not one of the user's companies. */
    public function authorize(int|string|null $clientId): void
    {
        abort_unless($this->owns($clientId), 403);
    }
}
