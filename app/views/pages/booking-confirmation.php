<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Success Banner Card -->
    <div class="rounded-3xl bg-white border border-slate-200/90 p-8 sm:p-10 shadow-xs relative overflow-hidden">
        <!-- Subtle accent stripe -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-sky-500"></div>

        <!-- Success Header -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 pt-2">
            <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shrink-0 shadow-xs">
                <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="text-center sm:text-left flex-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold tracking-wider uppercase mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Booking Confirmed &amp; Paid
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-heading">
                    Your Recovery Session is Confirmed!
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Thank you for choosing Sara Kinetic Sports Lab. A confirmation voucher and tax invoice have been generated for your records.
                </p>
            </div>
        </div>

        <!-- Reference & Status Badges -->
        <div class="mt-8 pt-6 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1">Booking Reference</span>
                <div class="flex items-center gap-2.5">
                    <span id="booking-ref-display" class="font-mono text-xl sm:text-2xl font-black text-sky-700 tracking-wider">
                        <?= h($booking['booking_reference']) ?>
                    </span>
                    <button 
                        type="button" 
                        onclick="navigator.clipboard.writeText('<?= h($booking['booking_reference']) ?>'); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 2000)" 
                        class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200 transition-colors"
                    >
                        Copy
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="bg-slate-50 px-4 py-2 rounded-2xl border border-slate-200 text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Session Status</span>
                    <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">
                        <?= h($booking['booking_status']) ?>
                    </span>
                </div>
                <div class="bg-slate-50 px-4 py-2 rounded-2xl border border-slate-200 text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Payment Status</span>
                    <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">
                        <?= h($booking['payment_status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        
        <!-- Left 2 Cols: Session Details -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs">
                <h2 class="text-base font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Session Details
                </h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 uppercase font-semibold">Modality</dt>
                        <dd class="text-base font-extrabold text-slate-900 font-heading mt-0.5">
                            <?= h($booking['service_name']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-500 uppercase font-semibold">Session Duration</dt>
                        <dd class="text-slate-800 font-medium mt-0.5">
                            <?= h($booking['service_duration_minutes']) ?> Minutes
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-500 uppercase font-semibold">Appointment Date</dt>
                        <dd class="text-slate-800 font-medium mt-0.5">
                            <?= date('l, d F Y', strtotime($booking['booking_date'])) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-500 uppercase font-semibold">Reserved Slot Window</dt>
                        <dd class="text-sky-700 font-mono font-bold mt-0.5">
                            <?= date('h:i A', strtotime($booking['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($booking['end_time'])) ?> IST
                        </dd>
                    </div>

                    <div class="sm:col-span-2 pt-4 border-t border-slate-100">
                        <dt class="text-xs text-slate-500 uppercase font-semibold">Facility Location</dt>
                        <dd class="text-slate-700 text-xs mt-1 leading-relaxed">
                            <strong class="text-slate-900">Sara Kinetic Sports Lab (SKSL)</strong><br>
                            Athletic Recovery Center, Bengaluru, Karnataka, India<br>
                            <span class="text-slate-500">Please report to the reception check-in desk upon arrival.</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Pre-Session Instructions -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs">
                <h3 class="text-base font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Arrival &amp; Preparation Guidelines
                </h3>

                <ul class="space-y-3 text-xs text-slate-600">
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-sky-600 mt-1.5 shrink-0"></div>
                        <span><strong>Arrive 10 minutes early</strong> to complete your thermal acclimation and locker orientation.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-sky-600 mt-1.5 shrink-0"></div>
                        <span><strong>Attire:</strong> Bring appropriate athletic swimwear or clean athletic compression gear. Fresh towels and private lockers are provided on-site.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-sky-600 mt-1.5 shrink-0"></div>
                        <span><strong>Hydration:</strong> Ensure you are adequately hydrated prior to entering hot sauna or cold plunge immersion.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-sky-600 mt-1.5 shrink-0"></div>
                        <span><strong>Cancellations:</strong> To cancel or request a refund, contact SKSL with your booking reference at least 2 hours in advance. <a href="<?= app_url('cancellation-refund') ?>" class="text-sky-600 font-semibold hover:underline">Policy</a></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right 1 Col: Billing Breakdown & Transaction Info -->
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs">
                <h3 class="text-base font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4">
                    Payment Receipt
                </h3>

                <div class="space-y-3 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Base Rate</span>
                        <span class="text-slate-800 font-medium">&#8377;<?= number_format((float) $booking['base_amount'], 2) ?></span>
                    </div>

                    <div class="flex justify-between text-slate-500">
                        <span>GST (18%)</span>
                        <span class="text-slate-800 font-medium">&#8377;<?= number_format((float) $booking['gst_amount'], 2) ?></span>
                    </div>

                    <?php if ((float) ($booking['convenience_fee'] ?? 0) > 0): ?>
                        <div class="flex justify-between text-slate-500">
                            <span>Convenience Fee</span>
                            <span class="text-slate-800 font-medium">&#8377;<?= number_format((float) $booking['convenience_fee'], 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="pt-3 border-t border-slate-100 flex justify-between text-sm font-extrabold text-slate-900 font-heading">
                        <span>Total Paid</span>
                        <span class="text-emerald-600 font-bold">&#8377;<?= number_format((float) $booking['total_amount'], 2) ?></span>
                    </div>
                </div>

                <!-- Transaction Audit Info -->
                <div class="mt-6 pt-5 border-t border-slate-100 space-y-2.5 text-[11px] text-slate-500">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Razorpay Payment ID</span>
                        <span class="font-mono text-slate-800 break-all font-semibold">
                            <?= h($payment['razorpay_payment_id'] ?? 'PAID-ON-FILE') ?>
                        </span>
                    </div>

                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Razorpay Order ID</span>
                        <span class="font-mono text-slate-800 break-all font-semibold">
                            <?= h($payment['razorpay_order_id'] ?? 'N/A') ?>
                        </span>
                    </div>

                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Billed Athlete</span>
                        <span class="text-slate-800 font-semibold">
                            <?= h($booking['user_name']) ?> (<?= h($booking['user_email']) ?>)
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 pt-5 border-t border-slate-100 space-y-3">
                    <?php if (($booking['booking_status'] ?? '') !== 'cancelled' && ($booking['booking_status'] ?? '') !== 'pending'): ?>
                        <a 
                            href="<?= app_url('bookings/' . h($booking['booking_reference']) . '/invoice') ?>" 
                            class="w-full py-3 px-4 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-semibold text-xs flex items-center justify-center gap-2 transition-colors shadow-xs"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download Tax Invoice (PDF)
                        </a>
                    <?php endif; ?>


                    <a 
                        href="<?= app_url('services') ?>" 
                        class="w-full py-3 px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider flex items-center justify-center transition-all shadow-md"
                    >
                        Book Another Session
                    </a>

                    <a 
                        href="<?= app_url('my-bookings') ?>" 
                        class="w-full py-2 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs text-center block transition-colors"
                    >
                        View My Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
