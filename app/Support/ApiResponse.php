<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta === [] ? (object) [] : $meta,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => $meta === [] ? (object) [] : $meta,
        ], $status);
    }

    /**
     * Success envelope for a paginated result set.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $message = 'Operation completed successfully.',
        array $meta = [],
    ): JsonResponse {
        return self::success($paginator->items(), $message, [
            ...$meta,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
