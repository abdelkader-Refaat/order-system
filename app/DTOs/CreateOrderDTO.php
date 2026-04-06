<?php

namespace App\DTOs;

final readonly class CreateOrderDTO
{
    /**
     * @param  list<OrderItemDTO>  $items
     */
    private function __construct(
        public ?int $userId,
        public string $customerName,
        public string $customerEmail,
        public array $items,
        public float $totalAmount,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $items = array_map(
            static fn (array $row): OrderItemDTO => OrderItemDTO::fromArray($row),
            $data['items']
        );

        $total = round(
            array_sum(array_map(static fn (OrderItemDTO $i): float => $i->subtotal(), $items)),
            2
        );

        return new self(
            userId: $data['user_id'] ?? null,
            customerName: $data['customer_name'],
            customerEmail: $data['customer_email'],
            items: $items,
            totalAmount: $total,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * @return list<array{name: string, quantity: int, price: float}>
     */
    public function itemsAsArray(): array
    {
        return array_map(static fn (OrderItemDTO $i): array => $i->toArray(), $this->items);
    }
}
