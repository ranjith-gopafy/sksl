<?php
/**
 * SKSL — Customer Portal: My Combo Packages & Credits
 */
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Athlete Portal</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-1">
                My Modality Packages
            </h1>
            <p class="text-sm text-slate-600 mt-1">
                Manage your active package credits, track expiration dates, and book sessions without online checkout.
            </p>
        </div>
        <div class="flex items-center gap-3 self-start md:self-auto">
            <a 
                href="<?= app_url('packages') ?>" 
                class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md transition-all"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                Explore New Bundles
            </a>
            <a 
                href="<?= app_url('my-bookings') ?>" 
                class="inline-flex items-center gap-2 px-4 py-3 rounded-2xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-heading font-bold text-xs uppercase tracking-wider transition-all"
            >
                My Bookings
            </a>
        </div>
    </div>

    <?php foreach (($pendingRequests ?? []) as $req): ?>
        <div class="mb-6 rounded-3xl border border-amber-200 bg-white p-6 sm:p-7 shadow-xs" data-testid="pending-package">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 border border-amber-200 text-amber-800">Awaiting payment</span>
                <span class="font-mono text-xs font-bold text-slate-600"><?= h($req['purchase_reference']) ?></span>
            </div>
            <h2 class="text-xl font-extrabold text-slate-900 font-heading"><?= h($req['package_name_snapshot']) ?></h2>
            <p class="text-xs text-slate-600 mt-1 mb-4">Your session credits are activated, and your tax invoice emailed, as soon as we have received the payment.</p>
            <?php
            $offAmount    = (float) $req['total_amount'];
            $offReference = (string) $req['purchase_reference'];
            $offDueAt     = (string) $req['payment_due_at'];
            $offKind      = 'package';
            require __DIR__ . '/_offline_payment.php';
            ?>
        </div>
    <?php endforeach; ?>

    <?php if (empty($customerPackages) && empty($pendingRequests)): ?>
        <div class="text-center py-16 bg-white border border-[#D9DBDA] rounded-3xl p-8 shadow-xs">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 font-heading">No Modality Packages Found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                You haven't purchased any multi-session combo packages yet. Bundles offer substantial savings on recovery modalities.
            </p>
            <div class="mt-6">
                <a 
                    href="<?= app_url('packages') ?>" 
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-md"
                >
                    View Available Bundles
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($customerPackages as $cp): 
                $credits = $cp['credits'] ?? [];
                $isExpired = strtotime($cp['expires_at']) < time();
                $isPaid = $cp['payment_status'] === 'paid';
                $totalAllocated = array_sum(array_column($credits, 'allocated_quantity'));
                $totalRemaining = array_sum(array_column($credits, 'remaining_quantity'));
                $progressPct = $totalAllocated > 0 ? round((($totalAllocated - $totalRemaining) / $totalAllocated) * 100) : 0;
            ?>
                <div class="border <?= $isExpired ? 'bg-slate-50 border-slate-200' : 'bg-white border-[#D9DBDA] hover:border-[#4A9CC0]' ?> rounded-3xl p-6 sm:p-7 shadow-xs transition-all">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 pb-6 border-b border-slate-100">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-xs font-mono font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-lg">
                                    Ref: <?= h($cp['purchase_reference']) ?>
                                </span>
                                <?php if ($isExpired): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-rose-100 text-rose-700">
                                        Expired
                                    </span>
                                <?php elseif ($totalRemaining === 0): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-600">
                                        All Credits Used
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                        Active &bull; <?= $totalRemaining ?> Credits Available
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2 class="text-2xl font-extrabold text-slate-900 font-heading">
                                <?= h($cp['package_name_snapshot']) ?>
                            </h2>
                            <p class="text-xs text-slate-500 mt-1">
                                Purchased on <?= date('d M Y', strtotime($cp['purchased_at'] ?: $cp['created_at'])) ?>
                                &bull; Valid until <strong class="<?= $isExpired ? 'text-rose-600' : 'text-slate-800' ?>"><?= date('d M Y, h:i A', strtotime($cp['expires_at'])) ?></strong>
                            </p>
                            <?php if ($isPaid): ?>
                                <a href="<?= h(app_url('my-packages/' . rawurlencode((string) $cp['purchase_reference']) . '/invoice')) ?>"
                                   class="inline-block mt-2 text-xs font-bold text-[#075183] hover:underline">Download GST tax invoice (PDF)</a>
                            <?php endif; ?>
                        </div>

                        <!-- Overall Package Progress -->
                        <div class="lg:w-64 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <div class="flex justify-between text-xs font-bold mb-1.5">
                                <span class="text-slate-600">Usage Progress</span>
                                <span class="text-[#075183]"><?= $totalAllocated - $totalRemaining ?> / <?= $totalAllocated ?> used</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                <div class="bg-[#075183] h-2 rounded-full transition-all duration-500" style="width: <?= $progressPct ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Credits Grid -->
                    <div class="mt-6">
                        <h4 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-4">
                            Modality Credit Balance
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($credits as $cred): 
                                $rem = (int) $cred['remaining_quantity'];
                                $alloc = (int) $cred['allocated_quantity'];
                                $canBook = !$isExpired && $rem > 0 && $isPaid;
                            ?>
                                <div class="p-4 rounded-2xl border <?= $rem > 0 ? 'bg-white border-slate-200' : 'bg-slate-50 border-slate-100' ?> flex flex-col justify-between">
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div>
                                            <h5 class="text-sm font-extrabold text-slate-900 leading-snug">
                                                <?= h($cred['service_name']) ?>
                                            </h5>
                                            <span class="text-[11px] text-slate-600">
                                                <?= (int) $cred['duration_minutes'] ?> Min Session
                                            </span>
                                        </div>
                                        <span class="text-base font-black <?= $rem > 0 ? 'text-[#075183]' : 'text-slate-600' ?> font-heading">
                                            <?= $rem ?> / <?= $alloc ?>
                                        </span>
                                    </div>

                                    <div>
                                        <?php if ($canBook): ?>
                                            <a 
                                                href="<?= app_url('booking?service_id=' . (int) $cred['service_id'] . '&package_id=' . (int) $cp['id']) ?>" 
                                                class="w-full py-2 px-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-[11px] uppercase tracking-wider text-center block transition-all shadow-xs"
                                            >
                                                Book Session
                                            </a>
                                        <?php else: ?>
                                            <button disabled class="w-full py-2 px-3 rounded-xl bg-slate-100 text-slate-400 font-bold text-[11px] uppercase tracking-wider cursor-not-allowed">
                                                <?= $rem === 0 ? 'Depleted' : 'Unavailable' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
