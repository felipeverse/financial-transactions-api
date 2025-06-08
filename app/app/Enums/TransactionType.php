<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Transfer = 'transfer';
}
