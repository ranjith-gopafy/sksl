<?php
/**
 * "Pay by UPI" box shown while a booking or combo package waits for its offline payment.
 *
 * @var float  $offAmount     amount to pay (incl. GST)
 * @var string $offReference  booking / purchase reference to quote
 * @var string $offDueAt      payment deadline (Y-m-d H:i:s)
 * @var string $offKind       'booking' | 'package' (wording only)
 */
$offInfo = \App\Helpers\OfflinePayment::info();
$offLink = \App\Helpers\OfflinePayment::upiLink((float) $offAmount, (string) $offReference);
$offDue  = $offDueAt !== '' ? date('d M Y, g:i A', strtotime($offDueAt)) . ' IST' : '';
?>
<div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 sm:p-6" data-testid="offline-payment-box">
    <div class="flex flex-col sm:flex-row gap-5 sm:gap-6">
        <?php if ($offInfo['qr_image'] !== ''): ?>
            <div class="shrink-0 mx-auto sm:mx-0">
                <img src="<?= h(app_url($offInfo['qr_image'])) ?>" alt="UPI QR code for <?= h($offInfo['payee']) ?>"
                     width="220" class="w-52 sm:w-56 h-auto rounded-xl border border-amber-200 bg-white" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0 text-sm text-slate-800">
            <h2 class="font-heading text-base font-extrabold text-slate-900">Pay <span class="text-[#075183]">&#8377;<?= number_format((float) $offAmount, 2) ?></span> by UPI</h2>
            <ol class="mt-3 space-y-2 text-[13px] leading-relaxed list-decimal pl-5">
                <li>
                    Scan the QR code with any UPI app<?php if ($offInfo['upi_id'] !== ''): ?>, or pay to
                    <strong class="font-mono break-all" data-testid="upi-id"><?= h($offInfo['upi_id']) ?></strong>
                    <?php if ($offInfo['payee'] !== ''): ?>(<?= h($offInfo['payee']) ?>)<?php endif; ?><?php endif; ?>.
                </li>
                <li>Enter exactly <strong>&#8377;<?= number_format((float) $offAmount, 2) ?></strong> and write <strong class="font-mono"><?= h($offReference) ?></strong> in the payment note.</li>
                <li>
                    <?= $offKind === 'package' ? 'Your package credits are activated' : 'Your booking is confirmed' ?>
                    and your tax invoice is emailed as soon as we have received the payment.
                </li>
            </ol>
            <?php if ($offLink !== null): ?>
                <a href="<?= h($offLink) ?>" class="sm:hidden mt-4 inline-flex w-full items-center justify-center px-4 py-3 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider">
                    Open my UPI app
                </a>
            <?php endif; ?>
            <?php if ($offDue !== ''): ?>
                <p class="mt-4 text-[13px] font-semibold text-amber-900" data-testid="pay-by">
                    Please pay before <?= h($offDue) ?>.
                    <?php if ($offKind !== 'package'): ?>
                        <span class="font-normal text-amber-800">Your slot is reserved until then; unpaid requests are released automatically.</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if ($offInfo['note'] !== ''): ?>
                <p class="mt-2 text-[12px] text-slate-700"><?= h($offInfo['note']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
