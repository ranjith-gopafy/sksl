<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AdminAuth;
use App\Models\DashboardModel;

/**
 * Admin Dashboard Controller
 *
 * The page an administrator lands on after signing in: a summary of what is already in the system.
 * It adds no new actions; every figure links to the existing page that handles it.
 */
class AdminDashboardController
{
    private DashboardModel $dashboard;

    public function __construct(?DashboardModel $dashboard = null)
    {
        $this->dashboard = $dashboard ?? new DashboardModel();
    }

    /**
     * GET /admin and GET /admin/dashboard
     */
    public function index(): void
    {
        AdminAuth::handle();

        $stats    = $this->dashboard->stats();
        $schedule = $this->dashboard->todaySchedule();
        $recent   = $this->dashboard->recentBookings(8);

        $title    = 'Dashboard — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/dashboard.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }
}
