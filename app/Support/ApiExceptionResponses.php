<?php

namespace App\Support;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API errors: same envelope as successes; {@see ApiResponse::KEY_FAIL} for all failure types.
 */
final class ApiExceptionResponses
{
    public static function wantsApiJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function validation(ValidationException $exception): JsonResponse
    {
        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            $exception->getMessage(),
            ['errors' => $exception->errors()],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function unauthenticated(AuthenticationException $exception): JsonResponse
    {
        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            $exception->getMessage() ?: __('Unauthenticated.'),
            null,
            JsonResponse::HTTP_UNAUTHORIZED,
        );
    }

    public static function notFound(NotFoundHttpException $exception, Request $request): JsonResponse
    {
        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            $exception->getMessage() ?: __('Not found.'),
            null,
            JsonResponse::HTTP_NOT_FOUND,
        );
    }

    public static function messageBrokerUnavailable(AMQPExceptionInterface $exception): JsonResponse
    {
        $data = null;
        if (config('app.debug')) {
            $data = ['exception' => $exception->getMessage()];
        }

        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            __('messages.broker_unavailable'),
            $data,
            JsonResponse::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
