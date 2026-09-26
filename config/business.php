<?php

/**
 * Business Identity Configuration
 *
 * Legal details printed on GST tax invoices, emails, and the contact page.
 * Every value comes from .env. Nothing here may be invented: a wrong GSTIN or
 * address on a tax invoice is a compliance problem, so InvoiceService refuses to
 * issue invoices in production until `gstin` passes validation.
 */

$env = static fn(string $key, string $default = ''): string => trim((string) ($_ENV[$key] ?? $default));

return [
    // Registered legal name as on the GST certificate
    'legal_name'    => $env('BUSINESS_LEGAL_NAME', 'Sara Kinetic Sports Lab'),
    'trade_name'    => $env('BUSINESS_TRADE_NAME', 'Sara Kinetic Sports Lab'),
    'tagline'       => $env('BUSINESS_TAGLINE', 'Recover. Recharge. Perform.'),

    // 15-character GSTIN, e.g. 29ABCDE1234F1Z5 (29 = Karnataka)
    'gstin'         => $env('BUSINESS_GSTIN'),
    'state_name'    => $env('BUSINESS_STATE', 'Karnataka'),
    'state_code'    => $env('BUSINESS_STATE_CODE', '29'),

    'address_line1' => $env('BUSINESS_ADDRESS_LINE1'),
    'address_line2' => $env('BUSINESS_ADDRESS_LINE2'),
    'city'          => $env('BUSINESS_CITY', 'Bengaluru'),
    'pincode'       => $env('BUSINESS_PINCODE'),

    'phone'         => $env('BUSINESS_PHONE'),
    'support_email' => $env('BUSINESS_SUPPORT_EMAIL'),
    'website'       => rtrim($env('BUSINESS_WEBSITE', $env('APP_URL')), '/'),

    // Services Accounting Code for physical well-being / fitness services
    'sac_code'      => $env('INVOICE_SAC_CODE', '999723'),
    'invoice_prefix' => $env('INVOICE_PREFIX', 'SKSL'),
];
