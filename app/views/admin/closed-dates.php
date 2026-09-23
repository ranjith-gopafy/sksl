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
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Hero Banner
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Add Closed Date Form -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h2 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Mark Facility Closure
                </h2>
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    Closed dates automatically block all time slots facility-wide. Customers cannot reserve any modality on these dates.
                </p>

                <form method="POST" action="<?= app_url('admin/closed-dates') ?>" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="closed_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Closure Date
                        </label>
                        <div class="relative">
                            <input 
                                type="date" 
                                id="closed_date" 
                                name="closed_date" 
                                min="<?= date('Y-m-d') ?>" 
                                required 
                                class="w-full pl-10 pr-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm focus:bg-white focus:outline-none focus:border-[#075183] transition-colors"
                            >
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>

                    <div>
                        <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Closure Reason
                        </label>
                        <input 
                            type="text" 
                            id="reason" 
                            name="reason" 
                            placeholder="e.g. Deep Facility Sanitization & Water Change" 
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-sm focus:bg-white focus:outline-none focus:border-sky-500"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-amber-500/20 transition-all cursor-pointer"
                    >
                        Block Date Facility-Wide
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Closed Dates List -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 font-heading uppercase tracking-wider">
                        Scheduled Closures (<?= count($closedDates) ?>)
                    </h3>
                </div>

                <?php if (empty($closedDates)): ?>
                    <div class="text-center py-16 text-slate-400 text-xs">
                        No closed dates recorded. The recovery facility operates normally 7 days a week (06:00 – 22:00 IST).
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="py-3.5 px-6">Closure Date</th>
                                    <th class="py-3.5 px-4">Reason / Notes</th>
                                    <th class="py-3.5 px-4">Added On</th>
                                    <th class="py-3.5 px-6 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($closedDates as $cd): ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-4 px-6 font-bold text-slate-900">
                                            <div class="text-sm"><?= date('l, d M Y', strtotime($cd['closed_date'])) ?></div>
                                            <span class="font-mono text-[10px] text-sky-700"><?= h($cd['closed_date']) ?></span>
                                        </td>

                                        <td class="py-4 px-4 text-slate-600">
                                            <?= h($cd['reason'] ?: 'Facility Closed / General Maintenance') ?>
                                        </td>

                                        <td class="py-4 px-4 text-slate-400 text-[11px]">
                                            <?= date('d M Y', strtotime($cd['created_at'])) ?>
                                        </td>

                                        <td class="py-4 px-6 text-right">
                                            <form method="POST" action="<?= app_url('admin/closed-dates/' . (int) $cd['id'] . '/delete') ?>" onsubmit="return confirm('Re-open facility on <?= h($cd['closed_date']) ?>?')">
                                                <?= \App\Helpers\Csrf::field() ?>
                                                <button 
                                                    type="submit" 
                                                    class="px-3 py-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs font-semibold transition-colors cursor-pointer"
                                                >
                                                    Remove &bull; Re-open
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
