<?php
/**
 * SKSL Admin — Combo Packages & Bundles Management
 */
?>

<div class="w-full max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Create Package Form -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h2 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    Create Combo Package
                </h2>
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    Bundle multiple recovery modalities into a prepaid combo package with allocated session credits and validity.
                </p>

                <form method="POST" action="<?= app_url('admin/packages') ?>" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="f_name_1" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Package Title</label>
                        <input id="f_name_1" type="text" name="name" required placeholder="e.g. Total Athlete Recovery Bundle" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <div>
                        <label for="f_description_2" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Description</label>
                        <textarea id="f_description_2" name="description" rows="2" placeholder="Brief summary of included benefits..." class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="f_price_3" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Price incl. GST (₹)</label>
                            <input id="f_price_3" type="number" step="0.01" name="price" required placeholder="499" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                        <div>
                            <label for="f_validity_days_4" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Validity (Days)</label>
                            <input id="f_validity_days_4" type="number" name="validity_days" value="30" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                    </div>

                    <div>
                        <label for="f_badge_text_5" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Badge Text (Optional)</label>
                        <input id="f_badge_text_5" type="text" name="badge_text" placeholder="e.g. Most Popular / Best Value" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <!-- Modality Items Allocator -->
                    <div class="pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-700">Included Modalities</label>
                            <button type="button" id="add-item-row-btn" class="text-[11px] font-bold text-[#075183] hover:underline cursor-pointer">
                                + Add Modality
                            </button>
                        </div>
                        <div id="items-container" class="space-y-2">
                            <div class="flex items-center gap-2 item-row">
                                <select name="service_id[]" aria-label="Modality" required class="flex-1 text-xs rounded-xl border border-slate-300 py-2 px-2.5 bg-white font-medium">
                                    <?php foreach ($services as $srv): ?>
                                        <option value="<?= (int) $srv['id'] ?>"><?= h($srv['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="quantity[]" value="2" min="1" max="50" required class="w-16 text-xs rounded-xl border border-slate-300 py-2 px-2 text-center bg-white font-bold" title="Quantity">
                                <input type="number" name="duration_minutes[]" min="5" max="360" placeholder="min" class="w-16 text-xs rounded-xl border border-slate-300 py-2 px-2 text-center bg-white font-medium" title="Session length in minutes (blank = normal length)">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-md">
                        Publish Combo Package
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Packages List -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 font-heading">
                            Active Packages &amp; Bundles
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Purchased packages grant individual credits tracked in customer balances.
                        </p>
                    </div>
                    <span class="text-xs font-bold bg-slate-100 text-slate-700 px-3 py-1 rounded-full">
                        <?= count($packages) ?> Packages Configured
                    </span>
                </div>

                <?php if (empty($packages)): ?>
                    <div class="text-center py-12 text-slate-500 text-xs">
                        No packages configured yet. Use the form on the left to publish your first recovery bundle.
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($packages as $pkg): 
                            $items = $pkg['items'] ?? [];
                            $isActive = $pkg['status'] === 'active';
                        ?>
                            <div class="p-5 rounded-2xl border border-slate-200 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?= $isActive ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                            <?= ucfirst($pkg['status']) ?>
                                        </span>
                                        <span class="text-xs font-bold text-slate-600">
                                            <?= (int) $pkg['validity_days'] ?> Days Validity
                                        </span>
                                        <?php if (!empty($pkg['badge_text'])): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-900">
                                                <?= h($pkg['badge_text']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="text-base font-extrabold text-slate-900 font-heading">
                                        <?= h($pkg['name']) ?>
                                    </h3>

                                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <?php foreach ($items as $it): ?>
                                            <span class="px-2 py-0.5 rounded-lg text-[11px] font-medium bg-slate-50 border border-slate-200 text-slate-700">
                                                <?= h($it['service_name']) ?> &times; <?= (int) $it['quantity'] ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="flex sm:flex-col items-center sm:items-end justify-between gap-3 shrink-0 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                    <div class="text-right">
                                        <div class="text-lg font-black text-slate-900 font-heading">&#8377;<?= number_format((float) $pkg['total_price'], 2) ?></div>
                                        <div class="text-[10px] text-slate-500 font-semibold">incl. GST</div>
                                    </div>

                                    <form method="POST" action="<?= app_url('admin/packages/' . (int) $pkg['id'] . '/toggle') ?>">
                                        <?= \App\Helpers\Csrf::field() ?>
                                        <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider <?= $isActive ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-700 hover:bg-emerald-800 text-white' ?> transition-colors">
                                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('add-item-row-btn');
    const container = document.getElementById('items-container');

    addBtn.addEventListener('click', () => {
        const firstRow = container.querySelector('.item-row');
        if (firstRow) {
            const clone = firstRow.cloneNode(true);
            container.appendChild(clone);
        }
    });
});
</script>
