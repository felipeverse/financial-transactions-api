<?php

namespace App\Models;

use App\Support\Money;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payer_wallet_id',
        'payee_wallet_id',
        'type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'type' => TransactionType::class,
    ];

    /**
     * Get the wallet that made the transaction.
     *
     * @return BelongsTo<\App\Models\Wallet, self>
     */
    public function payerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'payer_wallet_id');
    }

    /**
     * Get the wallet that received the transaction.
     *
     * @return BelongsTo<\App\Models\Wallet, self>
     */
    public function payeeWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'payee_wallet_id');
    }

    public function getAmountInReaisAttribute(): float
    {
        return Money::toReais($this->amount);
    }
}
