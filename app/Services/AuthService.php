<?php

namespace App\Services;

use App\DTOs\Auth\AuthTokenResult;
use App\DTOs\Auth\LoginCredentialsDTO;
use App\DTOs\Auth\RegisterUserDTO;
use App\DTOs\Auth\UpdateProfileDTO;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const TOKEN_NAME = 'api';

    public function register(RegisterUserDTO $dto): AuthTokenResult
    {
        $user = User::query()->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        return $this->issueToken($user);
    }

    public function login(LoginCredentialsDTO $dto): AuthTokenResult
    {
        $user = User::query()->where('email', $dto->email)->first();

        if ($user === null || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $this->issueToken($user);
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function updateProfile(User $user, UpdateProfileDTO $dto): User
    {
        if ($dto->isEmpty()) {
            throw ValidationException::withMessages([
                'name' => ['Provide at least one of: name, email.'],
            ]);
        }

        $user->fill($dto->toFillableArray());
        $user->save();

        return $user->fresh();
    }

    private function issueToken(User $user): AuthTokenResult
    {
        $plain = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        return new AuthTokenResult($user, $plain);
    }
}
