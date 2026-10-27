<?php

namespace App\Exceptions;

use RuntimeException;

class DiditException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $diditMessage,
        public readonly array $context = [],
    ) {
        parent::__construct($diditMessage);
    }
}
