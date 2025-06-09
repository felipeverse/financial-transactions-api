<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Http\Requests\Api\Transaction\DepositRequest;

/**
 * Handles API requests for transactions.
 */
class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    /**
     * Process a deposit request.
     * Returns wallet info on success, error message on failure.
     */
    public function deposit(DepositRequest $request)
    {
        try {
            $payerId = $request->payer_id;
            $valueInCents = (int) bcmul((string) $request->value, '100', 0);

            $response = $this->transactionService->deposit($payerId, $valueInCents);

            if (!$response->success) {
                return response()->json(['error' => $response->error], $response->code);
            }

            return response()->json(
                [
                    'user_id' => $response->wallet->user_id,
                    'balance' => number_format($response->wallet->balance / 100, 2, ',', '.')
                ],
                200
            );
        } catch (Throwable $th) {
            return response()->json(['error' => 'Unexpected error'], 500);
        }
    }
}
