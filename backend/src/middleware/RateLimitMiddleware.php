<?php
/**
 * StudyWS Backend - RateLimitMiddleware
 *
 * - Protect authentication endpoints from brute-force attacks.
 * - Track request count per IP + endpoint.
 * - Return 429 (Too Many Requests) when limit exceeded.
 * - Automatic cleanup of old records (expire old entries).
 *
 * Configuration per endpoint:
 * - login: 5 attempts per minute
 * - register: 3 attempts per minute
 * - refresh: 10 attempts per minute
 */

require_once __DIR__ . '/../Database.php';

class RateLimitMiddleware
{
    // Limit configuration: [route => [max_requests, window_seconds]]
    private const LIMITS = [
        '/auth/login' => [5, 60], // 5 attempts per minute
        '/auth/register' => [3, 60], // 3 attempts per minute
        '/auth/refresh' => [10, 60], // 10 attempts per minute
    ];

    /**
     * Check if the request exceeds rate limit for the given path.
     *
     * @param string $path Request path (e.g., "/auth/login")
     * @return bool True if within limit (allow), false if exceeded (block)
     */
    public static function checkLimit(string $path): bool
    {
        // No limit configured for this path
        if (!isset(self::LIMITS[$path])) {
            return true;
        }

        [$maxRequests, $windowSeconds] = self::LIMITS[$path];
        $clientIp = self::getClientIp();
        $now = time();
        $windowStart = $now - $windowSeconds;

        try {
            // Count requests from this IP for this path in the time window
            $sql = "
                SELECT COUNT(*) as count
                FROM rate_limit_log
                WHERE ip = :ip
                  AND path = :path
                  AND timestamp > :window_start
            ";

            $result = Database::fetchOne($sql, [
                ':ip' => $clientIp,
                ':path' => $path,
                ':window_start' => $windowStart,
            ]);

            // Int cast count
            $requestCount = (int)($result['count'] ?? 0);

            // Log this request
            $logSql = "
                INSERT INTO rate_limit_log (ip, path, timestamp)
                VALUES (:ip, :path, :timestamp)
            ";

            Database::execute($logSql, [
                ':ip' => $clientIp,
                ':path' => $path,
                ':timestamp' => $now,
            ]);

            // Cleanup old entries (older than 24 hours)
            self::cleanup();

            // Check if limit exceeded
            return $requestCount < $maxRequests;
        } 
        catch (Exception $e) {
            error_log('[RateLimit] Error: ' . $e->getMessage());
            // If DB fails, allow the request (don't break the app)
            return true;
        }
    }

    /**
     * Send 429 (Too Many Requests) response and exit.
     */
    public static function sendTooManyRequests(): void
    {
        http_response_code(429);

        // JSON error payload
        $response = [
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
        ];

        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Get client IP address (handles proxies like Nginx).
     */
    private static function getClientIp(): string
    {
        // If behind a proxy, use X-Forwarded-For
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Clean up old log entries (older than 24 hours).
     */
    private static function cleanup(): void
    {
        try {
            // Entries older than 24 hours (86400 seconds)
            $cutoffTime = time() - (24 * 3600);

            $sql = "DELETE FROM rate_limit_log 
            WHERE timestamp < :cutoff"
            ;

            Database::execute($sql, [':cutoff' => $cutoffTime]);
        } 
        catch (Exception $e) {
            // Silently fail cleanup; it's not critical
            error_log('[RateLimit] Cleanup error: ' . $e->getMessage());
        }
    }
}
