<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Thrown when a booking is not eligible for a tax invoice
 * (unpaid, pending, cancelled, or no settled payment record).
 */
class InvoiceNotAvailableException extends \RuntimeException
{
}
