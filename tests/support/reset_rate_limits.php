<?php

declare(strict_types=1);

/**
 * Clears the database-backed rate limiter so automated test runs are
 * deterministic (they all originate from one IP and would otherwise lock
 * themselves out). Refuses to run in production.
 *
 * Usage: php tests/support/reset_rate_limits.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (config('app.env') === 'production') {
    fwrite(STDERR, "Refusing to reset rate limits in production.\n");
    exit(1);
}

try {
    $deleted = getDb()->exec('DELETE FROM rate_limits');
    echo "rate_limits cleared ({$deleted} rows)\n";
} catch (\PDOException $e) {
    fwrite(STDERR, 'Could not clear rate_limits: ' . $e->getMessage() . "\n");
    exit(1);
}
