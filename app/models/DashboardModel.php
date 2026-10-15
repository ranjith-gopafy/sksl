<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Dashboard Model
 *
 * Read-only summaries for the admin overview page. It only counts and lists data the system already
 * stores (bookings, package purchases); nothing is written and nothing new is recorded.
 * Dates use the database session time zone, which the application sets to its own (Asia/Kolkata).
 */
class DashboardModel
{
    private \PDO $db;

    public function __construct(?\PDO $db = null)
    {
        $this->db = $db ?? getDb();
    }

    /**
     * Headline figures.
     *
     * @return array{today_sessions:int, next_7_days:int, awaiting_payment:int, refunds_to_review:int, online_sales_month:float, online_sales_count:int, package_sales_30d:int}
     */
    public function stats(): array
    {
        $row = $this->db->query(
            "SELECT
                SUM(booking_status = 'confirmed' AND booking_date = CURDATE())                                   AS today_sessions,
                SUM(booking_status = 'confirmed' AND booking_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY) AS next_7_days,
                SUM(booking_status = 'pending')                                                                   AS awaiting_payment,
                SUM(payment_mode = 'payment_required' AND payment_status = 'paid' AND booking_status IN ('cancelled', 'expired')) AS refunds_to_review,
                COALESCE(SUM(CASE WHEN payment_mode = 'payment_required' AND payment_status = 'paid'
                                   AND booking_status IN ('confirmed', 'completed')
                                   AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                  THEN total_amount END), 0)                                                      AS online_sales_month,
                SUM(payment_mode = 'payment_required' AND payment_status = 'paid'
                    AND booking_status IN ('confirmed', 'completed')
                    AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01'))                                         AS online_sales_count
             FROM bookings"
        )->fetch() ?: [];

        $packages = (int) $this->db->query(
            "SELECT COUNT(*) FROM customer_packages
             WHERE payment_status = 'paid' AND purchased_at >= (NOW() - INTERVAL 30 DAY)"
        )->fetchColumn();

        // Offline mode: requests (sessions and combo packages) waiting for staff to approve their payment.
        $approvals = (int) $this->db->query(
            "SELECT (SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending' AND payment_channel = 'offline' AND payment_status = 'pending')
                  + (SELECT COUNT(*) FROM customer_packages WHERE status = 'active' AND payment_channel = 'offline' AND payment_status = 'pending' AND payment_due_at > NOW())"
        )->fetchColumn();

        return [
            'awaiting_approval'  => $approvals,
            'today_sessions'     => (int) ($row['today_sessions'] ?? 0),
            'next_7_days'        => (int) ($row['next_7_days'] ?? 0),
            'awaiting_payment'   => (int) ($row['awaiting_payment'] ?? 0),
            'refunds_to_review'  => (int) ($row['refunds_to_review'] ?? 0),
            'online_sales_month' => (float) ($row['online_sales_month'] ?? 0),
            'online_sales_count' => (int) ($row['online_sales_count'] ?? 0),
            'package_sales_30d'  => $packages,
        ];
    }

    /**
     * Today's sessions in time order (confirmed and completed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function todaySchedule(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.booking_reference, b.start_time, b.end_time, b.booking_status, b.visit_status, b.booking_type,
                    b.service_name_snapshot, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             WHERE b.booking_date = CURDATE() AND b.booking_status IN ('confirmed', 'completed')
             ORDER BY b.start_time ASC, b.id ASC
             LIMIT :lim"
        );
        $stmt->bindValue('lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * The most recently created bookings of any status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentBookings(int $limit = 8): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.booking_reference, b.booking_date, b.start_time, b.booking_status, b.payment_status, b.payment_mode,
                    b.total_amount, b.created_at, b.service_name_snapshot, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             ORDER BY b.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue('lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
