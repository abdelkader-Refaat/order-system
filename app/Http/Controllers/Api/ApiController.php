<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Base API controller: shared helpers for the unified envelope { key, msg, data }.
 *
 * Pass translation keys (e.g. messages.auth.registered) to *Response methods; they are resolved with __().
 * This differs from older projects that used status 1/0 and "message" — this app uses key/msg/data via {@see ApiResponse}.
 */
abstract class ApiController extends Controller
{
    /**
     * Resolve a JsonResource (or Resource::collection) payload for nesting under "data".
     */
    protected function resolveResource(Request $request, JsonResource $resource): array
    {
        return $resource->resolve($request);
    }

    protected function successResponse(string $messageKey, mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::success(__($messageKey), $data, $status);
    }

    protected function createdResponse(string $messageKey, mixed $data = null): JsonResponse
    {
        return ApiResponse::created(__($messageKey), $data);
    }

    /**
     * Generic failure (key: fail). Optional $errorDetail is only attached when app.debug is true (under data.error).
     */
    protected function errorResponse(
        string $messageKey,
        mixed $errorDetail = null,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
    ): JsonResponse {
        $data = null;
        if ($errorDetail !== null && config('app.debug')) {
            $data = ['error' => is_string($errorDetail) ? $errorDetail : $errorDetail];
        }

        return ApiResponse::make(ApiResponse::KEY_FAIL, __($messageKey), $data, $status);
    }

    protected function forbiddenResponse(string $messageKey = 'messages.errors.forbidden'): JsonResponse
    {
        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            __($messageKey),
            null,
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Explicit not-found from a controller (global 404s are still handled in bootstrap/app.php).
     */
    protected function notFoundResponse(string $messageKey = 'messages.errors.not_found'): JsonResponse
    {
        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            __($messageKey),
            null,
            Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Manual validation shape — matches {@see ApiExceptionResponses::validation()} (data.errors, HTTP 422).
     */
    protected function validationErrorResponse(
        string $messageKey = 'messages.errors.validation_failed',
        mixed $errors = null,
    ): JsonResponse {
        $data = $errors !== null ? ['errors' => $errors] : null;

        return ApiResponse::make(
            ApiResponse::KEY_FAIL,
            __($messageKey),
            $data,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    protected function handleException(Throwable $e, string $defaultMessageKey = 'messages.errors.generic'): JsonResponse
    {
        logger()->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);

        return $this->errorResponse(
            $defaultMessageKey,
            $e->getMessage(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
