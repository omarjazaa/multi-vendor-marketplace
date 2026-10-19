<?php

namespace App\Exceptions;

use RuntimeException;

class RefundProcessingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $gatewayMessage = null,
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }
}
