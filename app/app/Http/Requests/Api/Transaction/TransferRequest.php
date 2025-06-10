<?php

namespace App\Http\Requests\Api\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payer_id' => ['required', 'integer', 'different:payee_id'],
            'payee_id' => ['required', 'integer', 'different:payer_id'],
            'value' => [
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
            ],
        ];
    }
}
