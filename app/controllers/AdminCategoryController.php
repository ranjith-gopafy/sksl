<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Flash;
use App\Middleware\AdminAuth;
use App\Models\ServiceCategoryModel;

/**
 * Admin Category Controller
 *
 * Manages the groups that recovery modalities belong to (the filter buttons on the home page and the
 * modalities page): add, rename and reorder, and delete a group once it holds no modalities.
 */
class AdminCategoryController
{
    public const MAX_NAME = 60;

    private ServiceCategoryModel $categories;

    public function __construct(?ServiceCategoryModel $categories = null)
    {
        $this->categories = $categories ?? new ServiceCategoryModel();
    }

    /** GET /admin/categories */
    public function index(): void
    {
        AdminAuth::handle();

        $categories = $this->categories->getAll();

        $title = 'Modality Categories — SKSL Admin';
        $viewFile = dirname(__DIR__) . '/views/admin/categories.php';
        require dirname(__DIR__) . '/views/layouts/admin.php';
    }

    /** POST /admin/categories */
    public function create(): void
    {
        AdminAuth::handle();

        $name = $this->cleanName();
        $error = $this->nameError($name, null);
        if ($error !== null) {
            $this->back('error', $error);
        }

        $order = $this->orderFromInput($this->categories->nextSortOrder());
        $this->categories->create($name, $order);
        $this->back('success', "Added category \"{$name}\". Now choose it when you add or edit a modality.");
    }

    /**
     * POST /admin/categories/{id}/update
     *
     * @param array<string, string> $params
     */
    public function update(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? 0);
        $category = $this->categories->findById($id);
        if (!$category) {
            $this->back('error', 'Category not found.');
        }

        $name = $this->cleanName();
        $error = $this->nameError($name, $id);
        if ($error !== null) {
            $this->back('error', $error);
        }

        $this->categories->update($id, $name, $this->orderFromInput((int) $category['sort_order']));
        $this->back('success', "Saved category \"{$name}\".");
    }

    /**
     * POST /admin/categories/{id}/delete
     * Only an empty category can be deleted, so no modality is ever left without its group by accident.
     *
     * @param array<string, string> $params
     */
    public function delete(array $params = []): void
    {
        AdminAuth::handle();

        $id = (int) ($params['id'] ?? 0);
        $category = $this->categories->findById($id);
        if (!$category) {
            $this->back('error', 'Category not found.');
        }

        $count = $this->categories->modalityCount($id);
        if ($count > 0) {
            $this->back('error', sprintf(
                '"%s" still has %d modalit%s. Move %s to another category (or delete %s) first, then delete the category.',
                $category['name'], $count, $count === 1 ? 'y' : 'ies', $count === 1 ? 'it' : 'them', $count === 1 ? 'it' : 'them'
            ));
        }

        try {
            $this->categories->delete($id);
        } catch (\PDOException $e) {
            $this->back('error', "\"{$category['name']}\" is still in use and could not be deleted.");
        }
        $this->back('success', "Deleted category \"{$category['name']}\".");
    }

    private function cleanName(): string
    {
        // collapse inner whitespace so "Cold   Cryo" and "Cold Cryo" count as the same name
        return trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')));
    }

    private function nameError(string $name, ?int $exceptId): ?string
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > self::MAX_NAME) {
            return 'Category name must be 2 to ' . self::MAX_NAME . ' characters.';
        }
        if (!ServiceCategoryModel::isValidName($name)) {
            return 'Category name: ' . ServiceCategoryModel::NAME_RULE;
        }
        $existing = $this->categories->findByName($name);
        if ($existing !== null && (int) $existing['id'] !== $exceptId) {
            return "There is already a category called \"{$name}\".";
        }
        return null;
    }

    private function orderFromInput(int $default): int
    {
        $raw = trim((string) ($_POST['sort_order'] ?? ''));
        if ($raw === '') {
            return $default;
        }
        return ctype_digit($raw) && (int) $raw <= 9999 ? (int) $raw : $default;
    }

    private function back(string $type, string $message): never
    {
        Flash::set($type, $message);
        header('Location: ' . app_url('admin/categories'));
        exit;
    }
}
