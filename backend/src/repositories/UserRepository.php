<?php
/**
 * StudyWS Backend - UserRepository
 *
 * - Data access layer for the `users` table.
 * - Provides CRUD operations used by AuthController and AuthMiddleware:
 *   - Create user (registration)
 *   - Find user by email (login)
 *   - Find user by id (middleware)
 *   - Update last_access timestamp (login audit)
 *   - Update profile fields (optional future feature)
 *   - Delete user (admin/testing)
 */

require_once __DIR__ . '/../Database.php';

class UserRepository
{
    /**
     * Creates a new user row.
     *
     * - Inserts basic profile fields plus timestamps.
     * - Returns the newly created user id (PostgreSQL RETURNING id).
     *
     * Error handling:
     * - If a unique constraint on email is violated, throws a 409 Conflict.
     * - For other DB errors, throws a generic 500 error.
     *
     * @param string $email User email.
     * @param string $passwordHash Bcrypt hash.
     * @param string $firstName First name.
     * @param string $lastName Last name.
     *
     * @return int Newly created user id.
     *
     * @throws Exception On duplicate email or database error.
     */
    public static function create(
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName
    ): int {
        try {
            // Insert a new user. Keep registration_date and last_access for auditing/UX.
            $sql = <<<SQL
                    INSERT INTO users (email, password_hash, first_name, last_name, registration_date, last_access)
                    VALUES (:email, :password_hash, :first_name, :last_name, NOW(), NOW())
                    RETURNING id
                    SQL;

            // Prepared parameters prevent SQL injection.
            $result = Database::fetchOne($sql, [
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
            ]);

            return (int)$result['id'];
        } 
        catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique') !== false) {
                throw new Exception('Email already registered', 409);
            }

            // Log internal details; return generic message to client.
            error_log('UserRepository::create error: ' . $e->getMessage());
            throw new Exception('Database error', 500);
        }
    }

    /**
     * Finds a user by email.
     *
     * @param string $email Email to search.
     *
     * @return array|null User row if found, null otherwise.
     */
    public static function findByEmail(string $email): ?array
    {
        // Include password_hash because login must verify password.
        $sql = 'SELECT id, email, password_hash, first_name, last_name, registration_date, last_access 
                FROM users 
                WHERE email = :email';
        return Database::fetchOne($sql, [':email' => $email]);
    }

    /**
     * Finds a user by id.
     *
     * @param int $id User primary key.
     *
     * @return array|null User row if found, null otherwise.
     */
    public static function findById(int $id): ?array
    {
        $sql = 'SELECT id, email, password_hash, first_name, last_name, registration_date, last_access 
                FROM users 
                WHERE id = :id';
        return Database::fetchOne($sql, [':id' => $id]);
    }

    /**
     * Updates the last_access timestamp for a user.
     *
     * @param int $userId User primary key.
     *
     * @return void
     */
    public static function updateLastAccess(int $userId): void
    {
        $sql = 'UPDATE users 
                SET last_access = NOW() 
                WHERE id = :id';
        Database::execute($sql, [':id' => $userId]);
    }

    /**
     * Updates allowed user profile fields.
     *
     * - If $updates contains no allowed fields, the function returns without doing anything.
     *
     * @param int $userId User primary key.
     * @param array $updates Key/value pairs of fields to update.
     *
     * @return void
     */
    public static function update(int $userId, array $updates): void
    {
        // Whitelist fields that can be updated through this method.
        $allowed = ['first_name', 'last_name', 'password_hash'];

        $setClauses = [];
        $params = [':id' => $userId];

        foreach ($updates as $key => $value) {
            // Only accept updates for allowed columns.
            if (in_array($key, $allowed, true)) {
                // Build "column = :param" fragments and bind values safely.
                $setClauses[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        // Nothing valid to update → exit early.
        if (empty($setClauses)) {
            return;
        }

        // Execute the dynamic update statement.
        $sql = 'UPDATE users 
                SET ' . implode(', ', $setClauses) . 
                ' WHERE id = :id';
        Database::execute($sql, $params);
    }

    /**
     * Deletes a user row.
     *
     * @param int $userId User primary key.
     *
     * @return void
     */
    public static function delete(int $userId): void
    {
        $sql = 'DELETE FROM users 
                WHERE id = :id';
        Database::execute($sql, [':id' => $userId]);
    }
}
