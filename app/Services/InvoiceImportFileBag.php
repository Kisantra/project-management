<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Indexes the files uploaded with a JSON invoice import by their base name.
 *
 * Accepts loose PDF/JPG/PNG files and ZIP archives. ZIP entries are read in
 * place, never extracted to disk, and folders inside the archive are ignored:
 * only the base name is used for matching.
 */
class InvoiceImportFileBag
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_ZIP_ENTRIES = 500;

    private const MAX_ENTRY_BYTES = 20 * 1024 * 1024;

    private const MAX_TOTAL_BYTES = 300 * 1024 * 1024;

    /**
     * Holds the upload objects, not just their paths: a temporary upload's file
     * can be removed once nothing references the object any more.
     *
     * @var array<string, array{name: string, upload: UploadedFile, entry: ?string}> keyed by lowercase base name
     */
    private array $files = [];

    /** @var array<int, string> base names that appeared more than once; the first one wins */
    private array $duplicates = [];

    /**
     * @param  array<int, UploadedFile>  $uploads
     */
    public static function fromUploads(array $uploads): self
    {
        $bag = new self();
        $totalBytes = 0;

        foreach ($uploads as $upload) {
            $extension = strtolower($upload->getClientOriginalExtension());

            if ($extension === 'zip') {
                $bag->addZip($upload, $totalBytes);

                continue;
            }

            if (in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $bag->add($upload->getClientOriginalName(), $upload, null);
            }
        }

        return $bag;
    }

    /**
     * @return array<int, string> original base names, in upload order
     */
    public function names(): array
    {
        return array_values(array_map(fn (array $file) => $file['name'], $this->files));
    }

    /**
     * @return array<int, string>
     */
    public function duplicates(): array
    {
        return array_values(array_unique($this->duplicates));
    }

    public function read(string $name): string
    {
        $file = $this->files[mb_strtolower($name)] ?? null;

        if ($file === null) {
            throw new RuntimeException("File {$name} tidak ditemukan di unggahan.");
        }

        $path = $file['upload']->getRealPath();

        if ($path === false) {
            $contents = false;
        } elseif ($file['entry'] === null) {
            $contents = file_get_contents($path);
        } else {
            $zip = new ZipArchive();
            $contents = $zip->open($path) === true ? $zip->getFromName($file['entry']) : false;
            $zip->close();
        }

        if ($contents === false) {
            throw new RuntimeException("File {$name} tidak dapat dibaca.");
        }

        return $contents;
    }

    private function add(string $name, UploadedFile $upload, ?string $entry): void
    {
        $key = mb_strtolower($name);

        if (isset($this->files[$key])) {
            $this->duplicates[] = $name;

            return;
        }

        $this->files[$key] = ['name' => $name, 'upload' => $upload, 'entry' => $entry];
    }

    private function addZip(UploadedFile $upload, int &$totalBytes): void
    {
        $zip = new ZipArchive();
        $zipName = $upload->getClientOriginalName();

        if ($zip->open((string) $upload->getRealPath()) !== true) {
            throw new RuntimeException("ZIP {$zipName} rusak atau tidak dapat dibuka.");
        }

        if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
            $zip->close();

            throw new RuntimeException("ZIP {$zipName} berisi lebih dari " . self::MAX_ZIP_ENTRIES . ' file.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entry = $stat['name'] ?? '';
            $baseName = basename(str_replace('\\', '/', $entry));
            $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

            // Skip folders, macOS resource forks and hidden files.
            if (str_ends_with($entry, '/') || str_starts_with($entry, '__MACOSX/') || str_starts_with($baseName, '.')) {
                continue;
            }

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            // Uncompressed sizes guard against zip bombs before anything is read.
            if ($stat['size'] > self::MAX_ENTRY_BYTES) {
                $zip->close();

                throw new RuntimeException("File {$baseName} di dalam ZIP lebih besar dari 20 MB.");
            }

            $totalBytes += $stat['size'];

            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                $zip->close();

                throw new RuntimeException('Total isi ZIP lebih dari 300 MB.');
            }

            $this->add($baseName, $upload, $entry);
        }

        $zip->close();
    }
}
