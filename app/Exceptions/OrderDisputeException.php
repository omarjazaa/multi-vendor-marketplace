<?php

namespace App\Exceptions;

use RuntimeException;

class OrderDisputeException extends RuntimeException
{
    public function __construct(string $message, int $code = 409)
    {
        parent::__construct($message, $code);
    }
}
