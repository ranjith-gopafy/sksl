<?php
/**
 * SKSL Admin sign-in layout: a clean, centred page for the admin login and one-time-code screens.
 * No public navigation bar, footer or marketing bar; just the card and a way back to the website.
 *
 * Controllers set $title and $viewFile before including this.
 */
$authCssPath   = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/css/app.css';
$authFontsPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/css/fonts.css';
$authJsPath    = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3)) . '/public/js/app.js';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= h($title ?? 'Admin sign in — SKSL') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" href="<?= h(asset('images/sksl-logo.png')) ?>">
    <meta name="theme-color" content="#075183">

    <link rel="preload" href="<?= h(asset('fonts/inter-300-700-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= h(asset('fonts/outfit-500-900-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>?v=<?= file_exists($authFontsPath) ? filemtime($authFontsPath) : '1.0' ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= file_exists($authCssPath) ? filemtime($authCssPath) : '1.0' ?>">
    <script src="<?= asset('js/app.js') ?>?v=<?= file_exists($authJsPath) ? filemtime($authJsPath) : '1.0' ?>" defer></script>

    <style>
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        h1, h2, h3, h4, .font-heading { font-family: 'Outfit', sans-serif; }
        .skip-link { position: absolute; left: 0; top: -100px; z-index: 10000; padding: .75rem 1.25rem; background: #053d63; color: #fff; font-weight: 700; font-size: .875rem; border-radius: 0 0 .75rem 0; text-decoration: none; }
        .skip-link:focus { top: 0; outline: 3px solid #D6981E; outline-offset: 0; }
        main:focus { outline: none; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-gradient-to-b from-sky-50 via-slate-50 to-slate-100">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="px-4 sm:px-6 pt-5">
        <a href="<?= app_url('') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-[#075183]">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to website
        </a>
    </div>

    <main id="main-content" tabindex="-1" class="flex-1 flex items-center justify-center px-4 py-8">
        <?php
        if (isset($viewFile) && file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<div class="text-slate-500">Page not found.</div>';
        }
        ?>
    </main>

    <p class="px-4 pb-6 text-center text-[11px] text-slate-500">Authorised staff only. Sign-in codes are sent to the registered administrator email.</p>

    <?php require __DIR__ . '/_toasts.php'; ?>
</body>
</html>
