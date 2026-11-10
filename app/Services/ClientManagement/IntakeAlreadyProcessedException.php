<?php

namespace App\Services\ClientManagement;

use RuntimeException;

/** Klien masuk sudah diproses (atau dibatalkan) oleh request lain. */
class IntakeAlreadyProcessedException extends RuntimeException
{
}
