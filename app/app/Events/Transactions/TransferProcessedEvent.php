<?php

namespace App\Events\Transactions;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TransferProcessedEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $payer,
        public User $payee,
        public Transaction $transaction
    ) {
    }
}
