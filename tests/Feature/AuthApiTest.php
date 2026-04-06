<?php

namespace Tests\Feature;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'key',
                'msg',
                'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']],
            ])
            ->assertJsonPath('key', ApiResponse::KEY_SUCCESS)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'test@example.com');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['key', 'msg', 'data' => ['token', 'token_type', 'user']])
            ->assertJsonPath('key', ApiResponse::KEY_SUCCESS)
            ->assertJsonPath('data.user.email', 'ada@example.com');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('key', ApiResponse::KEY_FAIL)
            ->assertJsonStructure(['key', 'msg', 'data' => ['errors' => ['email']]]);
    }

    public function test_validation_errors_use_consistent_api_json_shape(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-email',
            'password' => 'x',
            'password_confirmation' => 'y',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('key', ApiResponse::KEY_FAIL)
            ->assertJsonStructure(['key', 'msg', 'data' => ['errors']]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('key', ApiResponse::KEY_SUCCESS)
            ->assertJsonPath('data', null);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.com']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->patchJson('/api/user', [
            'name' => 'New Name',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('key', ApiResponse::KEY_SUCCESS)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'old@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'old@example.com',
        ]);
    }

    public function test_profile_update_requires_at_least_one_field(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->patchJson('/api/user', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('key', ApiResponse::KEY_FAIL);
    }
}
