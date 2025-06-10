<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats transaction data for API responses.
 */
class TransactionResource extends JsonResource
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
            'transaction_id' => $this->id,
            'payer_id' => $this->payerWallet->user_id ?? null,
            'payee_id' => $this->payeeWallet->user_id ?? null,
            'type' => $this->type,
            'amount' => $this->amountInReais,
            'created_at' => $this->created_at,
        ];
    }
}
