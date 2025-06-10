<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Http\Requests\Api\Transaction\DepositRequest;
use App\Http\Resources\Api\Transaction\DepositResponseResource;

/**
 * Handles API requests related to transactions.
 */
class TransactionController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    /**
     * Handles deposit transaction request.
     *
     * @param DepositRequest $request
     * @return JsonResponse
     */
    public function deposit(DepositRequest $request): JsonResponse
    {
        try {
            $payerId = $request->payer_id;

            $valueInCents = Money::toCents($request->value);

            $response = $this->transactionService->deposit($payerId, $valueInCents);

            if (!$response->success) {
                return response()->json(
                    ['error' => $response->message],
                    $response->statusCode
                );
            }

            return response()->json(
                new DepositResponseResource([
                    'message' => $response->message,
                    'transaction' => $response->data['transaction'],
                    'wallet' => $response->data['wallet'],
                ]),
                $response->statusCode
            );
        } catch (Throwable $th) {
            Log::error('Unexpected error during deposit', [
                'exception' => $th,
            ]);

            return response()->json(['error' => 'Unexpected error'], 500);
        }
    }
}
