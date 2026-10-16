<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Email delivery log (table: email_logs).
 *
 * One row per outbound email attempt: recipient, type, subject, outcome.
 * Never stores message bodies, attachments, or SMTP credentials.
 * Writing here must never break the calling flow (payments, resets), so every
 * method swallows database errors and reports them to the PHP error log.
 */
class EmailLogModel
{
    public const STATUS_SENT   = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_LOGGED = 'logged'; // development driver wrote it to storage/logs/mail.log

    private \PDO $db;

    public function __construct()
    {
        $this->db = getDb();
    }

    public function record(
        string $recipient,
        string $emailType,
        string $status,
        ?string $subject = null,
        ?string $errorMessage = null,
        ?int $bookingId = null
    ): ?int {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO email_logs (booking_id, recipient, email_type, subject, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $bookingId,
                mb_substr($recipient, 0, 255),
                mb_substr($emailType, 0, 50),
                $subject !== null ? mb_substr($subject, 0, 255) : null,
                in_array($status, [self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_LOGGED], true) ? $status : self::STATUS_FAILED,
                $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\Throwable $e) {
            error_log('EmailLogModel: could not record email log: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Most recent log rows for a recipient (optionally of one type), newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByRecipient(string $recipient, ?string $emailType = null, int $limit = 20): array
    {
        $sql = 'SELECT * FROM email_logs WHERE recipient = ?';
        $params = [$recipient];
        if ($emailType !== null) {
            $sql .= ' AND email_type = ?';
            $params[] = $emailType;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Retention: remove delivery records older than N days (privacy policy: 90).
     */
    public function purgeOlderThan(int $days = 90): int
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM email_logs WHERE created_at < (NOW() - INTERVAL ? DAY)');
            $stmt->execute([max(1, $days)]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('EmailLogModel: purge failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByBooking(int $bookingId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_logs WHERE booking_id = ? ORDER BY id DESC');
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll() ?: [];
    }
}
