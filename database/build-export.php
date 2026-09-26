<?php

/**
 * SKSL — Build database/sksl_export.sql from the migration and seed files.
 *
 * The export is what the Hostinger guide imports through phpMyAdmin. It must be
 * byte-for-byte equivalent to running every migration and seed, so it is generated
 * here instead of being edited by hand.
 *
 * Usage: php database/build-export.php
 */

declare(strict_types=1);

$root       = __DIR__;
$migrations = glob($root . '/migrations/*.sql') ?: [];
$seeds      = glob($root . '/seeds/*.sql') ?: [];
sort($migrations);
sort($seeds);

$out  = "-- ============================================================\n";
$out .= "-- SKSL — Database Export (GENERATED — do not edit by hand)\n";
$out .= "-- Sara Kinetic Sports Lab — Online Booking Platform\n";
$out .= "-- Generated: " . date('Y-m-d H:i') . " by database/build-export.php\n";
$out .= "--\n";
$out .= "-- Contains every file in database/migrations/ and database/seeds/, in order,\n";
$out .= "-- plus the `migrations` ledger so `php database/migrate.php` knows they ran.\n";
$out .= "--\n";
$out .= "-- HOW TO IMPORT:\n";
$out .= "--   phpMyAdmin: Import → Select this file → Go\n";
$out .= "--   CLI: mysql -u<user> -p <database> < sksl_export.sql\n";
$out .= "--\n";
$out .= "-- AFTER IMPORT:\n";
$out .= "--   1. UPDATE admins SET email='your-admin@yourdomain.com' WHERE id=1;\n";
$out .= "--   2. Fill BUSINESS_* and INVOICE_* values in .env (legal identity for invoices)\n";
$out .= "--   3. Update hero banner slides via /admin/banner\n";
$out .= "-- ============================================================\n\n";
$out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$out .= "SET time_zone = '+05:30';\n\n";

$out .= "CREATE TABLE IF NOT EXISTS `migrations` (\n";
$out .= "    `filename`   VARCHAR(191) NOT NULL,\n";
$out .= "    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
$out .= "    PRIMARY KEY (`filename`)\n";
$out .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

foreach ($migrations as $file) {
    $out .= "-- ── " . basename($file) . " " . str_repeat('─', max(3, 70 - strlen(basename($file)))) . "\n";
    $out .= rtrim(file_get_contents($file)) . "\n\n";
}

$out .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

foreach ($seeds as $file) {
    $out .= "-- ── Seed: " . basename($file) . " " . str_repeat('─', max(3, 64 - strlen(basename($file)))) . "\n";
    $out .= rtrim(file_get_contents($file)) . "\n\n";
}

$out .= "-- ── Migration ledger ────────────────────────────────────────────────────────\n";
foreach ($migrations as $file) {
    $out .= "INSERT IGNORE INTO `migrations` (`filename`) VALUES ('" . basename($file) . "');\n";
}

$target = $root . '/sksl_export.sql';
file_put_contents($target, $out);
echo "Wrote " . strlen($out) . " bytes to " . $target . PHP_EOL;
