<?php

namespace App\Services\ClientManagement;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Panggilan PM -> Client Management (CM). Base URL dan token bersama ada di
 * config('services.client_management').
 */
class ClientManagementClient
{
    public const KINDS = ['kontrak', 'proposal', 'lampiran'];

    public function isConfigured(): bool
    {
        return filled(config('services.client_management.url'))
            && filled(config('services.client_management.token'));
    }

    /**
     * Lampiran lead di CM (kontrak, proposal). Setiap `preview_url` adalah
     * signed URL yang hanya berlaku 5 menit, jadi jangan disimpan/di-cache.
     *
     * @return array<int, array{id:int, kind:?string, label:?string, name:string, mime:?string, size:?int, uploaded_at:?string, preview_url:?string}>
     */
    public function documents(int $cmLeadId): array
    {
        $response = $this->request()
            ->get($this->endpoint("api/pm/leads/{$cmLeadId}/documents"))
            ->throw();

        $items = $response->json('data');

        if (! is_array($items)) {
            throw new RuntimeException('Respons Client Management tidak berisi daftar dokumen.');
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item) && isset($item['id']))
            ->map(fn (array $item) => [
                'id' => (int) $item['id'],
                // Jenis baru dari CM diperlakukan sebagai lampiran biasa.
                'kind' => in_array($item['kind'] ?? null, self::KINDS, true) ? $item['kind'] : 'lampiran',
                'label' => $item['label'] ?? null,
                'name' => (string) ($item['name'] ?? 'Dokumen'),
                'mime' => $item['mime'] ?? null,
                'size' => isset($item['size']) ? (int) $item['size'] : null,
                'uploaded_at' => $item['uploaded_at'] ?? null,
                'preview_url' => $this->safeUrl($item['preview_url'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Unduh satu lampiran lewat signed preview_url-nya ke berkas lokal.
     * Signed URL tidak butuh token; yang diperiksa hanya status dan isinya.
     */
    public function download(string $previewUrl, string $targetPath): void
    {
        if ($this->safeUrl($previewUrl) === null) {
            throw new RuntimeException('Alamat unduhan tidak valid.');
        }

        $response = Http::connectTimeout(5)->timeout(60)->get($previewUrl);

        if (! $response->successful()) {
            throw new RuntimeException("Client Management menjawab HTTP {$response->status()}.");
        }

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('Berkas yang diterima kosong.');
        }

        if (file_put_contents($targetPath, $body) === false) {
            throw new RuntimeException('Berkas tidak bisa disimpan sementara di server.');
        }
    }

    /** Memberi tahu CM bahwa klien dan proyek sudah dibuat di PM. */
    public function sendHandoff(int $cmLeadId, array $payload): Response
    {
        return $this->request()->post($this->endpoint("api/pm/leads/{$cmLeadId}/handoff"), $payload);
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('CLIENT_MANAGEMENT_URL / CLIENT_MANAGEMENT_TOKEN belum diisi.');
        }

        $timeout = max(1, (int) config('services.client_management.timeout', 10));

        return Http::withToken((string) config('services.client_management.token'))
            ->acceptJson()
            ->connectTimeout(min(5, $timeout))
            ->timeout($timeout);
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) config('services.client_management.url'), '/') . '/' . ltrim($path, '/');
    }

    /** URL dari CM dipasang ke src iframe/img; tolak skema selain http(s). */
    protected function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
