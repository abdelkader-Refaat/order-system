<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_processed_updates_pending_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $changed = app(OrderProcessingService::class)->markProcessedIfPending($order->id);

        $this->assertTrue($changed);
        $this->assertSame(OrderStatus::Processed, $order->fresh()->status);
    }

    public function test_missing_order_is_no_op(): void
    {
        $changed = app(OrderProcessingService::class)->markProcessedIfPending(999_999);

        $this->assertFalse($changed);
    }

    public function test_non_pending_order_is_no_op(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Processed]);

        $changed = app(OrderProcessingService::class)->markProcessedIfPending($order->id);

        $this->assertFalse($changed);
        $this->assertSame(OrderStatus::Processed, $order->fresh()->status);
    }
}
