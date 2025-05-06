<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

abstract class Controller
{
    /**
     * @OA\Info(
     *    title="Swagger with Laravel",
     *    version="1.0.0",
     *    description="API documentation for the Laravel application"
     * )
     * @OA\SecurityScheme(
     *     securityScheme="bearerAuth",
     *     type="http",
     *     scheme="bearer",
     *     bearerFormat="JWT"
     * )
     */
}
