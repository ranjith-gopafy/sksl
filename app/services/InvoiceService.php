<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookingModel;
use App\Models\PaymentModel;
use Mpdf\Mpdf;

/**
 * Invoice Service
 *
 * Generates compliant GST Tax Invoices in PDF format using mPDF.
 * Implements SAC Code 999723 (Physical fitness and athletic recovery services)
 * with 9% CGST + 9% SGST itemized breakdowns.
 */
class InvoiceService
{
    private \PDO $db;
    private BookingModel $bookingModel;
    private PaymentModel $paymentModel;
    private string $storageDir;
    private string $tempDir;

    public function __construct(
        ?\PDO $db = null,
        ?BookingModel $bookingModel = null,
        ?PaymentModel $paymentModel = null
    ) {
        $this->db           = $db ?? getDb();
        $this->bookingModel = $bookingModel ?? new BookingModel();
        $this->paymentModel = $paymentModel ?? new PaymentModel();

        $this->storageDir = dirname(__DIR__, 2) . '/storage/invoices';
        $this->tempDir    = $this->storageDir . '/tmp';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0775, true);
        }
    }

    /**
     * Compile authoritative invoice data.
     *
     * @return array<string, mixed>|null
     */
    public function getInvoiceData(string $bookingReference): ?array
    {
        $booking = $this->bookingModel->findByReference($bookingReference);
        if (!$booking) {
            return null;
        }

        $payment = $this->paymentModel->findByBookingId((int) $booking['id']);

        $baseAmount = (float) $booking['base_amount'];
        $gstAmount  = (float) $booking['gst_amount'];
        $cgstAmount = round($gstAmount / 2, 2);
        $sgstAmount = round($gstAmount - $cgstAmount, 2); // Avoid rounding split discrepancy
        $totalAmount= (float) $booking['total_amount'];

        $createdTs  = strtotime($booking['created_at'] ?? 'now');
        $invoiceNo  = 'INV-' . date('Ymd', $createdTs) . '-' . strtoupper(substr(hash('crc32b', $bookingReference), 0, 6));

        return [
            'invoice_number'       => $invoiceNo,
            'invoice_date'         => date('d-m-Y', $createdTs),
            'booking_reference'    => $booking['booking_reference'],
            'booking_date'         => date('d-m-Y', strtotime($booking['booking_date'])),
            'slot_window'          => date('h:i A', strtotime($booking['start_time'])) . ' – ' . date('h:i A', strtotime($booking['end_time'])) . ' IST',
            'service_name'         => $booking['service_name'],
            'duration_minutes'     => (int) $booking['service_duration_minutes'],
            'customer_name'        => $booking['user_name'],
            'customer_email'       => $booking['user_email'],
            'customer_mobile'      => $booking['user_mobile'],
            'sac_code'             => '999723',
            'base_amount'          => $baseAmount,
            'cgst_percent'         => 9.0,
            'cgst_amount'          => $cgstAmount,
            'sgst_percent'         => 9.0,
            'sgst_amount'          => $sgstAmount,
            'total_amount'         => $totalAmount,
            'payment_status'       => $booking['payment_status'] ?? 'paid',
            'payment_method'       => 'Razorpay Online Gateway',
            'razorpay_payment_id'  => $payment['razorpay_payment_id'] ?? 'ONLINE-GATEWAY',
            'razorpay_order_id'    => $payment['razorpay_order_id'] ?? 'N/A',
            'business_name'        => 'Sara Kinetic Sports Lab',
            'business_tagline'     => 'Recover. Recharge. Perform.',
            'business_address'     => 'Athletic Recovery & Thermal Therapy Center, Bengaluru, Karnataka, India',
            'business_gstin'       => '33AATCS1234F1Z9',
            'business_contact'     => 'support@sk-sports-lab.com | +91 98765 43210',
        ];
    }

    /**
     * Render Tax Invoice HTML suitable for PDF export.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Tax Invoice - <?= htmlspecialchars($data['invoice_number'], ENT_QUOTES, 'UTF-8') ?></title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    font-size: 11pt;
                    color: #1e293b;
                    line-height: 1.4;
                    margin: 0;
                    padding: 0;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 25px;
                    border-bottom: 2px solid #0284c7;
                    padding-bottom: 15px;
                }
                .brand-title {
                    font-size: 18pt;
                    font-weight: bold;
                    color: #0369a1;
                    margin: 0;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .brand-tagline {
                    font-size: 9pt;
                    color: #64748b;
                    margin-top: 2px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .invoice-title {
                    font-size: 16pt;
                    font-weight: bold;
                    color: #0f172a;
                    text-align: right;
                    margin: 0;
                }
                .meta-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                .meta-box {
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 12px;
                    font-size: 9.5pt;
                }
                .section-title {
                    font-size: 9pt;
                    font-weight: bold;
                    color: #0369a1;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 6px;
                    border-bottom: 1px solid #cbd5e1;
                    padding-bottom: 3px;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                    margin-bottom: 20px;
                }
                .items-table th {
                    background-color: #0f172a;
                    color: #ffffff;
                    font-size: 8.5pt;
                    font-weight: bold;
                    text-transform: uppercase;
                    padding: 8px 10px;
                    text-align: left;
                }
                .items-table td {
                    border-bottom: 1px solid #e2e8f0;
                    padding: 9px 10px;
                    font-size: 9.5pt;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .totals-table {
                    width: 45%;
                    margin-left: auto;
                    border-collapse: collapse;
                    margin-bottom: 25px;
                }
                .totals-table td {
                    padding: 5px 8px;
                    font-size: 9.5pt;
                }
                .totals-table tr.grand-total td {
                    border-top: 2px solid #0f172a;
                    border-bottom: 2px solid #0f172a;
                    font-weight: bold;
                    font-size: 11pt;
                    color: #0369a1;
                    padding-top: 8px;
                    padding-bottom: 8px;
                }
                .payment-badge {
                    background-color: #ecfdf5;
                    border: 1px solid #10b981;
                    color: #047857;
                    font-weight: bold;
                    font-size: 9pt;
                    padding: 4px 10px;
                    border-radius: 4px;
                    display: inline-block;
                }
                .footer {
                    margin-top: 35px;
                    padding-top: 15px;
                    border-top: 1px solid #e2e8f0;
                    font-size: 8pt;
                    color: #64748b;
                    text-align: center;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <table class="header-table">
                <tr>
                    <td style="width: 60%; vertical-align: top;">
                        <?php
                        $logoPath = dirname(__DIR__, 2) . '/public/images/sksl-logo.png';
                        if (file_exists($logoPath)):
                        ?>
                            <img src="<?= $logoPath ?>" style="height: 45px; width: auto; margin-bottom: 8px;" alt="SKSL Logo"><br>
                        <?php endif; ?>
                        <div class="brand-title"><?= htmlspecialchars($data['business_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="brand-tagline"><?= htmlspecialchars($data['business_tagline'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size: 8.5pt; color: #475569; margin-top: 6px;">
                            <?= htmlspecialchars($data['business_address'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>GSTIN:</strong> <?= htmlspecialchars($data['business_gstin'], ENT_QUOTES, 'UTF-8') ?> | 
                            <strong>SAC:</strong> <?= htmlspecialchars($data['sac_code'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </td>
                    <td style="width: 40%; vertical-align: top; text-align: right;">
                        <div class="invoice-title">TAX INVOICE</div>
                        <div style="font-size: 9.5pt; color: #0f172a; font-weight: bold; margin-top: 4px;">
                            # <?= htmlspecialchars($data['invoice_number'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="font-size: 8.5pt; color: #64748b; margin-top: 2px;">
                            Date: <?= htmlspecialchars($data['invoice_date'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="margin-top: 6px;">
                            <span class="payment-badge">&bull; PAYMENT RECEIVED</span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Customer & Session Metadata -->
            <table class="meta-table">
                <tr>
                    <td style="width: 48%; vertical-align: top;">
                        <div class="meta-box">
                            <div class="section-title">Billed Athlete (Customer)</div>
                            <strong><?= htmlspecialchars($data['customer_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                            Email: <?= htmlspecialchars($data['customer_email'], ENT_QUOTES, 'UTF-8') ?><br>
                            Phone: <?= htmlspecialchars($data['customer_mobile'], ENT_QUOTES, 'UTF-8') ?><br>
                            Place of Supply: Karnataka (33)
                        </div>
                    </td>
                    <td style="width: 4%;"></td>
                    <td style="width: 48%; vertical-align: top;">
                        <div class="meta-box">
                            <div class="section-title">Appointment Details</div>
                            <strong>Booking Ref:</strong> <?= htmlspecialchars($data['booking_reference'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Appointment Date:</strong> <?= htmlspecialchars($data['booking_date'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Reserved Window:</strong> <?= htmlspecialchars($data['slot_window'], ENT_QUOTES, 'UTF-8') ?><br>
                            <strong>Session Duration:</strong> <?= (int) $data['duration_minutes'] ?> Minutes
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Itemized GST Breakdown Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 45%;">Service Description</th>
                        <th style="width: 12%;" class="text-center">SAC</th>
                        <th style="width: 18%;" class="text-right">Taxable Value (₹)</th>
                        <th style="width: 20%;" class="text-right">Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center">1</td>
                        <td>
                            <strong><?= htmlspecialchars($data['service_name'], ENT_QUOTES, 'UTF-8') ?> Recovery Session</strong><br>
                            <span style="font-size: 8pt; color: #64748b;">
                                Single Athlete Modality Reservation (<?= (int) $data['duration_minutes'] ?> mins)
                            </span>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($data['sac_code'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-right"><?= number_format((float) $data['base_amount'], 2) ?></td>
                        <td class="text-right"><strong><?= number_format((float) $data['total_amount'], 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals & Tax Breakdown -->
            <table class="totals-table">
                <tr>
                    <td class="text-right" style="color: #64748b;">Taxable Base:</td>
                    <td class="text-right">&#8377;<?= number_format((float) $data['base_amount'], 2) ?></td>
                </tr>
                <tr>
                    <td class="text-right" style="color: #64748b;">CGST (9.0%):</td>
                    <td class="text-right">&#8377;<?= number_format((float) $data['cgst_amount'], 2) ?></td>
                </tr>
                <tr>
                    <td class="text-right" style="color: #64748b;">SGST (9.0%):</td>
                    <td class="text-right">&#8377;<?= number_format((float) $data['sgst_amount'], 2) ?></td>
                </tr>
                <tr class="grand-total">
                    <td class="text-right">Total Amount Paid:</td>
                    <td class="text-right">&#8377;<?= number_format((float) $data['total_amount'], 2) ?></td>
                </tr>
            </table>

            <!-- Payment Gateway Audit -->
            <div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 10px; font-size: 8.5pt; color: #475569; margin-bottom: 20px;">
                <strong>Payment Gateway Settlement Details:</strong><br>
                Method: <?= htmlspecialchars($data['payment_method'], ENT_QUOTES, 'UTF-8') ?> &bull; 
                Razorpay Payment ID: <span style="font-family: monospace;"><?= htmlspecialchars($data['razorpay_payment_id'], ENT_QUOTES, 'UTF-8') ?></span> &bull; 
                Razorpay Order ID: <span style="font-family: monospace;"><?= htmlspecialchars($data['razorpay_order_id'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <!-- Legal Footer -->
            <div class="footer">
                This is a computer-generated tax invoice issued in accordance with the Central Goods and Services Tax Act, 2017.<br>
                For questions or support, contact <?= htmlspecialchars($data['business_contact'], ENT_QUOTES, 'UTF-8') ?>.<br>
                Sara Kinetic Sports Lab &bull; Recover. Recharge. Perform.
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Generate PDF invoice file and save to disk.
     * Returns absolute path to generated PDF.
     */
    public function generateInvoicePdf(string $bookingReference): string
    {
        $outputPath = $this->storageDir . '/' . $bookingReference . '.pdf';

        // Cache hit: return if already generated
        if (file_exists($outputPath) && filesize($outputPath) > 1000) {
            return $outputPath;
        }

        $data = $this->getInvoiceData($bookingReference);
        if (!$data) {
            throw new \RuntimeException('Booking reference not found for invoice generation: ' . $bookingReference);
        }

        $html = $this->renderHtml($data);

        $mpdf = new Mpdf([
            'tempDir'       => $this->tempDir,
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
        ]);

        $mpdf->SetTitle('SKSL Tax Invoice - ' . $data['invoice_number']);
        $mpdf->SetAuthor('Sara Kinetic Sports Lab');
        $mpdf->WriteHTML($html);
        $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

        return $outputPath;
    }

    /**
     * Stream PDF invoice to browser for download.
     */
    public function downloadInvoice(string $bookingReference, int $userId): void
    {
        $booking = $this->bookingModel->findByReference($bookingReference);
        if (!$booking) {
            http_response_code(404);
            echo 'Invoice not found.';
            exit;
        }

        // Customer access authorization (or admin if userId == 0)
        if ($userId > 0 && (int) $booking['user_id'] !== $userId) {
            http_response_code(403);
            echo 'Unauthorized invoice access.';
            exit;
        }

        // Restrict invoice download: tax invoices are not issued for cancelled or pending bookings
        if ($booking['booking_status'] === 'cancelled' || $booking['booking_status'] === 'pending') {
            http_response_code(403);
            echo 'Tax Invoice is not available for ' . htmlspecialchars($booking['booking_status']) . ' bookings.';
            exit;
        }

        $pdfPath = $this->generateInvoicePdf($bookingReference);

        if (!file_exists($pdfPath)) {
            http_response_code(500);
            echo 'Failed to generate invoice file.';
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="SKSL_Invoice_' . $bookingReference . '.pdf"');
        header('Content-Length: ' . (string) filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($pdfPath);
        exit;
    }
}
