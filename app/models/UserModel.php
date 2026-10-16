<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User Model
 *
 * All queries use PDO prepared statements.
 * No raw SQL string concatenation with untrusted input.
 * Password hashes are NEVER returned to the view layer.
 */
class UserModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    /**
     * Find a user by email address (case-insensitive, stored lowercase).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a user by ID. Excludes password_hash from the result.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, mobile, status, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new user. Returns the new user's ID.
     */
    public function create(string $name, string $email, string $mobile, string $passwordHash, ?string $termsAcceptedAt = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, mobile, password_hash, status, terms_accepted_at)
             VALUES (?, ?, ?, ?, 'active', ?)"
        );
        $stmt->execute([$name, strtolower(trim($email)), $mobile, $passwordHash, $termsAcceptedAt]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update the hashed password for a user.
     */
    public function updatePassword(int $userId, string $passwordHash): bool
    {
        // password_changed_at invalidates every session opened before this moment
        // (see App\Middleware\CustomerAuth).
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$passwordHash, $userId]);
    }

    /**
     * Update allowed profile fields (name, mobile only — never email/status via this method).
     *
     * @param array<string, string> $fields
     */
    public function updateProfile(int $userId, array $fields): bool
    {
        $allowed = ['name', 'mobile'];
        $sets    = [];
        $values  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[]  = "`{$field}` = ?";
                $values[] = $fields[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $values[] = $userId;
        $stmt = $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        return $stmt->execute($values);
    }
}
