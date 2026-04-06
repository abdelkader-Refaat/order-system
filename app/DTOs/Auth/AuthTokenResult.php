<?php

namespace App\DTOs\Auth;

use App\Models\User;

/**
 * Result of register/login: persisted user + plain-text Sanctum token (show once to client).
 */
final readonly class AuthTokenResult
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
    ) {}
}
