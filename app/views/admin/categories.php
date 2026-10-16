<?php
/**
 * @var array<int, array<string, mixed>> $categories each with id, name, slug, sort_order, modality_count, active_count
 */
$fieldClass = 'w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500';
?>
<div class="w-full max-w-4xl mx-auto">
    <div class="mb-6">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-[11px] font-bold tracking-wider uppercase mb-1.5">
            Catalogue (<?= count($categories) ?> categories)
        </div>
        <h2 class="text-2xl font-extrabold text-slate-900 font-heading tracking-tight">Modality Categories</h2>
        <p class="text-xs text-slate-600 mt-0.5">
            Categories are the filter buttons customers see on the home page and the modalities page. Each modality belongs to one category.
            A category appears for customers once it has at least one active modality.
        </p>
    </div>

    <!-- Add a category -->
    <form method="POST" action="<?= app_url('admin/categories') ?>" class="bg-white border border-slate-200/90 rounded-2xl shadow-xs p-4 sm:p-5 mb-6" data-testid="category-create-form">
        <?= \App\Helpers\Csrf::field() ?>
        <h3 class="text-sm font-extrabold text-slate-900 font-heading mb-3">Add a category</h3>
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_8rem_auto] gap-3 sm:items-end">
            <div>
                <label for="new_category_name" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Name</label>
                <input type="text" id="new_category_name" name="name" required minlength="2" maxlength="<?= (int) \App\Controllers\AdminCategoryController::MAX_NAME ?>" placeholder="e.g. Compression Therapy" class="<?= $fieldClass ?>">
            </div>
            <div>
                <label for="new_category_order" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Order</label>
                <input type="number" id="new_category_order" name="sort_order" min="0" max="9999" placeholder="Last" class="<?= $fieldClass ?>">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm transition-all cursor-pointer">
                Add category
            </button>
        </div>
    </form>

    <!-- Existing categories -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs divide-y divide-slate-100" data-testid="category-list">
        <?php if (empty($categories)): ?>
            <p class="px-5 py-10 text-center text-sm text-slate-500">No categories yet. Add the first one above.</p>
        <?php endif; ?>
        <?php foreach ($categories as $c):
            $cid   = (int) $c['id'];
            $count = (int) $c['modality_count'];
        ?>
            <div class="p-4 sm:p-5" data-testid="category-row" data-name="<?= h($c['name']) ?>">
                <form method="POST" action="<?= app_url('admin/categories/' . $cid . '/update') ?>" class="grid grid-cols-1 sm:grid-cols-[1fr_8rem_auto] gap-3 sm:items-end">
                    <?= \App\Helpers\Csrf::field() ?>
                    <div>
                        <label for="category_name_<?= $cid ?>" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Name</label>
                        <input type="text" id="category_name_<?= $cid ?>" name="name" required minlength="2" maxlength="<?= (int) \App\Controllers\AdminCategoryController::MAX_NAME ?>" value="<?= h($c['name']) ?>" class="<?= $fieldClass ?>">
                    </div>
                    <div>
                        <label for="category_order_<?= $cid ?>" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Order</label>
                        <input type="number" id="category_order_<?= $cid ?>" name="sort_order" min="0" max="9999" value="<?= (int) $c['sort_order'] ?>" class="<?= $fieldClass ?>">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Save
                    </button>
                </form>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[12px] text-slate-600">
                        <strong class="text-slate-800"><?= $count ?></strong> modalit<?= $count === 1 ? 'y' : 'ies' ?>
                        <?php if ($count > 0): ?>(<?= (int) $c['active_count'] ?> active)<?php endif; ?>
                        <?php if ($count > 0 && (int) $c['active_count'] === 0): ?>
                            <span class="text-amber-700 font-semibold">&middot; hidden from customers until one is active</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($count === 0): ?>
                        <form method="POST" action="<?= app_url('admin/categories/' . $cid . '/delete') ?>" data-confirm="Delete the category &quot;<?= h($c['name']) ?>&quot;?">
                            <?= \App\Helpers\Csrf::field() ?>
                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer">
                                Delete
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-[11px] text-slate-500">To delete it, first move or remove its modalities.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
