<?php

namespace App\Services;

use Throwable;
use App\Models\User;
use App\Models\Wallet;
use App\Enums\UserType;
use App\Models\Transaction;
use App\Enums\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use App\Exceptions\WalletNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\InsufficientBalanceException;
use App\Events\Transactions\TransferProcessedEvent;
use App\DTOs\Services\Responses\Transaction\DepositServiceResponseDTO;
use App\DTOs\Services\Responses\Transaction\TransferServiceResponseDTO;

/**
 * Service responsible for handling transaction operations.
 */
class TransactionService
{
    /**
     * Flag to enable/disable pessimistic locking during wallet updates.
     */
    protected bool $usePessimisticLock;

    protected bool $avoidDeadlocks;

    public function __construct(
        protected TransactionAuthorizerService $transactionAuthorizerService,
    ) {
        $this->usePessimisticLock = config('feature-flags.use_pessimistic_lock', true);
        $this->avoidDeadlocks = config('feature-flags.avoid_deadlock', true);
    }

    /**
     * Performs a deposit operation into a user's wallet.
     *
     * @param integer $payerId - ID of the user receiving the deposit
     * @param integer $valueInCents - Deposit amount in cents
     * @return DepositServiceResponseDTO - Standardized seervice response
     */
    public function deposit(int $payerId, int $valueInCents): DepositServiceResponseDTO
    {
        try {
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
                // Get wallet applying pessimistic locking if enabled
                $wallet = $this->usePessimisticLock
                    ? $user->wallet()->lockForUpdate()->first()
                    : $user->wallet;

                if (! $wallet) {
                    throw new WalletNotFoundException();
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
        } catch (WalletNotFoundException $e) {
            Log::error(
                'Service exception: ' . $e->getMessage(),
                ['exception' => $e,]
            );

            return DepositServiceResponseDTO::failure(
                $e->getMessage(),
                statusCode: $e->getCode()
            );
        } catch (Throwable $th) {
            Log::error('Service exception: Unexpected error during deposit', [
                'exception' => $th,
            ]);

            return DepositServiceResponseDTO::failure(
                'Unexpected error.',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Performs a transfer between two distinct users.
     *
     * @param integer $payerId - ID of the user sending the money.
     * @param integer $payeeId - ID of the user receiving the money.
     * @param integer $valueInCents - Transfer amount in cents.
     * @return TransferServiceResponseDTO - Standardized service response.
     */
    public function transfer(int $payerId, int $payeeId, int $valueInCents): TransferServiceResponseDTO
    {
        try {
            // Must transfer to a different user
            if ($payerId === $payeeId) {
                return TransferServiceResponseDTO::failure(
                    'Payer and payee must be different.',
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Validate positive amount
            if ($valueInCents <= 0) {
                return TransferServiceResponseDTO::failure(
                    'Amount must be greater than 0.',
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            // Load both users and their wallets
            $payer = User::with('wallet')->find($payerId);
            $payee = User::with('wallet')->find($payeeId);

            if (!$payer || !$payee) {
                $missing = ! $payer ? 'Payer' : 'Payee';
                return TransferServiceResponseDTO::failure(
                    "{$missing} not found.",
                    statusCode: Response::HTTP_NOT_FOUND
                );
            }

            // Only COMMON users can initiate transfers
            if ($payer->type !== UserType::Common) {
                return TransferServiceResponseDTO::failure(
                    'Only COMMON users can transfer.',
                    statusCode: Response::HTTP_FORBIDDEN
                );
            }

            // External service must authorize the transaction
            $authorizeResponse = $this->transactionAuthorizerService->authorize();

            if (!$authorizeResponse->success) {
                return TransferServiceResponseDTO::failure(
                    $authorizeResponse->message,
                    statusCode: $authorizeResponse->statusCode
                );
            }

            // Wrap all wallet updates and transaction creation in a DB transaction
            $response = DB::transaction(function () use ($payer, $payee, $valueInCents) {
                $payerWallet = $payer->wallet;
                $payeeWallet = $payee->wallet;

                if (!$payerWallet || !$payeeWallet) {
                    $missing = ! $payerWallet ? 'Payer' : 'Payee';
                    return TransferServiceResponseDTO::failure(
                        "{$missing} wallet not found.",
                        statusCode: Response::HTTP_NOT_FOUND
                    );
                }

                // Apply pessimistic locking if enabled
                if ($this->usePessimisticLock) {
                    // Lock both wallets in a deterministic order to avoid deadlocks
                    if ($this->avoidDeadlocks) {
                        $walletIds = [$payerWallet->id, $payeeWallet->id];
                        sort($walletIds);

                        $lockedWallets = Wallet::whereIn('id', $walletIds)
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('id');

                        $payerWallet = $lockedWallets[$payerWallet->id];
                        $payeeWallet = $lockedWallets[$payeeWallet->id];
                    } else {
                        $payerWallet = Wallet::where('id', $payerWallet->id)->lockForUpdate()->first();
                        $payeeWallet = Wallet::where('id', $payeeWallet->id)->lockForUpdate()->first();
                    }
                }

                // Payer must have sufficient balance
                if ($payerWallet->balance < $valueInCents) {
                    throw new InsufficientBalanceException();
                }

                // Perform balance updates
                $payerWallet->balance -= $valueInCents;
                $payeeWallet->balance += $valueInCents;

                $payerWallet->save();
                $payeeWallet->save();

                // Record transaction
                $transaction = Transaction::create([
                    'payer_wallet_id' => $payerWallet->id,
                    'payee_wallet_id' => $payeeWallet->id,
                    'type' => TransactionType::Transfer,
                    'amount' => $valueInCents,
                ]);

                Event::dispatch(new TransferProcessedEvent($payer, $payee, $transaction));

                return TransferServiceResponseDTO::success(
                    'Transfer processed successfully.',
                    [
                        'transaction' => $transaction,
                        'payerWallet' => $payerWallet,
                        'payeeWallet' => $payeeWallet
                    ],
                    Response::HTTP_OK
                );
            });

            return $response;
        } catch (InsufficientBalanceException $e) {
            Log::error(
                'Service exception: ' . $e->getMessage(),
                ['exception' => $e,]
            );

            return TransferServiceResponseDTO::failure(
                $e->getMessage(),
                statusCode: $e->getCode()
            );
        } catch (Throwable $th) {
            Log::error('Service exception: Unexpected error during transfer', [
                'exception' => $th,
            ]);

            return TransferServiceResponseDTO::failure(
                'Unexpected error.',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
