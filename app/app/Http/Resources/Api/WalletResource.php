<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats wallet data for API responses.
 */
class WalletResource extends JsonResource
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
            'user_id' => $this->user_id,
            'balance' => $this->balanceInReais,
        ];
    }
}
