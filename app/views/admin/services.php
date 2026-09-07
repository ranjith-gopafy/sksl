<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Admin Navigation Header -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold font-heading">
                AD
            </div>
            <div>
                <h1 class="text-base font-bold text-white font-heading">SKSL Control Center</h1>
                <p class="text-xs text-slate-400">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-2 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold uppercase tracking-wider">
                Modalities
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-400 hover:text-rose-300 hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <div class="mb-8">
        <h2 class="text-2xl font-extrabold text-white font-heading">Recovery Modalities Catalog</h2>
        <p class="text-xs text-slate-400 mt-1">
            Manage live pricing, athlete capacities per slot, session durations, and active status across all 10 approved recovery modalities.
        </p>
    </div>

    <!-- Modalities Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($services as $s): ?>
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between transition-all hover:border-slate-700/80">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider font-mono text-cyan-400">
                            #MOD-0<?= (int) $s['id'] ?>
                        </span>
                        <?php if ($s['status'] === 'active'): ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                                Active
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/10 border border-rose-500/30 text-rose-400">
                                Inactive
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-xl font-bold text-white font-heading">
                        <?= h($s['name']) ?>
                    </h3>
                    <p class="text-xs text-slate-400 mt-1 line-clamp-2">
                        <?= h($s['description']) ?>
                    </p>

                    <!-- Modality Specs -->
                    <div class="bg-slate-950 rounded-2xl p-4 my-4 border border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Session Duration:</span>
                            <span class="font-bold text-slate-200"><?= (int) $s['duration_minutes'] ?> Minutes</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Max Capacity:</span>
                            <span class="font-bold text-cyan-400"><?= (int) $s['capacity'] ?> athletes / slot</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Base Price:</span>
                            <span class="text-slate-200">&#8377;<?= number_format((float) $s['price'], 2) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">GST (18%):</span>
                            <span class="text-slate-200">&#8377;<?= number_format((float) ($s['pricing']['gst_amount'] ?? 0), 2) ?></span>
                        </div>
                        <div class="pt-2 border-t border-slate-800/80 flex justify-between text-sm font-bold text-white font-heading">
                            <span>Total Payable:</span>
                            <span class="text-emerald-400">&#8377;<?= number_format((float) ($s['pricing']['total_amount'] ?? $s['price']), 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="pt-3 border-t border-slate-800/80 flex items-center gap-3">
                    <form method="POST" action="<?= app_url('admin/services/' . (int) $s['id'] . '/toggle') ?>" class="flex-1">
                        <?= \App\Helpers\Csrf::field() ?>
                        <button 
                            type="submit" 
                            class="w-full py-2 px-3 rounded-xl border text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer <?= $s['status'] === 'active' ? 'border-rose-500/30 hover:bg-rose-500/10 text-rose-400' : 'border-emerald-500/30 hover:bg-emerald-500/10 text-emerald-400' ?>"
                        >
                            <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>

                    <button 
                        type="button" 
                        onclick="openEditModal(<?= (int) $s['id'] ?>, '<?= h($s['name']) ?>', <?= (float) $s['price'] ?>, <?= (int) $s['capacity'] ?>, '<?= h($s['description']) ?>', '<?= h($s['status']) ?>')"
                        class="py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer"
                    >
                        Edit
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Service Specifications Modal -->
<div id="edit-service-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl">
        <h3 class="text-xl font-bold text-white font-heading mb-1" id="modal-service-title">Edit Modality</h3>
        <p class="text-xs text-slate-400 mb-6">Modify price and live athlete capacity per slot.</p>

        <form id="edit-service-form" method="POST" action="" class="space-y-4">
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="edit_price" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Base Price (&#8377;)</label>
                <input 
                    type="number" 
                    id="edit_price" 
                    name="price" 
                    step="0.01" 
                    min="0" 
                    required 
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500"
                >
            </div>

            <div>
                <label for="edit_capacity" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Max Athletes Per Slot</label>
                <input 
                    type="number" 
                    id="edit_capacity" 
                    name="capacity" 
                    min="1" 
                    max="50" 
                    required 
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500"
                >
            </div>

            <div>
                <label for="edit_description" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Description</label>
                <textarea 
                    id="edit_description" 
                    name="description" 
                    rows="3" 
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500"
                ></textarea>
            </div>

            <div>
                <label for="edit_status" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Status</label>
                <select 
                    id="edit_status" 
                    name="status" 
                    class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500"
                >
                    <option value="active">Active (Available for booking)</option>
                    <option value="inactive">Inactive (Hidden from catalog)</option>
                </select>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-800">
                <button 
                    type="submit" 
                    class="flex-1 py-3 px-4 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/20 transition-all cursor-pointer"
                >
                    Save Changes
                </button>
                <button 
                    type="button" 
                    onclick="closeEditModal()" 
                    class="py-3 px-4 rounded-xl border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white text-xs font-medium transition-colors cursor-pointer"
                >
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, price, capacity, desc, status) {
    document.getElementById('modal-service-title').textContent = 'Edit ' + name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_description').value = desc;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit-service-form').action = '<?= app_url('admin/services') ?>/' + id + '/update';
    document.getElementById('edit-service-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-service-modal').classList.add('hidden');
}
</script>
