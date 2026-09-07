<?php

/**
 * Application Configuration
 *
 * Reads from environment variables (loaded from .env).
 * Do NOT hard-code secrets here.
 */

return [
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL']      ?? '',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',
    'secret'   => $_ENV['APP_SECRET']   ?? '',

    // Facility operating hours (Asia/Kolkata)
    'facility' => [
        'open'  => $_ENV['FACILITY_OPEN']  ?? '06:00',
        'close' => $_ENV['FACILITY_CLOSE'] ?? '22:00',
    ],

    // Booking rules (confirmed values only)
    'booking' => [
        'hold_minutes'            => (int) ($_ENV['BOOKING_HOLD_MINUTES'] ?? 10),
        'gst_rate'                => (float) ($_ENV['GST_RATE'] ?? 18),
        'checkin_buffer_minutes'  => (int) ($_ENV['CHECKIN_BUFFER_MINUTES'] ?? 0),
        'checkout_buffer_minutes' => (int) ($_ENV['CHECKOUT_BUFFER_MINUTES'] ?? 0),
        // Pending client decisions — null means not yet confirmed
        'advance_booking_days'    => isset($_ENV['ADVANCE_BOOKING_DAYS'])
                                     ? (int) $_ENV['ADVANCE_BOOKING_DAYS']
                                     : null,
        'same_day_cutoff_minutes' => isset($_ENV['SAME_DAY_CUTOFF_MINUTES'])
                                     ? (int) $_ENV['SAME_DAY_CUTOFF_MINUTES']
                                     : null,
    ],

    // Uploads
    'upload' => [
        'max_size'   => (int) ($_ENV['UPLOAD_MAX_SIZE_BYTES'] ?? 5242880),
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_ext'  => ['jpg', 'jpeg', 'png', 'webp'],
        'path'         => dirname(__DIR__) . '/storage/uploads/services/',
    ],

    // Storage paths
    'storage' => [
        'invoices' => dirname(__DIR__) . '/storage/invoices/',
        'logs'     => dirname(__DIR__) . '/storage/logs/',
    ],
];
