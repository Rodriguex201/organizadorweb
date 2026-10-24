<?php
namespace App\Payments\Bold\Activation;

use RuntimeException;

/** Safe domain error: no driver exception, credentials or DSN retained. */
final class ActivationReadException extends RuntimeException
{
    public function __construct(public readonly string $state, string $safeCode)
    {
        parent::__construct($safeCode);
    }
}
