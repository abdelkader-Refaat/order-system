<?php

namespace App\DTOs\Auth;

final readonly class RegisterUserDTO
{
    private function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
        );
    }
}
