<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\HeroBannerModel;
use App\Middleware\AdminAuth;
use App\Helpers\Flash;

/**
 * Admin Banner Controller
 *
 * Controls the hero section banner content, call-to-actions, and active visibility.
 */
class AdminBannerController
{
    private HeroBannerModel $bannerModel;

    public function __construct(?HeroBannerModel $bannerModel = null)
    {
        $this->bannerModel = $bannerModel ?? new HeroBannerModel();
    }

    /**
     * GET /admin/banner
     * Display the hero banner manager.
     */
    public function index(): void
    {
        AdminAuth::handle();

        $banner = $this->bannerModel->get();

        $title = 'Manage Hero Banner — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/banner.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/banner
     * Update hero banner details.
     */
    public function update(): void
    {
        AdminAuth::handle();

        $badgeText   = trim((string) ($_POST['badge_text'] ?? ''));
        $headline    = trim((string) ($_POST['headline'] ?? ''));
        $subheadline = trim((string) ($_POST['subheadline'] ?? ''));
        $imageUrl    = trim((string) ($_POST['image_url'] ?? 'images/hero-banner.jpg'));
        $ctaText     = trim((string) ($_POST['cta_text'] ?? ''));
        $ctaLink     = trim((string) ($_POST['cta_link'] ?? '/booking'));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($headline === '') {
            Flash::set('error', 'Headline is required.');
            header('Location: ' . app_url('admin/banner'));
            exit;
        }

        $success = $this->bannerModel->update([
            'badge_text'  => $badgeText,
            'headline'    => $headline,
            'subheadline' => $subheadline,
            'image_url'   => $imageUrl,
            'cta_text'    => $ctaText ?: 'Reserve Recovery Session',
            'cta_link'    => $ctaLink ?: '/booking',
            'is_active'   => $isActive,
        ]);

        if ($success) {
            Flash::set('success', 'Hero banner settings updated successfully. Check the homepage to see live changes.');
        } else {
            Flash::set('error', 'Failed to update hero banner.');
        }

        header('Location: ' . app_url('admin/banner'));
        exit;
    }
}
