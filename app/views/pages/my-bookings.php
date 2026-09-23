<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Athlete Portal</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-1">My Recovery Sessions</h1>
            <p class="text-sm text-slate-600 mt-1">Review your confirmed appointments, download tax invoices, or manage reservations.</p>
        </div>
        <a 
            href="<?= app_url('booking') ?>" 
            class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-sky-600/20 hover:shadow-sky-600/35 transition-all self-start md:self-auto"
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
            <div class="text-3xl font-black text-slate-400 font-heading mt-1"><?= $cancelledCount ?></div>
            <div class="text-xs text-slate-500 mt-1">Released prior to cutoff</div>
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
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 font-heading">No <?= ucfirst($activeTab) ?> Sessions Found</h3>
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
                    href="<?= app_url('booking') ?>" 
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-sm"
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
                                        Confirmed &bull; Paid
                                    </span>
                                <?php elseif ($b['booking_status'] === 'completed'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 border border-sky-200 text-sky-800">
                                        Completed
                                    </span>
                                <?php elseif ($b['booking_status'] === 'cancelled'): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 border border-rose-200 text-rose-800">
                                        Cancelled
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

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span><?= date('l, d M Y', strtotime($b['booking_date'])) ?></span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Amount Paid</div>
                                <div class="text-lg font-extrabold text-slate-900 font-heading">
                                    &#8377;<?= number_format((float) $b['total_amount'], 2) ?>
                                    <span class="text-[10px] font-normal text-slate-500">(incl. 18% GST)</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Download Invoice Button -->
                                <?php if ($b['payment_status'] === 'paid'): ?>
                                    <a 
                                        href="<?= app_url('bookings/' . h($b['booking_reference']) . '/invoice') ?>" 
                                        class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs flex items-center gap-1.5 transition-colors border border-slate-200"
                                        title="Download official GST tax invoice"
                                    >
                                        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Invoice PDF
                                    </a>
                                <?php endif; ?>

                                <!-- Cancellation Action -->
                                <?php if ($b['booking_status'] === 'confirmed'): ?>
                                    <?php if (!empty($b['cancellation']['can_cancel'])): ?>
                                        <button 
                                            type="button" 
                                            onclick="openCancelModal('<?= h($b['booking_reference']) ?>', '<?= h($b['service_name']) ?>', '<?= date('d M Y', strtotime($b['booking_date'])) ?>', '<?= date('h:i A', strtotime($b['start_time'])) ?>', '<?= (float) $b['cancellation']['hours_remaining'] ?>')" 
                                            class="px-3.5 py-2 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-700 font-semibold text-xs transition-colors cursor-pointer"
                                        >
                                            Cancel Session
                                        </button>
                                    <?php else: ?>
                                        <span 
                                            class="px-3 py-1.5 rounded-xl bg-slate-50 text-slate-400 text-[11px] font-medium border border-slate-200 cursor-not-allowed" 
                                            title="<?= h($b['cancellation']['reason'] ?? 'Cancellation policy: minimum 2 hours notice required') ?>"
                                        >
                                            Non-cancellable (&lt;2h)
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Cancellation Confirmation Modal -->
<div id="cancel-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-4 border border-rose-200">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h3 class="text-lg font-bold text-slate-900 font-heading">Cancel Recovery Session?</h3>
        <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
            Are you sure you want to cancel your <strong id="modal-service" class="text-slate-900"></strong> session on <strong id="modal-datetime" class="text-slate-900"></strong>?
        </p>

        <div class="my-4 p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1">
            <div>Booking Ref: <strong id="modal-ref" class="text-sky-700 font-mono"></strong></div>
            <div>Notice window remaining: <strong id="modal-hours" class="text-slate-800"></strong> hours</div>
            <div class="text-[11px] text-slate-500 pt-1.5 border-t border-slate-200">
                Cancellation complies with the 2-hour minimum notice requirement. Slot capacity will be released back to other athletes.
            </div>
        </div>

        <form id="cancel-form" method="POST" action="">
            <input type="hidden" name="_csrf_token" value="<?= \App\Helpers\Csrf::token() ?>">
            <div class="flex items-center gap-3">
                <button 
                    type="submit" 
                    class="flex-1 py-3 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-rose-600/20 transition-all cursor-pointer"
                >
                    Confirm Cancellation
                </button>
                <button 
                    type="button" 
                    onclick="closeCancelModal()" 
                    class="py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer"
                >
                    Keep Session
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(ref, service, date, time, hours) {
    document.getElementById('modal-ref').textContent = ref;
    document.getElementById('modal-service').textContent = service;
    document.getElementById('modal-datetime').textContent = `${date} at ${time}`;
    document.getElementById('modal-hours').textContent = hours;
    document.getElementById('cancel-form').action = '<?= app_url('my-bookings') ?>/' + encodeURIComponent(ref) + '/cancel';
    document.getElementById('cancel-modal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancel-modal').classList.add('hidden');
}
</script>
