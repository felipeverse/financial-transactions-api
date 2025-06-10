<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Symfony\Component\HttpFoundation\Response;
use App\DTOs\Services\Responses\TransactionAuthorizer\AuthorizeServiceResponseDTO;

/**
 * Service responsible for authorizing transactions via external service.
 */
class TransactionAuthorizerService
{
    protected PendingRequest $http;
    protected $retries = 3;
    protected $retryAfterMilisecons = 100;
    protected $timeoutSeconds = 5;

    public function __construct()
    {
        $baseUrl = env('TRANSACTION_AUTHORIZER_SERVICE_URL');

        $this->http = Http::withUrlParameters(['endpoint' => $baseUrl])
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->retry($this->retries, $this->retryAfterMilisecons, throw: false);
    }

    /**
     * Requests authorization from external service.
     *
     * @return AuthorizeServiceResponseDTO
     */
    public function authorize(): AuthorizeServiceResponseDTO
    {
        try {
            $response = $this->http->get('{+endpoint}/authorize');
            $json = $response->json();

            // Validate JSON structure
            if (!isset($json['status']) || !isset($json['data']['authorization'])) {
                return AuthorizeServiceResponseDTO::failure(
                    'Invalid authorizer response format.',
                    statusCode: Response::HTTP_BAD_GATEWAY
                );
            }

            // Validate JSON structure
            if ($json['status'] === 'success' && $json['data']['authorization'] === true) {
                return AuthorizeServiceResponseDTO::success(
                    'Authorization approved.',
                    $json,
                    Response::HTTP_OK
                );
            }

            // Unexpected but valid response, return failure with response code
            return AuthorizeServiceResponseDTO::failure(
                'Authorization denied by external service.',
                $json,
                Response::HTTP_FORBIDDEN
            );
        } catch (Throwable $th) {
            Log::error('Service exception: Unexpected error during transfer', [
                'exception' => $th,
            ]);

            return AuthorizeServiceResponseDTO::failure(
                'Unexpected error.',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
