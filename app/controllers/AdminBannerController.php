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
     * Display the hero banner manager with all banners (up to 5).
     */
    public function index(): void
    {
        AdminAuth::handle();

        $banners = $this->bannerModel->getAll();
        $totalCount = count($banners);
        
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $selectedBanner = null;
        if ($editId > 0) {
            $selectedBanner = $this->bannerModel->getById($editId);
        }
        if (!$selectedBanner && !empty($banners)) {
            $selectedBanner = $banners[0];
        }

        $title = 'Manage Hero Banners — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/banner.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * POST /admin/banner
     * Handle create, update, delete, or toggle for hero banners.
     */
    public function update(): void
    {
        AdminAuth::handle();

        $action = trim((string) ($_POST['action'] ?? 'update'));
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id <= 0) {
                Flash::set('error', 'Invalid banner ID.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            if ($this->bannerModel->count() <= 1) {
                Flash::set('error', 'Cannot delete the only remaining hero banner. At least one banner must exist.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            $deleted = $this->bannerModel->delete($id);
            if ($deleted) {
                Flash::set('success', 'Hero banner removed successfully.');
            } else {
                Flash::set('error', 'Failed to delete hero banner.');
            }
            header('Location: ' . app_url('admin/banner'));
            exit;
        }

        if ($action === 'toggle') {
            if ($id <= 0) {
                Flash::set('error', 'Invalid banner ID.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            $banner = $this->bannerModel->getById($id);
            if (!$banner) {
                Flash::set('error', 'Banner not found.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            $newStatus = empty($banner['is_active']) ? 1 : 0;
            $this->bannerModel->updateById($id, array_merge($banner, ['is_active' => $newStatus]));
            Flash::set('success', 'Banner status updated to ' . ($newStatus ? 'Active' : 'Inactive') . '.');
            header('Location: ' . app_url('admin/banner?edit=' . $id));
            exit;
        }

        if ($action === 'create') {
            if ($this->bannerModel->count() >= 5) {
                Flash::set('error', 'Maximum limit of 5 hero banners reached. Please edit or delete existing banners.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            $badgeText   = trim((string) ($_POST['badge_text'] ?? 'Sports Science & High-Performance Lab'));
            $headline    = trim((string) ($_POST['headline'] ?? ''));
            $subheadline = trim((string) ($_POST['subheadline'] ?? ''));
            $imageUrl    = trim((string) ($_POST['image_url'] ?? 'images/hero-banner.jpg'));
            $ctaText     = trim((string) ($_POST['cta_text'] ?? 'Reserve Recovery Session'));
            $ctaLink     = trim((string) ($_POST['cta_link'] ?? '/booking'));
            $isActive    = isset($_POST['is_active']) ? 1 : 0;

            if ($headline === '') {
                Flash::set('error', 'Headline is required to create a banner.');
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            if (($problem = $this->validateMedia($imageUrl, $ctaLink)) !== null) {
                Flash::set('error', $problem);
                header('Location: ' . app_url('admin/banner'));
                exit;
            }

            try {
                $newId = $this->bannerModel->create([
                    'badge_text'  => $badgeText,
                    'headline'    => $headline,
                    'subheadline' => $subheadline,
                    'image_url'   => $imageUrl,
                    'cta_text'    => $ctaText,
                    'cta_link'    => $ctaLink,
                    'is_active'   => $isActive,
                ]);
                Flash::set('success', 'New hero banner added successfully (ID #' . $newId . ').');
                header('Location: ' . app_url('admin/banner?edit=' . $newId));
                exit;
            } catch (\Throwable $e) {
                Flash::set('error', 'Error creating banner: ' . $e->getMessage());
                header('Location: ' . app_url('admin/banner'));
                exit;
            }
        }

        // Default: update existing banner
        $badgeText   = trim((string) ($_POST['badge_text'] ?? ''));
        $headline    = trim((string) ($_POST['headline'] ?? ''));
        $subheadline = trim((string) ($_POST['subheadline'] ?? ''));
        $imageUrl    = trim((string) ($_POST['image_url'] ?? 'images/hero-banner.jpg'));
        $ctaText     = trim((string) ($_POST['cta_text'] ?? ''));
        $ctaLink     = trim((string) ($_POST['cta_link'] ?? '/booking'));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($headline === '') {
            Flash::set('error', 'Headline is required.');
            header('Location: ' . app_url('admin/banner' . ($id > 0 ? '?edit=' . $id : '')));
            exit;
        }

        if (($problem = $this->validateMedia($imageUrl, $ctaLink)) !== null) {
            Flash::set('error', $problem);
            header('Location: ' . app_url('admin/banner' . ($id > 0 ? '?edit=' . $id : '')));
            exit;
        }

        if ($id <= 0) {
            // Fallback: pick first banner ID if none specified
            $first = $this->bannerModel->getAll();
            $id = !empty($first) ? (int) $first[0]['id'] : 1;
        }

        $success = $this->bannerModel->updateById($id, [
            'badge_text'  => $badgeText,
            'headline'    => $headline,
            'subheadline' => $subheadline,
            'image_url'   => $imageUrl,
            'cta_text'    => $ctaText ?: 'Reserve Recovery Session',
            'cta_link'    => $ctaLink ?: '/booking',
            'is_active'   => $isActive,
        ]);

        if ($success) {
            Flash::set('success', 'Hero banner #' . $id . ' updated successfully. Changes are live on the homepage carousel.');
        } else {
            Flash::set('error', 'Failed to update hero banner.');
        }

        header('Location: ' . app_url('admin/banner?edit=' . $id));
        exit;
    }

    /**
     * Allow-list the banner image and CTA link before they reach the database
     * (audit H5). Images must be bundled/uploaded files that exist on disk (or
     * an https URL); links must be site-relative or http(s) — never javascript:.
     */
    private function validateMedia(string $imageUrl, string $ctaLink): ?string
    {
        if (!is_safe_image_path($imageUrl)) {
            return 'Image must be a JPG/PNG/WebP under images/ or uploads/ (e.g. images/hero-banner.jpg) or an https:// image URL.';
        }
        if (!str_starts_with($imageUrl, 'https://')) {
            $onDisk = dirname(__DIR__, 2) . '/public/' . $imageUrl;
            if (!is_file($onDisk)) {
                return 'Image file "' . $imageUrl . '" was not found in the public folder.';
            }
        }
        if (!is_safe_link($ctaLink)) {
            return 'Button link must be a site path like /booking or /services, or a full http(s):// URL.';
        }
        return null;
    }
}
