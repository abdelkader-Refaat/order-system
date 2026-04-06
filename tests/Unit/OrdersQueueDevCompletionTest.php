<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrdersQueueDevCompletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersQueueDevCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_outside_local_environment(): void
    {
        $this->app['env'] = 'production';

        config(['orders.dev_mark_processed_without_broker' => true]);

        $order = Order::factory()->create();

        $done = app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($order->id, 'publish_failed');

        $this->assertFalse($done);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_marks_processed_in_local_when_config_enabled(): void
    {
        $this->app['env'] = 'local';

        config(['orders.dev_mark_processed_without_broker' => true]);

        $order = Order::factory()->create();

        $done = app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($order->id, 'publish_failed');

        $this->assertTrue($done);
        $this->assertSame(OrderStatus::Processed, $order->fresh()->status);
    }

    public function test_respects_config_disabled(): void
    {
        $this->app['env'] = 'local';

        config(['orders.dev_mark_processed_without_broker' => false]);

        $order = Order::factory()->create();

        $done = app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($order->id, 'publish_failed');

        $this->assertFalse($done);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_defers_until_application_terminate_when_not_running_in_console(): void
    {
        $this->app['env'] = 'local';
        config(['orders.dev_mark_processed_without_broker' => true]);

        $ref = new \ReflectionProperty($this->app::class, 'isRunningInConsole');
        $ref->setAccessible(true);
        $ref->setValue($this->app, false);

        try {
            $order = Order::factory()->create();

            $done = app(OrdersQueueDevCompletion::class)->markProcessedWhenEnabled($order->id, 'publish_failed');

            $this->assertTrue($done);
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

            $this->app->terminate();

            $this->assertSame(OrderStatus::Processed, $order->fresh()->status);
        } finally {
            $ref->setValue($this->app, null);
        }
    }
}
