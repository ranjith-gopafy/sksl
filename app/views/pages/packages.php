<?php
/**
 * SKSL — Combo Packages & Modality Bundles Page
 */
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <!-- Header -->
    <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Elite Recovery Bundles</span>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 font-heading mt-2">
            Combo Recovery Packages
        </h1>
        <p class="text-slate-600 text-sm sm:text-base mt-2.5 leading-relaxed">
            Pre-purchase multi-session recovery bundles at exclusive package rates. Enjoy full schedule flexibility — purchase your bundle once and book your individual modality sessions whenever you train.
        </p>
    </div>

    <!-- Quick Navigation / My Packages CTA for logged in users -->
    <div class="flex items-center justify-between mb-8 pb-4 border-b border-slate-200">
        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">
            Available Bundles (<?= count($packages) ?>)
        </div>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <a 
                href="<?= app_url('my-packages') ?>" 
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#075183] hover:text-[#4A9CC0] transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                View My Package Credits &rarr;
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($packages)): ?>
        <div class="text-center py-16 bg-white border border-[#D9DBDA] rounded-3xl p-8 shadow-xs">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 font-heading">No Packages Currently Available</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                Check back shortly or contact our front desk for bespoke athlete conditioning bundles.
            </p>
        </div>
    <?php else: ?>
        <div id="pkg-notice" role="alert" class="hidden max-w-3xl mx-auto mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold"></div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($packages as $pkg): 
                // packages.price is the GST-inclusive price the customer pays.
                $split = \App\Models\ServiceModel::calculatePricing((float) $pkg['price'], (float) ($pkg['gst_percent'] ?? 18.00));
                $base = $split['base_price'];
                $total = $split['total_amount'];
                $items = $pkg['items'] ?? [];
                $totalCredits = array_sum(array_column($items, 'quantity'));
            ?>
                <div class="bg-white border border-[#D9DBDA] rounded-3xl p-6 sm:p-7 shadow-xs hover:shadow-xl hover:border-[#4A9CC0] transition-all flex flex-col justify-between relative group">
                    <?php if (!empty($pkg['badge_text'])): ?>
                        <div class="absolute -top-3 right-6">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-[#D6981E] text-slate-900 shadow-sm">
                                <?= h($pkg['badge_text']) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#075183]/10 text-[#075183]">
                                <?= (int) $pkg['validity_days'] ?> Days Validity
                            </span>
                            <span class="text-xs text-slate-400 font-semibold">&bull;</span>
                            <span class="text-xs text-slate-600 font-bold">
                                <?= $totalCredits ?> Sessions Included
                            </span>
                        </div>

                        <h3 class="text-xl font-extrabold text-slate-900 font-heading leading-tight mt-1">
                            <?= h($pkg['name']) ?>
                        </h3>

                        <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                            <?= h($pkg['description'] ?: 'Curated athletic recovery protocol combining high-impact restoration modalities.') ?>
                        </p>

                        <!-- Included Sessions Breakdown -->
                        <div class="mt-5 pt-4 border-t border-slate-100">
                            <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-2.5">
                                Included Modality Credits:
                            </div>
                            <div class="space-y-2">
                                <?php foreach ($items as $item): ?>
                                    <div class="flex items-center justify-between text-xs py-1 px-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                        <span class="font-medium text-slate-800"><?= h($item['service_name']) ?> <span class="text-slate-500">&middot; <?= (int) $item['duration_minutes'] ?> min</span></span>
                                        <span class="font-bold text-[#075183] bg-white px-2 py-0.5 rounded-lg border border-slate-200">
                                            &times; <?= (int) $item['quantity'] ?> <?= ((int)$item['quantity'] === 1) ? 'session' : 'sessions' ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Price & Buy Button -->
                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <div class="flex items-baseline justify-between mb-4">
                            <div>
                                <span class="text-2xl font-black text-slate-900 font-heading">&#8377;<?= number_format($total, $total == floor($total) ? 0 : 2) ?></span>
                                <span class="text-[10px] uppercase font-bold text-slate-500 ml-1">incl. GST</span>
                            </div>
                            <div class="text-[11px] text-slate-500 font-medium">
                                Taxable value &#8377;<?= number_format($base, 2) ?>
                            </div>
                        </div>

                        <?php if (empty($_SESSION['user_id'])): ?>
                            <a 
                                href="<?= app_url('login?redirect=' . urlencode('packages')) ?>" 
                                class="w-full py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider text-center block shadow-md transition-all"
                            >
                                Login to Purchase
                            </a>
                        <?php else: ?>
                            <button 
                                type="button" 
                                class="buy-pkg-btn w-full py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider text-center shadow-md transition-all cursor-pointer"
                                data-id="<?= (int) $pkg['id'] ?>"
                                data-name="<?= h($pkg['name']) ?>"
                            >
                                <?= \App\Helpers\OfflinePayment::offlineMode() ? 'Request Bundle' : 'Purchase Bundle' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!\App\Helpers\OfflinePayment::offlineMode()): ?>
<!-- Razorpay Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const buyBtns = document.querySelectorAll('.buy-pkg-btn');
    const csrfToken = '<?= \App\Helpers\Csrf::token() ?>';
    const notice = document.getElementById('pkg-notice');

    // Read an API response; a missing session, rate limit or error page becomes an actionable message.
    async function apiJson(res) {
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        if (data && typeof data === 'object') { return data; }
        let message = 'The server sent an unexpected response. Please try again.';
        if (res.status === 401 || res.status === 403) { message = 'Your session has expired. Please sign in again and retry.'; }
        else if (res.status === 429) { message = 'Too many attempts. Please wait a minute and try again.'; }
        else if (res.status >= 500) { message = 'The server had a problem. Please try again in a moment.'; }
        return { success: false, message };
    }

    // Errors are shown on the page (and announced), not in a blocking alert() box.
    function showNotice(message) {
        if (!notice) { return; }
        notice.textContent = message;
        notice.classList.remove('hidden');
        notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (window.showToast) { window.showToast(message, 'error'); }
    }

    // After the gateway was opened the money may already be taken: never invite a second payment.
    function showPaymentUncertain(reason) {
        if (!notice) { return; }
        notice.textContent = '';
        const lead = document.createElement('span');
        lead.textContent = reason + ' If money was deducted from your account, do NOT pay again: ';
        const link = document.createElement('a');
        link.href = '<?= app_url('my-packages') ?>';
        link.className = 'underline font-bold';
        link.textContent = 'check My Packages';
        const tail = document.createElement('span');
        tail.textContent = ' or contact SKSL.';
        notice.append(lead, link, tail);
        notice.classList.remove('hidden');
        notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
        buyBtns.forEach(b => { b.disabled = true; });
    }

    buyBtns.forEach(btn => {
        btn.addEventListener('click', async () => {
            const pkgId = btn.getAttribute('data-id');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Initiating...';

            try {
                const res = await fetch(`<?= app_url('api/packages') ?>/${pkgId}/purchase`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                });
                const data = await apiJson(res);

                if (!data.success) {
                    showNotice(data.message || 'Unable to initiate purchase.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                    return;
                }

                const order = data.data;

                // Offline mode: the package is requested; it activates when staff approve the payment.
                if (order.offline) {
                    window.location.href = order.redirect;
                    return;
                }

                // Handle Mock Mode for local testing
                if (order.is_mock) {
                    const verifyRes = await fetch('<?= app_url('api/packages/verify') ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({
                            razorpay_order_id: order.order_id,
                            razorpay_payment_id: order.mock_payment_id,
                            razorpay_signature: order.mock_signature
                        })
                    });
                    const verifyData = await apiJson(verifyRes);
                    if (verifyData.success) {
                        window.location.href = '<?= app_url('my-packages') ?>';
                    } else {
                        showPaymentUncertain(verifyData.message || 'Verification failed.');
                        btn.textContent = originalText;
                    }
                    return;
                }

                // Standard Razorpay Launch
                const options = {
                    key: order.key_id,
                    amount: order.amount_paise,
                    currency: order.currency,
                    name: 'Sara Kinetic Sports Lab',
                    description: order.package_name,
                    order_id: order.order_id,
                    prefill: {
                        name: order.customer.name,
                        email: order.customer.email,
                        contact: order.customer.mobile
                    },
                    theme: { color: '#075183' },
                    handler: async function (response) {
                        try {
                            const vRes = await fetch('<?= app_url('api/packages/verify') ?>', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                                body: JSON.stringify({
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_signature: response.razorpay_signature
                                })
                            });
                            const vData = await apiJson(vRes);
                            if (vData.success) {
                                window.location.href = '<?= app_url('my-packages') ?>';
                            } else {
                                showPaymentUncertain(vData.message || 'Verification error.');
                                btn.textContent = originalText;
                            }
                        } catch (err) {
                            showPaymentUncertain('We could not confirm your payment because the connection was interrupted.');
                            btn.textContent = originalText;
                        }
                    },
                    modal: {
                        ondismiss: function () {
                            btn.disabled = false;
                            btn.textContent = originalText;
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.open();
            } catch (err) {
                showNotice('An unexpected error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });
});
</script>
