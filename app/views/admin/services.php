<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Admin Navigation Header -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold font-heading text-sm shadow-xs">
                AD
            </div>
            <div>
                <h1 class="text-base font-extrabold text-slate-900 font-heading">SKSL Control Center</h1>
                <p class="text-xs text-slate-500">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-1.5 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Hero Banner
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <div class="mb-8">
        <h2 class="text-2xl font-extrabold text-slate-900 font-heading">Recovery Modalities Catalog</h2>
        <p class="text-xs text-slate-500 mt-1">
            Manage live pricing, athlete capacities per slot, session durations, and active status across all 10 approved recovery modalities.
        </p>
    </div>

    <!-- Modalities Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($services as $s): 
            $imgSrc = str_starts_with($s['image'] ?? '', 'images/') || str_starts_with($s['image'] ?? '', 'uploads/')
                ? asset($s['image'])
                : asset('images/services/' . ($s['image'] ?? 'spa.jpg'));
        ?>
            <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs flex flex-col justify-between transition-all hover:shadow-md hover:border-sky-300">
                <div>
                    <!-- Thumbnail preview -->
                    <div class="relative w-full h-40 overflow-hidden bg-slate-100">
                        <img src="<?= $imgSrc ?>" alt="<?= h($s['name']) ?>" class="w-full h-full object-cover">
                        <div class="absolute top-3 left-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider font-mono bg-white/95 text-slate-900 px-2 py-1 rounded-full shadow-xs">
                                #MOD-0<?= (int) $s['id'] ?>
                            </span>
                        </div>
                        <div class="absolute top-3 right-3">
                            <?php if ($s['status'] === 'active'): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500 text-white shadow-xs">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500 text-white shadow-xs">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-6">
                        <h3 class="text-xl font-extrabold text-slate-900 font-heading">
                            <?= h($s['name']) ?>
                        </h3>
                        <p class="text-xs text-slate-600 mt-1 line-clamp-2">
                            <?= h($s['description']) ?>
                        </p>

                        <!-- Modality Specs -->
                        <div class="bg-slate-50 rounded-2xl p-4 my-4 border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Session Duration:</span>
                                <span class="font-bold text-slate-800"><?= (int) $s['duration_minutes'] ?> Minutes</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Max Capacity:</span>
                                <span class="font-bold text-sky-700"><?= (int) $s['capacity'] ?> athletes / slot</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Base Price:</span>
                                <span class="text-slate-800 font-medium">&#8377;<?= number_format((float) $s['price'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">GST (18%):</span>
                                <span class="text-slate-800 font-medium">&#8377;<?= number_format((float) ($s['pricing']['gst_amount'] ?? 0), 2) ?></span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between text-sm font-bold text-slate-900 font-heading">
                                <span>Total Payable:</span>
                                <span class="text-emerald-600 font-extrabold">&#8377;<?= number_format((float) ($s['pricing']['total_amount'] ?? $s['price']), 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="px-6 pb-6 pt-2 border-t border-slate-100 flex items-center gap-3">
                    <form method="POST" action="<?= app_url('admin/services/' . (int) $s['id'] . '/toggle') ?>" class="flex-1">
                        <?= \App\Helpers\Csrf::field() ?>
                        <button 
                            type="submit" 
                            class="w-full py-2.5 px-3 rounded-xl border text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer <?= $s['status'] === 'active' ? 'border-rose-200 hover:bg-rose-50 text-rose-700' : 'border-emerald-200 hover:bg-emerald-50 text-emerald-700' ?>"
                        >
                            <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>

                    <button 
                        type="button" 
                        onclick="openEditModal(<?= (int) $s['id'] ?>, '<?= h($s['name']) ?>', <?= (float) $s['price'] ?>, <?= (int) $s['capacity'] ?>, '<?= h($s['description']) ?>', '<?= h($s['status']) ?>')"
                        class="py-2.5 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer border border-slate-200"
                    >
                        Edit
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Service Specifications Modal -->
<div id="edit-service-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <h3 class="text-xl font-bold text-slate-900 font-heading mb-1" id="modal-service-title">Edit Modality</h3>
        <p class="text-xs text-slate-500 mb-6">Modify price and live athlete capacity per slot.</p>

        <form id="edit-service-form" method="POST" action="" class="space-y-4">
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="edit_price" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Base Price (&#8377;)</label>
                <input 
                    type="number" 
                    id="edit_price" 
                    name="price" 
                    step="0.01" 
                    min="0" 
                    required 
                    class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <div>
                <label for="edit_capacity" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Max Athletes Per Slot</label>
                <input 
                    type="number" 
                    id="edit_capacity" 
                    name="capacity" 
                    min="1" 
                    max="50" 
                    required 
                    class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <div>
                <label for="edit_description" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea 
                    id="edit_description" 
                    name="description" 
                    rows="3" 
                    class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:bg-white focus:outline-none focus:border-sky-500"
                ></textarea>
            </div>

            <div>
                <label for="edit_status" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Status</label>
                <select 
                    id="edit_status" 
                    name="status" 
                    class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:bg-white focus:outline-none focus:border-sky-500"
                >
                    <option value="active">Active (Available for booking)</option>
                    <option value="inactive">Inactive (Hidden from catalog)</option>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button 
                    type="submit" 
                    class="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-sky-600/20 transition-all cursor-pointer"
                >
                    Save Changes
                </button>
                <button 
                    type="button" 
                    onclick="closeEditModal()" 
                    class="py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, price, capacity, description, status) {
    document.getElementById('modal-service-title').textContent = 'Edit ' + name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit-service-form').action = '<?= app_url('admin/services') ?>/' + id + '/update';
    document.getElementById('edit-service-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-service-modal').classList.add('hidden');
}
</script>
