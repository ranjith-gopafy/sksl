<?php

/**
 * Customer account flows beyond the happy path: registration input matrix, sign-in edge cases,
 * password reset (single use, newest link only, expiry, tampering, throttling), profile updates and
 * password change guesses. Runs against AuthService with the log-only mail driver.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\RateLimit;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\AuthService;

$db = getDb();
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++;
    echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$tag = 'af' . substr(bin2hex(random_bytes(3)), 0, 6);
$auth = new AuthService();
$users = new UserModel();
$q = fn (string $sql, array $a = []) => (function () use ($db, $sql, $a) { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); })();
$resetLimits = function () {
    foreach (['cust_register', 'cust_register_ip', 'cust_login', 'cust_login_ip', 'cust_forgot', 'cust_forgot_ip', 'cust_reset_ip', 'cust_pwchange', 'cust_pwchange_ip'] as $s) { RateLimit::clearScope($s); }
};
$resetLimits();
$reg = function (array $in) use ($auth, $resetLimits): array { $resetLimits(); return $auth->register($in); };
$good = 'Sprint!' . random_int(1000, 9999);
$valid = fn (array $o = []) => $o + ['name' => 'Asha Verma', 'email' => "{$tag}_" . bin2hex(random_bytes(3)) . '@sk-sports-lab.test', 'mobile' => '9876543210', 'password' => $good, 'password_confirmation' => $good, 'terms' => '1'];
$errorsOf = fn (array $r) => array_keys($r['errors'] ?? []);

echo "SKSL — account flows\n";

// ---------------------------------------------------------------- A. registration input matrix
$r = $reg($valid());
check('A1 a normal registration succeeds', $r['success'] === true && ($r['user_id'] ?? 0) > 0, json_encode($r));

$cases = [
    'name too short'            => [['name' => 'A'], 'name'],
    'name blank'                => [['name' => '   '], 'name'],
    'name 101 chars'            => [['name' => str_repeat('N', 101)], 'name'],
    'email without @'           => [['email' => 'nobody.example.com'], 'email'],
    'email without domain'      => [['email' => 'nobody@'], 'email'],
    'email with spaces inside'  => [['email' => 'no body@example.com'], 'email'],
    'email two @'               => [['email' => 'a@@example.com'], 'email'],
    'email 300 chars'           => [['email' => str_repeat('a', 290) . '@example.com'], 'email'],
    'email empty'               => [['email' => ''], 'email'],
    'mobile 9 digits'           => [['mobile' => '987654321'], 'mobile'],
    'mobile 11 digits'          => [['mobile' => '98765432101'], 'mobile'],
    'mobile letters'            => [['mobile' => 'abcdefghij'], 'mobile'],
    'mobile empty'              => [['mobile' => ''], 'mobile'],
    'password too short'        => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password'],
    'password no digit'         => [['password' => 'OnlyLettersHere', 'password_confirmation' => 'OnlyLettersHere'], 'password'],
    'password common'           => [['password' => 'Password123', 'password_confirmation' => 'Password123'], 'password'],
    'password 129 chars'        => [['password' => str_repeat('aB1', 43), 'password_confirmation' => str_repeat('aB1', 43)], 'password'],
    'passwords differ'          => [['password_confirmation' => $good . 'x'], 'password_confirmation'],
    'confirmation missing'      => [['password_confirmation' => ''], 'password_confirmation'],
    'terms missing'             => [['terms' => ''], 'terms'],
    'terms "0"'                 => [['terms' => '0'], 'terms'],
    'terms "off"'               => [['terms' => 'off'], 'terms'],
    'terms "false"'             => [['terms' => 'false'], 'terms'],
];
foreach ($cases as $label => [$override, $field]) {
    $input = $valid($override);
    $r = $reg($input);
    check("A2 $label is refused with an error on '$field'", $r['success'] === false && in_array($field, $errorsOf($r), true), json_encode($r));
    $made = $q('SELECT COUNT(*) c FROM users WHERE email = ?', [strtolower(trim($input['email']))])[0]['c'];
    check("A3 $label creates no account", (int) $made === 0);
}
$r = $reg(['terms' => '1']);
check('A4 an empty form is refused with errors, not a crash', $r['success'] === false && count($r['errors'] ?? []) >= 3);
$r = $reg(['name' => ['array'], 'email' => ['x'], 'mobile' => ['1'], 'password' => ['p'], 'terms' => ['1']]);
check('A5 array-valued fields are refused cleanly', $r['success'] === false);

// formats that are accepted must be stored in a clean form
$email = "{$tag}_Mixed.Case+tag@Sk-Sports-Lab.test";
$r = $reg($valid(['email' => "  $email  ", 'mobile' => '98765 43210', 'name' => '  Ravi <b>K</b>  ']));
check('A6 e-mail with capitals/spaces is accepted and stored lower-case and trimmed', $r['success'] === true && $users->findByEmail(strtolower($email)) !== null);
$row = $users->findByEmail(strtolower($email));
check('A7 mobile with a space is stored as 10 digits', ($row['mobile'] ?? '') === '9876543210', (string) ($row['mobile'] ?? ''));
check('A8 the name is stored trimmed, markup kept as text (escaped on output)', ($row['name'] ?? '') === 'Ravi <b>K</b>', (string) ($row['name'] ?? ''));
$r = $reg($valid(['email' => strtoupper($email)]));
check('A9 the same e-mail in capitals is treated as a duplicate (no second account)', (int) $q('SELECT COUNT(*) c FROM users WHERE email = ?', [strtolower($email)])[0]['c'] === 1 && $r['success'] === true && !isset($r['user_id']));
foreach (['+919876543210', '+91 98765-43210', '919876543210', '09876543210', '98765 43210'] as $typed) {
    $em = "{$tag}_mob" . bin2hex(random_bytes(2)) . '@sk-sports-lab.test';
    $r = $reg($valid(['mobile' => $typed, 'email' => $em]));
    $stored = $users->findByEmail($em)['mobile'] ?? '';
    check('A10 mobile ' . json_encode($typed) . ' is accepted and stored as 9876543210', $r['success'] === true && $stored === '9876543210', (string) $stored);
}
foreach (['+91987654321', '+1 202 555 0143', '919876543210123'] as $typed) {
    $r = $reg($valid(['mobile' => $typed]));
    check('A11 mobile ' . json_encode($typed) . ' is refused', $r['success'] === false && isset($r['errors']['mobile']));
}

// ---------------------------------------------------------------- B. sign-in
$e1 = "{$tag}_login@sk-sports-lab.test";
$reg($valid(['email' => $e1, 'name' => 'Login Tester']));
$uid = (int) $users->findByEmail($e1)['id'];
$_SESSION = [];
$r = $auth->login(strtoupper($e1), $good);
check('B1 sign-in ignores the case of the e-mail', $r['success'] === true, $r['message']);
check('B2 the session now holds the customer', ($_SESSION['user_id'] ?? 0) === $uid);
$auth->logout();
$_SESSION = [];
$r = $auth->login("  $e1  ", $good);
check('B3 sign-in ignores spaces around the e-mail', $r['success'] === true, $r['message']);
$auth->logout();
$_SESSION = [];
$wrong = $auth->login($e1, 'Wrong-pass-1');
$none  = $auth->login("{$tag}_ghost@sk-sports-lab.test", 'Wrong-pass-1');
check('B4 wrong password and unknown account give the same message', $wrong['message'] === $none['message'] && $wrong['success'] === false);
check('B5 a failed sign-in leaves the session empty', empty($_SESSION['user_id']));
$r = $auth->login($e1, '');
check('B6 an empty password is refused', $r['success'] === false);
$r = $auth->login('', $good);
check('B7 an empty e-mail is refused', $r['success'] === false);
$r = $auth->login($e1, $good . "\0");
check('B8 a password with a NUL byte appended is refused (bcrypt would otherwise ignore it)', $r['success'] === false);
$r = $reg($valid(['password' => "Sprint!99\0tail", 'password_confirmation' => "Sprint!99\0tail"]));
check('B8b a new password containing control characters is refused', $r['success'] === false && isset($r['errors']['password']));
$r = $auth->login($e1, str_repeat('x', 100000));
check('B9 a 100 KB password is refused without a crash', $r['success'] === false);
$db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$uid]);
$resetLimits();
$r = $auth->login($e1, $good);
check('B10 a deactivated account cannot sign in', $r['success'] === false && empty($_SESSION['user_id']));
$wrongOnInactive = $auth->login($e1, 'Wrong-pass-1');
check('B11 a deactivated account does not reveal its state to someone guessing', $wrongOnInactive['message'] === 'Invalid email or password.');
$db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$uid]);
// lock-out after repeated failures, even for the right password, then a clean slate
$resetLimits();
for ($i = 0; $i < 5; $i++) { $auth->login($e1, 'Wrong-pass-' . $i); }
$r = $auth->login($e1, $good);
check('B12 after 5 wrong passwords the account is locked, even for the right password', $r['success'] === false && str_contains($r['message'], 'Too many'), $r['message']);
$resetLimits();
$r = $auth->login($e1, $good);
check('B13 once the lock clears the right password works again', $r['success'] === true);
$auth->logout();
$_SESSION = [];

// ---------------------------------------------------------------- C. forgot / reset password
$resets = new PasswordResetModel();
$tokenFor = function (int $userId) use ($q): ?string { return null; };
$latestRows = fn (int $userId) => $q('SELECT * FROM password_resets WHERE user_id = ? ORDER BY id', [$userId]);

// The plain token only ever travels by e-mail, so the tests mint tokens the same way the service does.
$mint = function (int $userId, string $expires = '+1 hour') use ($resets): string {
    $resets->revokeAllForUser($userId);
    $plain = bin2hex(random_bytes(32));
    $resets->create($userId, hash('sha256', $plain), new DateTime($expires));
    return $plain;
};

$r1 = $auth->forgotPassword($e1);
$r2 = $auth->forgotPassword("{$tag}_ghost@sk-sports-lab.test");
check('C1 forgot-password answers identically for known and unknown e-mails', $r1 === $r2, json_encode([$r1, $r2]));
$rows = $latestRows($uid);
check('C2 a reset row is created for the real account, and only its hash is stored', count($rows) >= 1 && strlen((string) end($rows)['token_hash']) === 64);
check('C3 no reset row exists for the unknown account', (int) $q("SELECT COUNT(*) c FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE u.email = ?", ["{$tag}_ghost@sk-sports-lab.test"])[0]['c'] === 0);
$resetLimits();
$before = count($latestRows($uid));
for ($i = 0; $i < 5; $i++) { $auth->forgotPassword($e1); }
$made = count($latestRows($uid)) - $before;
check('C4 more than 3 reset requests in 15 minutes do not create more links', $made <= 3, (string) $made);

$resetLimits();
$old = $mint($uid);
$new = $mint($uid);
check('C5 only the newest reset link is valid', $auth->verifyResetToken($old) === null && $auth->verifyResetToken($new) !== null);
check('C6 a tampered token is invalid', $auth->verifyResetToken(substr($new, 0, -1) . (substr($new, -1) === 'a' ? 'b' : 'a')) === null);
foreach (['', ' ', '0', 'null', str_repeat('a', 5000), "'; DROP TABLE users;--", '../../etc/passwd'] as $junk) {
    check('C7 junk token ' . json_encode(substr($junk, 0, 20)) . ' is invalid', $auth->verifyResetToken($junk) === null);
}
$r = $auth->resetPassword($new, 'weak', 'weak');
check('C8 a weak new password is refused and the link stays usable', $r['success'] === false && $auth->verifyResetToken($new) !== null);
$r = $auth->resetPassword($new, $good . 'a', $good . 'b');
check('C9 mismatched confirmation is refused and the link stays usable', $r['success'] === false && $auth->verifyResetToken($new) !== null);
$r = $auth->resetPassword($new, 'Login Tester 1', 'Login Tester 1');
check("C10 a password containing the owner's name is refused on reset", $r['success'] === false);
$expired = $mint($uid, '-1 minute');
check('C11 an expired link is invalid', $auth->verifyResetToken($expired) === null && $auth->resetPassword($expired, $good . 'z', $good . 'z')['success'] === false);

$_SESSION = ['user_id' => $uid, 'auth_at' => time() - 100];
sleep(1);
$fresh = $mint($uid);
$newPass = 'Finish-line-' . random_int(100, 999);
$r = $auth->resetPassword($fresh, $newPass, $newPass);
check('C12 a valid reset succeeds', $r['success'] === true, $r['message']);
check('C13 the same link cannot be used twice', $auth->resetPassword($fresh, $newPass . 'x', $newPass . 'x')['success'] === false && $auth->verifyResetToken($fresh) === null);
$resetLimits();
check('C14 the old password no longer works', $auth->login($e1, $good)['success'] === false);
$resetLimits();
check('C15 the new password works', $auth->login($e1, $newPass)['success'] === true);
$auth->logout();
$_SESSION = [];
$pc = $q('SELECT password_changed_at FROM users WHERE id = ?', [$uid])[0]['password_changed_at'];
check('C16 the reset stamps password_changed_at (signs other devices out)', $pc !== null);
check('C17 no reset links survive a successful reset', (int) $q('SELECT COUNT(*) c FROM password_resets WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()', [$uid])[0]['c'] === 0);

// a link belongs to its account only
$other = "{$tag}_other@sk-sports-lab.test";
$reg($valid(['email' => $other, 'name' => 'Other Person']));
$otherId = (int) $users->findByEmail($other)['id'];
$tok = $mint($otherId);
$auth->resetPassword($tok, 'Another-one-' . random_int(100, 999), 'Another-one-' . random_int(100, 999));
check('C18 resetting one account never touches another', $auth->login($e1, $newPass)['success'] === true);
$auth->logout();
$_SESSION = [];

// deactivated accounts get no link
$db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$otherId]);
$resetLimits();
$before = count($latestRows($otherId));
$auth->forgotPassword($other);
check('C19 a deactivated account is sent no reset link', count($latestRows($otherId)) === $before);
$db->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$otherId]);

// ---------------------------------------------------------------- D. profile and password change
$_SESSION = ['user_id' => $uid];
$r = $auth->updateProfile($uid, ['name' => 'Login Tester Renamed', 'mobile' => '99999 88888']);
check('D1 a valid profile update succeeds', $r['success'] === true && $users->findById($uid)['name'] === 'Login Tester Renamed' && $users->findById($uid)['mobile'] === '9999988888');
foreach ([['name' => 'A', 'mobile' => '9999988888'], ['name' => str_repeat('N', 101), 'mobile' => '9999988888'], ['name' => 'Fine Name', 'mobile' => '123'], ['name' => 'Fine Name', 'mobile' => ''], ['name' => '', 'mobile' => '9999988888']] as $bad) {
    $r = $auth->updateProfile($uid, $bad);
    check('D2 profile update refused: ' . json_encode($bad), $r['success'] === false);
}
check('D3 refused updates changed nothing', $users->findById($uid)['name'] === 'Login Tester Renamed');
$r = $auth->updateProfile($uid, ['name' => 'Hack', 'mobile' => '9999988888', 'email' => 'taken@evil.test', 'status' => 'inactive', 'id' => 1]);
check('D4 extra fields (email, status, id) cannot be smuggled into a profile update', $users->findById($uid)['email'] === $e1 && $users->findById($uid)['status'] === 'active' && $users->findById($uid)['id'] == $uid);

$r = $auth->changePassword($uid, 'wrong-current', 'Brand-new-' . random_int(100, 999), 'x');
check('D5 mismatched confirmation is refused', $r['success'] === false);
$r = $auth->changePassword($uid, 'wrong-current', 'Brand-new-1234', 'Brand-new-1234');
check('D6 a wrong current password is refused', $r['success'] === false && str_contains($r['message'], 'Current password'));
$r = $auth->changePassword($uid, $newPass, 'short1', 'short1');
check('D7 a weak new password is refused', $r['success'] === false);
$r = $auth->changePassword($uid, $newPass, $newPass, $newPass);
check('D8 the same password is refused', $r['success'] === false);
$resetLimits();
$guesses = 0;
for ($i = 0; $i < 12; $i++) {
    $r = $auth->changePassword($uid, 'guess-' . $i, 'Brand-new-1234', 'Brand-new-1234');
    if (str_contains($r['message'], 'Current password')) { $guesses++; }
}
$r = $auth->changePassword($uid, $newPass, 'Brand-new-1234', 'Brand-new-1234');
check('D9 guessing the current password is throttled (right password refused after repeated wrong ones)', $guesses <= 6 && $r['success'] === false, "guesses=$guesses " . $r['message']);
$resetLimits();
$r = $auth->changePassword($uid, $newPass, 'Brand-new-1234', 'Brand-new-1234');
check('D10 with the throttle cleared a correct change works', $r['success'] === true, $r['message']);

// ---------------------------------------------------------------- cleanup
$ids = array_column($q("SELECT id FROM users WHERE email LIKE ?", ["{$tag}%"]), 'id');
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $db->exec("DELETE FROM password_resets WHERE user_id IN ($in)");
    $db->exec("DELETE FROM users WHERE id IN ($in)");
}
$resetLimits();
echo "=== Account flows: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
