<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Symfony\Component\HttpFoundation\Response;
use App\DTOs\Services\Responses\Notification\SendNotificationServiceResponseDTO;

/**
 * Service responsible for sending user notifications via external service.
 */
class NotificationService
{
    protected PendingRequest $http;
    protected $retries = 3;
    protected $retryAfterMilisecons = 100;
    protected $timeoutSeconds = 5;

    public function __construct()
    {
        $baseUrl = env('NOTIFICATION_SERVICE_URL');

        $this->http = Http::withUrlParameters(['endpoint' => $baseUrl])
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->retry($this->retries, $this->retryAfterMilisecons, throw: false);
    }

    /**
     * Sends a notification to a user.
     *
     * @param integer $userId - ID of the user to notify
     * @param string $message - Message to be sent
     * @return SendNotificationServiceResponseDTO - Standardized service response
     */
    public function sendNotification(int $userId, string $message): SendNotificationServiceResponseDTO
    {
        try {
            $response = $this->http->post('{+endpoint}/notify', [
                'user_id' => $userId,
                'message' => $message
            ]);

            // 204 indicates success with no body
            if ($response->status() === Response::HTTP_NO_CONTENT) {
                return SendNotificationServiceResponseDTO::success(
                    'Notification sent successfully.',
                    statusCode: Response::HTTP_NO_CONTENT
                );
            }

            // Handle other successful but unexpected statuses
            return SendNotificationServiceResponseDTO::failure(
                'Notification not sent: unexpected response from external service.',
                statusCode: $response->status()
            );
        } catch (Throwable $th) {
            Log::error('Service exception: Unexpected error during notification', [
                'exception' => $th,
            ]);

            return SendNotificationServiceResponseDTO::failure(
                'Unexpected error.',
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
