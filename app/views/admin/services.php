<div class="w-full max-w-7xl mx-auto">
    <!-- Page Header & Modality Filter Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-[11px] font-bold tracking-wider uppercase mb-1.5">
                <svg class="w-3 h-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Facility Management (<?= count($services) ?> Modalities)
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 font-heading tracking-tight">Recovery Modalities Catalog</h2>
            <p class="text-xs text-slate-600 mt-0.5">
                Manage live pricing, athlete capacities, session durations, and activation status across all protocols.
            </p>
        </div>

        <!-- Search / Quick Filter Input -->
        <div class="w-full sm:w-auto flex items-center gap-2">
            <div class="relative w-full sm:w-64">
                <input 
                    type="text" 
                    id="service-search-input" 
                    data-oninput="filterServices" 
                    placeholder="Search modality..." 
                    class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-500 shadow-2xs"
                >
                <svg class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Modalities table: a real table from md up; on phones each row becomes a stacked card -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden md:overflow-x-auto" tabindex="0" role="region" aria-label="Modalities (scrolls sideways)">
        <table id="services-table" class="block md:table w-full text-left text-xs text-slate-700">
            <thead class="hidden md:table-header-group bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                <tr>
                    <th scope="col" class="py-3.5 px-4">Modality</th>
                    <th scope="col" class="py-3.5 px-3 whitespace-nowrap">Price (incl. GST)</th>
                    <th scope="col" class="py-3.5 px-3">Capacity</th>
                    <th scope="col" class="py-3.5 px-3">Session length</th>
                    <th scope="col" class="py-3.5 px-3">Status</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="block md:table-row-group divide-y divide-slate-100">
                <?php foreach ($services as $s):
                    $imgSrc   = service_image($s['image'] ?? null); // allow-listed + escaped
                    $isActive = ($s['status'] === 'active');
                    $money = static fn (float $v): string => number_format($v, $v == floor($v) ? 0 : 2);
                    $cell  = 'block md:table-cell before:content-[attr(data-label)] before:block before:mb-0.5 before:text-[10px] before:font-bold before:uppercase before:tracking-wider before:text-slate-500 md:before:hidden';
                ?>
                    <tr class="service-row grid grid-cols-2 gap-x-4 md:table-row px-4 py-4 md:p-0 hover:bg-slate-50/70 transition-colors"
                        data-name="<?= strtolower(h($s['name'])) ?>">

                        <td data-label="Modality" class="<?= $cell ?> order-1 col-span-2 py-1.5 md:py-3.5 md:px-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <img src="<?= $imgSrc ?>" alt="" width="64" height="48" loading="lazy"
                                     class="w-16 h-12 rounded-lg object-cover bg-slate-100 shrink-0 border border-slate-200">
                                <div class="min-w-0">
                                    <p class="font-heading font-extrabold text-sm text-slate-900 truncate md:max-w-[10rem]" title="<?= h($s['name']) ?>"><?= h($s['name']) ?></p>
                                    <?php if (!empty($s['description'])): ?>
                                        <p class="text-[11px] text-slate-500 line-clamp-1 max-w-xs md:hidden 2xl:block"><?= h($s['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td data-label="Price (incl. GST)" class="<?= $cell ?> order-2 py-1.5 md:py-3.5 md:px-3 md:whitespace-nowrap">
                            <span class="font-heading font-extrabold text-sm text-slate-900">&#8377;<?= $money((float) ($s['pricing']['total_amount'] ?? $s['price'])) ?></span>
                            <span class="block text-[10px] text-slate-500">GST &#8377;<?= number_format((float) ($s['pricing']['gst_amount'] ?? 0), 2) ?></span>
                        </td>

                        <td data-label="Capacity" class="<?= $cell ?> order-4 py-1.5 md:py-3.5 md:px-3 md:whitespace-nowrap">
                            <span class="font-semibold text-slate-800"><?= (int) $s['capacity'] ?></span>
                            <span class="text-slate-500">athlete<?= (int) $s['capacity'] === 1 ? '' : 's' ?></span>
                        </td>

                        <td data-label="Session length" class="<?= $cell ?> order-5 col-span-2 py-1.5 md:py-3.5 md:px-3">
                            <span class="font-semibold text-slate-800"><?= (int) $s['duration_minutes'] ?> min</span>
                            <?php if (!empty($s['duration_options']) && count($s['duration_options']) > 1): ?>
                                <span class="mt-1 flex flex-wrap gap-1">
                                    <?php foreach ($s['duration_options'] as $dOpt): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-sky-50 text-[10px] font-bold text-sky-800 border border-sky-200">
                                            <?= (int) $dOpt['duration_minutes'] ?>m<?php if (($s['pricing_type'] ?? '') !== 'pay_at_centre'): ?> &middot; &#8377;<?= $money((float) $dOpt['price']) ?><?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td data-label="Status" class="<?= $cell ?> order-3 py-1.5 md:py-3.5 md:px-3">
                            <?php if ($isActive): ?>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full border border-emerald-200 bg-emerald-50 text-emerald-800 text-[10px] font-bold uppercase tracking-wider">Active</span>
                            <?php else: ?>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full border border-rose-200 bg-rose-50 text-rose-800 text-[10px] font-bold uppercase tracking-wider">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td data-label="Actions" class="<?= $cell ?> order-6 col-span-2 py-1.5 md:py-3.5 md:px-4 md:text-right">
                            <div class="flex flex-wrap md:flex-nowrap items-center gap-1.5 md:justify-end">
                                <form method="POST" action="<?= app_url('admin/services/' . (int) $s['id'] . '/toggle') ?>">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <button type="submit" class="whitespace-nowrap py-1.5 px-3 rounded-lg border text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer <?= $isActive ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>">
                                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <button type="button"
                                        class="btn-durations-service whitespace-nowrap py-1.5 px-3 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= h($s['name']) ?>"
                                        data-pricing-type="<?= h($s['pricing_type'] ?? 'fixed') ?>"
                                        data-options='<?= json_encode($s['duration_options'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                                    Durations
                                </button>
                                <button type="button"
                                        class="btn-edit-service py-1.5 px-4 rounded-lg bg-[#053d63] hover:bg-[#075183] text-white text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= h($s['name']) ?>"
                                        data-price="<?= (float) $s['price'] ?>"
                                        data-capacity="<?= (int) $s['capacity'] ?>"
                                        data-description="<?= h($s['description']) ?>"
                                        data-status="<?= h($s['status']) ?>"
                                        data-image="<?= h($imgSrc) ?>">
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p id="services-no-match" class="hidden px-5 py-10 text-center text-sm text-slate-500" role="status">No modality matches your search.</p>
    </div>
</div>

<!-- Edit Service Specifications Modal -->
<div id="edit-service-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full shadow-2xl animate-scale-up max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 pb-4 border-b border-slate-100 sticky top-0 bg-white z-10">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 font-heading" id="modal-service-title">Edit Modality</h3>
                <p class="text-[11px] text-slate-500">Modify price, capacity, image, and status</p>
            </div>
            <button type="button" data-action="closeEditModal" aria-label="Close" class="text-slate-500 hover:text-slate-600 p-1 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="edit-service-form" method="POST" action="" enctype="multipart/form-data" class="p-5 space-y-3.5">
            <?= \App\Helpers\Csrf::field() ?>

            <!-- Current Image Preview -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-2">Current Image</label>
                <div class="relative rounded-xl overflow-hidden bg-slate-100 h-36" id="current-image-container">
                    <img 
                        id="modal-current-image"
                        src="" 
                        alt="Current modality image"
                        class="w-full h-full object-cover"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent"></div>
                    <span id="modal-no-image" class="hidden absolute inset-0 flex items-center justify-center text-slate-500 text-xs">No image set</span>
                </div>
            </div>

            <!-- Upload New Image -->
            <div>
                <label for="edit_image" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Upload New Image</label>
                <div class="relative">
                    <input 
                        type="file" 
                        id="edit_image" 
                        name="image" 
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 focus:bg-white focus:outline-none focus:border-sky-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-[#053d63] file:text-white file:cursor-pointer cursor-pointer"
                    >
                </div>
                <p class="text-[10px] text-slate-500 mt-1">JPG, PNG or WebP. Max 5MB. Leave blank to keep current image.</p>
                <!-- Live preview of selected new image -->
                <div id="new-image-preview-container" class="hidden mt-2 rounded-xl overflow-hidden h-28">
                    <img id="new-image-preview" src="" alt="New image preview" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Remove Current Image -->
            <div class="flex items-center gap-2">
                <input type="checkbox" id="edit_remove_image" name="remove_image" value="1" class="w-4 h-4 rounded border-slate-300 text-rose-600 cursor-pointer">
                <label for="edit_remove_image" class="text-[11px] font-medium text-rose-600 cursor-pointer">Remove current image (reset to default)</label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="edit_price" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Price incl. GST (&#8377;)</label>
                    <input 
                        type="number" 
                        id="edit_price" 
                        name="price" 
                        step="0.01" 
                        min="<?= number_format(\App\Controllers\AdminServiceController::MIN_PRICE, 2, '.', '') ?>" 
                        max="<?= number_format(\App\Controllers\AdminServiceController::MAX_PRICE, 2, '.', '') ?>" 
                        required 
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                </div>

                <div>
                    <label for="edit_capacity" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Max Athletes / Slot</label>
                    <input 
                        type="number" 
                        id="edit_capacity" 
                        name="capacity" 
                        min="1" 
                        max="<?= (int) \App\Controllers\AdminServiceController::MAX_CAPACITY ?>" 
                        required 
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                </div>
            </div>

            <div>
                <label for="edit_description" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Description</label>
                <textarea 
                    id="edit_description" 
                    name="description" 
                    rows="2" 
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                ></textarea>
            </div>

            <div>
                <label for="edit_status" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Active Status</label>
                <select 
                    id="edit_status" 
                    name="status" 
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                >
                    <option value="active">Active (Available for booking)</option>
                    <option value="inactive">Inactive (Hidden from catalog)</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button 
                    type="button" 
                    data-action="closeEditModal" 
                    class="px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-4 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm transition-all cursor-pointer"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Duration Options Management Modal -->
<div id="durations-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full shadow-2xl animate-scale-up max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-5 pb-4 border-b border-slate-100 sticky top-0 bg-white z-10">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 font-heading" id="durations-modal-title">Session Durations</h3>
                <p class="text-[11px] text-slate-500" id="durations-modal-subtitle">Manage session lengths and pricing tiers</p>
            </div>
            <button type="button" id="close-durations-modal-btn" aria-label="Close" class="text-slate-500 hover:text-slate-600 p-1 rounded-lg cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-5 space-y-5">
            <!-- Active Options List -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">Configured Durations</h4>
                <div id="durations-list" class="space-y-2">
                    <!-- Populated via JS -->
                </div>
                <div id="durations-empty" class="hidden text-center py-4 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                    No custom durations configured. Default service duration is used.
                </div>
            </div>

            <!-- Add Option Form -->
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">Add Duration Option</h4>
                <form id="add-duration-form" method="POST" action="" class="space-y-3">
                    <?= \App\Helpers\Csrf::field() ?>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="new_duration_minutes" class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Duration (Mins)</label>
                            <input 
                                type="number" 
                                id="new_duration_minutes" 
                                name="duration_minutes" 
                                required 
                                min="5" 
                                max="360" 
                                placeholder="e.g. 45" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:bg-white focus:outline-none focus:border-sky-500"
                            >
                        </div>
                        <div id="duration-price-field">
                            <label for="new_duration_price" class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Price incl. GST (₹)</label>
                            <input 
                                type="number" 
                                id="new_duration_price" 
                                name="price" 
                                step="0.01" 
                                min="0" 
                                placeholder="e.g. 199.00" 
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:bg-white focus:outline-none focus:border-sky-500"
                            >
                        </div>
                    </div>
                    <div>
                        <label for="new_duration_label" class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Display Label (Optional)</label>
                        <input 
                            type="text" 
                            id="new_duration_label" 
                            name="label" 
                            placeholder="e.g. Extended 45 Min Session" 
                            class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:bg-white focus:outline-none focus:border-sky-500"
                        >
                    </div>
                    <button 
                        type="submit" 
                        class="w-full py-2.5 px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer"
                    >
                        + Add Duration Option
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
function filterServices() {
    const query = document.getElementById('service-search-input').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.service-row');
    let visible = 0;
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const show = !query || name.includes(query);
        row.classList.toggle('hidden', !show);
        if (show) { visible++; }
    });
    const none = document.getElementById('services-no-match');
    if (none) { none.classList.toggle('hidden', visible > 0); }
}

function openEditModal(id, name, price, capacity, description, status, imageSrc) {
    document.getElementById('modal-service-title').textContent = 'Edit ' + name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit-service-form').action = '<?= app_url('admin/services') ?>/' + id + '/update';

    // Image preview setup
    var imgEl = document.getElementById('modal-current-image');
    var noImgEl = document.getElementById('modal-no-image');
    if (imageSrc) {
        imgEl.src = imageSrc;
        imgEl.classList.remove('hidden');
        noImgEl.classList.add('hidden');
    } else {
        imgEl.classList.add('hidden');
        noImgEl.classList.remove('hidden');
    }

    // Reset file input and previews
    document.getElementById('edit_image').value = '';
    document.getElementById('edit_remove_image').checked = false;
    document.getElementById('new-image-preview-container').classList.add('hidden');
    document.getElementById('new-image-preview').src = '';

    document.getElementById('edit-service-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-service-modal').classList.add('hidden');
}

function openDurationsModal(id, name, pricingType, options) {
    document.getElementById('durations-modal-title').textContent = 'Durations: ' + name;
    document.getElementById('add-duration-form').action = '<?= app_url('admin/services') ?>/' + id + '/duration-options';
    
    const priceField = document.getElementById('duration-price-field');
    const priceInput = document.getElementById('new_duration_price');
    if (pricingType === 'pay_at_centre') {
        priceField.classList.add('hidden');
        priceInput.value = '0';
        priceInput.required = false;
    } else {
        priceField.classList.remove('hidden');
        priceInput.value = '';
        priceInput.required = true;
    }

    const listEl = document.getElementById('durations-list');
    const emptyEl = document.getElementById('durations-empty');
    listEl.innerHTML = '';

    if (!options || options.length === 0) {
        emptyEl.classList.remove('hidden');
    } else {
        emptyEl.classList.add('hidden');
        options.forEach(opt => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200';
            
            const left = document.createElement('div');
            const dur = document.createElement('div');
            dur.className = 'text-xs font-bold text-slate-800';
            dur.textContent = opt.duration_minutes + ' Minutes' + (opt.label ? ' • ' + opt.label : '');
            
            const price = document.createElement('div');
            price.className = 'text-[11px] text-slate-500';
            price.textContent = (pricingType === 'pay_at_centre') 
                ? 'Assessed & Paid at Centre' 
                : 'Price (incl. GST): ₹' + parseFloat(opt.price || 0).toFixed(2);
            
            left.appendChild(dur);
            left.appendChild(price);

            const delForm = document.createElement('form');
            delForm.method = 'POST';
            delForm.action = '<?= app_url('admin/services/duration-options') ?>/' + opt.id + '/delete';
            delForm.className = 'inline';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_csrf_token';
            csrf.value = '<?= \App\Helpers\Csrf::token() ?>';

            const delBtn = document.createElement('button');
            delBtn.type = 'submit';
            delBtn.className = 'px-2 py-1 rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 text-[10px] font-bold uppercase transition-colors cursor-pointer';
            delBtn.textContent = 'Remove';
            delBtn.onclick = function(e) {
                if (!confirm('Remove ' + opt.duration_minutes + 'm duration option?')) {
                    e.preventDefault();
                }
            };

            delForm.appendChild(csrf);
            delForm.appendChild(delBtn);

            item.appendChild(left);
            item.appendChild(delForm);
            listEl.appendChild(item);
        });
    }

    document.getElementById('durations-modal').classList.remove('hidden');
}

function closeDurationsModal() {
    document.getElementById('durations-modal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-service').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var price = this.getAttribute('data-price');
            var capacity = this.getAttribute('data-capacity');
            var description = this.getAttribute('data-description');
            var status = this.getAttribute('data-status');
            var image = this.getAttribute('data-image') || '';
            openEditModal(id, name, price, capacity, description, status, image);
        });
    });

    document.querySelectorAll('.btn-durations-service').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var pricingType = this.getAttribute('data-pricing-type');
            var options = [];
            try {
                options = JSON.parse(this.getAttribute('data-options') || '[]');
            } catch (e) {
                options = [];
            }
            openDurationsModal(id, name, pricingType, options);
        });
    });

    const closeDurationsBtn = document.getElementById('close-durations-modal-btn');
    if (closeDurationsBtn) {
        closeDurationsBtn.addEventListener('click', closeDurationsModal);
    }

    // Live preview of newly selected image
    document.getElementById('edit_image').addEventListener('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('new-image-preview').src = e.target.result;
                document.getElementById('new-image-preview-container').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
            // Uncheck remove if user picked a new image
            document.getElementById('edit_remove_image').checked = false;
        } else {
            document.getElementById('new-image-preview-container').classList.add('hidden');
        }
    });

    // If remove is checked, hide new image preview
    document.getElementById('edit_remove_image').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('edit_image').value = '';
            document.getElementById('new-image-preview-container').classList.add('hidden');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeDurationsModal();
    }
});
</script>
