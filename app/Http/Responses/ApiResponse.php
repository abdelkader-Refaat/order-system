<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Unified API envelope: { "key", "msg", "data" }.
 *
 * **`key` is only `success` or `fail`** so clients (e.g. Flutter) can branch on that alone.
 * Use the HTTP status code and `data` to tell cases apart: e.g. 422 + `data.errors` = validation,
 * 401 = unauthenticated, 404 = not found.
 */
final class ApiResponse
{
    public const KEY_SUCCESS = 'success';

    public const KEY_FAIL = 'fail';

    public static function make(
        string $key,
        string $msg,
        mixed $data = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'key' => $key,
            'msg' => $msg,
            'data' => $data,
        ], $status);
    }

    public static function success(string $msg, mixed $data = null, int $status = 200): JsonResponse
    {
        return self::make(self::KEY_SUCCESS, $msg, $data, $status);
    }

    public static function created(string $msg, mixed $data = null): JsonResponse
    {
        return self::success($msg, $data, JsonResponse::HTTP_CREATED);
    }
}
