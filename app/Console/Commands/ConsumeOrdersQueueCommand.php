<?php

namespace App\Console\Commands;

use App\Services\OrderProcessingService;
use App\Support\RabbitMqEndpoint;
use Illuminate\Console\Command;
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
        if (config('orders.skip_queue_publish')) {
            $this->warn('ORDERS_SKIP_QUEUE_PUBLISH is true: the API does not enqueue messages, so this worker will only process messages published elsewhere.');
        }

        $p = RabbitMqEndpoint::connectionParams();
        $queue = $this->option('queue') ?: $p['queue'];

        $connection = new AMQPStreamConnection(
            $p['host'],
            $p['port'],
            $p['user'],
            $p['password'],
            $p['vhost'],
        );

        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        if ($this->option('once')) {
            $this->processOne($channel, $queue, $processor);
        } else {
            $this->listen($channel, $queue, $processor);
        }

        $channel->close();
        $connection->close();

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
                $processor->markProcessedIfPending($orderId);
            }

            $channel->basic_ack($message->getDeliveryTag());
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $channel->basic_nack($message->getDeliveryTag(), false, true);
        }
    }
}
