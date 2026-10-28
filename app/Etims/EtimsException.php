<?php

namespace App\Etims;

use RuntimeException;

class EtimsException extends RuntimeException
{
    /** @param bool $retryable false when retrying cannot help (e.g. KRA rejected the data) */
    public function __construct(string $message, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
