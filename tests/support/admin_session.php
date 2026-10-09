<?php
/**
 * Test helper for the browser (Playwright) suite: a throwaway administrator with a known sign-in code.
 *
 *   php tests/support/admin_session.php create            -> prints the new admin id and email as JSON
 *   php tests/support/admin_session.php otp <id> [code]   -> plants a valid one-time code (default 246810)
 *   php tests/support/admin_session.php cleanup <id>      -> removes the admin and its codes
 *   php tests/support/admin_session.php reset-limits      -> clears the admin sign-in throttle counters
 *   php tests/support/admin_session.php remove-package <name>        -> deletes a combo package a browser test created
 *   php tests/support/admin_session.php remove-closed-date <date>    -> deletes a closed date a browser test created
 *
 * Command line only, and refuses to run when APP_ENV is production.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
assert_cli_only(true);

$db      = getDb();
$command = $argv[1] ?? '';

switch ($command) {
    case 'create':
        $email = 'e2e_admin_' . bin2hex(random_bytes(4)) . '@sk-sports-lab.test';
        $id = (new \App\Models\AdminModel())->create('E2E Dashboard Admin', $email, 'active');
        echo json_encode(['id' => $id, 'email' => $email]);
        break;

    case 'otp':
        $id   = (int) ($argv[2] ?? 0);
        $code = (string) ($argv[3] ?? '246810');
        (new \App\Models\AdminModel())->createOtp($id, password_hash($code, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 300));
        echo 'ok';
        break;

    case 'reset-limits':
        // The sign-in throttles are what protect the real login; tests that sign in many times start clean.
        foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $scope) {
            \App\Helpers\RateLimit::clearScope($scope);
        }
        echo 'ok';
        break;

    case 'remove-package':
        $name = (string) ($argv[2] ?? '');
        if (str_starts_with($name, 'E2E ')) {
            $db->prepare('DELETE FROM package_items WHERE package_id IN (SELECT id FROM packages WHERE name = ?)')->execute([$name]);
            $db->prepare('DELETE FROM packages WHERE name = ?')->execute([$name]);
        }
        echo 'ok';
        break;

    case 'package-items':
        $s = $db->prepare('SELECT service_id, quantity FROM package_items WHERE package_id = (SELECT id FROM packages WHERE name = ?) ORDER BY id');
        $s->execute([(string) ($argv[2] ?? '')]);
        echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'remove-closed-date':
        $db->prepare("DELETE FROM closed_dates WHERE closed_date = ? AND reason LIKE 'E2E %'")->execute([(string) ($argv[2] ?? '')]);
        echo 'ok';
        break;

    case 'cleanup':
        $id = (int) ($argv[2] ?? 0);
        if ($id > 0) {
            $db->exec("DELETE FROM admin_otps WHERE admin_id = {$id}");
            $db->exec("DELETE FROM admins WHERE id = {$id}");
        }
        echo 'ok';
        break;

    default:
        fwrite(STDERR, "Unknown command\n");
        exit(2);
}
