<?php
$biz = (array) config('business', []);
$bizPhone = (string) ($biz['phone'] ?? '');
$bizEmail = (string) ($biz['support_email'] ?? '');
$bizName  = (string) ($biz['trade_name'] ?: 'Sara Kinetic Sports Lab');
?>
<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="bg-white border border-slate-200/90 rounded-3xl p-8 md:p-12 shadow-xs">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Policies</span>
        <h1 class="text-3xl font-extrabold text-slate-900 font-heading mt-2 mb-8">Cancellation &amp; Refund Policy</h1>
        
        <div class="space-y-6 text-sm text-slate-600 leading-relaxed">
            <p>
                Sara Kinetic Sports Lab limits capacity for each time slot to ensure an uncompromised, sanitary, and personalized recovery experience for every athlete. Because slot capacity is strictly capped, the following cancellation guidelines apply.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">1. How to Cancel a Session</h2>
            <p>
                Cancellations are handled by the <?= h($bizName) ?> team — there is no self-service cancellation in the athlete portal.
                To cancel an upcoming session, contact us with your <strong>booking reference</strong> (shown in
                <a href="<?= app_url('my-bookings') ?>" class="text-sky-600 font-semibold hover:underline">My Sessions</a> and in your confirmation email)
                at least <strong>2 hours before</strong> the scheduled start time:
            </p>
            <ul class="list-disc pl-6 space-y-1">
                <?php if ($bizPhone !== ''): ?>
                    <li>Phone / WhatsApp: <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $bizPhone)) ?>" class="text-sky-600 font-semibold hover:underline"><?= h($bizPhone) ?></a></li>
                <?php endif; ?>
                <?php if ($bizEmail !== ''): ?>
                    <li>Email: <a href="mailto:<?= h($bizEmail) ?>" class="text-sky-600 font-semibold hover:underline"><?= h($bizEmail) ?></a></li>
                <?php endif; ?>
                <li>In person at the facility reception</li>
            </ul>
            <p>
                Our team will confirm the cancellation and the slot will be released to other athletes.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">2. Refunds</h2>
            <p>
                Refunds are processed manually by <?= h($bizName) ?> to the original payment method after a cancellation is approved.
                Requests received with at least 2 hours' notice are eligible for a full refund; refunds typically reach your bank or card
                within 5–7 working days depending on your bank. Late cancellations (less than 2 hours' notice) and no-shows are non-refundable.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">3. Facility Closures or Rescheduling by SKSL</h2>
            <p>
                In the rare event that SKSL must close a date or adjust schedules due to routine maintenance, mechanical sanitation, or unexpected circumstances, affected athletes will be promptly notified and offered a full refund or priority rescheduling at their convenience.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">4. Contacting Support</h2>
            <p>
                To request assistance with an existing booking, please visit our <a href="<?= app_url('contact') ?>" class="text-sky-600 font-semibold hover:underline">Contact page</a>. Always quote your booking reference so we can locate your session quickly.
            </p>
        </div>
    </div>
</div>
