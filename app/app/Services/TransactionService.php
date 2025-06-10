<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\DTOs\Services\Responses\Transaction\DepositServiceResponseDTO;

/**
 * Service reponsible for handling transactions operatios.
 */
class TransactionService
{
    /**
     * Flag to enable/disable pessimistic locking during wallet updates.
     */
    protected bool $usePessimisticLock;

    public function __construct()
    {
        $this->usePessimisticLock = config('feature-flags.use_pessimistic_lock', true);
    }

    /**
     * Performs a deposit operation into a user's wallet.
     *
     * @param integer $payerId - ID of the user receiving the deposit
     * @param integer $valueInCents - Deposit amount in cents
     * @return DepositServiceResponseDTO - Standarzid seervice response
     */
    public function deposit(int $payerId, int $valueInCents): DepositServiceResponseDTO
    {
        // Validate positive amount
        if ($valueInCents <= 0) {
            return DepositServiceResponseDTO::failure(
                'Amount must be greater than 0.',
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Eager load wallet to avoid N+1 query later
        $user = User::with('wallet')->find($payerId);

        if (! $user) {
            return DepositServiceResponseDTO::failure(
                'User not found.',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        // Wrap deposit in a DB transaction
        return DB::transaction(function () use ($user, $valueInCents) {
            $wallet = $this->usePessimisticLock
                ? $user->wallet()->lockForUpdate()->first()
                : $user->wallet;

            if (! $wallet) {
                return DepositServiceResponseDTO::failure(
                    'Wallet not found.',
                    statusCode: Response::HTTP_NOT_FOUND
                );
            }

            // Update wallet balance
            $wallet->balance += $valueInCents;
            $wallet->save();

            // Record transaction
            $transaction = Transaction::create([
                'payer_wallet_id' => $wallet->id,
                'payee_wallet_id' => $wallet->id,
                'type' => TransactionType::Deposit,
                'amount' => $valueInCents,
            ]);

            return DepositServiceResponseDTO::success(
                'Deposit processed successfully.',
                ['transaction' => $transaction, 'wallet' => $wallet],
                Response::HTTP_OK
            );
        });
    }
}
