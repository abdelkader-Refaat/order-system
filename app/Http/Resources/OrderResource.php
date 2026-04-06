<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'items' => $this->formatItems(),
            'total_amount' => (string) $this->total_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }

    /**
     * @return list<array{name: string, quantity: int, price: float}>
     */
    private function formatItems(): array
    {
        $items = $this->items ?? [];

        return array_values(array_map(static function (array $row): array {
            return [
                'name' => (string) $row['name'],
                'quantity' => (int) $row['quantity'],
                'price' => (float) $row['price'],
            ];
        }, $items));
    }
}
