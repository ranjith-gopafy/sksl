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
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold uppercase tracking-wider">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
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

    <!-- Filters Card -->
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 mb-8 shadow-lg">
        <form method="GET" action="<?= app_url('admin/bookings') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Athlete Search</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?= h($filters['search'] ?? '') ?>" 
                    placeholder="Search by name, email, mobile, or reference..."
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 text-xs focus:outline-none focus:border-cyan-500"
                >
            </div>

            <!-- Date -->
            <div>
                <label for="date" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Session Date</label>
                <input 
                    type="date" 
                    id="date" 
                    name="date" 
                    value="<?= h($filters['date'] ?? '') ?>" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-cyan-500"
                >
            </div>

            <!-- Service -->
            <div>
                <label for="service_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Modality</label>
                <select 
                    id="service_id" 
                    name="service_id" 
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-cyan-500"
                >
                    <option value="">All Modalities</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?= (int) $srv['id'] ?>" <?= ((int) ($filters['service_id'] ?? 0) === (int) $srv['id']) ? 'selected' : '' ?>>
                            <?= h($srv['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Status</label>
                <div class="flex gap-2">
                    <select 
                        id="status" 
                        name="status" 
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-cyan-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="confirmed" <?= (($filters['status'] ?? '') === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= (($filters['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="pending" <?= (($filters['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending</option>
                    </select>
                    <button 
                        type="submit" 
                        class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer shrink-0"
                    >
                        Filter
                    </button>
                    <?php if (!empty(array_filter($filters))): ?>
                        <a 
                            href="<?= app_url('admin/bookings') ?>" 
                            class="px-3 py-2.5 rounded-xl border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white text-xs flex items-center justify-center transition-colors shrink-0"
                            title="Reset filters"
                        >
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Bookings Table -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white font-heading uppercase tracking-wider">
                Booking Records (<?= count($bookings) ?>)
            </h2>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-16 text-slate-500 text-xs">
                No bookings match the selected filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/80 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-6">Reference</th>
                            <th class="py-3.5 px-4">Athlete</th>
                            <th class="py-3.5 px-4">Modality</th>
                            <th class="py-3.5 px-4">Schedule (IST)</th>
                            <th class="py-3.5 px-4 text-right">Amount</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-6 text-right">Management</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php foreach ($bookings as $b): ?>
                            <tr class="hover:bg-slate-950/40 transition-colors">
                                <td class="py-4 px-6 font-mono text-cyan-400 font-bold">
                                    <?= h($b['booking_reference']) ?>
                                    <span class="block text-[10px] text-slate-500 font-sans font-normal mt-0.5">
                                        <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
                                    </span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-bold text-white"><?= h($b['user_name']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= h($b['user_email']) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= h($b['user_mobile']) ?></div>
                                </td>

                                <td class="py-4 px-4">
                                    <span class="font-bold text-white"><?= h($b['service_name']) ?></span>
                                    <span class="block text-[10px] text-slate-400"><?= (int) $b['service_duration_minutes'] ?> mins</span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-medium text-slate-200"><?= date('D, d M Y', strtotime($b['booking_date'])) ?></div>
                                    <div class="font-mono text-[11px] text-cyan-400"><?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?></div>
                                </td>

                                <td class="py-4 px-4 text-right">
                                    <div class="font-bold text-white font-heading">&#8377;<?= number_format((float) $b['total_amount'], 2) ?></div>
                                    <div class="text-[10px] text-slate-500">Base: &#8377;<?= number_format((float) $b['base_amount'], 2) ?></div>
                                </td>

                                <td class="py-4 px-4 text-center">
                                    <?php if ($b['booking_status'] === 'confirmed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                                            Confirmed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'completed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-500/10 border border-sky-500/30 text-sky-400">
                                            Completed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'cancelled'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/10 border border-rose-500/30 text-rose-400">
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-slate-400">
                                            <?= h($b['booking_status']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($b['razorpay_payment_id'])): ?>
                                        <span class="block text-[9px] font-mono text-slate-500 mt-1 truncate max-w-[120px] mx-auto" title="<?= h($b['razorpay_payment_id']) ?>">
                                            <?= h($b['razorpay_payment_id']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Invoice PDF Link -->
                                        <a 
                                            href="<?= app_url('bookings/' . h($b['booking_reference']) . '/invoice') ?>" 
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-medium transition-colors"
                                            title="Download Tax Invoice"
                                        >
                                            PDF
                                        </a>

                                        <!-- Status Update Dropdown -->
                                        <form method="POST" action="<?= app_url('admin/bookings/' . (int) $b['id'] . '/status') ?>" class="inline">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <select 
                                                name="status" 
                                                onchange="this.form.submit()" 
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-950 border border-slate-800 text-white text-[11px] focus:outline-none focus:border-cyan-500 cursor-pointer"
                                            >
                                                <option value="confirmed" <?= ($b['booking_status'] === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="completed" <?= ($b['booking_status'] === 'completed') ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= ($b['booking_status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
