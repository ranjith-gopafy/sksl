<?php
$biz      = (array) config('business', []);
$bizName  = (string) ($biz['trade_name'] ?: 'Sara Kinetic Sports Lab');
$bizLegal = (string) ($biz['legal_name'] ?: $bizName);
$bizEmail = (string) ($biz['support_email'] ?? '');
$bizPhone = (string) ($biz['phone'] ?? '');
$bizAddr  = trim(implode(', ', array_filter([
    (string) ($biz['address_line1'] ?? ''),
    (string) ($biz['address_line2'] ?? ''),
    (string) ($biz['city'] ?? ''),
    trim(((string) ($biz['state_name'] ?? '')) . ' ' . ((string) ($biz['pincode'] ?? ''))),
])));
$policyUpdated = '26 September 2026';
?>
<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="bg-white border border-slate-200/90 rounded-3xl p-8 md:p-12 shadow-xs">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Legal Document</span>
        <h1 class="text-3xl font-extrabold text-slate-900 font-heading mt-2 mb-2">Privacy Policy</h1>
        <p class="text-xs text-slate-500 mb-8">Last updated: <?= h($policyUpdated) ?></p>

        <div class="space-y-6 text-sm text-slate-600 leading-relaxed">
            <p>
                <?= h($bizLegal) ?> (&ldquo;SKSL&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo;) operates the recovery facility and this online booking
                platform. This policy explains what personal data we collect, why, who processes it on our behalf, how long we keep it,
                and the choices you have. It is written to comply with India&rsquo;s Digital Personal Data Protection Act, 2023 and the
                Information Technology Act, 2000.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">1. Information we collect</h2>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li><strong>Account details</strong> &mdash; your full name, email address and mobile number, plus a one-way hash of your password (we never store the password itself). We also record when you accepted these terms.</li>
                <li><strong>Booking details</strong> &mdash; the service, date, time slot, price and GST amounts for every reservation, the booking reference, and the resulting tax invoice.</li>
                <li><strong>Payment references</strong> &mdash; the Razorpay order ID, payment ID and payment status. We never receive or store card numbers, UPI PINs, CVVs or bank credentials.</li>
                <li><strong>Health declaration</strong> &mdash; your confirmation, made when booking, that you have read the safety guidance and have no listed contraindication. We do not collect medical records.</li>
                <li><strong>Technical data</strong> &mdash; your IP address and the time of login and booking attempts, used only for security (rate limiting and abuse prevention) and standard web-server logs.</li>
                <li><strong>Email delivery records</strong> &mdash; the recipient, type, subject and outcome (sent/failed) of the operational emails we send you. Email bodies are not stored.</li>
            </ul>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">2. How we use it</h2>
            <p>Your data is used strictly to:</p>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li>create and secure your account and let you sign in;</li>
                <li>reserve, confirm, reschedule or cancel your recovery sessions and manage facility capacity;</li>
                <li>take payment and issue the GST tax invoice the law requires us to keep;</li>
                <li>send essential service emails &mdash; booking confirmations with your invoice, password-reset links, and notices about your account. We do not send marketing email;</li>
                <li>verify your identity at reception and keep athletes safe in thermal and hydrotherapy modalities;</li>
                <li>detect and block abuse (repeated failed logins, automated booking attempts).</li>
            </ul>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">3. Who processes your data for us</h2>
            <p>We share data only with the service providers needed to run the platform, each acting on our instructions:</p>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li><strong>Razorpay Software Pvt. Ltd.</strong> (payments). When you pay, we pass your name, email address, mobile number, the booking reference and the amount to Razorpay so the checkout can be prefilled and the payment matched to your booking. Card and bank data is entered directly into Razorpay&rsquo;s PCI-DSS certified checkout and never touches our servers. Razorpay&rsquo;s own privacy policy applies to that processing.</li>
                <li><strong>Our email provider</strong> (SMTP). Transactional emails are relayed through the email service configured by SKSL; it receives your email address, name and the message content in order to deliver it.</li>
                <li><strong>Our hosting provider</strong>. The website, database and invoice files are stored on servers operated for SKSL. Access is restricted to authorised SKSL staff.</li>
            </ul>
            <p>We do not sell personal data and we do not use advertising networks, analytics trackers or social-media pixels on this site. Typefaces are served from our own domain, so ordinary page views make no requests to third parties; only the payment step contacts Razorpay.</p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">4. Cookies</h2>
            <p>
                We set one cookie: the <code>PHPSESSID</code> session cookie that keeps you signed in and protects forms against cross-site request forgery.
                It is <em>HttpOnly</em> (not readable by scripts) and <em>SameSite=Strict</em> (not sent on cross-site requests). On the live site, which is served over HTTPS,
                it is also marked <em>Secure</em>. It expires when you close your browser or sign out. There are no third-party, advertising or tracking cookies.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">5. Security</h2>
            <p>
                Passwords are stored as bcrypt hashes. Admin access uses one-time email codes. All traffic to the live site is encrypted with TLS. Sessions opened before a
                password change are invalidated automatically. Uploads, logs and invoices are kept outside the public web root. No system is perfectly secure; if we learn of a
                breach affecting your data we will notify you and the Data Protection Board as the law requires.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">6. How long we keep data</h2>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li><strong>Bookings, payments and tax invoices</strong> &mdash; retained for the period required by the GST and Income-tax laws (currently up to 8 years from the end of the financial year), even after an account is closed.</li>
                <li><strong>Account details</strong> &mdash; kept while your account is active and for up to 12 months after your last booking, unless you ask us to delete them sooner.</li>
                <li><strong>Security logs</strong> (login attempts, rate limits, email delivery records) &mdash; deleted or anonymised within 90 days.</li>
                <li><strong>Password-reset tokens and payment holds</strong> &mdash; expire within an hour and are purged automatically.</li>
            </ul>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">7. Your rights and choices</h2>
            <p>
                You can view and correct your name and mobile number and change your password at any time from
                <a href="<?= app_url('profile') ?>" class="text-sky-600 font-semibold hover:underline">My Profile</a>. You may also ask us to:
            </p>
            <ul class="list-disc pl-5 space-y-1.5 text-slate-600">
                <li>provide a copy of the personal data we hold about you;</li>
                <li>correct inaccurate data;</li>
                <li>delete your account and personal data (we will keep only the invoice and payment records the tax laws require, and remove your account within 30 days of the request);</li>
                <li>raise a grievance about how your data has been handled.</li>
            </ul>
            <p>
                Send requests from the email address on your account to the contact below. We respond within 30 days. If you are not satisfied with our response you may
                complain to the Data Protection Board of India.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">8. Children</h2>
            <p>
                Our services involve intense thermal exposure and are intended for adults. Accounts may only be created by persons aged 18 or over; a parent or guardian must
                book and accompany younger athletes in person.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">9. Changes to this policy</h2>
            <p>
                We will post any changes on this page and update the date at the top. Material changes will also be announced in the booking portal.
            </p>

            <h2 class="text-lg font-bold text-slate-900 font-heading mt-6">10. Contact &amp; grievance officer</h2>
            <p>
                <?= h($bizLegal) ?><?php if ($bizAddr !== ''): ?>, <?= h($bizAddr) ?><?php endif; ?>.
                <?php if ($bizEmail !== ''): ?>
                    Email: <a href="mailto:<?= h($bizEmail) ?>" class="text-sky-600 font-semibold hover:underline"><?= h($bizEmail) ?></a>.
                <?php endif; ?>
                <?php if ($bizPhone !== ''): ?>
                    Phone: <a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $bizPhone)) ?>" class="text-sky-600 font-semibold hover:underline"><?= h($bizPhone) ?></a>.
                <?php endif; ?>
                You can also reach us through <a href="<?= app_url('contact') ?>" class="text-sky-600 font-semibold hover:underline">our contact page</a>.
            </p>
        </div>
    </div>
</div>
