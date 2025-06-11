<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use App\Services\TransactionService;
use App\Http\Requests\Api\Transaction\DepositRequest;
use App\Http\Requests\Api\Transaction\TransferRequest;
use App\Http\Resources\Api\Transaction\DepositResponseResource;
use App\Http\Resources\Api\Transaction\TransferResponseResource;

/**
 * Handles API requests related to transactions.
 *
 * /**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Simplebank API",
 *     description="API for managing user wallets, including deposits, transfers, and balance retrieval.",
 * )
 */
class TransactionController extends ApiBaseController
{
    public function __construct(protected TransactionService $transactionService)
    {
    }

    /**
     * Handles deposit transaction request.
     *
     * @OA\Post(
     *     path="/transactions/deposit",
     *     summary="Make a deposit into a user wallet",
     *     tags={"Transactions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payer_id", "value"},
     *             @OA\Property(property="payer_id", type="integer", example=1),
     *             @OA\Property(property="value", type="number", format="float", example=100.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deposit successful"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error."
     *     )
     * )
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
            return $this->handleException($th);
        }
    }

    /**
     * Handles transfer transaction request.
     *
     * @OA\Post(
     *     path="/transactions/transfer",
     *     summary="Make a transfer between user wallets",
     *     tags={"Transactions"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payer_id", "payee_id", "value"},
     *             @OA\Property(property="payer_id", type="integer", example=1, description="ID of the user sending the money"),
     *             @OA\Property(property="payee_id", type="integer", example=2, description="ID of the user receiving the money"),
     *             @OA\Property(property="value", type="number", format="float", example=50.00, description="Amount to transfer (up to 2 decimal places, greater than zero)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer successful"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error"
     *     )
     * )
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
            return $this->handleException($th);
        }
    }
}
