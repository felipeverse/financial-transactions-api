<?php

namespace App\Listeners\Transactions;

use Exception;
use Throwable;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\Transactions\TransferProcessedEvent;

final class SendTransferProcessedNotification implements ShouldQueue
{
    protected NotificationService $notificationService;

    public $queue = 'notifications';
    public $tries = 3;
    public $backoff = 10;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(TransferProcessedEvent $event): void
    {
        try {
            $amountFormatted = Money::formatToReais($event->transaction->amount);

            $message = "You received a payment of R$ {$amountFormatted} from {$event->payer->name}";

            $response = $this->notificationService->sendNotification(
                $event->payee->id,
                $message
            );

            $response = $this->notificationService->sendNotification(
                $event->payee->id,
                $message
            );

            if (! $response->success) {
                throw new Exception('Notification failed.');
            }
        } catch (Throwable $e) {
            Log::error("Listener exception: " . $e->getMessage(), [
                'exception' => $e,
                'transaction_id' => $event->transaction->id ?? null,
            ]);
        }
    }
}
