<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = 'Insufficient balance.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
