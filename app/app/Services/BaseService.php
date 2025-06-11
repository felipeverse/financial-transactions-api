<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use App\DTOs\Services\Responses\BaseServiceResponseDTO;

abstract class BaseService
{
    /**
     * Handles exceptions with logging and a standard response.
     */
    protected function handleException(
        Throwable $e,
        string $defaultMessage = 'Unexpected error.',
        int $defaultCode = 500
    ): BaseServiceResponseDTO {
        Log::error('Service exception: ' . $e->getMessage(), ['exception' => $e]);

        return BaseServiceResponseDTO::failure(
            $defaultMessage,
            statusCode: $e->getCode() ?: $defaultCode
        );
    }
}
