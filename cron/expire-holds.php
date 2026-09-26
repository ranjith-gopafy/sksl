<?php

/**
 * SKSL — Hold & Pending-Booking Expiry Cron Job
 *
 * 1. Marks booking_holds whose expires_at has passed as 'expired'.
 *    (Availability already ignores them; this keeps the table tidy.)
 * 2. Marks bookings that are still pending/unpaid after
 *    PENDING_BOOKING_EXPIRY_MINUTES (default: hold minutes + 30) as 'expired',
 *    so abandoned checkouts stop cluttering the admin list.
 *    A late Razorpay webhook for such a booking is still recorded and flagged
 *    for staff by PaymentService::settlePayment().
 * 3. Deletes email_logs rows older than EMAIL_LOG_RETENTION_DAYS (default 90).
 *
 * The admin bookings page runs the same housekeeping on load, so this job is
 * a safety net; schedule it every 5–15 minutes:
 *   php /path/to/sksl/cron/expire-holds.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\BookingHoldModel;
use App\Models\BookingModel;
use App\Models\EmailLogModel;

$expiredHolds    = (new BookingHoldModel())->expireStale();
$expiredBookings = (new BookingModel())->expireStalePending();
// 3. Retention: email delivery records are kept for 90 days (see Privacy Policy §6).
$purgedEmailLogs = (new EmailLogModel())->purgeOlderThan((int) ($_ENV['EMAIL_LOG_RETENTION_DAYS'] ?? 90));

$timestamp = date('Y-m-d H:i:s');
$message = "[{$timestamp}] expire-holds cron executed. Expired {$expiredHolds} stale hold(s) and {$expiredBookings} unpaid pending booking(s); purged {$purgedEmailLogs} email log row(s) past retention.\n";

echo $message;

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
file_put_contents($logDir . '/cron.log', $message, FILE_APPEND | LOCK_EX);
