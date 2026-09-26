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

    /** @return array{0:int,1:int} facility open/close as minutes since midnight */
    public static function facilityMinutes(): array
    {
        [$openH, $openM]   = array_map('intval', explode(':', (string) ($_ENV['FACILITY_OPEN'] ?? '06:00')));
        [$closeH, $closeM] = array_map('intval', explode(':', (string) ($_ENV['FACILITY_CLOSE'] ?? '22:00')));
        return [$openH * 60 + $openM, $closeH * 60 + $closeM];
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
        $step   = self::slotStep($durationMinutes);
        $starts = [];
        for ($cursor = $open; $cursor + $durationMinutes <= $close; $cursor += $step) {
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
        return substr($startTime, 0, 5) <= self::earliestStartToday();
    }
}
