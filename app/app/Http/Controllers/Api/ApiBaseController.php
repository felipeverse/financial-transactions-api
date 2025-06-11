<?php

namespace App\Http\Controllers\Api;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

abstract class ApiBaseController
{
    /**
     * Handles exceptions with logging and a standard response.
     */
    protected function handleException(
        Throwable $e,
        string $defaultErrorMessage = 'Unexpected error.',
        int $defaultCode = 500
    ): JsonResponse {
        Log::error('Controller exception: ' . $e->getMessage(), ['exception' => $e]);

        return response()->json(
            [
                'error' => $defaultErrorMessage
            ],
            $e->getCode() ?: $defaultCode
        );
    }
}
