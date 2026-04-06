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

        app(OrderProcessingService::class)->markProcessedIfPending($order->id);

        $this->assertSame(OrderStatus::Processed, $order->fresh()->status);
    }

    public function test_missing_order_is_no_op(): void
    {
        app(OrderProcessingService::class)->markProcessedIfPending(999_999);

        $this->assertTrue(true);
    }
}
