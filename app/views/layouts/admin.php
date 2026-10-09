<?php
/**
 * SKSL Admin dashboard layout.
 *
 * A separate shell for everything an administrator does after signing in: a sidebar (a slide-in
 * drawer on phones), a slim top bar, and the page content. It deliberately has none of the public
 * website's navigation bar, footer or marketing bar.
 *
 * Controllers set $title ("Bookings Operations — SKSL Admin") and $viewFile before including this.
 */

$adminPath    = request_path();
$adminHeading = trim((string) preg_replace('/\s*[—-]\s*SKSL Admin\s*$/u', '', (string) ($title ?? 'Admin')));
$adminName    = (string) ($_SESSION['admin_name'] ?? 'Admin');
$adminInitial = mb_strtoupper(mb_substr(trim($adminName) !== '' ? trim($adminName) : 'A', 0, 1));

// Menu: [label, route, icon, active-prefixes]
$adminMenu = [
    'Overview' => [
        ['Dashboard',        'admin/dashboard',         'home',     ['/admin/dashboard']],
    ],
    'Operations' => [
        ['Bookings',         'admin/bookings',          'calendar', ['/admin/bookings']],
        ['Package Purchases', 'admin/package-purchases', 'receipt',  ['/admin/package-purchases']],
    ],
    'Catalogue' => [
        ['Modalities',       'admin/services',          'sparkles', ['/admin/services']],
        ['Combo Packages',   'admin/packages',          'cube',     ['/admin/packages']],
    ],
    'Availability' => [
        ['Practitioner Schedules', 'admin/availability',  'clock',  ['/admin/availability']],
        ['Manual Blocks',    'admin/manual-blocks',     'ban',      ['/admin/manual-blocks']],
        ['Closed Dates',     'admin/closed-dates',      'moon',     ['/admin/closed-dates']],
    ],
    'Website' => [
        ['Hero Banner',      'admin/banner',            'photo',    ['/admin/banner']],
    ],
];

$adminIcons = [
    'home'     => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10',
    'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    'receipt'  => 'M9 14l6-6M9.5 8.5h.01M14.5 13.5h.01M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-3-2-2 2-2-2-2 2-2-2-3 2z',
    'sparkles' => 'M5 3v4M3 5h4M6 17v4M4 19h4M13 3l2.5 6.5L22 12l-6.5 2.5L13 21l-2.5-6.5L4 12l6.5-2.5L13 3z',
    'cube'     => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    'clock'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'ban'      => 'M18.364 5.636a9 9 0 11-12.728 0 9 9 0 0112.728 0zM5.636 5.636l12.728 12.728',
    'moon'     => 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z',
    'photo'    => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
];

$adminCssPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/css/app.css';
$adminFontsPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/css/fonts.css';
$adminJsPath  = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/js/app.js';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= h($title ?? 'Admin — SKSL') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="<?= h(asset('images/sksl-logo.png')) ?>">
    <meta name="theme-color" content="#075183">

    <link rel="preload" href="<?= h(asset('fonts/inter-300-700-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= h(asset('fonts/outfit-500-900-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>?v=<?= file_exists($adminFontsPath) ? filemtime($adminFontsPath) : '1.0' ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= file_exists($adminCssPath) ? filemtime($adminCssPath) : '1.0' ?>">
    <script src="<?= asset('js/app.js') ?>?v=<?= file_exists($adminJsPath) ? filemtime($adminJsPath) : '1.0' ?>" defer></script>

    <style>
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        h1, h2, h3, h4, .font-heading { font-family: 'Outfit', sans-serif; }
        .skip-link { position: absolute; left: 0; top: -100px; z-index: 10000; padding: .75rem 1.25rem; background: #053d63; color: #fff; font-weight: 700; font-size: .875rem; border-radius: 0 0 .75rem 0; text-decoration: none; }
        .skip-link:focus { top: 0; outline: 3px solid #D6981E; outline-offset: 0; }
        main:focus { outline: none; }
        /* The sidebar keeps its own scroll; the page behind it does not move while the drawer is open. */
        body.admin-drawer-open { overflow: hidden; }
    </style>
</head>
<body class="min-h-full bg-slate-100 text-slate-800">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="min-h-screen lg:flex">

        <!-- Sidebar (drawer on phones and tablets, fixed column from lg up) -->
        <aside id="admin-sidebar"
               class="fixed inset-y-0 left-0 z-40 w-72 max-w-[85vw] -translate-x-full invisible lg:visible lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:max-w-none shrink-0 bg-white border-r border-slate-200 flex flex-col transition-transform duration-200 ease-out"
               aria-label="Admin sidebar">

            <div class="h-16 shrink-0 flex items-center justify-between gap-3 px-5 border-b border-slate-100">
                <a href="<?= app_url('admin/dashboard') ?>" class="flex items-center gap-2.5 min-w-0">
                    <img src="<?= asset('images/sksl-logo.png') ?>" alt="" width="192" height="192" class="h-9 w-9 object-contain shrink-0">
                    <span class="leading-tight min-w-0">
                        <span class="block font-heading font-extrabold text-sm uppercase tracking-wider text-[#075183] truncate">SKSL Admin</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-widest text-slate-500">Control Center</span>
                    </span>
                </a>
                <button type="button" id="admin-menu-close" class="lg:hidden p-2 -mr-2 rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close menu">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5" aria-label="Admin">
                <?php foreach ($adminMenu as $group => $items): ?>
                    <div>
                        <p class="px-3 mb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-500"><?= h($group) ?></p>
                        <ul class="space-y-0.5">
                            <?php foreach ($items as [$label, $route, $icon, $prefixes]):
                                $active = false;
                                foreach ($prefixes as $prefix) {
                                    if ($adminPath === $prefix || str_starts_with($adminPath, $prefix . '/')) { $active = true; }
                                }
                            ?>
                                <li>
                                    <a href="<?= app_url($route) ?>"
                                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors <?= $active ? 'bg-sky-50 text-[#075183] font-bold' : 'text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-900' ?>"
                                       <?= $active ? 'aria-current="page"' : '' ?>>
                                        <svg class="w-5 h-5 shrink-0 <?= $active ? 'text-[#075183]' : 'text-slate-500' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $adminIcons[$icon] ?>"/></svg>
                                        <span class="truncate"><?= h($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="shrink-0 border-t border-slate-100 p-3 space-y-1">
                <a href="<?= app_url('') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                    <svg class="w-5 h-5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    View website
                </a>
                <form method="POST" action="<?= app_url('admin/logout') ?>">
                    <?= \App\Helpers\Csrf::field() ?>
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer bg-transparent border-0 text-left">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Dim layer behind the drawer on small screens -->
        <div id="admin-overlay" class="hidden fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-hidden="true"></div>

        <!-- Content column -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="sticky top-0 z-20 h-16 bg-white/90 backdrop-blur border-b border-slate-200 flex items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" id="admin-menu-open" class="lg:hidden p-2 -ml-2 rounded-lg text-slate-700 hover:bg-slate-100" aria-label="Open menu" aria-controls="admin-sidebar" aria-expanded="false">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <h1 class="font-heading text-lg sm:text-xl font-extrabold text-slate-900 truncate"><?= h($adminHeading) ?></h1>
                <div class="ml-auto flex items-center gap-3 min-w-0">
                    <span class="hidden sm:block text-right leading-tight min-w-0">
                        <span class="block text-sm font-semibold text-slate-800 truncate max-w-[14rem]"><?= h($adminName) ?></span>
                        <span class="block text-[11px] text-slate-500 truncate max-w-[14rem]"><?= h((string) ($_SESSION['admin_email'] ?? '')) ?></span>
                    </span>
                    <span class="w-9 h-9 shrink-0 rounded-full bg-[#075183] text-white flex items-center justify-center font-heading font-bold text-sm" aria-hidden="true"><?= h($adminInitial) ?></span>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="flex-1 p-4 sm:p-6 lg:p-8">
                <?php
                if (isset($viewFile) && file_exists($viewFile)) {
                    require $viewFile;
                } else {
                    echo '<div class="py-12 text-center text-slate-500">Page not found.</div>';
                }
                ?>
            </main>
        </div>
    </div>

    <?php require __DIR__ . '/_toasts.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
    (function () {
        var sidebar = document.getElementById('admin-sidebar');
        var overlay = document.getElementById('admin-overlay');
        var openBtn = document.getElementById('admin-menu-open');
        var closeBtn = document.getElementById('admin-menu-close');
        if (!sidebar || !overlay || !openBtn) { return; }

        function isDrawerMode() { return window.matchMedia('(max-width: 1023px)').matches; }

        function setOpen(open) {
            sidebar.classList.toggle('-translate-x-full', !open);
            sidebar.classList.toggle('invisible', !open); // a closed drawer is not reachable by keyboard or screen reader
            overlay.classList.toggle('hidden', !open);
            document.body.classList.toggle('admin-drawer-open', open);
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var first = sidebar.querySelector('a[href]');
                if (first) { first.focus(); }
            } else {
                openBtn.focus();
            }
        }

        openBtn.addEventListener('click', function () { setOpen(true); });
        if (closeBtn) { closeBtn.addEventListener('click', function () { setOpen(false); }); }
        overlay.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isDrawerMode() && !overlay.classList.contains('hidden')) { setOpen(false); }
        });
        // Growing the window to desktop width closes the drawer state so nothing stays locked.
        window.addEventListener('resize', function () {
            if (!isDrawerMode() && !overlay.classList.contains('hidden')) {
                overlay.classList.add('hidden');
                document.body.classList.remove('admin-drawer-open');
                openBtn.setAttribute('aria-expanded', 'false');
            }
        });
    })();
    </script>
</body>
</html>
