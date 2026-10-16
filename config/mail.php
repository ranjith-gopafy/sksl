<?php

/**
 * Mail Configuration (PHPMailer + SMTP)
 *
 * SMTP credentials must come from environment variables.
 * Never hard-code credentials here.
 */

return [
    // 'smtp' or 'log'. Empty = smtp when SMTP_HOST is set, otherwise log (dev only;
    // the log driver is refused in production).
    'driver'       => $_ENV['MAIL_DRIVER']       ?? '',
    'host'         => $_ENV['SMTP_HOST']         ?? '',
    'port'         => (int) ($_ENV['SMTP_PORT']  ?? 587),
    'username'     => $_ENV['SMTP_USERNAME']      ?? '',
    'password'     => $_ENV['SMTP_PASSWORD']      ?? '',
    'encryption'   => $_ENV['SMTP_ENCRYPTION']   ?? 'tls',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS']  ?? '',
    'from_name'    => $_ENV['MAIL_FROM_NAME']     ?? 'SKSL',
    'admin_email'  => $_ENV['ADMIN_EMAIL']        ?? '',
];
