<?php

namespace App\Contracts;

interface OrdersQueuePublisherInterface
{
    /**
     * Publish a lightweight message so a worker can pick up this order id from {@code orders_queue}.
     */
    public function publish(int $orderId): void;
}
