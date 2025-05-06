<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *    title="Swagger with Laravel",
 *    version="1.0.0",
 *    description="API documentation for the Laravel application"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Parameter(
 *     parameter="AcceptHeader",
 *     name="Accept",
 *     in="header",
 *     required=true,
 *     description="Accept header for JSON response",
 *     @OA\Schema(
 *         type="string",
 *         default="application/json"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="username", type="string", example="john_doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:00:00Z")
 * )
 */
class SwaggerAnnotations {}
