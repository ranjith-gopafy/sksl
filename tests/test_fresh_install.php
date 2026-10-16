<?php
/**
 * Fresh-install regression suite.
 *
 * Builds a brand-new scratch database from database/sksl_export.sql under STRICT SQL mode (how most
 * hosts run MySQL/MariaDB) and checks that:
 *   1. the export is current: its ledger lists every file in database/migrations
 *   2. the import succeeds in strict mode (no invalid enum values, no truncation)
 *   3. the resulting catalogue (services, duration options, packages, package contents, schedules)
 *      is identical to the live database, so a new deployment sells what this one sells
 *
 * The scratch database is created and dropped by this script; the live database is only read.
 * Needs a MySQL user that may CREATE DATABASE (skipped otherwise).
 *
 * Run: php tests/test_fresh_install.php   (rebuild the export first with php database/build-export.php)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

$pass = 0;
$fail = 0;
function check(bool $ok, string $label): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

$live    = getDb();
$root    = dirname(__DIR__);
$export  = (string) file_get_contents($root . '/database/sksl_export.sql');
$scratch = 'sksl_fresh_test_' . substr(bin2hex(random_bytes(3)), 0, 6);

// 1. the export knows every migration
$missing = [];
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
    if (!str_contains($export, "'" . basename($file) . "'")) {
        $missing[] = basename($file);
    }
}
check($missing === [], '1 sksl_export.sql lists every migration (missing: ' . ($missing ? implode(', ', $missing) : 'none') . ') — run php database/build-export.php');

// 2. strict-mode import into a scratch database
try {
    $cfg = config('database');
    $dsnBase = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $cfg['host'], $cfg['port']);
    $admin = new PDO($dsnBase, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $admin->exec("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) {
    echo "[SKIP] cannot create a scratch database (" . $e->getMessage() . ")\n";
    exit(0);
}

try {
    $fresh = new PDO($dsnBase . ";dbname={$scratch}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $fresh->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    $lines = [];
    foreach (explode("\n", $export) as $line) {
        $pos = strpos($line, '--');
        $lines[] = $pos === false ? $line : substr($line, 0, $pos);
    }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))), static fn ($s) => $s !== '');

    $error = null;
    foreach ($statements as $sql) {
        try {
            $fresh->exec($sql);
        } catch (Throwable $e) {
            $error = $e->getMessage() . ' IN ' . substr($sql, 0, 120);
            break;
        }
    }
    check($error === null, '2 the export imports cleanly under strict SQL mode' . ($error ? " — {$error}" : ''));

    // 3. the catalogue matches the live database
    $queries = [
        'services and duration options' => "SELECT s.slug, s.category, s.status, s.price, s.duration_minutes, s.duration_mode, s.capacity, s.image,
              IFNULL((SELECT GROUP_CONCAT(CONCAT(o.duration_minutes,'=',o.price) ORDER BY o.duration_minutes) FROM service_duration_options o WHERE o.service_id = s.id), '-') AS opts
              FROM services s WHERE s.status = 'active' ORDER BY s.slug",
        'combo packages and contents' => "SELECT p.slug, p.price, p.total_price,
              (SELECT GROUP_CONCAT(CONCAT(s.slug,':',pi.quantity,':',pi.duration_minutes) ORDER BY s.slug) FROM package_items pi JOIN services s ON s.id = pi.service_id WHERE pi.package_id = p.id) AS items
              FROM packages p WHERE p.status = 'active' ORDER BY p.slug",
        'modality categories' => "SELECT s.slug, c.name AS category, c.sort_order FROM services s LEFT JOIN service_categories c ON c.id = s.category_id WHERE s.status = 'active' ORDER BY s.slug",
        'practitioner schedules' => "SELECT s.slug, r.weekday, r.start_time, r.end_time FROM availability_rules r JOIN services s ON s.id = r.service_id ORDER BY s.slug, r.weekday",
    ];
    foreach ($queries as $label => $sql) {
        $a = $live->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $b = $fresh->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $firstDiff = '';
        foreach ($a as $i => $row) { if ($row !== ($b[$i] ?? null)) { $firstDiff = ' - first difference: live ' . json_encode($row) . ' vs fresh ' . json_encode($b[$i] ?? null); break; } }
        check($a === $b && count($a) > 0, "3 {$label} on a fresh install are identical to the live database (" . count($a) . ' rows)' . $firstDiff);
    }
    check((int) $fresh->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 1, '4 exactly one admin is seeded on a fresh install');
} finally {
    $admin->exec("DROP DATABASE IF EXISTS `{$scratch}`");
}

echo "\n=== Fresh install: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
