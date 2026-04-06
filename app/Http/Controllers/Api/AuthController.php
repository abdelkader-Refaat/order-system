<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->toDto());

        return $this->createdResponse('messages.auth.registered', [
            'token' => $result->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->resolveResource($request, new UserResource($result->user)),
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->toDto());

        return $this->successResponse('messages.auth.logged_in', [
            'token' => $result->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->resolveResource($request, new UserResource($result->user)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->revokeCurrentToken($request->user());

        return $this->successResponse('messages.auth.logged_out', null);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            'messages.auth.profile',
            $this->resolveResource($request, new UserResource($request->user())),
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->auth->updateProfile($request->user(), $request->toDto());

        return $this->successResponse(
            'messages.auth.profile_updated',
            $this->resolveResource($request, new UserResource($user)),
        );
    }
}
