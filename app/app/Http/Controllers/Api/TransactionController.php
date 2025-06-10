<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Http\Requests\Api\Transaction\DepositRequest;
use App\Http\Requests\Api\Transaction\TransferRequest;
use App\Http\Resources\Api\Transaction\DepositResponseResource;
use App\Http\Resources\Api\Transaction\TransferResponseResource;

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

            // Convert value to cents
            $valueInCents = Money::toCents($request->value);

            // Execute the deposit via the service
            $response = $this->transactionService->deposit($payerId, $valueInCents);

            // Return error if service failed
            if (!$response->success) {
                return response()->json(
                    ['error' => $response->message],
                    $response->statusCode
                );
            }

            // Return successful deposit response
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

    /**
     * Handles transfer transaction request.
     *
     * @param TransferRequest $request
     * @return JsonResponse
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        try {
            $payerId = $request->payer_id;
            $payeeId = $request->payee_id;

            // Convert value to cents
            $valueInCents = Money::toCents($request->value);

            // Execute the transfer via the service
            $response = $this->transactionService->transfer($payerId, $payeeId, $valueInCents);

            // Return error if service failed
            if (!$response->success) {
                return response()->json(
                    ['error' => $response->message],
                    $response->statusCode
                );
            }

            // Return successful transfer response
            return response()->json(
                new TransferResponseResource([
                    'message' => $response->message,
                    'transaction' => $response->data['transaction'],
                    'wallet' => $response->data['payerWallet']
                ]),
                $response->statusCode
            );
        } catch (Throwable $th) {
            Log::error('Controller exception: Unexpected error during transfer', [
                'exception' => $th,
            ]);

            return response()->json(['error' => 'Unexpected error'], 500);
        } {
        }
    }
}
