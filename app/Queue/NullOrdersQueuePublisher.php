<?php

namespace App\Queue;

use App\Contracts\OrdersQueuePublisherInterface;
use App\Services\OrdersQueueDevCompletion;

/**
 * No-op publisher for local API testing when RabbitMQ is not running.
 */
final class NullOrdersQueuePublisher implements OrdersQueuePublisherInterface
{
    public function publish(int $orderId): void
    {
        app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($orderId, 'skip_publish');
    }
}
