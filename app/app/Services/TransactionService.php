<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Enums\UserType;
use App\Models\Transaction;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\DTOs\Services\Transaction\DepositResponse;

/**
 * Service handling transaction operations.
 */
class TransactionService
{
    /**
     * Process a deposit for a user.
     * Validates user and amount, updates wallet balance,
     * logs the transaction, and returns the result.
     *
     * @param integer $payerId
     * @param integer $valueInCents
     * @return DepositResponse
     */
    public function deposit(int $payerId, int $valueInCents): DepositResponse
    {
        $user = User::with('wallet')->find($payerId);

        if (! $user) {
            return DepositResponse::failure('User not found', 404);
        }

        if ($user->type !== UserType::Common) {
            return DepositResponse::failure('Only COMMON users can deposit.', 403);
        }

        if ($valueInCents <= 0) {
            return DepositResponse::failure('Amount must be greater than 0.', 422);
        }

        $wallet = DB::transaction(function () use ($user, $valueInCents) {
            $useLock = Config::get('feature-flags.use_pessimistic_lock', true);

            $wallet = $useLock ? $user->wallet()->lockForUpdate()->first() : $user->wallet;

            if (! $wallet) {
                throw new Exception('Wallet not found for user id ' . $user->id);
            }

            $wallet->balance += $valueInCents;
            $wallet->save();

            Transaction::create([
                'payer_wallet_id' => $wallet->id,
                'payee_wallet_id' => $wallet->id,
                'type' => TransactionType::Deposit,
                'amount' => $valueInCents,
            ]);

            return $wallet;
        });

        return DepositResponse::success($wallet);
    }
}
