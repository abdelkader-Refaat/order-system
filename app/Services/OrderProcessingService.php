<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Applies the consumer rule: pending orders become processed (idempotent).
 */
class OrderProcessingService
{
    /**
     * @return bool True if a row was loaded, was pending, and was saved as processed.
     */
    public function markProcessedIfPending(int $orderId): bool
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return false;
        }

        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        $order->status = OrderStatus::Processed;
        $order->save();

        return true;
    }
}
