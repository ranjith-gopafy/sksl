<?php

declare(strict_types=1);

/**
 * SEO tests (audit Medium/Low: descriptions, canonical, Open Graph, robots,
 * sitemap, favicon, structured data, navigation active state).
 *
 * Sections 1-2 are pure unit checks; section 3 probes Apache at
 * http://localhost/sksl/public and is skipped when it is not running.
 *
 * Run: php tests/test_seo.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers\Seo;

echo "===================================================================\n";
echo "SKSL — SEO Tests\n";
echo "===================================================================\n\n";

$passCount = 0;
$failCount = 0;
function check(bool $condition, string $label): void
{
    global $passCount, $failCount;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $condition ? $passCount++ : $failCount++;
}

// ── Section 1: request_path() strips the deployment base ──────────────────
echo "--- Section 1: request_path() ---\n";
$cases = [
    // [SCRIPT_NAME, REQUEST_URI, expected]
    ['/sksl/public/index.php', '/sksl/public/',               '/'],
    ['/sksl/public/index.php', '/sksl/public',                '/'],
    ['/sksl/public/index.php', '/sksl/',                      '/'],
    ['/sksl/public/index.php', '/sksl/services',              '/services'],
    ['/sksl/public/index.php', '/sksl/public/services/',      '/services'],
    ['/sksl/public/index.php', '/sksl/public/contact?x=1',    '/contact'],
    ['/index.php',             '/',                           '/'],
    ['/index.php',             '/services',                   '/services'],
    ['/index.php',             '/my-bookings?status=all',     '/my-bookings'],
    ['/index.php',             '/admin/bookings',             '/admin/bookings'],
];
foreach ($cases as [$script, $uri, $expected]) {
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['REQUEST_URI'] = $uri;
    check(request_path() === $expected, "1.x {$script} + {$uri} -> {$expected} (got " . request_path() . ')');
}
$_SERVER['SCRIPT_NAME'] = '/sksl/public/index.php';
$_SERVER['REQUEST_URI'] = '/sksl/public/';

// ── Section 2: Seo helper ─────────────────────────────────────────────────
echo "\n--- Section 2: Seo helper ---\n";
check(Seo::isIndexable('/') && Seo::isIndexable('/services') && Seo::isIndexable('/contact'), '2.1 public pages indexable');
check(!Seo::isIndexable('/booking') && !Seo::isIndexable('/my-bookings') && !Seo::isIndexable('/admin/bookings') && !Seo::isIndexable('/profile'), '2.2 private pages not indexable');
check(Seo::descriptionFor('/services') !== Seo::descriptionFor('/') && Seo::descriptionFor('/contact') !== Seo::descriptionFor('/'), '2.3 per-page descriptions differ');
check(strlen(Seo::descriptionFor('/')) <= 200 && strlen(Seo::descriptionFor('/services')) <= 220, '2.4 descriptions are search-snippet length');
check(Seo::descriptionFor('/does-not-exist') === Seo::DEFAULT_DESCRIPTION, '2.5 unknown route falls back to default description');
$entries = Seo::sitemapEntries();
$locs = array_column($entries, 'loc');
check(count($entries) === 6, '2.6 sitemap lists the six public pages (' . count($entries) . ')');
check(!in_array(app_url('login'), $locs, true) && !in_array(app_url('booking'), $locs, true), '2.7 sitemap excludes login/booking');
check($locs[0] === app_url(''), '2.8 sitemap starts at the site root');
foreach (Seo::disallowedPaths() as $p) {
    if (Seo::isIndexable($p)) {
        check(false, "2.9 disallowed path {$p} must not also be indexable");
    }
}
check(in_array('/admin', Seo::disallowedPaths(), true) && in_array('/api', Seo::disallowedPaths(), true), '2.9 robots disallows /admin and /api');

$ld = Seo::localBusiness();
check($ld['@context'] === 'https://schema.org' && $ld['@type'] === 'SportsActivityLocation', '2.10 JSON-LD type');
check($ld['url'] === app_url('') && str_ends_with($ld['logo'], '/images/sksl-logo.png'), '2.11 JSON-LD url/logo');
check($ld['address']['addressCountry'] === 'IN' && $ld['address']['addressLocality'] !== '', '2.12 JSON-LD address');
check($ld['openingHoursSpecification'][0]['opens'] === config('app.facility.open'), '2.13 JSON-LD hours follow FACILITY_OPEN');
$json = Seo::localBusinessJsonLd();
check(!str_contains($json, '</') && !str_contains($json, '<'), '2.14 JSON-LD cannot break out of <script> (< escaped)');
check(json_decode($json, true) !== null, '2.15 JSON-LD is valid JSON');

// ── Section 3: live HTTP ──────────────────────────────────────────────────
echo "\n--- Section 3: live HTTP (Apache) ---\n";
$base = 'http://localhost/sksl/public';
$get = static function (string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false]);
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$code, substr($raw, 0, $hlen), substr($raw, $hlen)];
};
[$probe] = $get($base . '/health');
if ($probe === 0) {
    echo "[SKIP] Apache not reachable at {$base}\n";
} else {
    [$code, $headers, $body] = $get($base . '/robots.txt');
    check($code === 200 && stripos($headers, 'Content-Type: text/plain') !== false, '3.1 /robots.txt is served as text/plain');
    check(str_contains($body, 'User-agent: *') && preg_match('~Disallow: \S*/admin~', $body) === 1, '3.2 robots.txt disallows admin');
    check(preg_match('~^Sitemap: https?://\S+/sitemap\.xml$~m', $body) === 1, '3.3 robots.txt points at the sitemap');
    [$code] = $get('http://localhost/sksl/robots.txt');
    check($code === 200, '3.4 robots.txt reachable through the project-root .htaccess too');

    [$code, $headers, $body] = $get($base . '/sitemap.xml');
    check($code === 200 && stripos($headers, 'Content-Type: application/xml') !== false, '3.5 /sitemap.xml is served as XML');
    $xml = @simplexml_load_string($body);
    check($xml !== false && count($xml->url) === 6, '3.6 sitemap parses and has six URLs');
    check(!str_contains($body, '/booking<') && !str_contains($body, '/admin'), '3.7 sitemap excludes booking/admin');

    [$code, , $body] = $get($base . '/services');
    check(preg_match('~<link rel="canonical" href="https?://[^"]+/services">~', $body) === 1, '3.8 services canonical');
    check(preg_match('~<meta name="description" content="[^"]*pricing[^"]*">~i', $body) === 1, '3.9 services has its own description');
    check(str_contains($body, '<meta property="og:title"') && str_contains($body, '<meta name="twitter:card"'), '3.10 Open Graph + Twitter tags present');
    check(str_contains($body, '<link rel="icon"'), '3.11 favicon link present');
    check(preg_match('~<script type="application/ld\+json" nonce="[^"]+">~', $body) === 1, '3.12 JSON-LD present with CSP nonce');
    check(substr_count($body, 'aria-current="page"') === 2, '3.13 Services link marked current in desktop + mobile nav');
    check(preg_match('~<a href="[^"]+/contact"[^>]*aria-current~', $body) === 0, '3.14 Contact link not marked current on services page');

    [$code, , $body] = $get($base . '/');
    check(preg_match('~<a href="[^"]+" class="[^"]*font-bold"[^>]*aria-current="page">Home</a>~', $body) === 1, '3.15 Home link active on home page');
    check(substr_count($body, 'aria-current="page"') === 2, '3.16 exactly one active item per nav on home');

    [$code, , $body] = $get($base . '/login');
    check(str_contains($body, '<meta name="robots" content="index, follow">'), '3.17 login page indexable (no structured data)');
    check(!str_contains($body, 'application/ld+json'), '3.18 login page carries no LocalBusiness JSON-LD');

    [$code, , $body] = $get($base . '/admin/login');
    check(str_contains($body, '<meta name="robots" content="noindex, nofollow">') && !str_contains($body, 'rel="canonical"'), '3.19 admin login is noindex without canonical');

    [$code, , $body] = $get($base . '/register');
    check(substr_count($body, 'aria-current="page"') === 1, '3.20 register marks only the mobile Sign In tab current');
}

echo "\nResults: {$passCount} Passed, {$failCount} Failed\n";
echo $failCount === 0 ? ">>> ALL SEO TESTS PASSED <<<\n" : ">>> SEO TESTS FAILED <<<\n";
exit($failCount === 0 ? 0 : 1);
