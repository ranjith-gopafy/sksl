<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-sky-700">Athlete Portal</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-1">My Recovery Sessions</h1>
            <p class="text-sm text-slate-600 mt-1">Review your confirmed appointments, download tax invoices, or manage reservations.</p>
        </div>
        <a 
            href="<?= app_url('services') ?>" 
            class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md transition-all self-start md:self-auto"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Book New Session
        </a>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-xs relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Upcoming Appointments</div>
            <div class="text-3xl font-black text-sky-700 font-heading mt-1"><?= $upcomingCount ?></div>
            <div class="text-xs text-slate-500 mt-1">Confirmed &amp; scheduled</div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-xs relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Completed Sessions</div>
            <div class="text-3xl font-black text-emerald-600 font-heading mt-1"><?= $completedCount ?></div>
            <div class="text-xs text-slate-500 mt-1">Recovery delivered</div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-xs relative overflow-hidden">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Cancelled Sessions</div>
            <div class="text-3xl font-black text-slate-500 font-heading mt-1"><?= $cancelledCount ?></div>
            <div class="text-xs text-slate-500 mt-1">Cancelled sessions</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-4 mb-8 overflow-x-auto">
        <a 
            href="<?= app_url('my-bookings?tab=all') ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap <?= $activeTab === 'all' ? 'bg-[#075183] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>"
        >
            All Sessions (<?= $totalCount ?>)
        </a>
        <a 
            href="<?= app_url('my-bookings?tab=upcoming') ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap <?= $activeTab === 'upcoming' ? 'bg-[#075183] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>"
        >
            Upcoming (<?= $upcomingCount ?>)
        </a>
        <a 
            href="<?= app_url('my-bookings?tab=completed') ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap <?= $activeTab === 'completed' ? 'bg-[#075183] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>"
        >
            Completed (<?= $completedCount ?>)
        </a>
        <a 
            href="<?= app_url('my-bookings?tab=cancelled') ?>" 
            class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap <?= $activeTab === 'cancelled' ? 'bg-[#075183] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>"
        >
            Cancelled (<?= $cancelledCount ?>)
        </a>
    </div>

    <!-- Bookings List -->
    <?php if (empty($filteredBookings)): ?>
        <div class="text-center py-16 bg-white border border-slate-200/90 rounded-3xl p-8 shadow-xs">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 font-heading"><?= $activeTab === 'all' ? 'No Sessions Found' : 'No ' . ucfirst($activeTab) . ' Sessions Found' ?></h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                <?php if ($activeTab === 'upcoming'): ?>
                    You don't have any upcoming recovery appointments scheduled right now.
                <?php elseif ($activeTab === 'completed'): ?>
                    No completed sessions recorded yet. Completed appointments will show here.
                <?php else: ?>
                    No cancelled sessions.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a 
                    href="<?= app_url('services') ?>" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-sm"
                >
                    Reserve a Modality
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($filteredBookings as $b): ?>
                <div class="bg-white border border-slate-200/90 hover:border-sky-300 rounded-3xl p-6 transition-all shadow-xs hover:shadow-md">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        
                        <!-- Left: Modality & Schedule info -->
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-mono text-xs font-bold text-sky-700 tracking-wider">
                                    <?= h($b['booking_reference']) ?>
                                </span>
                                <?php if ($b['booking_status'] === 'confirmed'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 border border-emerald-200 text-emerald-800">
                                        <?php $payMode = $b['payment_mode'] ?? 'payment_required'; ?>
                                        Confirmed &bull; <?= $payMode === 'payment_not_required' ? 'Pay at Centre' : ($payMode === 'package_credit' ? 'Package Credit' : 'Paid') ?>
                                    </span>
                                <?php elseif ($b['booking_status'] === 'completed'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 border border-sky-200 text-sky-800">
                                        Completed
                                    </span>
                                <?php elseif ($b['booking_status'] === 'cancelled'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 border border-rose-200 text-rose-800">
                                        Cancelled
                                    </span>
                                <?php elseif ($b['booking_status'] === 'pending' && ($b['payment_channel'] ?? '') === 'offline'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-50 border border-amber-200 text-amber-800" data-testid="awaiting-chip">
                                        Awaiting payment
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                                        <?= h($b['booking_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2 class="text-xl font-extrabold text-slate-900 font-heading">
                                <?= h($b['service_name']) ?>
                            </h2>
                            <?php if ($b['booking_status'] === 'pending' && ($b['payment_channel'] ?? '') === 'offline'): ?>
                                <p class="text-xs text-amber-900 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 max-w-md" data-testid="pay-by-note">
                                    Your slot is reserved. Pay by <strong><?= h(date('d M, g:i A', strtotime((string) $b['payment_due_at']))) ?> IST</strong>; the booking is confirmed once we receive the payment.
                                </p>
                            <?php endif; ?>
                            <?php if ($b['booking_status'] === 'cancelled' && $b['refund_amount'] !== null): ?>
                                <p class="text-xs text-rose-800 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2 max-w-md" data-testid="refund-note">
                                    <?php if ($b['cancelled_by'] === 'customer' && $b['cancellation_fee'] !== null): ?>
                                        Cancellation charge &#8377;<?= number_format((float) $b['cancellation_fee'], 2) ?> &middot;
                                    <?php endif; ?>
                                    <strong>Refund &#8377;<?= number_format((float) $b['refund_amount'], 2) ?></strong> will be sent by SKSL to your original payment method (usually 5&ndash;7 working days).
                                </p>
                            <?php endif; ?>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-sky-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span><?= date('l, d M Y', strtotime($b['booking_date'])) ?></span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-sky-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="font-mono font-semibold text-slate-800"><?= date('h:i A', strtotime($b['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($b['end_time'])) ?> IST</span>
                                </div>

                                <div class="text-slate-300">&bull;</div>

                                <div>
                                    <span class="text-slate-500">Duration:</span> <strong class="text-slate-800"><?= (int) $b['service_duration_minutes'] ?>m</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Pricing & Actions -->
                        <div class="flex flex-col sm:flex-row lg:flex-col sm:items-end justify-between gap-3 pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <?php $rowMode = $b['payment_mode'] ?? 'payment_required'; ?>
                                <?php if ($rowMode === 'payment_not_required'): ?>
                                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Consultation fee</div>
                                    <div class="text-sm font-extrabold text-slate-900 font-heading">Payable at the centre</div>
                                <?php elseif ($rowMode === 'package_credit'): ?>
                                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Paid with</div>
                                    <div class="text-sm font-extrabold text-slate-900 font-heading">1 package credit</div>
                                <?php else: ?>
                                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider"><?= ($b['booking_status'] === 'pending' && ($b['payment_channel'] ?? '') === 'offline') ? 'Amount to Pay' : 'Amount Paid' ?></div>
                                    <div class="text-lg font-extrabold text-slate-900 font-heading">
                                        &#8377;<?= number_format((float) $b['total_amount'], 2) ?>
                                        <span class="text-[10px] font-normal text-slate-500">(incl. 18% GST)</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <?php
                                $quote = (in_array($b['booking_status'], ['confirmed', 'pending'], true) && ($b['booking_status'] === 'confirmed' || ($b['payment_channel'] ?? '') === 'offline') && ($b['booking_date'] > \App\Helpers\TimeHelper::today() || ($b['booking_date'] === \App\Helpers\TimeHelper::today() && $b['end_time'] > \App\Helpers\TimeHelper::now()->format('H:i:s'))))
                                    ? \App\Helpers\BookingRules::cancellationQuote($b) : null;
                                ?>
                                <?php if ($quote !== null && $quote['allowed']): ?>
                                <?php if ($b['booking_status'] === 'pending' && ($b['payment_channel'] ?? '') === 'offline'): ?>
                                    <a href="<?= app_url('booking-confirmation?ref=' . rawurlencode($b['booking_reference'])) ?>" class="px-3.5 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-semibold text-xs transition-colors" data-testid="how-to-pay">How to pay</a>
                                <?php endif; ?>
                                    <button type="button" data-cancel-open
                                            data-ref="<?= h($b['booking_reference']) ?>"
                                            data-name="<?= h($b['service_name']) ?>"
                                            data-when="<?= h(date('D, d M Y', strtotime($b['booking_date'])) . ', ' . date('h:i A', strtotime($b['start_time']))) ?>"
                                            data-action="<?= h(app_url('my-bookings/' . $b['booking_reference'] . '/cancel')) ?>"
                                            data-paid="<?= $quote['paid_online'] ? '1' : '0' ?>"
                                            data-amount="<?= h(number_format((float) $b['total_amount'], 2)) ?>"
                                            data-fee="<?= h(number_format($quote['fee'], 2)) ?>"
                                            data-refund="<?= h(number_format($quote['refund'], 2)) ?>"
                                            data-credit="<?= ($b['booking_type'] ?? '') === 'package' ? '1' : '0' ?>"
                                            class="px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 font-semibold text-xs transition-colors cursor-pointer">
                                        <?= $b['booking_status'] === 'pending' ? 'Cancel request' : 'Cancel session' ?>
                                    </button>
                                <?php elseif ($b['booking_status'] === 'confirmed' && $quote !== null): ?>
                                    <span class="text-[11px] text-slate-500 max-w-[16rem] sm:text-right" data-testid="cancel-closed">Online cancellation closed (<?= \App\Helpers\BookingRules::cancellationNoticeHours() ?> h before start). Contact SKSL.</span>
                                <?php endif; ?>
                                <!-- Download Invoice Button -->
                                <?php if ($b['payment_status'] === 'paid' && ($b['payment_mode'] ?? 'payment_required') === 'payment_required' && $b['booking_status'] !== 'cancelled' && $b['booking_status'] !== 'pending'): ?>
                                    <a 
                                        href="<?= app_url('bookings/' . h($b['booking_reference']) . '/invoice') ?>" 
                                        class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs flex items-center gap-1.5 transition-colors border border-slate-200"
                                        title="Download official GST tax invoice"
                                    >
                                        <svg class="w-3.5 h-3.5 text-sky-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Invoice PDF
                                    </a>
                                <?php endif; ?>

                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $supportPhone = (string) (($business ?? [])['phone'] ?? '');
    $supportEmail = (string) (($business ?? [])['support_email'] ?? '');
    ?>
    <!-- Cancellation help: the self-service rule, plus a way to reach staff for anything else -->
    <div class="mt-8 bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center gap-4" data-testid="cancellation-help">
        <div class="w-10 h-10 shrink-0 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center border border-sky-100">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
        </div>
        <div class="flex-1">
            <h2 class="text-sm font-bold text-slate-900 font-heading">Need to cancel or request a refund?</h2>
            <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                You can cancel a confirmed session here up to <?= \App\Helpers\BookingRules::cancellationNoticeHours() ?> hours before it starts. A cancellation charge of <?= h(rtrim(rtrim(number_format(\App\Helpers\BookingRules::cancellationFeePercent(), 2), '0'), '.')) ?>% of the amount paid applies to online payments, and SKSL refunds the rest manually.
                For anything else, contact the centre with your booking reference.
                <a href="<?= app_url('cancellation-refund') ?>" class="text-sky-700 font-semibold hover:underline">Read the policy</a>.
            </p>
        </div>
        <div class="flex flex-col gap-1.5 text-xs font-semibold shrink-0">
            <?php if ($supportPhone !== ''): ?>
                <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>" class="px-3.5 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white text-center transition-colors"><?= h($supportPhone) ?></a>
            <?php endif; ?>
            <?php if ($supportEmail !== ''): ?>
                <a href="mailto:<?= h($supportEmail) ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-800 text-center transition-colors"><?= h($supportEmail) ?></a>
            <?php endif; ?>
            <?php if ($supportPhone === '' && $supportEmail === ''): ?>
                <a href="<?= app_url('contact') ?>" class="px-3.5 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white text-center transition-colors">Contact SKSL</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel confirmation (filled from the clicked booking's data-* attributes) -->
<dialog id="cancel-dialog" class="rounded-2xl p-0 w-[calc(100%-2rem)] max-w-md shadow-2xl border border-slate-200 backdrop:bg-slate-900/50" aria-labelledby="cancel-title">
    <form method="POST" id="cancel-form" class="p-6 space-y-4">
        <?= \App\Helpers\Csrf::field() ?>
        <h2 id="cancel-title" class="text-lg font-extrabold font-heading text-slate-900">Cancel this session?</h2>
        <p class="text-sm text-slate-700"><strong id="cancel-name"></strong><br><span id="cancel-when" class="text-slate-600"></span></p>
        <div id="cancel-money" class="hidden rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm space-y-1.5">
            <div class="flex justify-between"><span class="text-slate-600">Amount paid</span><span id="cancel-amount" class="font-semibold"></span></div>
            <div class="flex justify-between"><span class="text-slate-600">Cancellation charge (<?= h(rtrim(rtrim(number_format(\App\Helpers\BookingRules::cancellationFeePercent(), 2), '0'), '.')) ?>%)</span><span id="cancel-fee" class="font-semibold text-rose-700"></span></div>
            <div class="flex justify-between border-t border-slate-200 pt-1.5"><span class="font-bold text-slate-800">You will be refunded</span><span id="cancel-refund" class="font-extrabold text-slate-900"></span></div>
            <p class="text-[11px] text-slate-500 pt-1">SKSL refunds this manually to your original payment method, usually within 5&ndash;7 working days.</p>
        </div>
        <p id="cancel-free" class="hidden text-sm text-slate-600"></p>
        <p class="text-xs text-slate-500">This cannot be undone. The slot will be released for other athletes.</p>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <button type="button" id="cancel-keep" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 font-semibold text-sm hover:bg-slate-50 cursor-pointer">Keep my session</button>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm cursor-pointer">Yes, cancel session</button>
        </div>
    </form>
</dialog>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const dlg = document.getElementById('cancel-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') return;
    const $ = (id) => document.getElementById(id);
    document.querySelectorAll('[data-cancel-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            $('cancel-form').action = d.action;
            $('cancel-name').textContent = d.name + ' (' + d.ref + ')';
            $('cancel-when').textContent = d.when + ' IST';
            const paid = d.paid === '1';
            $('cancel-money').classList.toggle('hidden', !paid);
            $('cancel-free').classList.toggle('hidden', paid);
            if (paid) {
                $('cancel-amount').textContent = '\u20B9' + d.amount;
                $('cancel-fee').textContent = '\u2212\u20B9' + d.fee;
                $('cancel-refund').textContent = '\u20B9' + d.refund;
            } else {
                $('cancel-free').textContent = d.credit === '1'
                    ? 'No charge. Your package credit will be returned to your package.'
                    : 'No charge. Nothing was paid online for this session.';
            }
            dlg.showModal();
        });
    });
    $('cancel-keep').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
})();
</script>
