<?php
/**
 * SKSL Admin — Package purchases (who bought which combo package, and what is left).
 *
 * @var array<int, array<string, mixed>> $purchases
 */
?>

<div class="w-full max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <p class="text-sm text-slate-600">Most recent 100 purchases, including unpaid requests. Approve a request once its payment has arrived.</p>
        <a href="<?= app_url('admin/packages') ?>" class="text-xs font-bold uppercase tracking-wider text-sky-800 hover:underline">&larr; Back to Packages</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-3xl shadow-xs overflow-x-auto" tabindex="0" role="region" aria-label="Package purchases (scrolls sideways)">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Package</th>
                    <th class="px-4 py-3">Credits left</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3">Valid until</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No package purchases yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($purchases as $p):
                    $left = array_sum(array_column($p['credits'] ?? [], 'remaining_quantity'));
                    $all  = array_sum(array_column($p['credits'] ?? [], 'allocated_quantity'));
                ?>
                    <tr>
                        <td class="px-4 py-3 font-mono"><?= h($p['purchase_reference']) ?></td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900"><?= h($p['user_name']) ?></div>
                            <div class="text-slate-500"><?= h($p['user_email']) ?> &bull; <?= h($p['user_mobile']) ?></div>
                        </td>
                        <td class="px-4 py-3"><?= h($p['package_name_snapshot']) ?></td>
                        <td class="px-4 py-3"><?= $p['payment_status'] === 'paid' ? (int) $left . ' / ' . (int) $all : '&mdash;' ?></td>
                        <td class="px-4 py-3">&#8377;<?= number_format((float) $p['total_amount'], 2) ?></td>
                        <td class="px-4 py-3">
                            <?php $offlinePending = ($p['payment_channel'] ?? '') === 'offline' && $p['payment_status'] !== 'paid' && in_array($p['status'], ['active', 'cancelled'], true); ?>
                            <?php if ($offlinePending): ?>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full border border-amber-200 bg-amber-50 text-amber-800 text-[10px] font-bold uppercase tracking-wider" data-testid="pkg-awaiting-chip">
                                    <?= $p['status'] === 'active' ? 'Awaiting payment' : 'Lapsed unpaid' ?>
                                </span>
                                <div class="text-[10px] text-slate-500 mt-1">Pay by <?= h(date('d M, g:i A', strtotime((string) $p['payment_due_at']))) ?></div>
                                <button type="button" data-approve-open
                                        data-action="<?= h(app_url('admin/package-purchases/' . (int) $p['id'] . '/approve')) ?>"
                                        data-ref="<?= h($p['purchase_reference']) ?>"
                                        data-customer="<?= h($p['user_name'] . ' · ' . ($p['user_mobile'] ?? '')) ?>"
                                        data-session="<?= h($p['package_name_snapshot']) ?>"
                                        data-amount="<?= h(number_format((float) $p['total_amount'], 2)) ?>"
                                        class="mt-2 px-3 py-1.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold uppercase tracking-wider cursor-pointer">
                                    Approve payment
                                </button>
                            <?php else: ?>
                                <?= h(ucfirst((string) $p['payment_status'])) ?>
                                <?php if (!empty($p['payment_method'])): ?>
                                    <div class="text-[10px] text-slate-500"><?= h(\App\Helpers\OfflinePayment::METHODS[$p['payment_method']] ?? $p['payment_method']) ?> &middot; <?= h($p['payment_reference'] ?? '') ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?= $p['payment_status'] === 'paid' ? h(date('d M Y', strtotime((string) $p['expires_at']))) : '&mdash;' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="approve-dialog" class="rounded-2xl p-0 w-[calc(100%-2rem)] max-w-md shadow-2xl border border-slate-200 backdrop:bg-slate-900/50" aria-labelledby="approve-title">
    <form method="POST" id="approve-form" class="p-6 space-y-4">
        <?= \App\Helpers\Csrf::field() ?>
        <h2 id="approve-title" class="text-lg font-extrabold font-heading text-slate-900">Approve payment</h2>
        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm space-y-1">
            <div class="flex justify-between gap-3"><span class="text-slate-600">Purchase</span><span id="approve-ref" class="font-mono font-bold"></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-600">Customer</span><span id="approve-customer" class="font-semibold text-right"></span></div>
            <div class="flex justify-between gap-3"><span class="text-slate-600">Package</span><span id="approve-session" class="font-semibold text-right"></span></div>
            <div class="flex justify-between gap-3 border-t border-slate-200 pt-1.5"><span class="font-bold text-slate-800">Amount to have received</span><span id="approve-amount" class="font-extrabold"></span></div>
        </div>
        <div>
            <label for="approve-method" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">How was it paid?</label>
            <select id="approve-method" name="payment_method" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <?php foreach (\App\Helpers\OfflinePayment::METHODS as $code => $label): ?>
                    <option value="<?= h($code) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="approve-reference" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Payment reference</label>
            <input id="approve-reference" name="payment_reference" type="text" required maxlength="100" autocomplete="off"
                   placeholder="UPI transaction id, bank reference or receipt number"
                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <p class="text-[11px] text-slate-500 mt-1">Required. It is kept on the purchase and shown on the invoice.</p>
        </div>
        <p class="text-xs text-slate-600">Approving activates the session credits, creates the GST invoice and emails the customer exactly as an online payment would. Validity starts today.</p>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <button type="button" id="approve-cancel" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 font-semibold text-sm hover:bg-slate-50 cursor-pointer">Not yet</button>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm cursor-pointer">Approve &amp; activate package</button>
        </div>
    </form>
</dialog>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const dlg = document.getElementById('approve-dialog');
    if (!dlg || typeof dlg.showModal !== 'function') return;
    const $ = (id) => document.getElementById(id);
    document.querySelectorAll('[data-approve-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;
            $('approve-form').action = d.action;
            $('approve-ref').textContent = d.ref;
            $('approve-customer').textContent = d.customer;
            $('approve-session').textContent = d.session;
            $('approve-amount').textContent = '\u20B9' + d.amount;
            $('approve-reference').value = '';
            dlg.showModal();
            $('approve-reference').focus();
        });
    });
    $('approve-cancel').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
})();
</script>
