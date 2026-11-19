<?php

namespace App\Support\ClientPortal;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientDocumentRequirement;
use App\Models\SopLegalDocument;

/**
 * How many documents a company still has to upload: the same rule as the
 * "Perlu Diunggah" list on Ringkasan (SOP legal documents for its client type
 * plus admin requirements that are not waived, minus those with a file).
 * Used for the count next to "Dokumen" in the portal navigation.
 */
final class PendingDocuments
{
    /** Navigation is drawn twice per page (sidebar and phone bar); count once per request. */
    public static function forCurrentClient(): int
    {
        $client = app(ClientContext::class)->client();
        $key = 'client_portal_pending_documents_' . ($client?->id ?? 0);

        return app()->has($key) ? app($key) : tap(self::count($client), fn (int $count) => app()->instance($key, $count));
    }

    public static function count(?Client $client): int
    {
        if (! $client) {
            return 0;
        }

        $uploadedSop = ClientDocument::query()
            ->where('client_id', $client->id)
            ->whereNotNull('sop_legal_document_id')
            ->whereNotNull('file_path')
            ->distinct()
            ->pluck('sop_legal_document_id');

        $sop = SopLegalDocument::query()
            ->forClientType($client->client_type)
            ->active()
            ->whereNotIn('id', $uploadedSop)
            ->count();

        $uploadedReq = ClientDocument::query()
            ->where('client_id', $client->id)
            ->whereNotNull('requirement_id')
            ->whereNotNull('file_path')
            ->distinct()
            ->pluck('requirement_id');

        $requirements = ClientDocumentRequirement::query()
            ->where('client_id', $client->id)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'waived'))
            ->whereNotIn('id', $uploadedReq)
            ->count();

        return $sop + $requirements;
    }
}
