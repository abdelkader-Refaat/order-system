<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Local-only: completes the “consumer” step in-process when there is no broker.
 * Never runs when APP_ENV is not "local".
 *
 * During HTTP (php artisan serve, FPM), completion runs after the response is sent so the API
 * returns pending (0) first, then the row becomes processed (1) — closer to a real queue.
 */
final class OrdersQueueDevCompletion
{
    public function __construct(private readonly OrderProcessingService $processor) {}

    /**
     * @param  string  $reason  'publish_failed' | 'skip_publish'
     */
    public function markProcessedWhenEnabled(int $orderId, string $reason): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if (! config('orders.dev_mark_processed_without_broker')) {
            return false;
        }

        $context = [
            'order_id' => $orderId,
            'reason' => $reason,
        ];

        if (! app()->runningInConsole()) {
            app()->terminating(function () use ($orderId, $context): void {
                $this->processor->markProcessedIfPending($orderId);

                Log::info('Orders: local dev — order marked processed without RabbitMQ (after HTTP response; set ORDERS_LOCAL_MARK_PROCESSED_WITHOUT_BROKER=false to disable).', $context);
            });

            Log::info('Orders: local dev — will mark processed after HTTP response (no RabbitMQ).', $context);

            return true;
        }

        $this->processor->markProcessedIfPending($orderId);

        Log::info('Orders: local dev — order marked processed without RabbitMQ (CLI; set ORDERS_LOCAL_MARK_PROCESSED_WITHOUT_BROKER=false to disable).', $context);

        return true;
    }
}
