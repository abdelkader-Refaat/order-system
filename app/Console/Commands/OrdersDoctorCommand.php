<?php

namespace App\Console\Commands;

use App\Support\RabbitMqEndpoint;
use Illuminate\Console\Command;

/**
 * One-place diagnosis when "nothing works" (API + RabbitMQ + consumer).
 */
class OrdersDoctorCommand extends Command
{
    protected $signature = 'orders:doctor';

    protected $description = 'Show order-queue env flags and test RabbitMQ (same as orders:check-broker)';

    public function handle(): int
    {
        $this->newLine();
        $this->info('Order queue configuration (loaded from .env + config cache if any)');
        $amqp = RabbitMqEndpoint::connectionParams();
        $this->table(
            ['Setting', 'Value'],
            [
                ['ORDERS_SKIP_QUEUE_PUBLISH', config('orders.skip_queue_publish') ? 'true — API does not enqueue' : 'false'],
                ['ORDERS_GRACEFUL_QUEUE_FAILURE', config('orders.graceful_queue_failure') ? 'true — API returns 200 if publish fails' : 'false'],
                ['ORDERS_PUBLISH_FALLBACK_CLI', config('orders.fallback_cli_publish') ? 'true — FPM republish via CLI' : 'false'],
                ['ORDERS_LOCAL_MARK_PROCESSED_WITHOUT_BROKER', config('orders.dev_mark_processed_without_broker') ? 'true — fake consumer without broker' : 'false — real RabbitMQ + orders:consume'],
                ['RABBITMQ (runtime)', sprintf(
                    '%s:%s / queue %s',
                    $amqp['host'],
                    $amqp['port'],
                    $amqp['queue'],
                )],
                ['PHP_SAPI (this process)', PHP_SAPI],
            ],
        );

        if (config('orders.skip_queue_publish')) {
            $this->warn('Skip-publish is ON: new orders never reach RabbitMQ. Set ORDERS_SKIP_QUEUE_PUBLISH=false when the broker is running.');
        }

        if (app()->configurationIsCached()) {
            $this->warn('Config is cached. After editing .env run: php artisan optimize:clear');
        }

        $this->newLine();
        $this->info('Broker connectivity:');
        $code = $this->call('orders:check-broker');

        $this->newLine();
        if ($code !== 0) {
            $this->error('Broker check failed. From the project root run:');
            $this->line('  <fg=cyan>composer rabbitmq:up</>   <comment># or: docker compose up -d rabbitmq</comment>');
            $this->line('Then run this doctor again. Use <fg=cyan>composer dev</> to start server + consumer + RabbitMQ together.');
        } else {
            $this->info('Broker OK. Create an order via the API, then ensure <fg=cyan>php artisan orders:consume</> (or composer dev) is running.');
        }

        $this->newLine();

        return $code;
    }
}
