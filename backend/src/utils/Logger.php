<?php
/**
 * StudyWS Backend - Logger
 *
 * Structured logging system with:
 * - Multiple log levels (DEBUG, INFO, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)
 * - Separate log files by level and date
 * - Development vs Production modes
 * - Context data support (for structured logging)
 * - Log rotation based on date
 *
 * Usage:
 *   Logger::info('User registered', ['email' => 'user@example.com', 'user_id' => 123]);
 *   Logger::error('Database connection failed', ['error' => $e->getMessage()]);
 *   Logger::debug('Processing payment request', ['amount' => 99.99, 'currency' => 'USD']);
 */

require_once __DIR__ . '/LogLevel.php';

class Logger
{
    /**
     * Log directory path
     */
    private static string $logsDir = '';

    /**
     * Current environment (development / production)
     */
    private static string $environment = 'development';

    /**
     * Minimum log level to write (filter logs)
     * In production, skip DEBUG and NOTICE logs
     */
    private static string $minLevel = LogLevel::DEBUG;

    /**
     * Whether to also output to stdout (useful for Docker)
     */
    private static bool $echoToStdout = false;

    /**
     * Initialize logger with configuration
     *
     * @param string $logsDir Directory where logs will be stored
     * @param string $environment 'development' or 'production'
     * @param bool $echoToStdout Whether to output logs to stdout
     * @param string $minLevel Minimum log level to process
     */
    public static function init(
        string $logsDir,
        string $environment = 'development',
        bool $echoToStdout = false,
        string $minLevel = LogLevel::DEBUG
    ): void {
        self::$logsDir = rtrim($logsDir, '/\\');
        self::$environment = $environment;
        self::$echoToStdout = $echoToStdout;
        self::$minLevel = $minLevel;

        // In production, don't log DEBUG and NOTICE
        if ($environment === 'production' && self::$minLevel === LogLevel::DEBUG) {
            self::$minLevel = LogLevel::WARNING;
        }

        // Create logs directory if it doesn't exist
        self::ensureLogsDirectory();
    }

    /**
     * Log an emergency message (system is unusable)
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log an alert (action must be taken immediately)
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a critical error
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log an error
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice 
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Main logging method
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $context Additional context data
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        // Validate log level
        if (!LogLevel::isValid($level)) {
            return;
        }

        // Check if this level should be logged (filter by minimum level)
        if (!self::shouldLog($level)) {
            return;
        }

        // Format the log entry
        $entry = self::formatLogEntry($level, $message, $context);

        // Write to file(s)
        self::writeToFile($level, $entry);

        // Echo to stdout if enabled (useful for Docker, systemd, etc)
        if (self::$echoToStdout) {
            fwrite(STDERR, $entry);
        }
    }

    /**
     * Check if this log level should be logged based on minimum level
     */
    private static function shouldLog(string $level): bool
    {
        $minSeverity = LogLevel::getSeverity(self::$minLevel);
        $levelSeverity = LogLevel::getSeverity($level);

        // Lower severity number = more important = should log
        // DEBUG=7, INFO=6, WARNING=4, ERROR=3, etc
        // If min is WARNING (4), only log if severity <= 4 (WARNING, ERROR, CRITICAL, etc)
        return $levelSeverity !== null && $minSeverity !== null && $levelSeverity <= $minSeverity;
    }

    /**
     * Format a log entry with timestamp, level, message, and context
     *
     * Format (production):
     *   [2024-02-02 14:30:45] [ERROR] User login failed | {"email":"user@test.com","attempts":3}
     *
     * Format (development - pretty printed):
     *   [2024-02-02 14:30:45] [ERROR] User login failed
     *   Context: {
     *     "email": "user@test.com",
     *     "attempts": 3
     *   }
     */
    private static function formatLogEntry(string $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelPadded = str_pad($level, 9, ' ', STR_PAD_RIGHT);

        // Production: compact format on single line
        if (self::$environment === 'production') {
            $contextStr = !empty($context)
                ? ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : '';
            return "[$timestamp] [$levelPadded] $message$contextStr\n";
        }

        // Development: printed format
        $entry = "[$timestamp] [$levelPadded] $message";

        // Append pretty-printed context if available
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $entry .= "\nContext: $contextJson";
        }

        $entry .= "\n";
        return $entry;
    }

    /**
     * Write log entry to file(s)
     *
     * Files are organized as:
     * - logs/app-YYYY-MM-DD.log (all logs)
     * - logs/error-YYYY-MM-DD.log (only ERROR, CRITICAL, ALERT, EMERGENCY)
     * - logs/debug-YYYY-MM-DD.log (only DEBUG logs, development only)
     * - logs/YYYY-MM-DD.log (daily rollover)
     */
    private static function writeToFile(string $level, string $entry): void
    {
        try {
            $today = date('Y-m-d');

            // Write to main application log
            self::appendToFile("app-$today.log", $entry);

            // Write to level-specific logs
            if ($level === LogLevel::ERROR || $level === LogLevel::CRITICAL || 
                $level === LogLevel::ALERT || $level === LogLevel::EMERGENCY) {
                self::appendToFile("error-$today.log", $entry);
            }

            // In development, also write DEBUG logs to separate file
            if (self::$environment === 'development' && $level === LogLevel::DEBUG) {
                self::appendToFile("debug-$today.log", $entry);
            }
        } catch (Exception $e) {
            // If logging fails, attempt to log the error to stderr
            fwrite(STDERR, "[LOGGING ERROR] Failed to write log: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Append content to a log file with file locking
     *
     * Uses LOCK_EX to prevent multiple processes from writing simultaneously
     */
    private static function appendToFile(string $filename, string $content): void
    {
        // Full path to log file
        $filepath = self::$logsDir . '/' . $filename;
        $handle = fopen($filepath, 'a');

        if ($handle === false) {
            throw new Exception("Cannot open log file: $filepath");
        }

        // Acquire exclusive lock
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new Exception("Cannot acquire lock on: $filepath");
        }

        // Write content
        fwrite($handle, $content);

        // Release lock and close
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Ensure logs directory exists and is writable
     */
    private static function ensureLogsDirectory(): void
    {
        // Create directory if it doesn't exist
        if (!is_dir(self::$logsDir)) {
            $created = @mkdir(self::$logsDir, 0755, true);
            if (!$created) {
                throw new Exception("Cannot create logs directory: " . self::$logsDir);
            }
        }

        // Check if writable
        if (!is_writable(self::$logsDir)) {
            throw new Exception("Logs directory is not writable: " . self::$logsDir);
        }
    }

    /**
     * Get path to logs directory
     */
    public static function getLogsDir(): string
    {
        return self::$logsDir;
    }

    /**
     * Get current environment
     */
    public static function getEnvironment(): string
    {
        return self::$environment;
    }

    /**
     * Get minimum log level being processed
     */
    public static function getMinLevel(): string
    {
        return self::$minLevel;
    }

    /**
     * Clear old log files (older than $days)
     * Useful for maintenance and disk space management
     *
     * @param int $days Delete logs older than this many days
     * @return int Number of files deleted
     */
    public static function cleanup(int $days = 30): int
    {
        // Ensure logs directory exists
        if (!is_dir(self::$logsDir)) {
            return 0;
        }

        // Delete old log files
        $deleted = 0;
        $cutoffTime = strtotime("-$days days");
        $files = scandir(self::$logsDir);

        // Iterate files and delete old ones
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Full path to file
            $filepath = self::$logsDir . '/' . $file;
            if (is_file($filepath) && filemtime($filepath) < $cutoffTime) {
                if (@unlink($filepath)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
