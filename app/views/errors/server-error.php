<?php
/**
 * 500 — rendered by the global exception handler (bootstrap.php) and by
 * Router::serverError(). Self-contained on purpose: the failure may be inside
 * the layout, config or database, so nothing here depends on them.
 * Never prints the error: details go to storage/logs/php-error.log.
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Something went wrong — Sara Kinetic Sports Lab</title>
    <style>
        body { margin: 0; font-family: Inter, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .card { max-width: 440px; margin: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 40px 32px; text-align: center; box-shadow: 0 10px 30px rgba(5, 61, 99, .08); }
        .badge { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #9f1239; background: #ffe4e6; border-radius: 999px; padding: 6px 12px; }
        h1 { font-size: 24px; margin: 16px 0 8px; color: #053d63; }
        p { font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px; }
        a.btn { display: inline-block; background: #053d63; color: #fff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 12px 22px; border-radius: 14px; }
        a.btn:hover { background: #075183; }
        .brand { margin-top: 24px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <span class="badge">500 &middot; Server Error</span>
        <h1>Something went wrong</h1>
        <p>An unexpected error stopped this request. If you were paying, check <strong>My Bookings</strong> before trying again — a slot is never charged twice. Our team has been notified.</p>
        <a class="btn" href="javascript:history.back()">Go back</a>
        <div class="brand">Sara Kinetic Sports Lab</div>
    </main>
</body>
</html>
