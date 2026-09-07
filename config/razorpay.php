<?php

/**
 * Razorpay Configuration
 *
 * NEVER expose key_secret or webhook_secret to the browser.
 * Only key_id may be sent to the frontend where required by Razorpay Checkout.
 */

return [
    'key_id'         => $_ENV['RAZORPAY_KEY_ID']         ?? '',
    'key_secret'     => $_ENV['RAZORPAY_KEY_SECRET']     ?? '',
    'webhook_secret' => $_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '',
    'currency'       => 'INR',
];
