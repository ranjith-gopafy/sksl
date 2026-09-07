<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceModel;
use App\Models\ClosedDateModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Helpers\TimeHelper;

/**
 * Booking Service
 *
 * Implements authoritative booking hold creation with database transactions,
 * SELECT ... FOR UPDATE row locking for concurrency protection, exact pricing snapshots,
 * and user conflict checks.
 */
class BookingService
{
    private \PDO $db;
    private ClosedDateModel $closedDateModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;

    public function __construct(
        ?\PDO $db = null,
        ?ClosedDateModel $closedDateModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null
    ) {
        $this->db              = $db ?? getDb();
        $this->closedDateModel = $closedDateModel ?? new ClosedDateModel();
        $this->bookingModel    = $bookingModel ?? new BookingModel();
        $this->holdModel       = $holdModel ?? new BookingHoldModel();
    }

    /**
     * Create a temporary 10-minute booking hold with concurrency-safe row locking.
     *
     * @param int    $userId
     * @param int    $serviceId
     * @param string $date       Format: Y-m-d
     * @param string $startTime  Format: H:i
     * @return array{success: bool, message: string, data?: array}
     */
    public function createHold(int $userId, int $serviceId, string $date, string $startTime): array
    {
        // 1. Basic validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['success' => false, 'message' => 'Invalid date format (YYYY-MM-DD required).'];
        }

        [$y, $m, $d] = array_map('intval', explode('-', $date));
        if (!checkdate($m, $d, $y)) {
            return ['success' => false, 'message' => 'Invalid calendar date.'];
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return ['success' => false, 'message' => 'Invalid time format (HH:MM required).'];
        }

        if (TimeHelper::isPastDate($date)) {
            return ['success' => false, 'message' => 'Cannot reserve slots for past dates.'];
        }

        if ($this->closedDateModel->isDateClosed($date)) {
            return ['success' => false, 'message' => 'The facility is closed on this date.'];
        }

        $holdMinutes = (int) ($_ENV['BOOKING_HOLD_MINUTES'] ?? 10);
        if ($holdMinutes <= 0) {
            $holdMinutes = 10;
        }

        // 2. Begin Concurrency-Safe Transaction
        $this->db->beginTransaction();

        try {
            // Lock the service record to serialize hold creation attempts for this service
            $stmt = $this->db->prepare(
                'SELECT id, name, slug, price, gst_percent, duration_minutes, capacity, status
                 FROM services WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();

            if (!$service || ($service['status'] ?? '') !== 'active') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Service not found or currently inactive.'];
            }

            $durationMinutes = (int) $service['duration_minutes'];
            $capacity        = (int) $service['capacity'];

            // Calculate end time
            [$startH, $startM] = array_map('intval', explode(':', $startTime));
            $startTotalMinutes = ($startH * 60) + $startM;
            $endTotalMinutes   = $startTotalMinutes + $durationMinutes;

            // Facility operating hours check (06:00 - 22:00)
            $openStr  = $_ENV['FACILITY_OPEN'] ?? '06:00';
            $closeStr = $_ENV['FACILITY_CLOSE'] ?? '22:00';
            [$openH, $openM]   = array_map('intval', explode(':', $openStr));
            [$closeH, $closeM] = array_map('intval', explode(':', $closeStr));

            $openMinutes  = ($openH * 60) + $openM;
            $closeMinutes = ($closeH * 60) + $closeM;

            if ($startTotalMinutes < $openMinutes || $endTotalMinutes > $closeMinutes) {
                $this->db->rollBack();
                return ['success' => false, 'message' => "Slot falls outside facility hours ({$openStr} – {$closeStr} IST)."];
            }

            $endTimeStr = sprintf('%02d:%02d', intdiv($endTotalMinutes, 60), $endTotalMinutes % 60);

            // Same-day past time check
            if ($date === TimeHelper::today() && TimeHelper::currentTime() >= $startTime) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This time slot has already passed for today.'];
            }

            $dbStartTime = $startTime . ':00';
            $dbEndTime   = $endTimeStr . ':00';

            // 3. Customer Conflict Check: Does this user already have a confirmed booking or active hold overlapping this interval?
            if ($this->bookingModel->hasCustomerOverlap($userId, $date, $dbStartTime, $dbEndTime)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'You already have another confirmed booking scheduled during this time interval.',
                ];
            }

            if ($this->holdModel->hasCustomerActiveHold($userId, $date, $dbStartTime, $dbEndTime)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'You already have an active payment hold for this time interval.',
                ];
            }

            // 4. Live Capacity Recalculation inside the locked transaction
            $confirmedCount = $this->bookingModel->countActiveOverlapping($serviceId, $date, $dbStartTime, $dbEndTime);
            $activeHoldCount = $this->holdModel->countActiveOverlapping($serviceId, $date, $dbStartTime, $dbEndTime);
            $totalOccupied  = $confirmedCount + $activeHoldCount;

            if ($totalOccupied >= $capacity) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'This slot is fully booked. Another athlete just secured the last available capacity.',
                ];
            }

            // 5. Generate Unique Booking Reference (SKSL-YYYYMMDD-XXXXXX)
            $datePart = TimeHelper::now()->format('Ymd');
            $randPart = strtoupper(bin2hex(random_bytes(3)));
            $bookingRef = "SKSL-{$datePart}-{$randPart}";

            // 6. Calculate Authoritative Pricing Breakdown
            $basePrice  = (float) $service['price'];
            $gstPercent = (float) ($service['gst_percent'] ?? 18.0);
            $pricing    = ServiceModel::calculatePricing($basePrice, $gstPercent);

            // 7. Calculate Expiration Timestamp (10 minutes)
            $expiresAt = TimeHelper::now()->modify("+{$holdMinutes} minutes");
            $expiresAtDb = $expiresAt->format('Y-m-d H:i:s');

            // 8. Insert Hold Record
            $this->holdModel->create([
                'booking_reference' => $bookingRef,
                'user_id'           => $userId,
                'service_id'        => $serviceId,
                'booking_date'      => $date,
                'start_time'        => $dbStartTime,
                'end_time'          => $dbEndTime,
                'expires_at'        => $expiresAtDb,
            ]);

            // Commit the transaction to release the row lock and confirm hold
            $this->db->commit();

            return [
                'success' => true,
                'message' => "Slot temporarily held for {$holdMinutes} minutes. Please proceed to payment.",
                'data'    => [
                    'booking_reference' => $bookingRef,
                    'service_id'        => (int) $service['id'],
                    'service_name'      => $service['name'],
                    'booking_date'      => $date,
                    'start_time'        => $startTime,
                    'end_time'          => $endTimeStr,
                    'display_start'     => TimeHelper::formatTime($startTime),
                    'display_end'       => TimeHelper::formatTime($endTimeStr),
                    'duration_minutes'  => $durationMinutes,
                    'expires_at'        => $expiresAt->format('c'),
                    'expires_at_db'     => $expiresAtDb,
                    'hold_minutes'      => $holdMinutes,
                    'pricing'           => $pricing,
                ],
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('BookingService::createHold Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while reserving this slot. Please try again.',
            ];
        }
    }

    /**
     * Release an existing temporary hold if customer decides not to proceed.
     */
    public function releaseHold(int $userId, string $reference): bool
    {
        $hold = $this->holdModel->findActiveByReference($reference);
        if (!$hold || (int) $hold['user_id'] !== $userId) {
            return false;
        }

        return $this->holdModel->release($reference);
    }

    /**
     * Get hold details if active and valid for payment.
     */
    public function getActiveHold(string $reference): ?array
    {
        return $this->holdModel->findActiveByReference($reference);
    }
}
