<?php

namespace Tests\Unit;

use App\Services\InvoiceImportFileBag;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class InvoiceImportFileBagTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function zipUpload(array $entries, string $name = 'faktur.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bag') . '.zip';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function fileUpload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bag');
        $this->tempFiles[] = $path;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_zip_entries_are_indexed_by_base_name_and_read_in_place(): void
    {
        $bag = InvoiceImportFileBag::fromUploads([
            $this->zipUpload([
                'Februari/04002500064522168.pdf' => 'pdf-1',
                'Februari/scan.JPG' => 'jpg-1',
                '__MACOSX/Februari/._04002500064522168.pdf' => 'resource fork',
                'Februari/catatan.txt' => 'ignored',
                '.DS_Store' => 'ignored',
            ]),
        ]);

        $this->assertSame(['04002500064522168.pdf', 'scan.JPG'], $bag->names());
        $this->assertSame('pdf-1', $bag->read('04002500064522168.PDF'));
        $this->assertSame('jpg-1', $bag->read('scan.jpg'));
    }

    public function test_loose_files_and_duplicate_names(): void
    {
        $bag = InvoiceImportFileBag::fromUploads([
            $this->fileUpload('a.pdf', 'loose'),
            $this->zipUpload(['folder/A.pdf' => 'zipped', 'b.png' => 'png']),
        ]);

        $this->assertSame(['a.pdf', 'b.png'], $bag->names());
        $this->assertSame('loose', $bag->read('a.pdf'));
        $this->assertSame(['A.pdf'], $bag->duplicates());
    }

    public function test_reading_an_unknown_file_fails(): void
    {
        $bag = InvoiceImportFileBag::fromUploads([$this->fileUpload('a.pdf', 'x')]);

        $this->expectException(RuntimeException::class);
        $bag->read('b.pdf');
    }

    public function test_corrupt_zip_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        InvoiceImportFileBag::fromUploads([$this->fileUpload('rusak.zip', 'not a zip')]);
    }
}
