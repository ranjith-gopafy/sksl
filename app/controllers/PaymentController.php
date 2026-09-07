<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentService;
use App\Models\BookingModel;
use App\Models\PaymentModel;
use App\Middleware\CustomerAuth;
use App\Helpers\Response;
use App\Helpers\Flash;

/**
 * Payment Controller
 *
 * Handles Razorpay order creation, payment verification, webhooks, and booking confirmation.
 */
class PaymentController
{
    private PaymentService $paymentService;
    private BookingModel $bookingModel;
    private PaymentModel $paymentModel;

    public function __construct(
        ?PaymentService $paymentService = null,
        ?BookingModel $bookingModel = null,
        ?PaymentModel $paymentModel = null
    ) {
        $this->paymentService = $paymentService ?? new PaymentService();
        $this->bookingModel   = $bookingModel ?? new BookingModel();
        $this->paymentModel   = $paymentModel ?? new PaymentModel();
    }

    /**
     * POST /api/payment/create-order
     * Requires customer authentication.
     */
    public function createOrder(): void
    {
        CustomerAuth::handle();

        $userId = (int) $_SESSION['user_id'];
        $bookingReference = trim((string) ($_POST['booking_reference'] ?? ''));

        if ($bookingReference === '') {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $bookingReference = trim((string) ($json['booking_reference'] ?? ''));
            }
        }

        if ($bookingReference === '') {
            Response::error('booking_reference is required.', [], 422);
        }

        $result = $this->paymentService->createOrder($userId, $bookingReference);

        if (!$result['success']) {
            Response::error($result['message'], [], 400);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ], 200);
    }

    /**
     * POST /api/payment/verify
     * Requires customer authentication.
     */
    public function verify(): void
    {
        CustomerAuth::handle();

        $userId = (int) $_SESSION['user_id'];
        $bookingReference = trim((string) ($_POST['booking_reference'] ?? ''));
        $orderId          = trim((string) ($_POST['razorpay_order_id'] ?? ''));
        $paymentId        = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
        $signature        = trim((string) ($_POST['razorpay_signature'] ?? ''));

        if ($bookingReference === '' || $orderId === '' || $paymentId === '') {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $bookingReference = $bookingReference ?: trim((string) ($json['booking_reference'] ?? ''));
                $orderId          = $orderId ?: trim((string) ($json['razorpay_order_id'] ?? ''));
                $paymentId        = $paymentId ?: trim((string) ($json['razorpay_payment_id'] ?? ''));
                $signature        = $signature ?: trim((string) ($json['razorpay_signature'] ?? ''));
            }
        }

        if ($bookingReference === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            Response::error('Missing required payment verification details.', [], 422);
        }

        $result = $this->paymentService->verifyPayment($userId, $bookingReference, $orderId, $paymentId, $signature);

        if (!$result['success']) {
            Response::error($result['message'], [], 400);
        }

        Response::json([
            'success' => true,
            'message' => $result['message'],
            'data'    => $result['data'],
        ], 200);
    }

    /**
     * POST /api/payment/webhook
     * Public endpoint called directly by Razorpay servers.
     * Excluded from CSRF checks; signature verified using RAZORPAY_WEBHOOK_SECRET.
     */
    public function webhook(): void
    {
        $rawBody   = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

        $result = $this->paymentService->handleWebhook($rawBody, $signature);

        http_response_code($result['code'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    /**
     * GET /booking-confirmation?ref=SKSL-XXXX
     * Displays confirmed booking voucher details to the customer.
     */
    public function confirmation(): void
    {
        CustomerAuth::handle();

        $reference = trim((string) ($_GET['ref'] ?? ''));
        if ($reference === '') {
            Flash::set('error', 'No booking reference provided.');
            header('Location: ' . app_url('booking'));
            exit;
        }

        $booking = $this->bookingModel->findByReference($reference);
        if (!$booking || (int) $booking['user_id'] !== (int) $_SESSION['user_id']) {
            Flash::set('error', 'Booking record not found or access denied.');
            header('Location: ' . app_url('booking'));
            exit;
        }

        $payment = $this->paymentModel->findByBookingId((int) $booking['id']);

        $title = 'Booking Confirmation — ' . h($booking['booking_reference']);
        $viewFile = dirname(__DIR__) . '/views/pages/booking-confirmation.php';
        require dirname(__DIR__) . '/views/layouts/main.php';
    }

    /**
     * GET /bookings/{ref}/invoice
     * Download GST Tax Invoice PDF. Requires customer authentication.
     *
     * @param array<string, string> $params
     */
    public function downloadInvoice(array $params = []): void
    {
        CustomerAuth::handle();

        $reference = trim((string) ($params['ref'] ?? $_GET['ref'] ?? ''));
        if ($reference === '') {
            http_response_code(400);
            echo 'Booking reference required.';
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $invoiceService = new \App\Services\InvoiceService();
        $invoiceService->downloadInvoice($reference, $userId);
    }
}
