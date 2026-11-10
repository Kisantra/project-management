<?php

namespace App\Services\ClientManagement;

use RuntimeException;
use Throwable;

/** Satu dokumen CM gagal diambil; membawa nama berkas untuk pesan ke staf. */
class IntakeDocumentException extends RuntimeException
{
    public function __construct(public readonly ?string $fileName, string $reason, ?Throwable $previous = null)
    {
        parent::__construct($fileName ? "{$fileName}: {$reason}" : $reason, 0, $previous);
    }

    public function reason(): string
    {
        $message = $this->getMessage();

        return $this->fileName ? substr($message, strlen($this->fileName) + 2) : $message;
    }
}
