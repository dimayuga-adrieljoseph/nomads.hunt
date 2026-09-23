<?php

namespace App\Exceptions;

use RuntimeException;

class ClaimException extends RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode = 'CLAIM_ERROR')
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
