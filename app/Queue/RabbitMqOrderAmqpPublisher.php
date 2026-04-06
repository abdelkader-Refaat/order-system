<?php

namespace App\Queue;

use App\Support\RabbitMqEndpoint;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Low-level publish of {"order_id": n} to the configured RabbitMQ queue.
 * Used by {@see RabbitMqOrdersQueuePublisher} and the orders:publish Artisan command.
 */
final class RabbitMqOrderAmqpPublisher
{
    public function publish(int $orderId): void
    {
        if ($orderId < 1) {
            throw new \InvalidArgumentException('orderId must be positive.');
        }

        $p = RabbitMqEndpoint::connectionParams();
        $queue = $p['queue'];

        $connection = null;

        try {
            $connection = new AMQPStreamConnection(
                $p['host'],
                $p['port'],
                $p['user'],
                $p['password'],
                $p['vhost'],
            );

            $channel = $connection->channel();
            $channel->queue_declare($queue, false, true, false, false);

            $body = json_encode(['order_id' => $orderId], JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($body, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $channel->basic_publish($message, '', $queue);
            $channel->close();
        } finally {
            if ($connection !== null) {
                try {
                    $connection->close();
                } catch (AMQPExceptionInterface) {
                    //
                }
            }
        }
    }
}
