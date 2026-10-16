<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Offline (pay outside the website) payment mode.
 *
 * While the online payment gateway is not live, ONLINE_PAYMENTS_ENABLED=false hides it. A booking or
 * combo package is then requested without paying, stays "awaiting payment" until staff have received
 * the money (UPI / bank transfer / cash) and approve it, and only then is confirmed, invoiced and
 * emailed exactly like an online payment. Setting ONLINE_PAYMENTS_ENABLED=true brings the gateway back.
 *
 * Environment variables (see .env.example):
 *   ONLINE_PAYMENTS_ENABLED        true = Razorpay checkout, false = offline approval (default true)
 *   OFFLINE_PAYMENT_HOLD_HOURS     how long a request keeps its slot while waiting for payment (default 24)
 *   OFFLINE_PAYMENT_CUTOFF_HOURS   a slot is never held past this long before the session starts (default 2)
 *   UPI_ID, UPI_PAYEE_NAME         shown to the customer (and used for the "Pay with UPI app" link)
 *   UPI_QR_IMAGE                   optional QR picture under public/ (e.g. images/upi-qr.png)
 *   OFFLINE_PAYMENT_NOTE           optional extra line, e.g. "Send the screenshot to WhatsApp ..."
 */
final class OfflinePayment
{
    /** Payment methods staff can record when approving. */
    public const METHODS = [
        'upi'           => 'UPI',
        'bank_transfer' => 'Bank transfer',
        'cash'          => 'Cash at the centre',
        'card'          => 'Card at the centre',
        'other'         => 'Other',
    ];

    private static function env(string $key, string $default = ''): string
    {
        $raw = $_ENV[$key] ?? getenv($key);
        return ($raw === false || $raw === null) ? $default : trim((string) $raw);
    }

    /** Is the online gateway switched on? (default yes, so existing set-ups keep working) */
    public static function onlineEnabled(): bool
    {
        $raw = strtolower(self::env('ONLINE_PAYMENTS_ENABLED', 'true'));
        return !in_array($raw, ['0', 'false', 'off', 'no'], true);
    }

    public static function offlineMode(): bool
    {
        return !self::onlineEnabled();
    }

    public static function holdHours(): int
    {
        return max(1, (int) self::env('OFFLINE_PAYMENT_HOLD_HOURS', '24'));
    }

    public static function cutoffHours(): int
    {
        return max(0, (int) self::env('OFFLINE_PAYMENT_CUTOFF_HOURS', '2'));
    }

    /**
     * Until when a booking request keeps its slot: hold hours from now, but never later than
     * cutoff hours before the session starts. Null when that moment is already (almost) here, i.e.
     * the session starts too soon for staff to receive and approve a payment.
     */
    public static function bookingDueAt(string $date, string $startTime): ?\DateTimeImmutable
    {
        $now   = TimeHelper::now();
        $limit = $now->modify('+' . self::holdHours() . ' hours');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . substr($startTime, 0, 5), $now->getTimezone());
        $problems = \DateTimeImmutable::getLastErrors();
        if ($start === false || ($problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0))) {
            return null; // not a real date/time (PHP would otherwise roll month 13 into next year)
        }
        $latest = $start->modify('-' . self::cutoffHours() . ' hours');
        $due = $latest < $limit ? $latest : $limit;
        // Less than 30 minutes left to pay and be approved is not a real chance to pay.
        return $due > $now->modify('+30 minutes') ? $due : null;
    }

    public static function packageDueAt(): \DateTimeImmutable
    {
        return TimeHelper::now()->modify('+' . self::holdHours() . ' hours');
    }

    /** @return array{upi_id:string, payee:string, qr_image:string, note:string} */
    public static function info(): array
    {
        $business = (array) config('business', []);
        $qr = self::env('UPI_QR_IMAGE');
        if ($qr !== '' && !is_file(dirname(__DIR__, 2) . '/public/' . ltrim($qr, '/'))) {
            $qr = ''; // never point at a picture that is not there
        }
        return [
            'upi_id'   => self::env('UPI_ID'),
            'payee'    => self::env('UPI_PAYEE_NAME', (string) ($business['trade_name'] ?? 'Sara Kinetic Sports Lab')),
            'qr_image' => $qr,
            'note'     => self::env('OFFLINE_PAYMENT_NOTE'),
        ];
    }

    /** upi:// deep link that opens the customer's UPI app with the amount and reference filled in. */
    public static function upiLink(float $amount, string $reference): ?string
    {
        $info = self::info();
        if ($info['upi_id'] === '' || !preg_match('/^[A-Za-z0-9._\-]{2,}@[A-Za-z0-9.\-]{2,}$/', $info['upi_id'])) {
            return null;
        }
        $query = http_build_query([
            'pa' => $info['upi_id'],
            'pn' => $info['payee'],
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
            'tn' => $reference,
        ], '', '&', PHP_QUERY_RFC3986);
        // The id was validated above, so its "@" is safe to leave as is; some UPI apps read it more reliably.
        return 'upi://pay?' . str_replace('%40', '@', $query);
    }

    /**
     * QR code (SVG data URI, so it works under the page's img-src rules) of the upi:// link, i.e. a code that
     * already carries the amount and the booking reference. Null when there is no valid UPI id or drawing
     * fails; the caller then falls back to the fixed QR picture.
     */
    public static function qrDataUri(float $amount, string $reference): ?string
    {
        $link = self::upiLink($amount, $reference);
        if ($link === null || !class_exists(\chillerlan\QRCode\QRCode::class)) {
            return null;
        }
        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType'  => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
                'outputBase64' => true,
                'eccLevel'    => \chillerlan\QRCode\Common\EccLevel::M,
                'addQuietzone' => true,
                'quietzoneSize' => 2,
                'svgViewBoxSize' => 300,
                'drawLightModules' => false,
                'svgUseFillAttributes' => true,
            ]);
            $uri = (new \chillerlan\QRCode\QRCode($options))->render($link);
            return str_starts_with($uri, 'data:image/svg+xml') ? $uri : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Plain-text payment instructions for emails. */
    public static function instructionsText(float $amount, string $reference): string
    {
        $info = self::info();
        $lines = ["Amount to pay: ₹" . number_format($amount, 2) . " (incl. GST)", "Payment reference to quote: {$reference}"];
        if ($info['upi_id'] !== '') {
            $lines[] = "Pay by UPI to: {$info['upi_id']} ({$info['payee']})";
        }
        if ($info['note'] !== '') {
            $lines[] = $info['note'];
        }
        return implode("\n", $lines);
    }
}
