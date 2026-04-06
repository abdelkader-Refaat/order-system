<?php

namespace App\Console\Commands;

use App\Queue\RabbitMqOrderAmqpPublisher;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

/**
 * Internal: publishes one order id to RabbitMQ (used by FPM → CLI fallback).
 */
class OrdersPublishCommand extends Command
{
    protected $hidden = true;

    protected $signature = 'orders:publish {orderId : The order primary key}';

    protected $description = 'Publish a single order_id JSON message to RabbitMQ';

    public function handle(): int
    {
        $orderId = (int) $this->argument('orderId');
        if ($orderId < 1) {
            $this->error('Invalid orderId.');

            return self::FAILURE;
        }

        try {
            (new RabbitMqOrderAmqpPublisher)->publish($orderId);
        } catch (AMQPExceptionInterface $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\JsonException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
