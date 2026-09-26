<?php

/**
 * Shared helpers for payment-related test scripts.
 *
 * The application never skips signature verification. Tests therefore sign
 * exactly the way Razorpay would, using the same secret the server uses.
 */

declare(strict_types=1);

use App\Services\PaymentService;

const SKSL_TEST_KEY_SECRET     = 'sksl-test-key-secret';
const SKSL_TEST_WEBHOOK_SECRET = 'sksl-test-webhook-secret';

/**
 * PaymentService in offline mock mode with known secrets.
 * Order creation makes no network calls; signature checks are fully active.
 */
function sksl_test_payment_service(): PaymentService
{
    return new PaymentService(null, null, null, null, null, null, [
        'key_id'         => 'rzp_test_offline',
        'key_secret'     => SKSL_TEST_KEY_SECRET,
        'webhook_secret' => SKSL_TEST_WEBHOOK_SECRET,
        'mock'           => true,
    ]);
}

/** Checkout signature as Razorpay would compute it for the offline test service. */
function sksl_test_sign(string $orderId, string $paymentId): string
{
    return PaymentService::signCheckout($orderId, $paymentId, SKSL_TEST_KEY_SECRET);
}

/** Webhook signature for a raw JSON body, for the offline test service. */
function sksl_test_sign_webhook(string $rawBody): string
{
    return hash_hmac('sha256', $rawBody, SKSL_TEST_WEBHOOK_SECRET);
}

/**
 * Secret the RUNNING web server (Apache/php -S) uses for checkout signatures.
 * Mirrors PaymentService::signingSecret(): real key secret if configured,
 * otherwise the local mock secret (only valid when APP_ENV is local/testing).
 */
function sksl_server_checkout_secret(): string
{
    $secret = trim((string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? ''));
    return $secret !== '' ? $secret : 'sksl-local-mock-signing-secret';
}

function sksl_server_sign(string $orderId, string $paymentId): string
{
    return PaymentService::signCheckout($orderId, $paymentId, sksl_server_checkout_secret());
}
