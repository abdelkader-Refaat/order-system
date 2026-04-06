<?php

namespace Tests\Unit;

use App\Support\RabbitMqEndpoint;
use Tests\TestCase;

class RabbitMqEndpointTest extends TestCase
{
    public function test_uses_config_host_when_not_docker_loopback(): void
    {
        config([
            'queue.connections.rabbitmq' => [
                'hosts' => [[
                    'host' => 'broker.example',
                    'port' => 5672,
                    'user' => 'u',
                    'password' => 'p',
                    'vhost' => '/',
                ]],
                'queue' => 'q1',
            ],
        ]);

        $this->assertSame('broker.example', RabbitMqEndpoint::connectionParams()['host']);
        $this->assertSame('q1', RabbitMqEndpoint::connectionParams()['queue']);
    }

    public function test_rewrites_loopback_to_rabbitmq_inside_docker(): void
    {
        if (! file_exists('/.dockerenv')) {
            $this->markTestSkipped('Not running inside Docker.');
        }

        config([
            'queue.connections.rabbitmq' => [
                'hosts' => [[
                    'host' => '127.0.0.1',
                    'port' => 5672,
                    'user' => 'guest',
                    'password' => 'guest',
                    'vhost' => '/',
                ]],
                'queue' => 'orders_queue',
            ],
        ]);

        $this->assertSame('rabbitmq', RabbitMqEndpoint::connectionParams()['host']);
    }
}
