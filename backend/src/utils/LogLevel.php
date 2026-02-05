<?php
/**
 * StudyWS Backend - Log Level Constants
 *
 * Standard log levels following RFC 5424 (syslog severity)
 */

class LogLevel
{
    // Emergency: system is unusable
    public const EMERGENCY = 'EMERGENCY';

    // Alert: action must be taken immediately
    public const ALERT = 'ALERT';

    // Critical: critical conditions
    public const CRITICAL = 'CRITICAL';

    // Error: error conditions
    public const ERROR = 'ERROR';

    // Warning: warning conditions
    public const WARNING = 'WARNING';

    // Notice: normal but significant condition
    public const NOTICE = 'NOTICE';

    // Info: informational messages
    public const INFO = 'INFO';

    // Debug: debug-level messages
    public const DEBUG = 'DEBUG';

    /**
     * All valid log levels
     */
    public const ALL_LEVELS = [
        self::EMERGENCY,
        self::ALERT,
        self::CRITICAL,
        self::ERROR,
        self::WARNING,
        self::NOTICE,
        self::INFO,
        self::DEBUG,
    ];

    /**
     * Log level severity ranking (lower number = higher severity)
     */
    public const SEVERITY = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7,
    ];

    /**
     * Validate if a log level is valid
     */
    public static function isValid(string $level): bool
    {
        return in_array($level, self::ALL_LEVELS, true);
    }

    /**
     * Get severity number (for filtering)
     */
    public static function getSeverity(string $level): ?int
    {
        return self::SEVERITY[$level] ?? null;
    }
}
