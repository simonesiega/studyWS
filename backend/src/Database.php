<?php
/**
 * StudyWS Backend - Database
 *
 * - PDO wrapper for PostgreSQL.
 * - Provides a shared (singleton-like) PDO connection and helper methods for:
 *   - Preparing/executing parameterized queries
 *   - Fetching one row / all rows
 *   - Executing write queries and returning affected rows
 */

class Database
{
    /**
     * Shared PDO connection instance.
     * It is created the first time getConnection() is called.
     */
    private static ?PDO $connection = null;

    /**
     * Get (or create) the PDO database connection.
     *
     * Connection options:
     * - ERRMODE_EXCEPTION: throw exceptions on DB errors
     * - FETCH_ASSOC: fetch results as associative arrays by default
     * - EMULATE_PREPARES=false: use native prepared statements
     *
     * @return PDO Active PDO connection.
     *
     * @throws Exception If the connection cannot be established.
     */
    public static function getConnection(): PDO
    {
        // Lazy initialization of the PDO instance
        if (self::$connection === null) {
            try {
                // PostgreSQL DSN
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME
                );

                // Create and store the PDO instance 
                self::$connection = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                // Log internal details; throw a generic exception to the caller
                error_log('Database connection failed: ' . $e->getMessage());
                throw new Exception('Database connection failed', 0, $e);
            }
        }

        return self::$connection;
    }

    /**
     * Close the current database connection.
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Prepare and execute a SQL statement with optional bound parameters.
     *
     * @param string $sql SQL query with named placeholders (e.g., :id, :email).
     * @param array $params Parameters to bind (e.g., [':id' => 1]).
     *
     * @return PDOStatement Executed statement (ready for fetch()).
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);

        // Execute with bound params (prevents SQL injection).
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Fetch a single row as an associative array.
     *
     * @param string $sql SQL query.
     * @param array $params Bound parameters.
     *
     * @return array|null First row if found, otherwise null.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);

        // fetch() returns false when there are no rows
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Fetch all rows as an array of associative arrays.
     *
     * @param string $sql SQL query.
     * @param array $params Bound parameters.
     *
     * @return array List of rows (empty array if none).
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert helper that returns lastInsertId().
     *
     * IMPORTANT (PostgreSQL):
     * - lastInsertId() depends on sequences and may not behave as expected unless configured.
     *
     * @param string $sql INSERT query.
     * @param array $params Bound parameters.
     *
     * @return int lastInsertId() cast to int.
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);

        return (int)self::getConnection()->lastInsertId();
    }

    /**
     * Execute an UPDATE/DELETE (or any write query) and return affected rows count.
     *
     * @param string $sql SQL query.
     * @param array $params Bound parameters.
     *
     * @return int Number of affected rows.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
}
