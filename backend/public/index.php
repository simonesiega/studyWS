<?php
/**
 * StudyWS Backend - Front Controller
 *
 * This file is the single entry point of the backend (Front Controller pattern):
 * - Sets common response headers (JSON + CORS).
 * - Handles CORS preflight requests (OPTIONS).
 * - Boots the application by loading config and router.
 * - Delegates the request handling to the Router.
 */


/* Response headers (JSON + CORS)  */
// Always respond with JSON (API-first).
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// Allowed methods: aligned with REST methods used by the backend.
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
// Allowed headers: Authorization is required for JWT Bearer tokens.
header('Access-Control-Allow-Headers: Content-Type, Authorization');


/* CORS preflight (OPTIONS)  */
// Preflight requests check whether the browser can perform the actual request.
// If the request is OPTIONS, respond immediately without going through the router.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


/* Bootstrap + Dispatch  */
try {
    /**
     * Load configuration (constants, env vars, etc.):
     * If config.php validates JWT_SECRET and throws exceptions, they will be handled here.
     */
    require_once __DIR__ . '/../src/config/config.php';

    /**
     * Load the router that maps METHOD+PATH → Controller::action.
     * The Router also handles "protected routes" (middleware).
     */
    require_once __DIR__ . '/../src/Router.php';

    // Dispatch the request.
    $router = new Router();
    $router->dispatch();

} 
catch (Throwable $e) {
    /**
     * Global fail-safe exception handler:
     * - Catches any unhandled Throwable (Exception + Error) to prevent PHP from returning an HTML fatal error.
     * - Returns a JSON response to the client.
     * - Shows the real message only when APP_DEBUG is enabled; otherwise returns a generic message (safer for production).
     */
    error_log('Fatal error: ' . $e->getMessage());

    // Internal Server Error
    http_response_code(500);

    // JSON error payload 
    echo json_encode([
        'error'   => 'Internal Server Error',
        'message' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'An error occurred',
    ]);
}
