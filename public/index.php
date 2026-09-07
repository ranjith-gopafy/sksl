<?php

/**
 * SKSL — Front Controller
 *
 * This is the single entry point for all web requests.
 * Apache .htaccess routes all requests here.
 *
 * Responsibilities:
 *  1. Bootstrap the application (env, config, session, DB)
 *  2. Require the router
 *  3. Register all routes
 *  4. Dispatch the request
 */

declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/Router.php';

// ─── Controller includes ──────────────────────────────────────────────────
// Controllers are loaded via Composer PSR-4 autoload (App\ namespace)
// but since we have a simple structure, require them explicitly for now.
// Phase by phase, controllers will be added here as they are built.

// ─── Router ───────────────────────────────────────────────────────────────
$router = new Router();

// ── Public routes ─────────────────────────────────────────────────────────
// These will be populated in subsequent phases as controllers are built.

// Temporary health-check / smoke-test route (remove before production)
$router->get('/', function() {
    // This placeholder closure confirms routing works.
    // Replace with HomeController once built in Phase 4.
    header('Content-Type: text/plain');
    echo 'SKSL — Application is running. Phase 1 foundation complete.';
});

$router->get('/health', function() {
    header('Content-Type: application/json');
    try {
        $db = getDb();
        $db->query('SELECT 1');
        echo json_encode([
            'success' => true,
            'message' => 'Application is healthy.',
            'env'     => config('app.env'),
            'db'      => 'connected',
        ]);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed.',
        ]);
    }
});

// ─── Dispatch ─────────────────────────────────────────────────────────────
$router->dispatch();
