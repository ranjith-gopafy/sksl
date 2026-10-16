<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Booking Rules — the single source of truth for the booking window, the
 * same-day cutoff, turnaround buffers and the slot grid.
 *
 * Before this class existed the date picker, the availability API and the
 * hold endpoint each had their own idea of these rules (audit: "Booking rules
 * disagree with each other"). Every surface must now read from here.
 *
 * Environment variables (see .env.example):
 *   ADVANCE_BOOKING_DAYS        how far ahead a session may be booked (default 30)
 *   SAME_DAY_CUTOFF_MINUTES     minimum lead time before a slot starts   (default 0)
 *   CHECKIN_BUFFER_MINUTES /
 *   CHECKOUT_BUFFER_MINUTES /
 *   BUFFER_MINUTES              turnaround between consecutive sessions  (default 0)
 *   MAX_ACTIVE_HOLDS_PER_USER   simultaneous unpaid holds per customer  (default 3)
 *   PENDING_BOOKING_EXPIRY_MINUTES  when an unpaid booking row is expired
 *                               (default: hold minutes + 30)
 *   CANCELLATION_NOTICE_HOURS   a customer may cancel up to this long before the start (default 24)
 *   CANCELLATION_FEE_PERCENT    share of the amount paid that is kept on a cancellation (default 10)
 */
final class BookingRules
{
    public const DEFAULT_ADVANCE_DAYS       = 30;
    public const DEFAULT_CUTOFF_MINUTES     = 0;
    public const DEFAULT_MAX_ACTIVE_HOLDS   = 3;
    public const DEFAULT_PENDING_GRACE_MINS = 30;

    private static function envInt(string $key, int $default, int $min = 0): int
    {
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null || trim((string) $raw) === '') {
            return $default;
        }
        return max($min, (int) $raw);
    }

    /** Days ahead (inclusive of today) that may be booked. */
    public static function advanceDays(): int
    {
        // MAX_ADVANCE_DAYS was the old name used by the availability API.
        $legacy = self::envInt('MAX_ADVANCE_DAYS', -1, -1);
        return self::envInt('ADVANCE_BOOKING_DAYS', $legacy >= 0 ? $legacy : self::DEFAULT_ADVANCE_DAYS, 0);
    }

    /** Last bookable calendar date (Y-m-d). */
    public static function maxDate(): string
    {
        return TimeHelper::now()->modify('+' . self::advanceDays() . ' days')->format('Y-m-d');
    }

    /** Minutes of lead time required before a same-day slot starts. */
    public static function sameDayCutoffMinutes(): int
    {
        return self::envInt('SAME_DAY_CUTOFF_MINUTES', self::DEFAULT_CUTOFF_MINUTES);
    }

    /** Hours before the start of a session until which a customer may cancel it. */
    public static function cancellationNoticeHours(): int
    {
        return max(0, self::envInt('CANCELLATION_NOTICE_HOURS', 24));
    }

    /** Percentage of the amount paid that SKSL keeps when a paid booking is cancelled (0-100). */
    public static function cancellationFeePercent(): float
    {
        $raw = $_ENV['CANCELLATION_FEE_PERCENT'] ?? getenv('CANCELLATION_FEE_PERCENT');
        if ($raw === false || $raw === null || trim((string) $raw) === '' || !is_numeric($raw)) {
            return 10.0;
        }
        return min(100.0, max(0.0, (float) $raw));
    }

    /** The moment the customer may no longer cancel: start of the session minus the notice period. */
    public static function cancellationDeadline(string $date, string $startTime): ?\DateTimeImmutable
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . substr($startTime, 0, 5), TimeHelper::now()->getTimezone());
        return $start === false ? null : $start->modify('-' . self::cancellationNoticeHours() . ' hours');
    }

    /**
     * What a customer cancellation of this booking would mean right now.
     *
     * @param array<string, mixed> $booking
     * @return array{allowed: bool, reason: string, paid_online: bool, fee: float, refund: float, deadline: ?\DateTimeImmutable}
     */
    public static function cancellationQuote(array $booking): array
    {
        $none = ['allowed' => false, 'reason' => '', 'paid_online' => false, 'fee' => 0.0, 'refund' => 0.0, 'deadline' => null];
        $status = (string) ($booking['booking_status'] ?? '');
        if ($status === 'pending' && ($booking['payment_channel'] ?? '') === 'offline') {
            // Nothing has been paid yet, so withdrawing a request costs nothing and has no notice period.
            return ['allowed' => true, 'reason' => '', 'paid_online' => false, 'fee' => 0.0, 'refund' => 0.0, 'deadline' => null];
        }
        if ($status === 'cancelled') { return ['reason' => 'This booking is already cancelled.'] + $none; }
        if ($status !== 'confirmed') { return ['reason' => 'This booking can no longer be cancelled.'] + $none; }
        if (($booking['visit_status'] ?? 'pending') !== 'pending') { return ['reason' => 'This session has already been attended or marked as a no-show.'] + $none; }

        $deadline = self::cancellationDeadline((string) $booking['booking_date'], (string) $booking['start_time']);
        $none['deadline'] = $deadline;
        $hours = self::cancellationNoticeHours();
        if ($deadline === null || TimeHelper::now() > $deadline) {
            return ['reason' => "Sessions can only be cancelled online up to {$hours} hours before they start. Please contact SKSL."] + $none;
        }

        $paid = ($booking['payment_mode'] ?? 'payment_required') === 'payment_required' && ($booking['payment_status'] ?? '') === 'paid';
        $fee = $refund = 0.0;
        if ($paid) {
            $total  = round((float) $booking['total_amount'], 2);
            $fee    = round($total * self::cancellationFeePercent() / 100, 2);
            $refund = round($total - $fee, 2);
        }
        return ['allowed' => true, 'reason' => '', 'paid_online' => $paid, 'fee' => $fee, 'refund' => $refund, 'deadline' => $deadline];
    }

    /** Turnaround minutes added after every session when laying out the grid. */
    public static function bufferMinutes(): int
    {
        $general = self::envInt('BUFFER_MINUTES', 0);
        if ($general > 0) {
            return $general;
        }
        return self::envInt('CHECKIN_BUFFER_MINUTES', 0) + self::envInt('CHECKOUT_BUFFER_MINUTES', 0);
    }

    public static function holdMinutes(): int
    {
        return max(1, self::envInt('BOOKING_HOLD_MINUTES', 10));
    }

    public static function maxActiveHoldsPerUser(): int
    {
        return max(1, self::envInt('MAX_ACTIVE_HOLDS_PER_USER', self::DEFAULT_MAX_ACTIVE_HOLDS));
    }

    /** Age (minutes since creation) after which an unpaid pending booking is expired. */
    public static function pendingBookingExpiryMinutes(): int
    {
        return max(
            self::holdMinutes(),
            self::envInt('PENDING_BOOKING_EXPIRY_MINUTES', self::holdMinutes() + self::DEFAULT_PENDING_GRACE_MINS)
        );
    }

    /**
     * A facility time from .env as minutes since midnight. A malformed value ("24:00", "9pm", "")
     * falls back to the default instead of silently producing a broken slot grid.
     */
    private static function timeEnvMinutes(string $key, string $default): int
    {
        $raw = trim((string) ($_ENV[$key] ?? $default));
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw, $m)) {
            $raw = $default;
            preg_match('/^(\d+):(\d+)$/', $raw, $m);
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /** @return array{0:int,1:int} facility open/close as minutes since midnight */
    public static function facilityMinutes(): array
    {
        $open  = self::timeEnvMinutes('FACILITY_OPEN', '06:00');
        $close = self::timeEnvMinutes('FACILITY_CLOSE', '21:00');
        if ($close <= $open) {
            // Closing at or before opening is a configuration mistake; use the standard hours.
            return [6 * 60, 21 * 60];
        }
        return [$open, $close];
    }

    /** Last permissible slot start time (default 20:00 / 8:00 PM), never later than closing time. */
    public static function lastSlotMinutes(): int
    {
        return min(self::timeEnvMinutes('FACILITY_LAST_SLOT', '20:00'), self::facilityMinutes()[1]);
    }

    /** Distance between consecutive slot starts for a service. */
    public static function slotStep(int $durationMinutes): int
    {
        return $durationMinutes + max(0, self::bufferMinutes());
    }

    /**
     * All grid start times ("HH:MM") for a service duration, identical to the
     * list the availability API renders.
     *
     * @return string[]
     */
    public static function gridStarts(int $durationMinutes): array
    {
        if ($durationMinutes <= 0) {
            return [];
        }
        [$open, $close] = self::facilityMinutes();
        $lastSlotCutoff = self::lastSlotMinutes();
        $step   = self::slotStep($durationMinutes);
        $starts = [];
        for ($cursor = $open; $cursor <= $lastSlotCutoff && $cursor + $durationMinutes <= $close; $cursor += $step) {
            $starts[] = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
        }
        return $starts;
    }

    /** True when "HH:MM" is exactly one of the grid starts for this duration. */
    public static function isOnGrid(int $durationMinutes, string $startTime): bool
    {
        return in_array(substr($startTime, 0, 5), self::gridStarts($durationMinutes), true);
    }

    /**
     * Validate the calendar date against the booking window.
     * Returns null when OK, otherwise a customer-facing message.
     */
    public static function dateWindowError(string $date): ?string
    {
        if (TimeHelper::isPastDate($date)) {
            return 'Appointments cannot be booked for past dates.';
        }
        if ($date > self::maxDate()) {
            $days = self::advanceDays();
            return "Bookings can only be made up to {$days} days in advance.";
        }
        return null;
    }

    /**
     * Earliest "HH:MM" that may still be booked today (now + cutoff).
     * A same-day slot is bookable only when its start time is strictly later.
     */
    public static function earliestStartToday(): string
    {
        return TimeHelper::now()->modify('+' . self::sameDayCutoffMinutes() . ' minutes')->format('H:i');
    }

    /** True when a slot on $date starting at $startTime is too late to book (past or inside cutoff). */
    public static function isTooLate(string $date, string $startTime): bool
    {
        if ($date < TimeHelper::today()) {
            return true;
        }
        if ($date > TimeHelper::today()) {
            return false;
        }
        // Compare full date-times: adding the cutoff to "now" can cross midnight, which a plain
        // HH:MM string comparison would silently get wrong.
        $now  = TimeHelper::now();
        $slot = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . substr($startTime, 0, 5), $now->getTimezone());
        if ($slot === false) {
            return true;
        }
        return $slot <= $now->modify('+' . self::sameDayCutoffMinutes() . ' minutes');
    }
}
