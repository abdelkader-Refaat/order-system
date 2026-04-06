<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Applies the consumer rule: pending orders become processed (idempotent).
 */
class OrderProcessingService
{
    public function markProcessedIfPending(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if ($order === null) {
            return;
        }

        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        $order->status = OrderStatus::Processed;
        $order->save();
    }
}
