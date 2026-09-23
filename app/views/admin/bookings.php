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
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
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

    <!-- Filters Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 mb-8 shadow-xs">
        <form method="GET" action="<?= app_url('admin/bookings') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Athlete Search</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?= h($filters['search'] ?? '') ?>" 
                    placeholder="Search by name, email, mobile, or reference..."
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <!-- Date -->
            <div>
                <label for="date" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Session Date</label>
                <div class="relative">
                    <input 
                        type="date" 
                        id="date" 
                        name="date" 
                        value="<?= h($filters['date'] ?? '') ?>" 
                        class="w-full pl-9 pr-3 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-[#075183] transition-colors"
                    >
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>

            <!-- Service -->
            <div>
                <label for="service_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Modality</label>
                <select 
                    id="service_id" 
                    name="service_id" 
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
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
                <label for="status" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                <div class="flex gap-2">
                    <select 
                        id="status" 
                        name="status" 
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="confirmed" <?= (($filters['status'] ?? '') === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= (($filters['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="pending" <?= (($filters['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending</option>
                    </select>
                    <button 
                        type="submit" 
                        class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer shrink-0 shadow-xs"
                    >
                        Filter
                    </button>
                    <?php if (!empty(array_filter($filters))): ?>
                        <a 
                            href="<?= app_url('admin/bookings') ?>" 
                            class="px-3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs flex items-center justify-center transition-colors shrink-0"
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
    <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 font-heading uppercase tracking-wider">
                Booking Records (<?= count($bookings) ?>)
            </h2>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-16 text-slate-400 text-xs">
                No bookings match the selected filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
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
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($bookings as $b): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-4 px-6 font-mono text-sky-700 font-bold">
                                    <?= h($b['booking_reference']) ?>
                                    <span class="block text-[10px] text-slate-400 font-sans font-normal mt-0.5">
                                        <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
                                    </span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-bold text-slate-900"><?= h($b['user_name']) ?></div>
                                    <div class="text-[11px] text-slate-500"><?= h($b['user_email']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= h($b['user_mobile']) ?></div>
                                </td>

                                <td class="py-4 px-4">
                                    <span class="font-bold text-slate-900"><?= h($b['service_name']) ?></span>
                                    <span class="block text-[10px] text-slate-500"><?= (int) $b['service_duration_minutes'] ?> mins</span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-medium text-slate-800"><?= date('D, d M Y', strtotime($b['booking_date'])) ?></div>
                                    <div class="font-mono text-[11px] text-sky-700"><?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?></div>
                                </td>

                                <td class="py-4 px-4 text-right">
                                    <div class="font-extrabold text-slate-900 font-heading">&#8377;<?= number_format((float) $b['total_amount'], 2) ?></div>
                                    <div class="text-[10px] text-slate-400">Base: &#8377;<?= number_format((float) $b['base_amount'], 2) ?></div>
                                </td>

                                <td class="py-4 px-4 text-center">
                                    <?php if ($b['booking_status'] === 'confirmed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 border border-emerald-200 text-emerald-800">
                                            Confirmed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'completed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 border border-sky-200 text-sky-800">
                                            Completed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'cancelled'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 border border-rose-200 text-rose-800">
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                                            <?= h($b['booking_status']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($b['razorpay_payment_id'])): ?>
                                        <span class="block text-[9px] font-mono text-slate-400 mt-1 truncate max-w-[120px] mx-auto" title="<?= h($b['razorpay_payment_id']) ?>">
                                            <?= h($b['razorpay_payment_id']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Invoice PDF Link -->
                                        <a 
                                            href="<?= app_url('bookings/' . h($b['booking_reference']) . '/invoice') ?>" 
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold transition-colors border border-slate-200"
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
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-900 text-[11px] focus:outline-none focus:border-sky-500 cursor-pointer font-medium"
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
