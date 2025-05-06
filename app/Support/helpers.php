<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

if (!function_exists('apiResponse')) {
    function apiResponse(bool $success, string $message, $data = [], ?string $wrapperKey = 'items', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => [],
        ];

        // Check if data is an array and contains a paginator under any key
        if (is_array($data)) {
            $paginatorKey = null;
            foreach ($data as $key => $value) {
                if ($value instanceof LengthAwarePaginator) {
                    $paginatorKey = $key;
                    break; // Stop after finding the first paginator
                }
            }

            if ($paginatorKey !== null) {
                $paginator = $data[$paginatorKey];
                $response['data'] = $data;
                $response['data'][$paginatorKey] = $paginator->items(); // Replace paginator with items under its original key
                $response['metadata'] = [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'total_pages' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total_items' => $paginator->total(),
                    ]
                ];
            } else {
                // No paginator found, treat as plain data
                $response['data'] = $wrapperKey ? [$wrapperKey => $data] : $data;
            }
        } elseif ($data instanceof LengthAwarePaginator) {
            // Handle case where entire data is a paginator
            $response['data'] = $wrapperKey ? [$wrapperKey => $data->items()] : $data->items();
            $response['metadata'] = [
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'total_pages' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total_items' => $data->total(),
                ]
            ];
        } else {
            // Non-paginated data
            $response['data'] = $wrapperKey ? [$wrapperKey => $data] : $data;
        }

        return response()->json($response, $statusCode);
    }
}
