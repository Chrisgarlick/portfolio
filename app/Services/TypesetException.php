<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * A render that did not produce a document. Carries the HTTP status the
 * caller should answer with and a message fit to show a visitor.
 */
final class TypesetException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}
