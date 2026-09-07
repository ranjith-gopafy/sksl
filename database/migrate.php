<?php

/**
 * SKSL Database Migration Runner
 *
 * Usage:
 *   php database/migrate.php            -- Run all pending migrations
 *   php database/migrate.php --seed     -- Run migrations + seeds
 *   php database/migrate.php --status   -- Show migration status
 *
 * Migrations are run in filename order (alphabetical/timestamp).
 * Safe to run multiple times — uses CREATE TABLE IF NOT EXISTS.
 */

declare(strict_types=1);

// Load bootstrap (env + DB connection)
require_once dirname(__DIR__) . '/bootstrap.php';

// ── CLI argument parsing ──────────────────────────────────────────────────
$args     = $argv ?? [];
$runSeed  = in_array('--seed',   $args, true);
$showOnly = in_array('--status', $args, true);

// ── CLI output helpers ────────────────────────────────────────────────────
function cliOk(string $msg): void   { echo "\033[32m[OK]  \033[0m" . $msg . PHP_EOL; }
function cliErr(string $msg): void  { echo "\033[31m[ERR] \033[0m" . $msg . PHP_EOL; }
function cliInfo(string $msg): void { echo "\033[36m[..]  \033[0m" . $msg . PHP_EOL; }
function cliHead(string $msg): void { echo PHP_EOL . "\033[1m" . $msg . "\033[0m" . PHP_EOL; }
function cliWarn(string $msg): void { echo "\033[33m[WARN]\033[0m " . $msg . PHP_EOL; }

/**
 * Parse a SQL file into individual statements.
 * Strips -- comment lines first, then splits on semicolons.
 *
 * @return string[]
 */
function parseSqlFile(string $filePath): array
{
    $raw   = file_get_contents($filePath);
    $lines = explode("\n", $raw);
    $kept  = [];

    foreach ($lines as $line) {
        // Strip everything from -- onwards (SQL line comments)
        $commentPos = strpos($line, '--');
        if ($commentPos !== false) {
            $line = substr($line, 0, $commentPos);
        }
        $kept[] = $line;
    }

    $cleanSql   = implode("\n", $kept);
    $statements = explode(';', $cleanSql);

    return array_values(array_filter(
        array_map('trim', $statements),
        fn(string $s): bool => $s !== ''
    ));
}

// ── DB connection ─────────────────────────────────────────────────────────
try {
    $db = getDb();
} catch (Throwable $e) {
    cliErr('Cannot connect to database: ' . $e->getMessage());
    exit(1);
}

// ── Run migrations ────────────────────────────────────────────────────────
cliHead('=== SKSL Migration Runner ===');

$migrationDir = __DIR__ . '/migrations/';
$files        = glob($migrationDir . '*.sql');

if (empty($files)) {
    cliInfo('No migration files found in database/migrations/.');
    exit(0);
}

sort($files);

$ran     = 0;
$failed  = 0;
$skipped = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if ($showOnly) {
        cliInfo("Would run: $filename");
        continue;
    }

    cliInfo("Running: $filename");

    $statements = parseSqlFile($file);

    try {
        // NOTE: MySQL DDL (CREATE TABLE / ALTER TABLE) causes implicit commits.
        // Transactions around DDL are not effective — execute statements directly.
        foreach ($statements as $stmt) {
            $db->exec($stmt);
        }

        cliOk("Done:    $filename");
        $ran++;
    } catch (PDOException $e) {
        cliErr("Failed:  $filename — " . $e->getMessage());
        $failed++;
        break; // Stop on first failure to avoid cascading FK errors
    }
}

// ── Run seeds ─────────────────────────────────────────────────────────────
if ($runSeed && $failed === 0) {
    cliHead('=== Running Seeds ===');

    $seedDir   = __DIR__ . '/seeds/';
    $seedFiles = glob($seedDir . '*.sql');
    sort($seedFiles);

    foreach ($seedFiles as $file) {
        $filename = basename($file);
        cliInfo("Seeding: $filename");

        $statements = parseSqlFile($file);

        try {
            $db->beginTransaction();

            foreach ($statements as $stmt) {
                $db->exec($stmt);
            }

            $db->commit();
            cliOk("Seeded:  $filename");
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // Duplicate key = seed already applied — warn and continue
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                cliWarn("$filename — already seeded (skipped).");
                $skipped++;
            } else {
                cliErr("Seed failed: $filename — " . $e->getMessage());
                $failed++;
                break;
            }
        }
    }
}

// ── Summary ───────────────────────────────────────────────────────────────
cliHead('=== Summary ===');
echo "  Migrations run:  $ran" . PHP_EOL;
echo "  Seeds skipped:   $skipped" . PHP_EOL;
echo "  Failures:        $failed" . PHP_EOL;

if ($failed > 0) {
    cliErr('Completed with errors.');
    exit(1);
}

cliOk('All done.');
exit(0);
