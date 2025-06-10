<?php

namespace App\Http\Resources\Api\Transaction;

use Illuminate\Http\Request;
use App\Http\Resources\Api\WalletResource;
use App\Http\Resources\Api\TransactionResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats the response for a deposit operation.
 */
class DepositResponseResource extends JsonResource
{
    /**
     * Transform the response data into an array for API response.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'message' => $this['message'],
            'transaction' => new TransactionResource($this['transaction']),
            'wallet' => new WalletResource($this['wallet']),
        ];
    }
}
