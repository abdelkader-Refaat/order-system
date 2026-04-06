<?php

namespace App\Services;

use App\Contracts\OrdersQueuePublisherInterface;
use App\DTOs\CreateOrderDTO;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderService
{
    public function __construct(private readonly OrdersQueuePublisherInterface $ordersQueue)
    {
    }

    /**
     * Persist a pending order and publish a plain JSON message to RabbitMQ {@code orders_queue} (not a Laravel job).
     */
    public function createOrder(CreateOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            $order = Order::query()->create([
                'user_id' => $dto->userId,
                'customer_name' => $dto->customerName,
                'customer_email' => $dto->customerEmail,
                'items' => $dto->itemsAsArray(),
                'total_amount' => $dto->totalAmount,
                'status' => OrderStatus::Pending,
                'notes' => $dto->notes,
            ]);

            $orderId = $order->id;
            // Publish after commit so consumers never see queue messages before the row is visible.
            DB::afterCommit(function () use ($orderId): void {
                try {
                    $this->ordersQueue->publish($orderId);
                } catch (Throwable $e) {
                    Order::query()->whereKey($orderId)->delete();
                    throw $e;
                }
            });

            return $order->fresh();
        });
    }
}
