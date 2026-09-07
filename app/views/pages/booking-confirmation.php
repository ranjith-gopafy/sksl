<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Success Banner Card -->
    <div class="rounded-3xl bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 border border-emerald-500/30 p-8 sm:p-10 shadow-2xl shadow-emerald-500/10 backdrop-blur-xl relative overflow-hidden">
        <!-- Ambient glow decoration -->
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Success Header -->
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 relative z-10">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-slate-950 flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/30">
                <svg class="w-9 h-9" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="text-center sm:text-left flex-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold tracking-wider uppercase mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Booking Confirmed &amp; Paid
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white font-heading">
                    Your Recovery Session is Confirmed!
                </h1>
                <p class="text-sm text-slate-300 mt-1">
                    Thank you for choosing Sara Kinetic Sports Lab. A confirmation copy and tax invoice have been generated for your records.
                </p>
            </div>
        </div>

        <!-- Reference & Status Badges -->
        <div class="mt-8 pt-6 border-t border-slate-800 flex flex-wrap items-center justify-between gap-4 relative z-10">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-1">Booking Reference</span>
                <div class="flex items-center gap-2">
                    <span id="booking-ref-display" class="font-mono text-xl sm:text-2xl font-black text-cyan-400 tracking-wider">
                        <?= h($booking['booking_reference']) ?>
                    </span>
                    <button 
                        type="button" 
                        onclick="navigator.clipboard.writeText('<?= h($booking['booking_reference']) ?>'); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 2000)" 
                        class="text-[11px] font-bold px-2 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 transition-colors"
                    >
                        Copy
                    </button>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="bg-slate-950/80 px-4 py-2 rounded-xl border border-slate-800 text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Session Status</span>
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">
                        <?= h($booking['booking_status']) ?>
                    </span>
                </div>
                <div class="bg-slate-950/80 px-4 py-2 rounded-xl border border-slate-800 text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Payment Status</span>
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">
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
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h2 class="text-base font-bold text-white font-heading border-b border-slate-800 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Session Details
                </h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400 uppercase font-semibold">Modality</dt>
                        <dd class="text-base font-bold text-white font-heading mt-0.5">
                            <?= h($booking['service_name']) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400 uppercase font-semibold">Session Duration</dt>
                        <dd class="text-slate-200 font-medium mt-0.5">
                            <?= h($booking['service_duration_minutes']) ?> Minutes
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400 uppercase font-semibold">Appointment Date</dt>
                        <dd class="text-slate-200 font-medium mt-0.5">
                            <?= date('l, d F Y', strtotime($booking['booking_date'])) ?>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-slate-400 uppercase font-semibold">Reserved Slot Window</dt>
                        <dd class="text-cyan-400 font-mono font-bold mt-0.5">
                            <?= date('h:i A', strtotime($booking['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($booking['end_time'])) ?> IST
                        </dd>
                    </div>

                    <div class="sm:col-span-2 pt-3 border-t border-slate-800/80">
                        <dt class="text-xs text-slate-400 uppercase font-semibold">Facility Location</dt>
                        <dd class="text-slate-200 text-xs mt-1 leading-relaxed">
                            <strong>Sara Kinetic Sports Lab (SKSL)</strong><br>
                            Athletic Recovery Center, Chennai, Tamil Nadu, India<br>
                            <span class="text-slate-400">Please report to the reception check-in desk upon arrival.</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Pre-Session Instructions -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-base font-bold text-white font-heading border-b border-slate-800 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Arrival &amp; Preparation Guidelines
                </h3>

                <ul class="space-y-3 text-xs text-slate-300">
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-400 mt-1.5 shrink-0"></div>
                        <span><strong>Arrive 10 minutes early</strong> to complete your thermal acclimation and locker orientation.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-400 mt-1.5 shrink-0"></div>
                        <span><strong>Attire:</strong> Bring appropriate athletic swimwear or clean athletic compression gear. Fresh towels and private lockers are provided on-site.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-400 mt-1.5 shrink-0"></div>
                        <span><strong>Hydration:</strong> Ensure you are adequately hydrated prior to entering hot sauna or cold plunge immersion.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-400 mt-1.5 shrink-0"></div>
                        <span><strong>Cancellations:</strong> As per our policy, cancellations must be requested at least 2 hours in advance.</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right 1 Col: Billing Breakdown & Transaction Info -->
        <div class="md:col-span-1 space-y-6">
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-base font-bold text-white font-heading border-b border-slate-800 pb-3 mb-4">
                    Payment Receipt
                </h3>

                <div class="space-y-3 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Base Rate</span>
                        <span class="text-slate-200">&#8377;<?= number_format((float) $booking['base_amount'], 2) ?></span>
                    </div>

                    <div class="flex justify-between text-slate-400">
                        <span>GST (18%)</span>
                        <span class="text-slate-200">&#8377;<?= number_format((float) $booking['gst_amount'], 2) ?></span>
                    </div>

                    <?php if ((float) ($booking['convenience_fee'] ?? 0) > 0): ?>
                        <div class="flex justify-between text-slate-400">
                            <span>Convenience Fee</span>
                            <span class="text-slate-200">&#8377;<?= number_format((float) $booking['convenience_fee'], 2) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="pt-3 border-t border-slate-800 flex justify-between text-sm font-extrabold text-white font-heading">
                        <span>Total Paid</span>
                        <span class="text-emerald-400">&#8377;<?= number_format((float) $booking['total_amount'], 2) ?></span>
                    </div>
                </div>

                <!-- Transaction Audit Info -->
                <div class="mt-6 pt-5 border-t border-slate-800 space-y-2.5 text-[11px] text-slate-400">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-500">Razorpay Payment ID</span>
                        <span class="font-mono text-slate-300 break-all">
                            <?= h($payment['razorpay_payment_id'] ?? 'PAID-ON-FILE') ?>
                        </span>
                    </div>

                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-500">Razorpay Order ID</span>
                        <span class="font-mono text-slate-300 break-all">
                            <?= h($payment['razorpay_order_id'] ?? 'N/A') ?>
                        </span>
                    </div>

                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-500">Billed Athlete</span>
                        <span class="text-slate-300 font-medium">
                            <?= h($booking['user_name']) ?> (<?= h($booking['user_email']) ?>)
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 pt-5 border-t border-slate-800 space-y-3">
                    <a 
                        href="<?= app_url('bookings/' . h($booking['booking_reference']) . '/invoice') ?>" 
                        class="w-full py-2.5 px-4 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 font-medium text-xs flex items-center justify-center gap-2 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download Tax Invoice (PDF)
                    </a>

                    <button 
                        type="button" 
                        onclick="window.print()" 
                        class="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-medium text-xs flex items-center justify-center gap-2 transition-colors cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print / Save Voucher
                    </button>

                    <a 
                        href="<?= app_url('booking') ?>" 
                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider flex items-center justify-center transition-all shadow-md shadow-cyan-500/20"
                    >
                        Book Another Session
                    </a>

                    <a 
                        href="<?= app_url('profile') ?>" 
                        class="w-full py-2 px-4 rounded-xl border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-slate-200 text-xs text-center block transition-colors"
                    >
                        View Account Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
