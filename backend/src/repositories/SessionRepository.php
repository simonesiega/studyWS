<?php
/**
 * StudyWS Backend - SessionRepository
 *
 * - Data access layer for the `sessions` table.
 * - Stores and manages refresh-token sessions (refresh token is stored as a SHA-256 hash).
 * - Supports:
 *   - Token rotation (revoke old refresh session, create a new one)
 *   - Logout (revoke one or all sessions)
 *   - Session listing per user (multi-device support)
 *   - Cleanup of expired sessions
 */

// Load Database class for DB access
require_once __DIR__ . '/../Database.php';

class SessionRepository
{
    /**
     * Creates a new session row for a refresh token.
     *
     * Security:
     * - Store only a hash of the refresh token (never the plaintext refresh token).
     *
     * @param int $userId The user owning this session.
     * @param string $refreshTokenHash SHA-256 hash of the refresh token.
     * @param int $expiresAt Expiration time as UNIX epoch seconds.
     * @param string $userAgent Optional client user-agent for audit / device listing.
     * @param string $ip Optional client IP for audit / device listing.
     *
     * @return int The newly created session id.
     */
    public static function create(
        int $userId,
        string $refreshTokenHash,
        int $expiresAt,
        string $userAgent = '',
        string $ip = ''
    ): int {
        // Insert session and return its id (PostgreSQL "RETURNING id").
        $sql = <<<SQL
            INSERT INTO sessions (user_id, refresh_token_hash, created_at, expires_at, user_agent, ip)
            VALUES (:user_id, :refresh_token_hash, NOW(), to_timestamp(:expires_at), :user_agent, :ip)
            RETURNING id
            SQL;

        // Use prepared parameters to avoid SQL injection and keep types consistent.
        $result = Database::fetchOne($sql, [
            ':user_id' => $userId,
            ':refresh_token_hash' => $refreshTokenHash,
            ':expires_at' => $expiresAt,
            ':user_agent' => $userAgent,
            ':ip' => $ip,
        ]);

        // fetchOne() should return ['id' => ...] because of "RETURNING id".
        return (int)$result['id'];
    }

    /**
     * Finds an active session by (user_id + refresh_token_hash).
     *
     * "Active" means:
     * - Not revoked (revoked_at IS NULL),
     * - Not expired (expires_at > NOW()).
     *
     * Used by:
     * - POST /auth/refresh to ensure the presented refresh token is still valid server-side.
     *
     * @param int $userId The user id from the refresh token payload (sub claim).
     * @param string $refreshTokenHash SHA-256 hash of the presented refresh token.
     *
     * @return array|null The session row if found, otherwise null.
     */
    public static function findByTokenHash(int $userId, string $refreshTokenHash): ?array
    {
        // Match token hash + user, while enforcing server-side validity rules.
        $sql = <<<SQL
                SELECT id, user_id, refresh_token_hash, created_at, expires_at, revoked_at, user_agent, ip
                FROM sessions
                WHERE user_id = :user_id
                AND refresh_token_hash = :refresh_token_hash
                AND revoked_at IS NULL
                AND expires_at > NOW()
                SQL;

        return Database::fetchOne($sql, [
            ':user_id' => $userId,
            ':refresh_token_hash' => $refreshTokenHash,
        ]);
    }

    /**
     * Returns all active sessions for a given user.
     *
     * @param int $userId The owner of the sessions.
     *
     * @return array A list of active session rows (may be empty).
     */
    public static function findByUserId(int $userId): array
    {
        // Only active sessions (not revoked, not expired), newest first.
        $sql = <<<SQL
                SELECT id, user_id, refresh_token_hash, created_at, expires_at, revoked_at, user_agent, ip
                FROM sessions
                WHERE user_id = :user_id
                AND revoked_at IS NULL
                AND expires_at > NOW()
                ORDER BY created_at DESC
                SQL;

        return Database::fetchAll($sql, [':user_id' => $userId]);
    }

    /**
     * Revokes a single session (invalidates the refresh token server-side).
     *
     * @param int $sessionId Session primary key.
     *
     * @return void
     */
    public static function revoke(int $sessionId): void
    {
        // After this, findByTokenHash() will no longer consider this session active.
        $sql = 'UPDATE sessions SET revoked_at = NOW() WHERE id = :id';
        Database::execute($sql, [':id' => $sessionId]);
    }

    /**
     * Revokes all active sessions for a user.
     *
     * @param int $userId The user whose sessions should be revoked.
     *
     * @return void
     */
    public static function revokeAllForUser(int $userId): void
    {
        // Revoke only non-revoked sessions to keep the operation idempotent.
        $sql = 'UPDATE sessions SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL';
        Database::execute($sql, [':user_id' => $userId]);
    }

    /**
     * Deletes expired sessions (hard cleanup).
     *
     * @return int Number of deleted rows (depends on Database::execute() implementation).
     */
    public static function deleteExpired(): int
    {
        // Expired means expires_at < NOW() (independent from revoked_at).
        $sql = 'DELETE FROM sessions WHERE expires_at < NOW()';

        // Empty params array keeps a consistent "prepared statement" API.
        return Database::execute($sql, []);
    }

    /**
     * Finds a session by its primary key (id).
     *
     * @param int $id Session primary key.
     *
     * @return array|null The session row if found, otherwise null.
     */
    public static function findById(int $id): ?array
    {
        $sql = 'SELECT id, user_id, refresh_token_hash, created_at, expires_at, revoked_at, user_agent, ip 
                FROM sessions 
                WHERE id = :id';
        return Database::fetchOne($sql, [':id' => $id]);
    }
}

