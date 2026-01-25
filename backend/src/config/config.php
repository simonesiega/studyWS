<?php
/**
 * StudyWS Backend - Configuration
 *
 * - Load environment variables from a local .env file.
 * - Define application constants (DB connection, JWT settings, runtime flags).
 * - Validate mandatory configuration (e.g., JWT_SECRET).
 * - Configure PHP error reporting based on APP_DEBUG.
 */

/* Load .env file if it exists (project root assumed at /src/config/../../.env) */
$envFile = __DIR__ . '/../../../.env';

// error_log('[config] Looking for .env at: ' . $envFile);
// error_log('[config] .env exists? ' . (file_exists($envFile) ? 'YES' : 'NO'));

if (file_exists($envFile)) {
    // parse_ini_file expects INI-like syntax (KEY=value)
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);


    if (is_array($env)) {
        // error_log('[config] Loaded keys: ' . implode(', ', array_keys($env)));

        foreach ($env as $key => $value) {
            // Normalize values a bit (remove surrounding quotes/spaces)
            $value = is_string($value) ? trim($value) : $value;
            if (is_string($value)) {
                $value = trim($value, "\"'");
            }

            // Populate $_ENV so the rest of the app can read from it
            $_ENV[$key] = $value;
        }

        // Debug JWT_SECRET presence without leaking full secret
        $hasJwt = isset($_ENV['JWT_SECRET']) && $_ENV['JWT_SECRET'] !== '';
        // error_log('[config] JWT_SECRET loaded? ' . ($hasJwt ? 'YES' : 'NO'));
        if ($hasJwt) {
            error_log('[config] JWT_SECRET length: ' . strlen((string)$_ENV['JWT_SECRET']));
        }
    }
} 
else {
    error_log('[config] .env not found, using defaults/real environment only');
}

/* Database configuration (PostgreSQL) */
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 5432));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'studyws');
define('DB_USER', $_ENV['DB_USER'] ?? 'studyws_user');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'password');


/* JWT configuration */
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? null);
define('JWT_ACCESS_EXPIRY', (int)($_ENV['JWT_ACCESS_EXPIRY'] ?? 3600)); // 1 hour
define('JWT_REFRESH_EXPIRY', (int)($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800)); // 7 days
define('JWT_ALGORITHM', 'HS256');


/* App configuration flags */
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

// Boolean parsing
$appDebug = $_ENV['APP_DEBUG'] ?? null;
$appDebugParsed = ($appDebug === null)
    ? true
    : filter_var($appDebug, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
define('APP_DEBUG', $appDebugParsed ?? true);

define('HASH_ALGORITHM', 'bcrypt');


/* Server configuration (informational) */
define('SERVER_PORT', (int)($_ENV['SERVER_PORT'] ?? 8080));

/* Validation */
// JWT_SECRET must be stable and present, otherwise tokens become unverifiable.
if (!JWT_SECRET) {
    throw new Exception('JWT_SECRET environment variable is required');
}


/* Error handling */
if (APP_DEBUG) {
    // Development: show errors
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} 
else {
    // Production: do not display errors to clients, only log
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    // Convert PHP warnings/notices into logs and prevent them from leaking
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        error_log("[$errno] $errstr in $errfile:$errline");
        return true; // marks the error as handled
    });
}
