<?php

namespace App\Exceptions;

use Exception;

class WalletNotFoundException extends Exception
{
    public function __construct(string $message = 'Insufficient balance.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
