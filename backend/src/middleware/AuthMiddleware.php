<?php
/**
 * StudyWS Backend - AuthMiddleware
 *
 * - Protect "Auth: Yes" endpoints by validating JWT access tokens.
 * - Read the Authorization header (Bearer token).
 * - Verify token signature and expiry using the JWT utility.
 * - Reject refresh tokens for API authorization (token type must be "access").
 * - Load the user from the database and attach a minimal user context for downstream code.
 *
 * Router marks certain routes as protected. 
 * For protected routes, Router calls AuthMiddleware::authenticate().
 * If authentication fails, Router can call AuthMiddleware::sendUnauthorized() and stop execution.
 *
 * Expected Authorization header format:
 * - "Authorization: Bearer <access_token>"
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthMiddleware
{
    /**
     * In-memory request context for the currently authenticated user.
     * Router calls authenticate() once per request for protected routes.
     */
    private static ?array $user = null;

    /**
     * Validates the Authorization header and populates the authenticated user context.
     *
     * - Read Authorization header
     * - Extract Bearer token
     * - Verify JWT signature + exp
     * - Ensure token type === "access" (refresh tokens must NOT authorize API calls)
     * - Load user from DB (ensures user still exists / not deleted)
     *
     * @return bool True if authenticated, false otherwise (Router decides what to do).
     */
    public static function authenticate(): bool
    {
        // Extract Authorization header from different server setups
        $authHeader = self::getAuthorizationHeader();
        if (!$authHeader) {
            // No auth provided
            return false; 
        }

        // Expect "Bearer <token>"
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            // Malformed header
            return false; 
        }

        $token = trim($matches[1]);

        // Verify JWT (signature + expiry)
        $payload = JWT::verify($token);
        if (!$payload) {
            // Invalid/Expired token
            return false; 
        }

        // Ensure this is an access token, not a refresh token
        if (!isset($payload['type']) || $payload['type'] !== 'access') {
            return false;
        }

        // Load user from DB using "sub" claim (subject = userId)
        $user = UserRepository::findById((int)$payload['sub']);
        if (!$user) {
            // User does not exist anymore
            return false; 
        }

        // Store only the fields you want downstream code to access
        self::$user = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'registration_date' => $user['registration_date'],
            'last_access' => $user['last_access'],
        ];

        return true;
    }

    /**
     * Returns the authenticated user context (or null if unauthenticated).
     */
    public static function getUser(): ?array
    {
        return self::$user;
    }

    /**
     * Returns authenticated user id (or null).
     */
    public static function getUserId(): ?int
    {
        return self::$user['id'] ?? null;
    }

    /**
     * Checks whether authenticate() has successfully populated the context.
     */
    public static function isAuthenticated(): bool
    {
        return self::$user !== null;
    }

    /**
     * Extract Authorization header (handles different server configurations).
     *
     * @return string|null The raw Authorization header value.
     */
    private static function getAuthorizationHeader(): ?string
    {
        // Most common (Nginx/FPM or general PHP setups)
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Some Apache setups 
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // Fallback to getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }

        return null;
    }

    /**
     * Sends a standard JSON 401 response and terminates the request.
     * Router can call this when authenticate() returns false on protected routes.
     */
    public static function sendUnauthorized(): void
    {
        // 401 Unauthorized response
        http_response_code(401);
        header('Content-Type: application/json');

        // JSON error payload
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => 'Invalid or missing authentication token',
        ], JSON_UNESCAPED_SLASHES);

        exit;
    }
}
