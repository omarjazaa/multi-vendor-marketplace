<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct("Only {$available} units are available.");
    }
}
