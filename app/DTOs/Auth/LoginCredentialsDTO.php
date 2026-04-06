<?php

namespace App\DTOs\Auth;

final readonly class LoginCredentialsDTO
{
    private function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{email: string, password: string}  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
        );
    }
}
