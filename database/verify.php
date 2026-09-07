<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$db = getDb();

// List all tables
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables created (' . count($tables) . '):' . PHP_EOL;
foreach ($tables as $t) { echo '  - ' . $t . PHP_EOL; }

// Verify services count and names
$services = $db->query('SELECT name, price, duration_minutes, capacity, status FROM services ORDER BY id')->fetchAll();
echo PHP_EOL . 'Services (' . count($services) . '):' . PHP_EOL;
foreach ($services as $s) {
    echo sprintf('  %-15s  price=%.2f  dur=%dmin  cap=%d  [%s]',
        $s['name'], $s['price'], $s['duration_minutes'], $s['capacity'], $s['status']) . PHP_EOL;
}

// Verify admin
$admin = $db->query('SELECT id, name, email, status FROM admins LIMIT 1')->fetch();
echo PHP_EOL . 'Admin: ' . $admin['name'] . ' <' . $admin['email'] . '> [' . $admin['status'] . ']' . PHP_EOL;

// Verify FKs
$stmt = $db->prepare("
    SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME
");
$stmt->execute();
$fks = $stmt->fetchAll();
echo PHP_EOL . 'Foreign keys (' . count($fks) . '):' . PHP_EOL;
foreach ($fks as $fk) {
    echo '  ' . $fk['TABLE_NAME'] . '.' . $fk['COLUMN_NAME']
       . ' -> ' . $fk['REFERENCED_TABLE_NAME'] . '.' . $fk['REFERENCED_COLUMN_NAME'] . PHP_EOL;
}

echo PHP_EOL . 'Phase 2 verification complete.' . PHP_EOL;
