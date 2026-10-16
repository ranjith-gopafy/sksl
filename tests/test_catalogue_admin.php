<?php

/**
 * Admin category and modality management over real HTTP, as a signed-in admin: add / rename / reorder /
 * delete categories, add / edit / delete modalities (including the "has bookings, so hide instead" rule),
 * and what customers see (filter buttons, the modality on the pages, the navbar without Home).
 * Everything the test creates or changes is put back at the end.
 *
 * Needs the site running (XAMPP/Apache at http://localhost/sksl/public, or pass a base URL as argv[1]).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
assert_cli_only(true);

use App\Helpers\RateLimit;
use App\Helpers\TimeHelper;
use App\Models\AdminModel;
use App\Models\BookingModel;
use App\Models\ServiceCategoryModel;
use App\Models\ServiceModel;
use App\Models\UserModel;

$base = rtrim($argv[1] ?? getenv('SKSL_TEST_BASE_URL') ?: 'http://localhost/sksl/public', '/');
$db   = getDb();
$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; return; }
    $fail++;
    echo "[FAIL] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
}

final class Admin
{
    public string $jar;
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'sksl_cat'); }
    public function __destruct() { @unlink($this->jar); }
    /** @return array{code:int, body:string, location:string} */
    public function req(string $method, string $url, array|string|null $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
        ]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $res = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        preg_match('/^Location:\s*(.+)$/mi', substr($res, 0, $size), $m);
        return ['code' => $code, 'body' => substr($res, $size), 'location' => trim($m[1] ?? '')];
    }
    public function token(string $path): string
    {
        $r = $this->req('GET', $GLOBALS['base'] . $path);
        return preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : (preg_match('/name="csrf-token"\s+content="([a-f0-9]+)"/i', $r['body'], $m) ? $m[1] : '');
    }
    /** POST a form with a fresh CSRF token, follow the redirect once and return the page that shows the flash message. */
    public function post(string $path, array $fields = [], string $tokenFrom = '/admin/dashboard'): array
    {
        $fields['_csrf_token'] = $this->token($tokenFrom);
        $r = $this->req('POST', $GLOBALS['base'] . $path, http_build_query($fields));
        $next = $r['location'] !== '' ? $this->req('GET', $r['location']) : ['code' => 0, 'body' => '', 'location' => ''];
        return ['code' => $r['code'], 'location' => $r['location'], 'flash' => html_entity_decode(strip_tags($next['body']), ENT_QUOTES | ENT_HTML5), 'html' => $next['body']];
    }
}

if ((new Admin())->req('GET', $base . '/health')['code'] === 0) {
    echo "[SKIP] site not reachable at $base\n";
    exit(0);
}
echo "SKSL — admin categories and modalities\n";
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $s) { RateLimit::clearScope($s); }

$q = function (string $sql, array $a = []) use ($db): array { $s = $db->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };
$tag = 'ct' . substr(bin2hex(random_bytes(3)), 0, 6);
$cats = new ServiceCategoryModel();
$svcs = new ServiceModel();

// ---------------------------------------------------------------- not signed in
$anon = new Admin();
$before = (int) $db->query('SELECT COUNT(*) FROM service_categories')->fetchColumn();
$r = $anon->req('GET', $base . '/admin/categories');
check('A1 the categories page needs an admin sign-in', $r['code'] === 302 && str_contains($r['location'], 'login'), $r['code'] . ' ' . $r['location']);
$r = $anon->req('POST', $base . '/admin/categories', http_build_query(['name' => 'Anon ' . $tag]));
check('A2 and so does adding a category (nothing is created)', (int) $db->query('SELECT COUNT(*) FROM service_categories')->fetchColumn() === $before && $r['code'] !== 200);
$beforeSvc = (int) $db->query('SELECT COUNT(*) FROM services')->fetchColumn();
$anon->req('POST', $base . '/admin/services', http_build_query(['name' => 'Anon ' . $tag, 'price' => '100', 'capacity' => '1', 'duration_minutes' => '15', 'category_id' => '1']));
check('A3 and adding a modality (nothing is created)', (int) $db->query('SELECT COUNT(*) FROM services')->fetchColumn() === $beforeSvc);

// ---------------------------------------------------------------- sign in
$admins = new AdminModel();
$adminId = $admins->create('Catalogue Admin', "{$tag}_admin@sk-sports-lab.test", 'active');
$a = new Admin();
$a->req('POST', $base . '/admin/login', http_build_query(['email' => "{$tag}_admin@sk-sports-lab.test", '_csrf_token' => $a->token('/admin/login')]));
$admins->createOtp($adminId, password_hash('246810', PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + 600));
$r = $a->req('POST', $base . '/admin/verify-otp', http_build_query(['otp' => '246810', '_csrf_token' => $a->token('/admin/verify-otp')]));
check('S0 admin signed in', $r['code'] === 302 && str_contains($r['location'], 'dashboard'), $r['location']);

$createdCategories = [];
$createdServices = [];
$bookingUser = null;
$cleanup = function () use ($db, &$createdServices, &$createdCategories, &$bookingUser, $adminId): void {
    foreach ($createdServices as $sid) {
        $db->exec("DELETE FROM bookings WHERE service_id = " . (int) $sid);
        $db->exec("DELETE FROM services WHERE id = " . (int) $sid);
    }
    foreach ($createdCategories as $cid) { $db->exec("DELETE FROM service_categories WHERE id = " . (int) $cid); }
    if ($bookingUser) { $db->exec("DELETE FROM users WHERE id = " . (int) $bookingUser); }
    $db->exec("DELETE FROM admin_otps WHERE admin_id = $adminId");
    $db->exec("DELETE FROM admins WHERE id = $adminId");
};
register_shutdown_function($cleanup);

// ---------------------------------------------------------------- B. categories
$page = $a->req('GET', $base . '/admin/categories');
check('B1 the categories page loads and lists the four starting categories in order',
    $page['code'] === 200 && preg_match('/Cold &amp; Cryo Immersion.*Thermal &amp; Sauna.*Hydrotherapy.*Myotherapy/s', $page['body']) === 1, (string) $page['code']);
check('B2 the old name "Aquatic Conditioning" is gone', !str_contains($page['body'], 'Aquatic Conditioning'));

$name = "Compression $tag";
$r = $a->post('/admin/categories', ['name' => $name], '/admin/categories');
$row = $q('SELECT * FROM service_categories WHERE name = ?', [$name])[0] ?? null;
check('B3 adding a category creates it, with a URL key, last in order', $row !== null && $row['slug'] === strtolower("compression-$tag") && (int) $row['sort_order'] > 4, json_encode($row) . $r['flash']);
$catId = (int) ($row['id'] ?? 0);
$createdCategories[] = $catId;

$r = $a->post('/admin/categories', ['name' => strtoupper($name)], '/admin/categories');
check('B4 the same name in other capitals is refused', str_contains($r['flash'], 'already a category') && (int) $q('SELECT COUNT(*) c FROM service_categories WHERE name = ?', [$name])[0]['c'] === 1, $r['flash']);
foreach (['', ' ', 'x', str_repeat('a', 61), "bad\x01name", '<img src=x onerror=alert(1)>', "' OR '1'='1'; --", '-1', '12345', "Smile \u{1F600}"] as $bad) {
    $beforeCount = (int) $db->query('SELECT COUNT(*) FROM service_categories')->fetchColumn();
    $a->post('/admin/categories', ['name' => $bad], '/admin/categories');
    check('B5 category name ' . json_encode($bad) . ' is refused', (int) $db->query('SELECT COUNT(*) FROM service_categories')->fetchColumn() === $beforeCount);
}
$r = $a->post('/admin/categories', ['name' => "  Spaced   Out $tag  "], '/admin/categories');
$sp = $q('SELECT * FROM service_categories WHERE name = ?', ["Spaced Out $tag"])[0] ?? null;
check('B6 extra spaces in a name are tidied', $sp !== null, $r['flash']);
if ($sp) { $createdCategories[] = (int) $sp['id']; }

$r = $a->post("/admin/categories/$catId/update", ['name' => "Compression Therapy $tag", 'sort_order' => '9'], '/admin/categories');
$row = $q('SELECT * FROM service_categories WHERE id = ?', [$catId])[0];
check('B7 renaming and reordering saves, and the URL key follows the name', $row['name'] === "Compression Therapy $tag" && (int) $row['sort_order'] === 9 && $row['slug'] === strtolower("compression-therapy-$tag"), json_encode($row));
$r = $a->post("/admin/categories/$catId/update", ['name' => 'Hydrotherapy'], '/admin/categories');
check('B8 renaming onto another category\'s name is refused', $q('SELECT name FROM service_categories WHERE id = ?', [$catId])[0]['name'] === "Compression Therapy $tag" && str_contains($r['flash'], 'already a category'), $r['flash']);
$r = $a->post('/admin/categories/999999999/update', ['name' => 'Whatever'], '/admin/categories');
check('B9 updating an unknown category is refused cleanly', $r['code'] === 302 && str_contains($r['flash'], 'not found'), $r['flash']);

// ---------------------------------------------------------------- C. adding a modality
$hydro = $q("SELECT id FROM service_categories WHERE slug = 'hydrotherapy'")[0]['id'];
$mName = "Cryo Chamber $tag";
$good = ['name' => $mName, 'category_id' => (string) $catId, 'price' => '799', 'capacity' => '2', 'duration_minutes' => '20', 'description' => 'Whole-body cryotherapy.', 'status' => 'active'];
$countSvc = fn () => (int) $db->query('SELECT COUNT(*) FROM services')->fetchColumn();

$n = $countSvc();
$r = $a->post('/admin/services', array_merge($good, ['category_id' => '']), '/admin/services');
check('C1 a modality without a category is refused', $countSvc() === $n && str_contains($r['flash'], 'category'), $r['flash']);
$r = $a->post('/admin/services', array_merge($good, ['category_id' => '999999999']), '/admin/services');
check('C2 a made-up category is refused', $countSvc() === $n);
foreach ([['price', 'abc'], ['price', '0'], ['price', '12.345'], ['price', '9999999'], ['capacity', '0'], ['capacity', '51'], ['capacity', 'x'], ['duration_minutes', '4'], ['duration_minutes', '361'], ['duration_minutes', 'abc'], ['name', 'x'], ['name', str_repeat('n', 101)], ['name', '<script>alert(1)</script>'], ['name', '-1'], ['name', 'Cryo; DROP TABLE services']] as [$field, $val]) {
    $a->post('/admin/services', array_merge($good, [$field => $val]), '/admin/services');
    check("C3 $field=" . json_encode($val) . ' is refused', $countSvc() === $n);
}
$r = $a->post('/admin/services', $good, '/admin/services');
$new = $q('SELECT * FROM services WHERE name = ?', [$mName])[0] ?? null;
check('C4 a valid modality is created as a fixed-price recovery modality in its category',
    $new !== null && $new['category'] === 'modality' && (int) $new['category_id'] === $catId && (float) $new['price'] === 799.00 && (int) $new['capacity'] === 2 && (int) $new['duration_minutes'] === 20 && $new['pricing_type'] === 'fixed' && $new['status'] === 'active' && (float) $new['gst_percent'] > 0,
    json_encode($new) . $r['flash']);
$newId = (int) ($new['id'] ?? 0);
$createdServices[] = $newId;
check('C5 its URL key comes from the name', ($new['slug'] ?? '') === strtolower("cryo-chamber-$tag"), (string) ($new['slug'] ?? ''));
$r = $a->post('/admin/services', array_merge($good, ['name' => strtolower($mName)]), '/admin/services');
check('C6 the same name again is refused', $countSvc() === $n + 1 && str_contains($r['flash'], 'already a modality'), $r['flash']);

$public = (new Admin())->req('GET', $base . '/services')['body'];
check('C7 the new modality is on the public modalities page', str_contains($public, $mName));
check('C8 and so is its category as a filter button, with the card carrying the same key',
    preg_match('/data-category="compression-therapy-' . $tag . '"[^>]*>\s*Compression Therapy ' . $tag . '/i', $public) === 1 && substr_count($public, 'data-category="compression-therapy-' . $tag . '"') >= 2);
$home = (new Admin())->req('GET', $base . '/')['body'];
check('C9 the home page shows the same button and modality', str_contains($home, "Compression Therapy $tag") && str_contains($home, $mName));
$avail = json_decode((new Admin())->req('GET', $base . '/api/availability?service_id=' . $newId . '&date=' . TimeHelper::now()->modify('+3 days')->format('Y-m-d'))['body'], true);
check('C10 the new modality can be booked: it has slots on an open day', is_array($avail) && !empty($avail['success'] ?? $avail['data'] ?? null), substr(json_encode($avail), 0, 200));

// ---------------------------------------------------------------- D. the names customers see
foreach (['/services' => $public, '/' => $home] as $path => $html) {
    check("D1 $path has the new category names", str_contains($html, 'Hydrotherapy') && str_contains($html, 'Myotherapy') && str_contains($html, 'Cold &amp; Cryo Immersion') && str_contains($html, 'Thermal &amp; Sauna'));
    check("D2 $path has no Aquatic Conditioning filter button", !preg_match('/data-category="[^"]*"[^>]*>\s*Aquatic Conditioning/i', $html));
}
$massage = $q("SELECT category_id FROM services WHERE slug = 'massages'")[0]['category_id'];
check('D3 Massages sits under Myotherapy', $q('SELECT name FROM service_categories WHERE id = ?', [$massage])[0]['name'] === 'Myotherapy');

// ---------------------------------------------------------------- E. editing
$r = $a->post("/admin/services/$newId/update", array_merge($good, ['name' => "Cryo Chamber Pro $tag", 'category_id' => (string) $hydro, 'duration_minutes' => '25', 'price' => '850.50']), '/admin/services');
$row = $q('SELECT * FROM services WHERE id = ?', [$newId])[0];
check('E1 editing changes name, category, session length and price', $row['name'] === "Cryo Chamber Pro $tag" && (int) $row['category_id'] === (int) $hydro && (int) $row['duration_minutes'] === 25 && (float) $row['price'] === 850.50, json_encode($row) . $r['flash']);
check('E2 the URL key does not change on a rename (old links keep working)', $row['slug'] === strtolower("cryo-chamber-$tag"), $row['slug']);
$a->post("/admin/services/$newId/update", array_merge($good, ['name' => 'Sauna']), '/admin/services');
check('E3 renaming onto another modality\'s name is refused', $q('SELECT name FROM services WHERE id = ?', [$newId])[0]['name'] === "Cryo Chamber Pro $tag");
$a->post("/admin/services/$newId/update", array_merge($good, ['name' => "Cryo Chamber Pro $tag", 'category_id' => '0']), '/admin/services');
check('E4 clearing the category is refused', (int) $q('SELECT category_id FROM services WHERE id = ?', [$newId])[0]['category_id'] === (int) $hydro);
// an edit form that does not mention the category keeps it (older forms)
$a->post("/admin/services/$newId/update", ['price' => '860', 'capacity' => '2', 'description' => 'x', 'status' => 'active'], '/admin/services');
$row = $q('SELECT * FROM services WHERE id = ?', [$newId])[0];
check('E5 an edit without a category keeps the current category and name', (int) $row['category_id'] === (int) $hydro && $row['name'] === "Cryo Chamber Pro $tag" && (float) $row['price'] === 860.00);
$phys = $svcs->findBySlug('physiotherapy');
$physBefore = $q('SELECT name, category_id, price, capacity, description, status FROM services WHERE id = ?', [$phys['id']])[0];
check('E6 practitioner services have no category', $physBefore['category_id'] === null);

// ---------------------------------------------------------------- F. categories with modalities cannot be deleted
// the modality was moved to Hydrotherapy in E1: put it back in the new category
$a->post("/admin/services/$newId/update", array_merge($good, ['name' => "Cryo Chamber Pro $tag", 'category_id' => (string) $catId]), '/admin/services');
$r = $a->post("/admin/categories/$catId/delete", [], '/admin/categories');
check('F1 a category that still has a modality is not deleted', (int) $q('SELECT COUNT(*) c FROM service_categories WHERE id = ?', [$catId])[0]['c'] === 1 && $r['code'] === 302, $r['flash']);
$r = $a->post("/admin/categories/$catId/delete", [], '/admin/categories');
check('F2 ...and the message says why', str_contains($r['flash'], 'still has 1 modality'), $r['flash']);

// hidden when nothing in it is active
$a->post("/admin/services/$newId/toggle", [], '/admin/services');
$pub2 = (new Admin())->req('GET', $base . '/services')['body'];
check('F3 a category with no active modality has no filter button for customers', !str_contains($pub2, "Compression Therapy $tag") || !preg_match('/data-category="compression-therapy-' . $tag . '"[^>]*>\s*Compression/i', $pub2));
$a->post("/admin/services/$newId/toggle", [], '/admin/services');

// ---------------------------------------------------------------- G. deleting a modality
// one with a booking is hidden, not deleted
$bookingUser = (new UserModel())->create('Catalogue Customer', "{$tag}_c@sk-sports-lab.test", '9876543210', password_hash('Xx1!' . random_int(1000, 9999), PASSWORD_BCRYPT));
$start = TimeHelper::now()->modify('+50 hours');
$split = ServiceModel::calculatePricing(860.00);
(new BookingModel())->create([
    'booking_reference' => 'SKSL-' . $start->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))), 'user_id' => $bookingUser, 'service_id' => $newId,
    'booking_type' => 'standard', 'payment_mode' => 'payment_required', 'duration_option_id' => null, 'package_credit_id' => null,
    'booking_date' => $start->format('Y-m-d'), 'start_time' => '10:00:00', 'end_time' => '10:25:00',
    'service_name_snapshot' => "Cryo Chamber Pro $tag", 'service_duration_minutes' => 25,
    'base_amount' => $split['base_price'], 'gst_amount' => $split['gst_amount'], 'total_amount' => 860.00,
    'booking_status' => 'confirmed', 'payment_status' => 'paid', 'visit_status' => 'pending',
]);
$r = $a->post("/admin/services/$newId/delete", [], '/admin/services');
$row = $q('SELECT status FROM services WHERE id = ?', [$newId]);
check('G1 a modality with a booking is hidden instead of deleted', count($row) === 1 && $row[0]['status'] === 'inactive' && str_contains($r['flash'], 'hidden'), json_encode($row) . $r['flash']);
check('G2 the booking and its name are untouched', (int) $q('SELECT COUNT(*) c FROM bookings WHERE service_id = ?', [$newId])[0]['c'] === 1);

// one nothing uses is really deleted
$a->post('/admin/services', array_merge($good, ['name' => "Throwaway $tag", 'category_id' => (string) $catId]), '/admin/services');
$tw = $q('SELECT id FROM services WHERE name = ?', ["Throwaway $tag"])[0] ?? null;
$createdServices[] = (int) ($tw['id'] ?? 0);
$r = $a->post('/admin/services/' . (int) ($tw['id'] ?? 0) . '/delete', [], '/admin/services');
check('G3 an unused modality is deleted for good', (int) $q('SELECT COUNT(*) c FROM services WHERE name = ?', ["Throwaway $tag"])[0]['c'] === 0 && str_contains($r['flash'], 'Deleted'), $r['flash']);
$r = $a->post('/admin/services/999999999/delete', [], '/admin/services');
check('G4 deleting an unknown modality is refused cleanly', $r['code'] === 302 && str_contains($r['flash'], 'not found'), $r['flash']);
$r = $a->post('/admin/services/' . (int) $phys['id'] . '/delete', [], '/admin/services');
check('G5 practitioner services cannot be deleted', (int) $q('SELECT COUNT(*) c FROM services WHERE id = ?', [$phys['id']])[0]['c'] === 1 && str_contains($r['flash'], 'cannot be deleted'), $r['flash']);
// a modality that is part of a combo package is hidden, not erased (checked on a throwaway modality + package item)
$a->post('/admin/services', array_merge($good, ['name' => "InPackage $tag", 'category_id' => (string) $hydro]), '/admin/services');
$ip = $q('SELECT id FROM services WHERE name = ?', ["InPackage $tag"])[0] ?? null;
$createdServices[] = (int) ($ip['id'] ?? 0);
$pkgRow = $q('SELECT id FROM packages ORDER BY id LIMIT 1')[0] ?? null;
if ($ip && $pkgRow) {
    $db->prepare('INSERT INTO package_items (package_id, service_id, quantity) VALUES (?, ?, 1)')->execute([$pkgRow['id'], $ip['id']]);
    $a->post('/admin/services/' . (int) $ip['id'] . '/delete', [], '/admin/services');
    check('G6 a modality that is part of a combo package is hidden, not erased', (int) $q('SELECT COUNT(*) c FROM services WHERE id = ?', [$ip['id']])[0]['c'] === 1 && $q('SELECT status FROM services WHERE id = ?', [$ip['id']])[0]['status'] === 'inactive');
    $db->prepare('DELETE FROM package_items WHERE service_id = ?')->execute([$ip['id']]);
}

// ---------------------------------------------------------------- H. deleting a category
$sid2 = (int) $q('SELECT id FROM services WHERE id = ?', [$newId])[0]['id'];
$a->post("/admin/services/$sid2/update", array_merge($good, ['name' => "Cryo Chamber Pro $tag", 'category_id' => (string) $hydro, 'status' => 'inactive']), '/admin/services');
$r = $a->post("/admin/categories/$catId/delete", [], '/admin/categories');
check('H1 an empty category can be deleted', (int) $q('SELECT COUNT(*) c FROM service_categories WHERE id = ?', [$catId])[0]['c'] === 0 && str_contains($r['flash'], 'Deleted'), $r['flash']);
$r = $a->post('/admin/categories/999999999/delete', [], '/admin/categories');
check('H2 deleting an unknown category is refused cleanly', $r['code'] === 302 && str_contains($r['flash'], 'not found'), $r['flash']);
$r = $a->post('/admin/categories/' . (int) $hydro . '/delete', [], '/admin/categories');
check('H3 Hydrotherapy (it has modalities) cannot be deleted', (int) $q('SELECT COUNT(*) c FROM service_categories WHERE id = ?', [$hydro])[0]['c'] === 1);

// ---------------------------------------------------------------- I. admin pages and the navbar
foreach (['/admin/categories', '/admin/services'] as $p) {
    $x = $a->req('GET', $base . $p);
    check("I1 $p loads for an admin without errors", $x['code'] === 200 && !preg_match('/Warning:|Fatal error|Stack trace/', $x['body']), (string) $x['code']);
}
$svcPage = $a->req('GET', $base . '/admin/services')['body'];
check('I2 the modalities page offers Add modality, a category choice and Delete', str_contains($svcPage, 'data-testid="add-modality"') && str_contains($svcPage, 'id="edit_category"') && str_contains($svcPage, 'btn-delete-service'));
$menu = $a->req('GET', $base . '/admin/dashboard')['body'];
check('I3 the admin menu has a Categories entry', preg_match('/href="[^"]*admin\/categories"/', $menu) === 1);
$siteAsAdmin = $a->req('GET', $base . '/')['body'];
preg_match('/<nav aria-label="Primary".*?<\/nav>/s', $siteAsAdmin, $m);
$nav = $m[0] ?? '';
check('I4 the website menu has no Home link', $nav !== '' && !preg_match('/>\s*Home\s*</', $nav), substr(strip_tags($nav), 0, 200));
check('I5 and no admin shortcuts (Bookings / Banners) next to it, even when an admin is signed in', !str_contains($nav, 'admin/') && !preg_match('/>\s*(Bookings|Banners|Packages)\s*</', $nav));
check('I6 the other menu items are still there', str_contains($nav, 'Modalities') && str_contains($nav, 'Combo Packages') && str_contains($nav, 'Contact'));
check('I7 the admin still has a way back to the Control Center on the website', str_contains($siteAsAdmin, 'Admin Control Center'));

// ---------------------------------------------------------------- cleanup
$cleanup();
$createdServices = []; $createdCategories = []; $bookingUser = null;
$after = $q("SELECT slug FROM service_categories ORDER BY sort_order, id");
check('Z1 the four starting categories are back exactly as they were', array_column($after, 'slug') === ['cold-cryo-immersion', 'thermal-sauna', 'hydrotherapy', 'myotherapy'], json_encode(array_column($after, 'slug')));
check('Z2 the catalogue is back to its twelve services', $countSvc() === $beforeSvc, (string) $countSvc());
foreach (['admin_otp_req', 'admin_otp_req_ip', 'admin_otp_verify', 'admin_otp_verify_ip'] as $s) { RateLimit::clearScope($s); }

echo "=== Catalogue admin: $pass passed, $fail failed ===\n";
exit($fail === 0 ? 0 : 1);
