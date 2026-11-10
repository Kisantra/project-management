<?php

namespace App\Jobs;

use App\Models\ClientIntake;
use App\Services\ClientManagement\ClientManagementClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Memberi tahu Client Management bahwa klien dan proyek sudah dibuat di PM
 * (CM lalu memindahkan lead ke "Client aktif"). Lewat antrean supaya CM yang
 * sedang mati tidak menggagalkan penyimpanan di PM.
 */
class SendHandoffToClientManagement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /**
     * @param  array<string, mixed>  $payload  body sesuai kontrak, disusun saat ditandai lengkap
     */
    public function __construct(public int $intakeId, public array $payload)
    {
    }

    /** Jeda antar percobaan: 30 dtk, 2 mnt, 10 mnt, 30 mnt, 1 jam. */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    public function handle(ClientManagementClient $cm): void
    {
        $intake = ClientIntake::find($this->intakeId);

        // Klien (dan serah terimanya) sudah dihapus: tidak ada yang dikirim.
        if (! $intake) {
            return;
        }

        if (! $cm->isConfigured()) {
            $intake->update(['handoff_error' => 'Integrasi Client Management belum dikonfigurasi (CLIENT_MANAGEMENT_URL / CLIENT_MANAGEMENT_TOKEN).']);

            return;
        }

        try {
            $response = $cm->sendHandoff($intake->cm_lead_id, $this->payload);
        } catch (ConnectionException $e) {
            $intake->update(['handoff_error' => 'Client Management tidak dapat dihubungi: ' . $e->getMessage()]);

            throw $e;
        }

        if ($response->successful()) {
            $intake->update(['handoff_sent_at' => now(), 'handoff_error' => null]);

            return;
        }

        $error = "Client Management menjawab HTTP {$response->status()}: " . Str::limit($response->body(), 300);
        $intake->update(['handoff_error' => $error]);

        // 4xx berarti permintaannya yang ditolak (lead tidak dikenal, token
        // salah); mengulang tidak akan membantu. 408/429 tetap diulang.
        if ($response->clientError() && ! in_array($response->status(), [408, 429], true)) {
            $this->fail(new RuntimeException($error));

            return;
        }

        $response->throw();
    }

    public function failed(?Throwable $e): void
    {
        $intake = ClientIntake::find($this->intakeId);

        if ($intake && ! $intake->handoff_sent_at && blank($intake->handoff_error)) {
            $intake->update(['handoff_error' => Str::limit((string) $e?->getMessage(), 500)]);
        }
    }
}
