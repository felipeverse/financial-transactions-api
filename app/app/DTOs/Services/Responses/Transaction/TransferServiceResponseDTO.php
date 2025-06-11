<?php

namespace App\DTOs\Services\Responses\Transaction;

use App\DTOs\Services\Responses\BaseServiceResponseDTO;

class TransferServiceResponseDTO extends BaseServiceResponseDTO
{
    public function __construct(
        bool $success,
        string $message = '',
        ?array $data = null,
        int $statusCode
    ) {
        parent::__construct($success, $message, $data, $statusCode);
    }
}
