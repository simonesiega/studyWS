<?php
/**
 * StudyWS Backend - AuthController
 *
 * - Handle authentication endpoints:
 *   - POST /auth/register
 *   - POST /auth/login
 *   - POST /auth/refresh
 *   - POST /auth/logout (protected)
 * - Validate incoming JSON payloads.
 * - Create and verify password hashes (bcrypt).
 * - Issue access/refresh tokens (JWT).
 * - Persist refresh tokens as sessions in DB (hashed) and support token rotation.
 *
 * This controller is designed to be called by Router.php in the format:
 *   $controller = new AuthController($requestBodyArray);
 *   $controller->register();
 *   $controller->sendResponse();
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/SessionRepository.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController
{
    /** @var array Parsed JSON request body */
    private array $request;

    /** @var array Standard JSON response payload */
    private array $response = [
        'success' => false,
        'data' => null,
        'error' => null,
    ];

    /**
     * @param array $request Parsed JSON body from Router (may be empty).
     */
    public function __construct(array $request = [])
    {
        $this->request = $request;
    }

    /**
     * POST /auth/register
     *
     * - Create a new user account (email + password + basic profile fields).
     * - Hash the password using bcrypt before storing it in the database.
     * - Issue an access token (short-lived JWT) to authenticate API calls.
     * - Issue a refresh token (long-lived JWT) to allow the client to refresh the session.
     * - Persist the refresh token server-side as a DB "session" record (stored as sha256 hash),
     *   so you can revoke/rotate refresh tokens and support logout.
     *
     * Request JSON:
     * {
     *   "email": "user@example.com",
     *   "password": "SecurePassword123!",
     *   "first_name": "John",
     *   "last_name": "Doe"
     * }
     *
     * Success response (201 - Created):
     * {
     *   "success": true,
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "email": "user@example.com",
     *       "first_name": "John",
     *       "last_name": "Doe"
     *     },
     *     "access_token": "<JWT access token>",
     *     "refresh_token": "<JWT refresh token>",
     *     "token_type": "Bearer",
     *     "expires_in": 3600,
     *     "session_id": 123
     *   }
     * }
     *
     * Possible error responses:
     * - 400: Missing/invalid email, short password, missing first/last name.
     * - 409: Email already registered (if UserRepository throws this).
     * - 500: Database or unexpected server error.
     */
    public function register(): void
    {
        try {
            // Read fields from parsed JSON body (Router already decoded php://input)
            $email = $this->request['email'] ?? null;
            $password = $this->request['password'] ?? null;
            $firstName = $this->request['first_name'] ?? null;
            $lastName = $this->request['last_name'] ?? null;
            
            // Validate request data and throw an Exception with an HTTP-like code on failure
            $this->validateRegistration($email, $password, $firstName, $lastName);

            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            // Create the user in DB 
            $userId = UserRepository::create($email, $passwordHash, $firstName, $lastName);

            // Issue tokens:
            // - access_token: short-lived JWT used in Authorization header
            // - refresh_token: long-lived JWT used to obtain a new access token later
            $accessToken  = JWT::createAccessToken($userId, $email);
            $refreshToken = JWT::createRefreshToken($userId, $email);

            // Store refresh token server-side as a hash (so DB leaks won't expose real tokens)
            $refreshTokenHash = hash('sha256', $refreshToken);
            $expiresAt = time() + JWT_REFRESH_EXPIRY;

            // Create a session row in DB
            $sessionId = SessionRepository::create(
                $userId,
                $refreshTokenHash,
                $expiresAt,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            );

            // Build JSON response payload
            $this->response = [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $userId,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ],
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => JWT_ACCESS_EXPIRY,
                    'session_id' => $sessionId,
                ],
            ];

            http_response_code(201);
        } 
        catch (Exception $e) {
            // Convert thrown Exceptions into a consistent JSON error response
            http_response_code($e->getCode() ?: 400);
            $this->response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /auth/login
     *
     * - Authenticate a user using email + password.
     * - Issue a short-lived access token (JWT) for API authorization.
     * - Issue a long-lived refresh token (JWT) for obtaining new access tokens.
     * - Create a DB session record (hashed refresh token) to support:
     *   - Token rotation on refresh,
     *   - Logout / session revocation,
     *   - Multi-device sessions (each login can create a new session_id).
     *
     * Request JSON:
     * {
     *   "email": "user@example.com",
     *   "password": "SecurePassword123!"
     * }
     *
     * Success response (200):
     * {
     *   "success": true,
     *   "data": {
     *     "user": {
     *       "id": 123,
     *       "email": "user@example.com",
     *       "first_name": "John",
     *       "last_name": "Doe"
     *     },
     *     "access_token": "<JWT access token>",
     *     "refresh_token": "<JWT refresh token>",
     *     "token_type": "Bearer",
     *     "expires_in": 3600,
     *     "session_id": 456
     *   }
     * }
     *
     * Possible error responses:
     * - 400: Missing email or password.
     * - 401: Invalid credentials (user not found or wrong password).
     * - 500: Unexpected server error (DB issues, etc.).
     */
    public function login(): void
    {
        try {
            // Read credentials from request body
            $email = $this->request['email'] ?? null;
            $password = $this->request['password'] ?? null;

            // Validation
            if (!$email || !$password) {
                throw new Exception('Email and password are required', 400);
            }

            // Load user record by email from DB
            $user = UserRepository::findByEmail($email);
            if (!$user) {
                throw new Exception('Invalid credentials', 401);
            }

            // Verify password against stored bcrypt hash
            if (!password_verify($password, $user['password_hash'])) {
                throw new Exception('Invalid credentials', 401);
            }

            $userId = (int)$user['id'];

            // Update last_access for auditing/UX
            UserRepository::updateLastAccess($userId);

            // Issue new tokens for this login
            $accessToken  = JWT::createAccessToken($userId, $email);
            $refreshToken = JWT::createRefreshToken($userId, $email);

            // Persist refresh token as hashed session (enables logout + refresh rotation)
            $refreshTokenHash = hash('sha256', $refreshToken);
            $expiresAt = time() + JWT_REFRESH_EXPIRY;

            $sessionId = SessionRepository::create(
                $userId,
                $refreshTokenHash,
                $expiresAt,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            );

            // Build success response
            $this->response = [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $userId,
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                    ],
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => JWT_ACCESS_EXPIRY,
                    'session_id' => $sessionId,
                ],
            ];

            http_response_code(200);
        } 
        catch (Exception $e) {
            http_response_code($e->getCode() ?: 400);
            $this->response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /auth/refresh
     *
     * - Exchange a valid refresh token for a new access token.
     * - Implement refresh-token rotation:
     *   - The presented refresh token must match an active DB session (stored as hash).
     *   - Once used, the old session is revoked.
     *   - A new refresh token is issued and stored (hashed) as a new session row.
     *
     * Request JSON:
     * {
     *   "refresh_token": "<JWT refresh token>"
     * }
     *
     * Success response (200):
     * {
     *   "success": true,
     *   "data": {
     *     "access_token": "<new JWT access token>",
     *     "refresh_token": "<new JWT refresh token>",
     *     "token_type": "Bearer",
     *     "expires_in": 3600,
     *     "session_id": 789
     *   }
     * }
     *
     * Security notes:
     * - The refresh token must have payload.type === "refresh".
     * - We do NOT store refresh tokens in plaintext; we store hash('sha256', refresh_token).
     * - Rotation reduces the impact of a stolen refresh token (one-time use).
     *
     * Possible error responses:
     * - 400: Missing refresh_token.
     * - 401: Invalid refresh token (bad signature/expired/type mismatch),
     *        or refresh token not found/revoked in DB.
     * - 500: Unexpected server error (DB issues, etc.).
     */
    public function refresh(): void
    {
        try {
            // Read refresh_token from request body
            $refreshToken = $this->request['refresh_token'] ?? null;
            if (!$refreshToken) {
                throw new Exception('Refresh token is required', 400);
            }

            // Verify JWT signature/expiry and ensure it's a refresh token
            $payload = JWT::verify($refreshToken);
            if (!$payload || ($payload['type'] ?? null) !== 'refresh') {
                throw new Exception('Invalid refresh token', 401);
            }

            // Identify the user from the token payload
            $userId = (int)$payload['sub'];
            $email  = $payload['email'];

            // Match presented refresh token to an active DB session (hash comparison)
            $refreshTokenHash = hash('sha256', $refreshToken);
            $session = SessionRepository::findByTokenHash($userId, $refreshTokenHash);

            if (!$session) {
                // // Token is expired/revoked/unknown (or DB cleaned it up)
                throw new Exception('Refresh token not found or revoked', 401);
            }

            // Rotation: revoke the old session so this refresh token becomes one-time use
            SessionRepository::revoke((int)$session['id']);

            // Issue a new access token + a new refresh token
            $newAccessToken  = JWT::createAccessToken($userId, $email);
            $newRefreshToken = JWT::createRefreshToken($userId, $email);

            // Persist the new refresh token as a new DB session row
            $newRefreshTokenHash = hash('sha256', $newRefreshToken);
            $expiresAt = time() + JWT_REFRESH_EXPIRY;

            $newSessionId = SessionRepository::create(
                $userId,
                $newRefreshTokenHash,
                $expiresAt,
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            );

            // Return the rotated tokens to the client
            $this->response = [
                'success' => true,
                'data' => [
                    'access_token' => $newAccessToken,
                    'refresh_token' => $newRefreshToken,
                    'token_type' => 'Bearer',
                    'expires_in' => JWT_ACCESS_EXPIRY,
                    'session_id' => $newSessionId,
                ],
            ];

            http_response_code(200);
        } 
        catch (Exception $e) {
            http_response_code($e->getCode() ?: 400);
            $this->response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * POST /auth/logout 
     *
     * - Invalidate server-side sessions for the authenticated user.
     * - This effectively revokes refresh tokens stored in DB and prevents future refresh.
     *
     * Requirements:
     * - Route must be protected by AuthMiddleware (Router sets protected=true).
     * - Client must send: Authorization: Bearer <access_token>.
     * - AuthMiddleware must validate the access token and populate the authenticated user context.
     *
     * Success response (200):
     * {
     *   "success": true,
     *   "message": "Logged out successfully"
     * }
     *
     * Possible error responses:
     * - 401: Missing/invalid access token, or middleware not executed.
     * - 500: Unexpected server error (DB issues, etc.).
     */
    public function logout(): void
    {
        try {
            if (!AuthMiddleware::isAuthenticated()) {
                throw new Exception('Unauthorized', 401);
            }

            // Read authenticated user ID from middleware context
            $userId = AuthMiddleware::getUserId();
            if (!$userId) {
                throw new Exception('Unauthorized', 401);
            }

            // Revoke all active sessions for the user (logout from all devices)
            SessionRepository::revokeAllForUser($userId);

            $this->response = [
                'success' => true,
                'message' => 'Logged out successfully',
            ];

            http_response_code(200);
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 400);
            $this->response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sends the JSON response to the client.
     * Router calls this after invoking a controller action.
     */
    public function sendResponse(): void
    {   
        // Ensure JSON output for every controller action
        header('Content-Type: application/json');

        // JSON_UNESCAPED_SLASHES keeps tokens/URLs cleaner (no escaping of "/")
        echo json_encode($this->response, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validates registration input and throws exceptions with proper HTTP codes.
     */
    private function validateRegistration(
        ?string $email,
        ?string $password,
        ?string $firstName,
        ?string $lastName
    ): void {
        // Validate email format (basic, but prevents obvious invalid input)
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid email is required', 400);
        }

        // Password policy
        if (!$password || strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters', 400);
        }

        // Profile fields required by API contract
        if (!$firstName || !$lastName) {
            throw new Exception('First name and last name are required', 400);
        }
    }
}
