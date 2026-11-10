<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\ClientResource;
use App\Http\Controllers\Controller;
use App\Models\ClientIntake;
use App\Services\ClientManagement\ClientIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint yang dipanggil Client Management (CM) saat lead mencapai Deal
 * atau Deal-nya dibatalkan. Dilindungi middleware client-management.token.
 */
class ClientIntakeController extends Controller
{
    public function __construct(protected ClientIntakeService $intakes)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cm_lead_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            // Longgar untuk entity: nilai baru dari CM tidak boleh menggagalkan serah terima.
            'entity' => ['nullable', 'string', 'max:50'],
            'client_type' => ['required', 'in:Badan,Pribadi'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            // Kolom clients.adress hanya 255; dipotong saat klien dibuat, teks lengkap tetap di payload.
            'address' => ['nullable', 'string', 'max:2000'],
            'contact' => ['nullable', 'array'],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.position' => ['nullable', 'string', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'service' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0'],
            'marketing_owner' => ['nullable', 'string', 'max:255'],
            'deal_at' => ['nullable', 'date'],
            'lead_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $intake = $this->intakes->receive($data);

        $processed = $intake->isCompleted() && $intake->client_id !== null;

        return response()->json([
            'intake_id' => $intake->id,
            'status' => 'received',
            'completed' => $intake->isCompleted(),
            'pm_client_id' => $processed ? $intake->client_id : null,
            'client_url' => $processed
                ? ClientResource::getUrl('view', ['record' => $intake->client_id], panel: 'admin')
                : $intake->reviewUrl(),
        ]);
    }

    public function withdraw(Request $request, int $cmLeadId): JsonResponse
    {
        $intake = ClientIntake::query()->where('cm_lead_id', $cmLeadId)->first();

        if (! $intake) {
            return response()->json(['message' => 'Lead ini belum pernah dikirim ke Project Management.'], 404);
        }

        $data = $request->validate([
            'reason' => ['required', 'in:mundur,ditutup'],
            'detail' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->intakes->withdraw($intake, $data);

        return response()->json(['ok' => true]);
    }
}
