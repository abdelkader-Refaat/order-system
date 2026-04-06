<?php

namespace App\DTOs;

final class OrderItemDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $price,
    ) {}

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function fromArray(array $item): self
    {
        return new self(
            name: $item['name'],
            quantity: (int) $item['quantity'],
            price: (float) $item['price'],
        );
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function subtotal(): float
    {
        return round($this->quantity * $this->price, 2);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
