<?php

namespace App\Queue;

use App\Contracts\OrdersQueuePublisherInterface;
use App\Services\OrdersQueueDevCompletion;
use App\Support\RabbitMqEndpoint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPIOException;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Publishes plain JSON to RabbitMQ (not a Laravel serialized job).
 */
final class RabbitMqOrdersQueuePublisher implements OrdersQueuePublisherInterface
{
    public function publish(int $orderId): void
    {
        $p = RabbitMqEndpoint::connectionParams();
        $amqpHost = $p['host'];
        $amqpPort = $p['port'];

        try {
            (new RabbitMqOrderAmqpPublisher)->publish($orderId);

            return;
        } catch (AMQPIOException $e) {
            if ($this->publishViaCliFallback($orderId)) {
                return;
            }

            $this->failOrRethrow($orderId, $e, $amqpHost, $amqpPort);
        } catch (AMQPExceptionInterface $e) {
            $this->failOrRethrow($orderId, $e, $amqpHost, $amqpPort);
        }
    }

    private function publishViaCliFallback(int $orderId): bool
    {
        if (! config('orders.fallback_cli_publish')) {
            return false;
        }

        // cli-server = php artisan serve — same PHP as CLI; subprocess cannot fix TCP issues.
        if (in_array(PHP_SAPI, ['cli', 'phpdbg', 'cli-server'], true)) {
            return false;
        }

        $php = (new PhpExecutableFinder)->find(false);
        if ($php === false) {
            Log::warning('Orders queue: CLI fallback skipped (php binary not found).', [
                'order_id' => $orderId,
                'sapi' => PHP_SAPI,
            ]);

            return false;
        }

        $result = Process::path(base_path())
            ->timeout(30)
            ->run([$php, base_path('artisan'), 'orders:publish', (string) $orderId]);

        if ($result->successful()) {
            Log::info('Orders queue: published via CLI fallback after FPM could not open AMQP.', [
                'order_id' => $orderId,
                'sapi' => PHP_SAPI,
            ]);

            return true;
        }

        Log::warning('Orders queue: CLI fallback failed.', [
            'order_id' => $orderId,
            'exit_code' => $result->exitCode(),
            'output' => $result->output(),
            'error_output' => $result->errorOutput(),
        ]);

        return false;
    }

    private function failOrRethrow(int $orderId, AMQPExceptionInterface $e, string $amqpHost, int $amqpPort): void
    {
        if (! config('orders.graceful_queue_failure')) {
            throw $e;
        }

        if (app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($orderId, 'publish_failed')) {
            return;
        }

        Log::warning('Orders queue publish failed; order was saved but not queued.', [
            'order_id' => $orderId,
            'amqp' => "{$amqpHost}:{$amqpPort}",
            'exception' => $e->getMessage(),
            'sapi' => PHP_SAPI,
            'hint' => $this->gracefulFailureHint(),
        ]);
    }

    private function gracefulFailureHint(): string
    {
        if (PHP_SAPI === 'cli-server') {
            return 'Nothing is listening on this host:port. From the project root run: docker compose up -d rabbitmq — then php artisan orders:check-broker must succeed.';
        }

        if (in_array(PHP_SAPI, ['fpm-fcgi', 'cgi-fcgi'], true)) {
            return 'Start RabbitMQ (docker compose up -d rabbitmq). If php artisan orders:check-broker works in Terminal but the API still fails, ORDERS_PUBLISH_FALLBACK_CLI=true uses a CLI republish.';
        }

        return 'Start RabbitMQ (e.g. docker compose up -d rabbitmq) and confirm php artisan orders:check-broker succeeds.';
    }
}
