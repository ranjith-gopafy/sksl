<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Admin Navigation Header -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold font-heading text-xs shadow-xs">
                AD
            </div>
            <div>
                <h1 class="text-sm font-extrabold text-slate-900 font-heading">SKSL Control Center</h1>
                <p class="text-[11px] text-slate-500">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-1.5 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3 py-1.5 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Hero Banners
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3 py-1.5 rounded-lg text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

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
            <p class="text-xs text-slate-500 mt-0.5">
                Manage live pricing, athlete capacities, session durations, and activation status across all protocols.
            </p>
        </div>

        <!-- Search / Quick Filter Input -->
        <div class="w-full sm:w-auto flex items-center gap-2">
            <div class="relative w-full sm:w-64">
                <input 
                    type="text" 
                    id="service-search-input" 
                    oninput="filterServices()" 
                    placeholder="Search modality..." 
                    class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-none focus:border-sky-500 shadow-2xs"
                >
                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Modalities Responsive Grid: Optimized for Desktop and Mobile Viewports -->
    <div id="services-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($services as $s): 
            $imgSrc = str_starts_with($s['image'] ?? '', 'images/') || str_starts_with($s['image'] ?? '', 'uploads/')
                ? asset($s['image'])
                : asset('images/services/' . ($s['image'] ?? 'spa.jpg'));
            $isActive = ($s['status'] === 'active');
        ?>
            <div 
                class="service-card bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-2xs hover:shadow-sm hover:border-sky-300 transition-all flex flex-col justify-between"
                data-name="<?= strtolower(h($s['name'])) ?>"
            >
                <!-- MOBILE VIEW: Compact Horizontal App Card (< 768px) -->
                <div class="flex md:hidden items-center p-3 gap-3.5">
                    <!-- Left: Compact Image Thumbnail -->
                    <div class="relative w-24 h-24 rounded-xl overflow-hidden bg-slate-100 shrink-0">
                        <img 
                            src="<?= $imgSrc ?>" 
                            alt="<?= h($s['name']) ?>" 
                            class="w-full h-full object-cover"
                            loading="lazy"
                        >
                        <!-- Status Badge on Thumbnail -->
                        <div class="absolute bottom-1 left-1">
                            <?php if ($isActive): ?>
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-emerald-500 text-white">Active</span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-bold bg-rose-500 text-white">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Details + Actions -->
                    <div class="flex-1 min-w-0 flex flex-col justify-between self-stretch py-0.5">
                        <div>
                            <!-- Modality badge -->
                            <div class="border-l-2 border-[#053d63] pl-2 leading-none mb-1">
                                <div class="font-heading font-extrabold text-[10px] tracking-wider text-[#053d63] uppercase">SKSL Admin</div>
                            </div>
                            <h3 class="text-sm font-extrabold font-heading text-slate-900 leading-snug truncate"><?= h($s['name']) ?></h3>
                            <div class="text-[11px] text-slate-500 font-medium"><?= (int) $s['duration_minutes'] ?>m &bull; Max <?= (int) $s['capacity'] ?> athletes</div>
                        </div>

                        <!-- Price + Actions -->
                        <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 mt-1">
                            <div>
                                <div class="text-[8px] uppercase tracking-wider font-bold text-slate-400">Base Rate</div>
                                <div class="text-sm font-extrabold text-slate-900 font-heading -mt-0.5">&#8377;<?= number_format((float) $s['price'], 2) ?></div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <form method="POST" action="<?= app_url('admin/services/' . (int) $s['id'] . '/toggle') ?>">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold uppercase cursor-pointer <?= $isActive ? 'border-rose-200 text-rose-700' : 'border-emerald-200 text-emerald-700' ?>">
                                        <?= $isActive ? 'Off' : 'On' ?>
                                    </button>
                                </form>
                                <button 
                                    type="button" 
                                    class="btn-edit-service px-2.5 py-1.5 rounded-lg bg-[#053d63] text-white text-[10px] font-bold uppercase cursor-pointer"
                                    data-id="<?= (int) $s['id'] ?>"
                                    data-name="<?= h($s['name']) ?>"
                                    data-price="<?= (float) $s['price'] ?>"
                                    data-capacity="<?= (int) $s['capacity'] ?>"
                                    data-description="<?= h($s['description']) ?>"
                                    data-status="<?= h($s['status']) ?>"
                                    data-image="<?= h($imgSrc) ?>"
                                >
                                    Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DESKTOP VIEW: Full Card (>= 768px) -->
                <div class="hidden md:flex md:flex-col justify-between h-full">
                    <div>
                        <!-- Desktop Thumbnail -->
                        <div class="relative w-full h-32 sm:h-36 overflow-hidden bg-slate-900 group">
                            <img 
                                src="<?= $imgSrc ?>" 
                                alt="<?= h($s['name']) ?>" 
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-black/20 to-transparent"></div>
                            
                            <!-- Badges -->
                            <div class="absolute top-2.5 left-2.5">
                                <span class="text-[10px] font-bold font-mono bg-black/60 backdrop-blur-md text-white px-2 py-0.5 rounded-md border border-white/10">
                                    #MOD-0<?= (int) $s['id'] ?>
                                </span>
                            </div>
                            <div class="absolute top-2.5 right-2.5">
                                <?php if ($isActive): ?>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-500 text-white shadow-xs">Active</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-rose-500 text-white shadow-xs">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="absolute bottom-2.5 left-2.5 right-2.5">
                                <h3 class="text-sm sm:text-base font-extrabold text-white font-heading truncate drop-shadow"><?= h($s['name']) ?></h3>
                            </div>
                        </div>

                        <!-- Details Body -->
                        <div class="p-3.5 sm:p-4">
                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed mb-3"><?= h($s['description']) ?></p>
                            <div class="bg-slate-50/80 rounded-xl p-2.5 border border-slate-200/70 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Duration &amp; Capacity:</span>
                                    <span class="font-bold text-slate-800"><?= (int) $s['duration_minutes'] ?>m &bull; <span class="text-sky-700"><?= (int) $s['capacity'] ?> athletes</span></span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Base Rate:</span>
                                    <span class="font-semibold text-slate-800">&#8377;<?= number_format((float) $s['price'], 2) ?></span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">GST (18%):</span>
                                    <span class="text-slate-500">&#8377;<?= number_format((float) ($s['pricing']['gst_amount'] ?? 0), 2) ?></span>
                                </div>
                                <div class="pt-1.5 border-t border-slate-200/80 flex items-center justify-between font-heading">
                                    <span class="text-[11px] font-bold text-slate-700">Total Customer Price:</span>
                                    <span class="text-xs font-extrabold text-emerald-600">&#8377;<?= number_format((float) ($s['pricing']['total_amount'] ?? $s['price']), 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Controls: Compact Bottom Bar -->
                    <div class="px-3.5 sm:px-4 py-2.5 bg-slate-50/50 border-t border-slate-100 flex items-center gap-2">
                        <form method="POST" action="<?= app_url('admin/services/' . (int) $s['id'] . '/toggle') ?>" class="flex-1">
                            <?= \App\Helpers\Csrf::field() ?>
                            <button 
                                type="submit" 
                                class="w-full py-1.5 px-2.5 rounded-lg border text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer <?= $isActive ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>"
                            >
                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <button 
                            type="button" 
                            class="btn-edit-service py-1.5 px-4 rounded-lg bg-[#053d63] hover:bg-[#075183] text-white text-[11px] font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-2xs"
                            data-id="<?= (int) $s['id'] ?>"
                            data-name="<?= h($s['name']) ?>"
                            data-price="<?= (float) $s['price'] ?>"
                            data-capacity="<?= (int) $s['capacity'] ?>"
                            data-description="<?= h($s['description']) ?>"
                            data-status="<?= h($s['status']) ?>"
                            data-image="<?= h($imgSrc) ?>"
                        >
                            Edit
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
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
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
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
                    <span id="modal-no-image" class="hidden absolute inset-0 flex items-center justify-center text-slate-400 text-xs">No image set</span>
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
                <p class="text-[10px] text-slate-400 mt-1">JPG, PNG or WebP. Max 5MB. Leave blank to keep current image.</p>
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
                    <label for="edit_price" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Base Price (&#8377;)</label>
                    <input 
                        type="number" 
                        id="edit_price" 
                        name="price" 
                        step="0.01" 
                        min="0" 
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
                        max="50" 
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
                    onclick="closeEditModal()" 
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

<script>
function filterServices() {
    const query = document.getElementById('service-search-input').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.service-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name') || '';
        if (!query || name.includes(query)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
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
    }
});
</script>
