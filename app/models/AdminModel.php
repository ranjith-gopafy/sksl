<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Admin Model
 *
 * Manages database access for administrators and single-use 6-digit OTPs.
 */
class AdminModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Find admin by email.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM admins WHERE email = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find admin by ID.
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM admins WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new administrator account.
     */
    public function create(string $name, string $email, string $status = 'active'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admins (name, email, status) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, strtolower(trim($email)), $status]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Create a new 5-minute single-use OTP record.
     */
    public function createOtp(int $adminId, string $otpHashed, string $expiresAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_otps (admin_id, otp_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$adminId, $otpHashed, $expiresAt]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Retrieve the latest un-consumed, non-expired OTP for an administrator.
     */
    public function getLatestValidOtp(int $adminId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM admin_otps 
             WHERE admin_id = ? AND used_at IS NULL AND expires_at > NOW() 
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$adminId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark an OTP as consumed.
     */
    public function markOtpUsed(int $otpId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_otps SET used_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$otpId]);
    }

    /**
     * Increment attempts count for an OTP.
     */
    public function incrementAttempts(int $otpId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE admin_otps SET attempts = attempts + 1 WHERE id = ?'
        );
        return $stmt->execute([$otpId]);
    }
}
