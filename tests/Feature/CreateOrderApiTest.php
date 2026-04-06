<?php

namespace Tests\Feature;

use App\Contracts\OrdersQueuePublisherInterface;
use App\Enums\OrderStatus;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CreateOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pending_order_and_publishes_to_queue(): void
    {
        $mock = Mockery::mock(OrdersQueuePublisherInterface::class);
        $mock->shouldReceive('publish')->once()->with(Mockery::on(fn (int $id): bool => $id > 0));
        $this->app->instance(OrdersQueuePublisherInterface::class, $mock);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'items' => [
                ['name' => 'Keyboard', 'quantity' => 2, 'price' => 49.99],
                ['name' => 'Mouse', 'quantity' => 1, 'price' => 25.00],
            ],
            'notes' => 'Leave at desk',
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('key', ApiResponse::KEY_SUCCESS)
            ->assertJsonStructure(['key', 'msg', 'data'])
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer_name', 'Ada Lovelace')
            ->assertJsonPath('data.total_amount', '124.98');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'customer_email' => 'ada@example.com',
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_orders_require_authentication(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'X',
            'customer_email' => 'x@example.com',
            'items' => [['name' => 'A', 'quantity' => 1, 'price' => 1]],
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('key', ApiResponse::KEY_FAIL);
    }

    public function test_rejects_invalid_payload(): void
    {
        $mock = Mockery::mock(OrdersQueuePublisherInterface::class);
        $mock->shouldNotReceive('publish');
        $this->app->instance(OrdersQueuePublisherInterface::class, $mock);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/orders', [
            'customer_name' => '',
            'customer_email' => 'not-an-email',
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('key', ApiResponse::KEY_FAIL);
    }
}
