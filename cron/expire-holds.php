<?php

/**
 * SKSL — Hold Expiry Cron Job
 *
 * Scheduled task to periodically update expired booking holds from 'active' to 'expired'.
 *
 * Availability logic independently ignores expired holds even if this cron has not run.
 * This job keeps the database clean and provides operational auditing.
 *
 * Usage:
 *   php cron/expire-holds.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Models\BookingHoldModel;

$holdModel = new BookingHoldModel();
$expiredCount = $holdModel->expireStale();

$timestamp = date('Y-m-d H:i:s');
$message = "[{$timestamp}] expire-holds cron executed. Expired {$expiredCount} stale booking hold(s).\n";

echo $message;

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
file_put_contents($logDir . '/cron.log', $message, FILE_APPEND | LOCK_EX);
