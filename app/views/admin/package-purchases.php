<?php
/**
 * SKSL Admin — Package purchases (who bought which combo package, and what is left).
 *
 * @var array<int, array<string, mixed>> $purchases
 */
?>

<div class="w-full max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <p class="text-sm text-slate-600">Most recent 100 purchases, including unpaid checkouts.</p>
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
                        <td class="px-4 py-3"><?= h(ucfirst((string) $p['payment_status'])) ?></td>
                        <td class="px-4 py-3"><?= $p['payment_status'] === 'paid' ? h(date('d M Y', strtotime((string) $p['expires_at']))) : '&mdash;' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
