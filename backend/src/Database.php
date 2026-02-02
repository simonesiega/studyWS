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

                // Log successful connection
                if (class_exists('Logger')) {
                    Logger::info('Database connection established', [
                        'host' => DB_HOST,
                        'port' => DB_PORT,
                        'name' => DB_NAME,
                    ]);
                }
            } 
            catch (PDOException $e) {
                if (class_exists('Logger')) {
                    Logger::critical('Database connection failed', [
                        'error' => $e->getMessage(),
                        'host' => DB_HOST,
                        'port' => DB_PORT,
                    ]);
                }
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
        // Execute the insert query
        self::query($sql, $params);

        // Return the last inserted ID
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
        // Execute the query
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin a database transaction.
     * 
     * Used for multi-step operations to ensure atomicity.
     * If any step fails, the entire transaction is rolled back.
     * 
     * Example:
     *   Database::beginTransaction();
     *   try {
     *       Database::execute("INSERT ...");
     *       Database::execute("INSERT ...");
     *       Database::commit();
     *   } catch (Exception $e) {
     *       Database::rollback();
     *       throw $e;
     *   }
     *
     * @return bool True if transaction started successfully.
     * @throws Exception If transaction is already active.
     */
    public static function beginTransaction(): bool
    {
        // Get PDO connection
        $pdo = self::getConnection();
        
        // Check for existing transaction
        if ($pdo->inTransaction()) {
            throw new Exception('Transaction already in progress', 400);
        }

        $result = $pdo->beginTransaction();
        
        // Log begin transaction event
        if (class_exists('Logger') && $result) {
            Logger::debug('Database transaction started');
        }

        return $result;
    }

    /**
     * Commit the current database transaction.
     * 
     * All changes made since beginTransaction() are permanently saved.
     *
     * @return bool True if commit was successful.
     * @throws Exception If no transaction is active.
     */
    public static function commit(): bool
    {   
        // Get PDO connection
        $pdo = self::getConnection();

        // Check for active transaction
        if (!$pdo->inTransaction()) {
            throw new Exception('No active transaction to commit', 400);
        }

        $result = $pdo->commit();

        // Log commit event
        if (class_exists('Logger') && $result) {
            Logger::debug('Database transaction committed');
        }

        return $result;
    }

    /**
     * Roll back the current database transaction.
     *
     * All changes made since beginTransaction() are discarded.
     *
     * @return bool True if the rollback was successful.
     * @throws Exception If no transaction is active.
     */
    public static function rollback(): bool
    {
        // Get PDO connection
        $pdo = self::getConnection();

        // Check for active transaction
        if (!$pdo->inTransaction()) {
            throw new Exception('No active transaction to rollback', 400);
        }

        $result = $pdo->rollback();

        // Log rollback event
        if (class_exists('Logger')) {
            Logger::warning('Database transaction rolled back');
        }

        return $result;
    }

    /**
     * Check whether a transaction is currently active on the PDO connection.
     *
     * @return bool True if a transaction is in progress, false otherwise.
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }
}
