<?php

namespace App\Exceptions;

use RuntimeException;

class IneligibleReviewException extends RuntimeException
{
    public function __construct(string $message = 'Only verified buyers with delivered orders can review this product.')
    {
        parent::__construct($message);
    }
}
