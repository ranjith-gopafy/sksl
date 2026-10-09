<?php
/**
 * SKSL Admin — Dashboard (overview).
 *
 * @var array<string, mixed>             $stats
 * @var array<int, array<string, mixed>> $schedule
 * @var array<int, array<string, mixed>> $recent
 */

$statusStyle = [
    'confirmed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'completed' => 'bg-sky-50 text-sky-800 border-sky-200',
    'pending'   => 'bg-amber-50 text-amber-800 border-amber-200',
    'cancelled' => 'bg-rose-50 text-rose-800 border-rose-200',
    'expired'   => 'bg-slate-100 text-slate-700 border-slate-200',
];
$modeLabel = [
    'payment_required'     => 'Paid online',
    'payment_not_required' => 'Pay at centre',
    'package_credit'       => 'Package credit',
];
$greeting = (static function (): string {
    $hour = (int) \App\Helpers\TimeHelper::now()->format('G');
    return $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
})();
$firstName = explode(' ', trim((string) ($_SESSION['admin_name'] ?? 'there')))[0] ?: 'there';
$bookingLink = static fn (string $ref): string => app_url('admin/bookings?status=all&search=' . rawurlencode($ref));
?>

<div class="space-y-6 max-w-7xl mx-auto">

    <div>
        <p class="text-sm text-slate-600"><?= h($greeting) ?>, <strong class="text-slate-900"><?= h($firstName) ?></strong>.
            Here is what is happening at the lab on <?= h(\App\Helpers\TimeHelper::now()->format('l, d F Y')) ?>.</p>
    </div>

    <?php if ($stats['refunds_to_review'] > 0): ?>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900" role="status">
            <svg class="w-6 h-6 shrink-0 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <p class="text-sm flex-1">
                <strong><?= (int) $stats['refunds_to_review'] ?> paid booking<?= $stats['refunds_to_review'] === 1 ? ' was' : 's were' ?> cancelled or expired</strong>
                and may need a refund in the Razorpay dashboard.
            </p>
            <div class="flex gap-2 shrink-0">
                <a href="<?= app_url('admin/bookings?status=cancelled') ?>" class="px-3.5 py-2 rounded-xl bg-white border border-amber-300 text-xs font-bold uppercase tracking-wider hover:bg-amber-100">Cancelled</a>
                <a href="<?= app_url('admin/bookings?status=expired') ?>" class="px-3.5 py-2 rounded-xl bg-white border border-amber-300 text-xs font-bold uppercase tracking-wider hover:bg-amber-100">Expired</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Headline figures -->
    <section aria-label="Key figures" class="grid grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
        <a href="<?= app_url('admin/bookings?status=confirmed&date=' . urlencode(\App\Helpers\TimeHelper::today())) ?>" class="group bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Today's sessions</p>
            <p class="mt-2 font-heading text-2xl sm:text-3xl font-extrabold text-slate-900"><?= (int) $stats['today_sessions'] ?></p>
            <p class="mt-1 text-xs text-slate-600 group-hover:text-[#075183]">Confirmed for today &rarr;</p>
        </a>
        <a href="<?= app_url('admin/bookings?status=confirmed') ?>" class="group bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Next 7 days</p>
            <p class="mt-2 font-heading text-2xl sm:text-3xl font-extrabold text-slate-900"><?= (int) $stats['next_7_days'] ?></p>
            <p class="mt-1 text-xs text-slate-600 group-hover:text-[#075183]">Confirmed bookings &rarr;</p>
        </a>
        <a href="<?= app_url('admin/bookings?status=pending') ?>" class="group bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Awaiting payment</p>
            <p class="mt-2 font-heading text-2xl sm:text-3xl font-extrabold <?= $stats['awaiting_payment'] > 0 ? 'text-amber-700' : 'text-slate-900' ?>"><?= (int) $stats['awaiting_payment'] ?></p>
            <p class="mt-1 text-xs text-slate-600 group-hover:text-[#075183]">Checkouts in progress &rarr;</p>
        </a>
        <a href="<?= app_url('admin/package-purchases') ?>" class="group bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-xs hover:border-sky-300 hover:shadow-md transition">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Online sales this month</p>
            <p class="mt-2 font-heading text-2xl sm:text-3xl font-extrabold text-slate-900">&#8377;<?= number_format((float) $stats['online_sales_month'], 2) ?></p>
            <p class="mt-1 text-xs text-slate-600 group-hover:text-[#075183]"><?= (int) $stats['online_sales_count'] ?> paid booking<?= $stats['online_sales_count'] === 1 ? '' : 's' ?> (incl. GST) &middot; <?= (int) $stats['package_sales_30d'] ?> package<?= $stats['package_sales_30d'] === 1 ? '' : 's' ?> sold in 30 days &rarr;</p>
        </a>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

        <!-- Today's schedule -->
        <section aria-labelledby="today-heading" class="xl:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
                <h2 id="today-heading" class="font-heading text-base font-bold text-slate-900">Today's schedule</h2>
                <a href="<?= app_url('admin/bookings?status=confirmed&date=' . urlencode(\App\Helpers\TimeHelper::today())) ?>" class="text-xs font-bold uppercase tracking-wider text-[#075183] hover:underline">Manage</a>
            </div>
            <?php if (empty($schedule)): ?>
                <p class="px-5 py-10 text-center text-sm text-slate-500">No sessions are booked for today.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($schedule as $s): ?>
                        <li class="px-5 py-3 flex items-center gap-4">
                            <div class="w-20 shrink-0 text-sm font-bold text-[#075183] font-heading leading-tight">
                                <?= h(date('g:i A', strtotime((string) $s['start_time']))) ?>
                                <span class="block text-[11px] font-medium text-slate-500"><?= h(date('g:i A', strtotime((string) $s['end_time']))) ?></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 truncate"><?= h((string) $s['service_name_snapshot']) ?></p>
                                <p class="text-xs text-slate-600 truncate"><?= h((string) $s['customer_name']) ?> &middot; <span class="font-mono"><?= h((string) $s['booking_reference']) ?></span></p>
                            </div>
                            <?php if (($s['visit_status'] ?? 'pending') !== 'pending'): ?>
                                <span class="shrink-0 px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider <?= $s['visit_status'] === 'visited' ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-rose-50 text-rose-800 border-rose-200' ?>"><?= $s['visit_status'] === 'visited' ? 'Visited' : 'No-show' ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Recent bookings -->
        <section aria-labelledby="recent-heading" class="xl:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100">
                <h2 id="recent-heading" class="font-heading text-base font-bold text-slate-900">Recent bookings</h2>
                <a href="<?= app_url('admin/bookings?status=all') ?>" class="text-xs font-bold uppercase tracking-wider text-[#075183] hover:underline">View all</a>
            </div>
            <?php if (empty($recent)): ?>
                <p class="px-5 py-10 text-center text-sm text-slate-500">No bookings have been made yet.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($recent as $b):
                        $style = $statusStyle[$b['booking_status']] ?? $statusStyle['expired'];
                    ?>
                        <li>
                            <a href="<?= h($bookingLink((string) $b['booking_reference'])) ?>" class="px-5 py-3 flex items-center gap-3 hover:bg-slate-50">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900 truncate"><?= h((string) $b['service_name_snapshot']) ?>
                                        <span class="font-normal text-slate-600">&middot; <?= h((string) $b['customer_name']) ?></span></p>
                                    <p class="text-xs text-slate-600">
                                        <?= h(date('d M Y', strtotime((string) $b['booking_date']))) ?>, <?= h(date('g:i A', strtotime((string) $b['start_time']))) ?>
                                        &middot; <?= h($modeLabel[$b['payment_mode']] ?? 'Booking') ?>
                                        <?php if ($b['payment_mode'] === 'payment_required'): ?>&middot; &#8377;<?= number_format((float) $b['total_amount'], 2) ?><?php endif; ?>
                                    </p>
                                </div>
                                <span class="shrink-0 px-2.5 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider <?= $style ?>"><?= h(ucfirst((string) $b['booking_status'])) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
