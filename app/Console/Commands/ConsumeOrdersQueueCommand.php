<?php

namespace App\Console\Commands;

use App\Contracts\OrdersQueuePublisherInterface;
use App\Services\OrderProcessingService;
use App\Support\RabbitMqEndpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumeOrdersQueueCommand extends Command
{
    protected $signature = 'orders:consume
                            {--once : Fetch and process a single message then exit}
                            {--queue= : Override queue name (default: config rabbitmq.queue)}';

    protected $description = 'Worker: consume JSON {order_id} from orders_queue and set order status to processed';

    public function handle(OrderProcessingService $processor): int
    {
        $p = RabbitMqEndpoint::connectionParams();
        $queue = $this->option('queue') ?: $p['queue'];

        $publisherClass = get_class(app(OrdersQueuePublisherInterface::class));

        if (config('orders.skip_queue_publish')) {
            $this->warn('ORDERS_SKIP_QUEUE_PUBLISH is true: the HTTP API uses '.$publisherClass.' and will not enqueue new orders. This worker only processes messages already in RabbitMQ.');
            Log::warning('orders:consume: skip_queue_publish is true — API may not publish new messages.', [
                'publisher' => $publisherClass,
                'amqp' => "{$p['host']}:{$p['port']}",
                'queue' => $queue,
            ]);
        } else {
            $this->info("API publisher: {$publisherClass} (expects messages on this queue after POST /api/orders).");
        }

        Log::info('orders:consume: starting', [
            'amqp' => "{$p['host']}:{$p['port']}",
            'queue' => $queue,
            'skip_queue_publish' => config('orders.skip_queue_publish'),
            'api_publisher' => $publisherClass,
        ]);

        try {
            $connection = new AMQPStreamConnection(
                $p['host'],
                $p['port'],
                $p['user'],
                $p['password'],
                $p['vhost'],
            );
        } catch (Throwable $e) {
            $this->error('Cannot connect to RabbitMQ: '.$e->getMessage());
            Log::error('orders:consume: AMQP connection failed', [
                'exception' => $e->getMessage(),
                'amqp' => "{$p['host']}:{$p['port']}",
            ]);

            return self::FAILURE;
        }

        try {
            $channel = $connection->channel();
            $channel->queue_declare($queue, false, true, false, false);

            if ($this->option('once')) {
                $this->processOne($channel, $queue, $processor);
            } else {
                $this->listen($channel, $queue, $processor);
            }

            $channel->close();
            $connection->close();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::error('orders:consume: failed', ['exception' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function processOne(AMQPChannel $channel, string $queue, OrderProcessingService $processor): void
    {
        $message = $channel->basic_get($queue);
        if ($message === null) {
            $this->warn('No message available in queue.');

            return;
        }

        $this->handleMessage($channel, $message, $processor);
    }

    private function listen(AMQPChannel $channel, string $queue, OrderProcessingService $processor): void
    {
        $channel->basic_qos(null, 1, null);
        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            fn (AMQPMessage $message) => $this->handleMessage($channel, $message, $processor),
        );

        $this->info("Listening on queue [{$queue}]… (Ctrl+C to stop)");
        Log::info("orders:consume: listening on queue [{$queue}]");

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    private function handleMessage(AMQPChannel $channel, AMQPMessage $message, OrderProcessingService $processor): void
    {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $orderId = (int) ($payload['order_id'] ?? 0);

            if ($orderId > 0) {
                if ($processor->markProcessedIfPending($orderId)) {
                    Log::info('orders:consume: order status saved as processed', ['order_id' => $orderId]);
                    $this->info("Order {$orderId} updated: status → processed.");
                } else {
                    Log::warning('orders:consume: message ack’d but no pending row updated (order missing or already processed)', [
                        'order_id' => $orderId,
                    ]);
                    $this->warn("Order {$orderId}: not in DB as pending — check the same SQLite file as the API (path in DB_DATABASE).");
                }
            } else {
                Log::warning('orders:consume: invalid payload (missing order_id)', ['body' => $message->getBody()]);
            }

            $channel->basic_ack($message->getDeliveryTag());
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $channel->basic_nack($message->getDeliveryTag(), false, true);
        }
    }
}
