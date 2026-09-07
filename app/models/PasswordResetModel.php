<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Password Reset Token Model
 *
 * Stores the SHA-256 hash of the reset token — NEVER the plain token.
 * Plain token is only in the email link and is never stored.
 */
class PasswordResetModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Store a new hashed reset token for a user.
     *
     * @param \DateTime $expiresAt  Must be in the future (typically +1 hour).
     * @return int  The new record ID.
     */
    public function create(int $userId, string $tokenHash, \DateTime $expiresAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $tokenHash,
            $expiresAt->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a valid (unused, not expired) reset record by hashed token.
     * Returns null if no valid record found.
     */
    public function findValidByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark a token as used (one-time enforcement).
     * Called immediately after successful password update.
     */
    public function markUsed(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    /**
     * Delete expired and already-used tokens for a user.
     * Call before issuing a new token to keep the table clean.
     */
    public function deleteOldForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM password_resets
             WHERE user_id = ?
               AND (expires_at < NOW() OR used_at IS NOT NULL)'
        );
        $stmt->execute([$userId]);
    }
}
