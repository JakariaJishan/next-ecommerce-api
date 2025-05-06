<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="API endpoints for authentication"
 * )
 */
class AuthDocs
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register a new user",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="username", type="string", description="User's username", example="john_doe"),
     *             @OA\Property(property="email", type="string", description="User's email address", example="john@example.com"),
     *             @OA\Property(property="password", type="string", description="User's password", example="strongPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="User registered!"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response="422", description="Validation errors")
     * )
     */
    public function register()
    {
        // This method is only for documentation purposes.
    }

    /**
     * @OA\Post(
     *      path="/api/login",
     *      summary="Login a user",
     *      tags={"Authentication"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="email", type="string", description="User's email address", example="john@example.com"),
     *              @OA\Property(property="password", type="string", description="User's password", example="strongPassword123")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="User logged in successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="User registered!"),
     *              @OA\Property(property="user", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response="422", description="Validation errors")
     *  )
     */
    public function login()
    {
        // This method is only for documentation purposes.
    }

    /**
     * @OA\Post(
     *       path="/api/logout",
     *       summary="Logs out the authenticated user",
     *       tags={"Authentication"},
     *       security={{"bearerAuth": {}}},
     *       @OA\Response(
     *           response=200,
     *           description="User logged out successfully",
     *           @OA\JsonContent(
     *               type="object",
     *               @OA\Property(property="success", type="boolean", example=true),
     *               @OA\Property(property="message", type="string", example="Logged out successfully.")
     *           )
     *       ),
     *       @OA\Response(
     *           response=401,
     *           description="Unauthorized",
     *           @OA\JsonContent(
     *               type="object",
     *               @OA\Property(property="success", type="boolean", example=false),
     *               @OA\Property(property="message", type="string", example="User not authenticated.")
     *           )
     *       )
     *  )
     */
    public function logout()
    {
        // This method is only for documentation purposes.
    }

    /**
     * @OA\Get(
     *     path="/api/current-user-sessions",
     *     summary="Retrieve all active sessions of the current authenticated user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="User sessions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User sessions retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sessions",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="session_id_123"),
     *                         @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
     *                         @OA\Property(property="user_agent", type="string", example="Mozilla/5.0 (Windows NT 10.0; Win64; x64)"),
     *                         @OA\Property(property="last_activity", type="string", format="date-time", example="2023-01-01T12:00:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not authenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while retrieving user sessions."),
     *             @OA\Property(property="data", type="object", @OA\Property(property="error", type="string", example="Detailed error message"))
     *         )
     *     )
     * )
     */

    public function currentUserSessions()
    {
        // This method is only for documentation purposes.
    }

    /**
     * @OA\Get(
     *     path="/api/auth/google/callback",
     *     summary="Handle Google authentication callback",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=true,
     *         description="Google access token",
     *         @OA\Schema(
     *             type="string",
     *             example="ya29.a0AXeO80SpuFLJd4H2KOyWGlDJPaqo3n0_BJ2zCaWlBfu3QrfkMQc3CH60RgcarTvdpv6tEEuuz_XqIruCDlhb94DkJ4kCkwfEEY1I3jeUmDxUu3wLP4kXCyyZ36raoCAKDRnxyStgTqtOF8jUOQpLw0QBM_YDDh4q209IKe4paCgYKAWISARISFQHGX2MiWcWqA98ZrYe7R2P0RDTnBA0175"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Google authentication successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Google authentication successful."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     description="Authenticated user details",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="john_doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="google_id", type="string", example="11223344556677889900"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T12:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:00:00Z")
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="object",
     *                     @OA\Property(property="accessToken", type="object", description="Access token details"),
     *                     @OA\Property(property="plainTextToken", type="string", example="1|iSwhUrlAdciGb5keCPhwewRwEFwxuADLVfqm9CSS1c1f38d1")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Google token is required",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Google token is required."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Authentication failed."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Invalid token or some other error.")
     *             )
     *         )
     *     )
     * )
     */

    public function handleGoogleCallback(){

    }
}
