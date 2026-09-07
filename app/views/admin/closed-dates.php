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
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold uppercase tracking-wider">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-400 hover:text-rose-300 hover:bg-slate-800 text-xs font-medium uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Add Closed Date Form -->
        <div class="lg:col-span-1">
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-xl sticky top-24">
                <h2 class="text-lg font-bold text-white font-heading border-b border-slate-800 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Mark Facility Closure
                </h2>
                <p class="text-xs text-slate-400 mb-6 leading-relaxed">
                    Closed dates automatically block all time slots facility-wide. Customers cannot reserve any modality on these dates.
                </p>

                <form method="POST" action="<?= app_url('admin/closed-dates') ?>" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="closed_date" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Closure Date
                        </label>
                        <input 
                            type="date" 
                            id="closed_date" 
                            name="closed_date" 
                            min="<?= date('Y-m-d') ?>" 
                            required 
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500"
                        >
                    </div>

                    <div>
                        <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Closure Reason
                        </label>
                        <input 
                            type="text" 
                            id="reason" 
                            name="reason" 
                            placeholder="e.g. Deep Facility Sanitization & Water Change" 
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 text-sm focus:outline-none focus:border-cyan-500"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-amber-500/20 transition-all cursor-pointer"
                    >
                        Block Date Facility-Wide
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Closed Dates List -->
        <div class="lg:col-span-2">
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white font-heading uppercase tracking-wider">
                        Scheduled Closures (<?= count($closedDates) ?>)
                    </h3>
                </div>

                <?php if (empty($closedDates)): ?>
                    <div class="text-center py-16 text-slate-500 text-xs">
                        No closed dates recorded. The recovery facility operates normally 7 days a week (06:00 – 22:00 IST).
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950/80 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="py-3.5 px-6">Closure Date</th>
                                    <th class="py-3.5 px-4">Reason / Notes</th>
                                    <th class="py-3.5 px-4">Added On</th>
                                    <th class="py-3.5 px-6 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/80">
                                <?php foreach ($closedDates as $cd): ?>
                                    <tr class="hover:bg-slate-950/40 transition-colors">
                                        <td class="py-4 px-6 font-bold text-white">
                                            <div class="text-sm"><?= date('l, d M Y', strtotime($cd['closed_date'])) ?></div>
                                            <span class="font-mono text-[10px] text-cyan-400"><?= h($cd['closed_date']) ?></span>
                                        </td>

                                        <td class="py-4 px-4 text-slate-300">
                                            <?= h($cd['reason'] ?: 'Facility Closed / General Maintenance') ?>
                                        </td>

                                        <td class="py-4 px-4 text-slate-500 text-[11px]">
                                            <?= date('d M Y', strtotime($cd['created_at'])) ?>
                                        </td>

                                        <td class="py-4 px-6 text-right">
                                            <form method="POST" action="<?= app_url('admin/closed-dates/' . (int) $cd['id'] . '/delete') ?>" onsubmit="return confirm('Re-open facility on <?= h($cd['closed_date']) ?>?')">
                                                <?= \App\Helpers\Csrf::field() ?>
                                                <button 
                                                    type="submit" 
                                                    class="px-3 py-1.5 rounded-lg border border-rose-500/30 hover:bg-rose-500/10 text-rose-400 text-xs font-semibold transition-colors cursor-pointer"
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
