<?php

namespace App\DTOs\Auth;

final readonly class UpdateProfileDTO
{
    private function __construct(
        public ?string $name,
        public ?string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toFillableArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->toFillableArray() === [];
    }
}
