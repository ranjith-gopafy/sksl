<?php
$biz = (array) config('business', []);
$bizName    = $biz['trade_name'] ?: 'Sara Kinetic Sports Lab';
$bizAddress = array_filter([
    $biz['address_line1'] ?? '',
    $biz['address_line2'] ?? '',
    trim(($biz['city'] ?? 'Bengaluru') . (!empty($biz['pincode']) ? ' – ' . $biz['pincode'] : '')),
    trim(($biz['state_name'] ?? 'Karnataka') . ', India'),
]);
$bizPhone   = (string) ($biz['phone'] ?? '');
$bizEmail   = (string) ($biz['support_email'] ?? '');
$facility   = (array) config('app.facility', []);
$openLabel  = date('g:i A', strtotime($facility['open'] ?? '06:00'));
$closeLabel = date('g:i A', strtotime($facility['close'] ?? '22:00'));
?>
<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Visit Our Facility</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-2">Contact &amp; Location</h1>
        <p class="text-slate-600 text-sm mt-3">We are here to answer your questions and assist with athletic recovery protocols.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Facility Info Card -->
        <div class="bg-white border border-slate-200/90 rounded-3xl p-8 shadow-xs space-y-6">
            <h2 class="text-xl font-bold text-slate-900 font-heading"><?= h($bizName) ?></h2>
            
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-2xl bg-sky-50 border border-sky-200 text-sky-700 flex items-center justify-center shrink-0 mt-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Facility Location</h3>
                    <address class="text-sm text-slate-800 mt-1 leading-relaxed" style="font-style: normal;">
                        <?= h($bizName) ?><br>
                        <?php foreach ($bizAddress as $line): ?>
                            <?= h($line) ?><br>
                        <?php endforeach; ?>
                    </address>
                </div>
            </div>

            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center shrink-0 mt-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Operating Hours</h3>
                    <p class="text-sm text-slate-800 mt-1">
                        <strong>Daily:</strong> <?= h($openLabel) ?> – <?= h($closeLabel) ?> IST<br>
                        <span class="text-xs text-slate-500">Open all 7 days for athlete appointments.</span>
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center shrink-0 mt-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Phone, Email &amp; Support</h3>
                    <p class="text-sm text-slate-800 mt-1 space-y-0.5">
                        <?php if ($bizPhone !== ''): ?>
                            <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $bizPhone)) ?>" class="block text-sky-700 font-semibold hover:underline"><?= h($bizPhone) ?></a>
                        <?php endif; ?>
                        <?php if ($bizEmail !== ''): ?>
                            <a href="mailto:<?= h($bizEmail) ?>" class="block text-sky-700 font-semibold hover:underline"><?= h($bizEmail) ?></a>
                        <?php endif; ?>
                        <?php if ($bizPhone === '' && $bizEmail === ''): ?>
                            <span class="text-slate-500">Contact details will be published shortly. Please speak to reception.</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-500 mt-2">
                        For cancellations or refunds, contact us with your booking reference. See the
                        <a href="<?= app_url('cancellation-refund') ?>" class="text-sky-600 font-semibold hover:underline">Cancellation &amp; Refund Policy</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Facility Standards / Arrival Advisory -->
        <div class="bg-white border border-slate-200/90 rounded-3xl p-8 shadow-xs flex flex-col justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900 font-heading mb-4">Arrival Advisory</h2>
                <div class="space-y-4 text-sm text-slate-600 leading-relaxed">
                    <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900">
                        <strong>Arrive 10 Minutes Early:</strong> Please allow time for check-in, changing, and pre-session hydration before your reserved slot.
                    </div>
                    <p>
                        <strong>What We Provide:</strong> Sanitized bath towels, cold drinking water, secure lockers, and private change rooms with warm showers.
                    </p>
                    <p>
                        <strong>What to Bring:</strong> Clean athletic swimwear or recovery shorts and personal flip-flops or pool footwear.
                    </p>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <a 
                    href="<?= app_url('services') ?>" 
                    class="block w-full py-3.5 px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm transition-all"
                >
                    View All Modalities
                </a>
            </div>
        </div>
    </div>
</div>
