<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Time and Date Helper for Asia/Kolkata (IST)
 *
 * Ensures all booking and business-rule calculations strictly adhere to
 * the application timezone.
 */
class TimeHelper
{
    private static ?\DateTimeZone $tz = null;

    public static function timezone(): \DateTimeZone
    {
        if (self::$tz === null) {
            self::$tz = new \DateTimeZone(config('app.timezone', 'Asia/Kolkata'));
        }
        return self::$tz;
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::timezone());
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function currentTime(): string
    {
        return self::now()->format('H:i');
    }

    /**
     * Check if a date (Y-m-d) is strictly before today in Asia/Kolkata.
     */
    public static function isPastDate(string $date): bool
    {
        return $date < self::today();
    }

    /**
     * Check if a given date and time (H:i) is already in the past.
     */
    public static function isPastDateTime(string $date, string $time): bool
    {
        if (self::isPastDate($date)) {
            return true;
        }

        if ($date === self::today()) {
            return $time <= self::currentTime();
        }

        return false;
    }

    /**
     * Format a date string (e.g. 2026-09-15 -> 15 Sep 2026).
     */
    public static function formatDate(string $date, string $format = 'd M Y'): string
    {
        try {
            $dt = new \DateTimeImmutable($date, self::timezone());
            return $dt->format($format);
        } catch (\Throwable) {
            return $date;
        }
    }

    /**
     * Format a 24-hr time (e.g. 14:30 -> 2:30 PM).
     */
    public static function formatTime(string $time, string $format = 'g:i A'): string
    {
        try {
            $dt = \DateTimeImmutable::createFromFormat('H:i', substr($time, 0, 5), self::timezone());
            return $dt ? $dt->format($format) : $time;
        } catch (\Throwable) {
            return $time;
        }
    }

    /**
     * Add minutes to a 24h time string (H:i), returning H:i.
     */
    public static function addMinutes(string $time, int $minutes): string
    {
        $dt = \DateTimeImmutable::createFromFormat('H:i', substr($time, 0, 5), self::timezone());
        if (!$dt) {
            return $time;
        }
        return $dt->modify("+{$minutes} minutes")->format('H:i');
    }
}
