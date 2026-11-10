<?php

namespace App\Services\ClientManagement;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientIntake;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * Menyalin lampiran lead CM (kontrak, proposal, lampiran lain) menjadi
 * dokumen klien PM. Dua tahap supaya tidak ada simpanan setengah jadi:
 * download() di luar transaksi (boleh gagal, tidak menyentuh database),
 * lalu store() di dalam transaksi pemrosesan.
 */
class IntakeDocumentImporter
{
    public const KIND_LABELS = [
        'kontrak' => 'Kontrak / surat perjanjian',
        'proposal' => 'Proposal',
        'lampiran' => 'Lampiran',
    ];

    public function __construct(protected ClientManagementClient $cm)
    {
    }

    /**
     * Unduh dokumen terpilih ke folder sementara. Daftar diambil ulang dulu
     * karena signed URL dari CM hanya berlaku 5 menit.
     *
     * @param  array<int|string>  $documentIds
     * @return array{dir: ?string, files: list<array{document: array, tmp: string}>}
     *
     * @throws IntakeDocumentException
     */
    public function download(int $cmLeadId, array $documentIds): array
    {
        $documentIds = array_values(array_unique(array_map('intval', $documentIds)));

        if ($documentIds === []) {
            return ['dir' => null, 'files' => []];
        }

        try {
            $available = collect($this->cm->documents($cmLeadId))->keyBy('id');
        } catch (Throwable $e) {
            throw new IntakeDocumentException(null, 'Daftar dokumen tidak bisa diambil dari Client Management.', $e);
        }

        $dir = storage_path('app/tmp/cm-intake/' . Str::uuid());
        Files::ensureDirectoryExists($dir);

        $files = [];

        try {
            foreach ($documentIds as $id) {
                $document = $available->get($id);

                if (! $document) {
                    throw new IntakeDocumentException(null, "Dokumen #{$id} tidak lagi tersedia di Client Management.");
                }

                if (! $document['preview_url']) {
                    throw new IntakeDocumentException($document['name'], 'Client Management tidak memberikan tautan unduhan.');
                }

                $tmp = "{$dir}/{$id}";

                try {
                    $this->cm->download($document['preview_url'], $tmp);
                } catch (Throwable $e) {
                    throw new IntakeDocumentException($document['name'], $e->getMessage(), $e);
                }

                $files[] = ['document' => $document, 'tmp' => $tmp];
            }
        } catch (Throwable $e) {
            $this->cleanup($dir);

            throw $e;
        }

        return ['dir' => $dir, 'files' => $files];
    }

    /**
     * Simpan hasil unduhan sebagai dokumen klien (disk public, folder klien,
     * kategori "additional" seperti unggahan dokumen tambahan oleh admin).
     * Kontrak pertama juga dipasang di clients.contract_file bila masih kosong.
     *
     * @param  list<array{document: array, tmp: string}>  $files
     * @param  list<string>  $written  path yang sudah ditulis, untuk dibersihkan bila transaksi gagal
     */
    public function store(Client $client, ClientIntake $intake, array $files, array &$written): int
    {
        $disk = Storage::disk('public');
        $folder = $client->getFolderPath();

        foreach ($files as $file) {
            $document = $file['document'];
            $originalName = $this->safeName($document);

            $path = $disk->putFileAs($folder, new File($file['tmp']), $this->availableName($folder, $originalName));

            if ($path === false) {
                throw new RuntimeException("Berkas {$originalName} gagal disimpan.");
            }

            $written[] = $path;

            ClientDocument::create([
                'client_id' => $client->id,
                'user_id' => auth()->id(),
                'file_path' => $path,
                'original_filename' => $originalName,
                'description' => self::KIND_LABELS[$document['kind']] . " dari Client Management (lead #{$intake->cm_lead_id})",
                'document_category' => 'additional',
                'status' => 'valid',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        }

        // Salinan terpisah: tab Kontrak menghapus berkas lama saat kontrak diganti,
        // jadi jangan berbagi berkas dengan dokumen klien di atas.
        $contract = collect($files)->first(fn ($file) => $file['document']['kind'] === 'kontrak');

        if ($contract && blank($client->contract_file)) {
            $name = Str::random(8) . '-' . $this->safeName($contract['document']);
            $path = $disk->putFileAs('client-contracts', new File($contract['tmp']), $name);

            if ($path === false) {
                throw new RuntimeException('Berkas kontrak gagal disimpan.');
            }

            $written[] = $path;
            $client->forceFill(['contract_file' => $path])->save();
        }

        return count($files);
    }

    /** Hapus berkas yang sudah ditulis ke disk public (dipakai saat transaksi gagal). */
    public function discard(array $written): void
    {
        foreach ($written as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function cleanup(?string $dir): void
    {
        if ($dir && is_dir($dir)) {
            Files::deleteDirectory($dir);
        }
    }

    /** Nama berkas dari CM tanpa path, dengan ekstensi dari MIME bila tidak ada. */
    protected function safeName(array $document): string
    {
        $name = trim(basename(str_replace('\\', '/', (string) $document['name'])));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'dokumen';

        if (pathinfo($name, PATHINFO_EXTENSION) === '' && filled($document['mime'])) {
            $extension = MimeTypes::getDefault()->getExtensions($document['mime'])[0] ?? null;
            $name .= $extension ? ".{$extension}" : '';
        }

        return Str::limit($name, 200, '');
    }

    /** Sama seperti unggahan admin: tambahkan -2, -3, … bila nama sudah dipakai. */
    protected function availableName(string $folder, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $candidate = $filename;
        $counter = 2;

        while (Storage::disk('public')->exists(trim($folder, '/') . '/' . $candidate)) {
            $candidate = $extension ? "{$name}-{$counter}.{$extension}" : "{$name}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
