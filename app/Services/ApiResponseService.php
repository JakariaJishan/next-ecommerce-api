<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ApiResponseService
{
    /**
     * Handle exceptions and return an appropriate API response.
     *
     * @param \Exception $e
     * @param array $requestData The request data to map validation errors (optional)
     * @return JsonResponse
     */
    public static function handleException(\Exception $e, array $requestData = []): JsonResponse
    {
        if ($e instanceof ValidationException) {
            // Get all validation errors
            $errors = $e->errors();

            // Extract the first error message or concatenate all messages
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = $message;
                }
            }
            // Use the first error message or join all for the response message
            $message = !empty($errorMessages) ? implode(' ', $errorMessages) : 'Validation failed.';

            // Optionally include detailed errors in data if needed
            $customErrors = [];
            foreach ($errors as $key => $messages) {
                if (preg_match('/permissions\.(\d+)/', $key, $matches) && isset($requestData['permissions'])) {
                    $index = $matches[1];
                    $permissionName = $requestData['permissions'][$index] ?? $key;
                    $customErrors[$permissionName] = ["The permission '$permissionName' has already been taken."];
                } else {
                    $customErrors[$key] = $messages;
                }
            }

            return apiResponse(false, $message, $customErrors, 'error', 422);
        }

        // Handle other unexpected errors
        return apiResponse(false, $e->getMessage(), [], 'error', 500);
    }
}
