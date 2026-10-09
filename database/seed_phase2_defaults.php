<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
assert_cli_only(true);

$pdo = getDb();

echo "Seeding Phase 2 Availability Rules & Combo Packages...\n";

// 1. Availability Rules for Professional Services
$ruleCount = (int) $pdo->query("SELECT count(*) FROM availability_rules")->fetchColumn();
if ($ruleCount === 0) {
    echo "  -> Seeding practitioner availability schedules...\n";
    $insertRule = $pdo->prepare(
        "INSERT INTO availability_rules (
            service_id, rule_type, weekday, start_time, end_time, is_available, status
        ) VALUES (
            :service_id, 'recurring_weekday', :weekday, :start_time, :end_time, :is_available, 'active'
        )"
    );

    // Physiotherapy (ID 11): Mon-Fri 09:00 - 18:00, Sat 09:00 - 14:00 (1=Mon..6=Sat)
    for ($day = 1; $day <= 5; $day++) {
        $insertRule->execute([
            'service_id'   => 11,
            'weekday'      => $day,
            'start_time'   => '09:00:00',
            'end_time'     => '18:00:00',
            'is_available' => 1,
        ]);
    }
    $insertRule->execute([
        'service_id'   => 11,
        'weekday'      => 6,
        'start_time'   => '09:00:00',
        'end_time'     => '14:00:00',
        'is_available' => 1,
    ]);

    // Sports Psychology (ID 12): Tue (2), Thu (4), Sat (6) 10:00 - 17:00
    foreach ([2, 4, 6] as $day) {
        $insertRule->execute([
            'service_id'   => 12,
            'weekday'      => $day,
            'start_time'   => '10:00:00',
            'end_time'     => '17:00:00',
            'is_available' => 1,
        ]);
    }
    echo "  [OK] Seeded 9 availability rules for Professional Services.\n";
} else {
    echo "  [INFO] Availability rules already exist ({$ruleCount} rules). Skipping.\n";
}

// 2. Combo Packages using PackageModel
$packageCount = (int) $pdo->query("SELECT count(*) FROM packages")->fetchColumn();
if ($packageCount === 0) {
    echo "  -> Seeding combo recovery packages...\n";
    $packageModel = new \App\Models\PackageModel($pdo);

    // Package 1
    $packageModel->create(
        [
            'name'          => 'Ultimate Recovery Trio',
            'slug'          => 'ultimate-recovery-trio',
            'description'   => 'Designed for active athletes. Combines Spa, Sauna, and Ice Bath sessions for full-body regeneration.',
            'price'         => 1999.00,
            'validity_days' => 30,
            'badge_text'    => 'Most Popular',
            'status'        => 'active',
        ],
        [
            ['service_id' => 1, 'quantity' => 1], // Spa
            ['service_id' => 2, 'quantity' => 1], // Sauna
            ['service_id' => 4, 'quantity' => 1], // Ice Bath
        ]
    );

    // Package 2
    $packageModel->create(
        [
            'name'          => 'Hydro & Cryo Pro 5-Pack',
            'slug'          => 'hydro-cryo-pro-5-pack',
            'description'   => 'Complete hot/cold immersion therapy. Includes 3 Ice Bath sessions and 2 Hot Bath sessions.',
            'price'         => 3499.00,
            'validity_days' => 60,
            'badge_text'    => 'Best Value',
            'status'        => 'active',
        ],
        [
            ['service_id' => 4, 'quantity' => 3], // Ice Bath
            ['service_id' => 5, 'quantity' => 2], // Hot Bath
        ]
    );

    // Package 3
    $packageModel->create(
        [
            'name'          => 'Endless Pool & Lap Athlete Bundle',
            'slug'          => 'endless-pool-lap-athlete-bundle',
            'description'   => 'Aquatic conditioning and recovery bundle. Includes 2 Endless Pool sessions and 3 Lap Pool sessions.',
            'price'         => 4999.00,
            'validity_days' => 45,
            'badge_text'    => 'Performance',
            'status'        => 'active',
        ],
        [
            ['service_id' => 6, 'quantity' => 2],  // Endless Pool
            ['service_id' => 10, 'quantity' => 3], // Lap Pool
        ]
    );

    echo "  [OK] Seeded 3 Combo Packages with relational bundle items.\n";
} else {
    echo "  [INFO] Packages already exist ({$packageCount} packages). Skipping.\n";
}

// 3. Multi-Duration Options for Modalities (e.g. Spa 45m & 60m, Sauna 20m & 30m)
echo "  -> Seeding extended duration options for modalities...\n";
$dOptModel = new \App\Models\DurationOptionModel($pdo);

if (!$dOptModel->findByServiceAndDuration(1, 45)) {
    $dOptModel->create([
        'service_id'       => 1,
        'duration_minutes' => 45,
        'price'            => 1999.00,
        'label'            => '45 Mins Extended Spa',
        'sort_order'       => 2,
        'status'           => 'active',
    ]);
}
if (!$dOptModel->findByServiceAndDuration(1, 60)) {
    $dOptModel->create([
        'service_id'       => 1,
        'duration_minutes' => 60,
        'price'            => 2499.00,
        'label'            => '60 Mins Luxury Spa',
        'sort_order'       => 3,
        'status'           => 'active',
    ]);
}
if (!$dOptModel->findByServiceAndDuration(2, 20)) {
    $dOptModel->create([
        'service_id'       => 2,
        'duration_minutes' => 20,
        'price'            => 1199.00,
        'label'            => '20 Mins Deep Heat',
        'sort_order'       => 2,
        'status'           => 'active',
    ]);
}
if (!$dOptModel->findByServiceAndDuration(2, 30)) {
    $dOptModel->create([
        'service_id'       => 2,
        'duration_minutes' => 30,
        'price'            => 1499.00,
        'label'            => '30 Mins Thermal Immersion',
        'sort_order'       => 3,
        'status'           => 'active',
    ]);
}
echo "  [OK] Extended duration options configured.\n";

echo "Seeding completed successfully.\n";
