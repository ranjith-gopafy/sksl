<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceModel;
use App\Models\ClosedDateModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Models\DurationOptionModel;
use App\Models\AvailabilityRuleModel;
use App\Models\ManualBlockModel;
use App\Models\CustomerPackageModel;
use App\Helpers\TimeHelper;
use App\Helpers\BookingRules;
use App\Helpers\RateLimit;

/**
 * Booking Service — Unified Phase 2 Booking Engine
 *
 * Implements authoritative booking hold creation, professional appointment booking (pay at centre),
 * package credit redemptions, with database transactions, row locking for concurrency protection,
 * exact pricing snapshots, manual maintenance block checks, and athlete conflict checks.
 *
 * All three booking paths share validateSlot(), so facility hours, practitioner rules,
 * the slot grid, blocks, overlap and capacity can never drift apart between them.
 */
class BookingService
{
    private \PDO $db;
    private ClosedDateModel $closedDateModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;
    private DurationOptionModel $durationOptionModel;
    private AvailabilityRuleModel $ruleModel;
    private ManualBlockModel $manualBlockModel;
    private CustomerPackageModel $customerPackageModel;

    public function __construct(
        ?\PDO $db = null,
        ?ClosedDateModel $closedDateModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null,
        ?DurationOptionModel $durationOptionModel = null,
        ?AvailabilityRuleModel $ruleModel = null,
        ?ManualBlockModel $manualBlockModel = null,
        ?CustomerPackageModel $customerPackageModel = null
    ) {
        $this->db                   = $db ?? getDb();
        $this->closedDateModel      = $closedDateModel ?? new ClosedDateModel();
        $this->bookingModel         = $bookingModel ?? new BookingModel();
        $this->holdModel            = $holdModel ?? new BookingHoldModel();
        $this->durationOptionModel  = $durationOptionModel ?? new DurationOptionModel();
        $this->ruleModel            = $ruleModel ?? new AvailabilityRuleModel();
        $this->manualBlockModel     = $manualBlockModel ?? new ManualBlockModel();
        $this->customerPackageModel = $customerPackageModel ?? new CustomerPackageModel();
    }

    /**
     * Shared slot validation for every booking path. Must run inside the
     * transaction that holds the service row lock, so capacity is evaluated
     * against live data.
     *
     * @param array<string, mixed> $service  Locked services row
     * @return string|null  Customer-facing error, or null when the slot is valid
     */
    private function validateSlot(
        array $service,
        int $userId,
        int $durationMinutes,
        string $date,
        string $startTime
    ): ?string {
        $serviceId = (int) $service['id'];

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            return 'Invalid time format (HH:MM required).';
        }

        [$startH, $startM] = array_map('intval', explode(':', $startTime));
        $startTotal = ($startH * 60) + $startM;
        $endTotal   = $startTotal + $durationMinutes;
        $dbStart    = $startTime . ':00';
        $dbEnd      = sprintf('%02d:%02d:00', intdiv($endTotal, 60), $endTotal % 60);

        // Facility hours (a slot may never spill past closing or midnight)
        [$openMinutes, $closeMinutes] = BookingRules::facilityMinutes();
        if ($startTotal < $openMinutes || $endTotal > $closeMinutes) {
            $openStr  = $_ENV['FACILITY_OPEN'] ?? '06:00';
            $closeStr = $_ENV['FACILITY_CLOSE'] ?? '21:00';
            return "Slot falls outside facility hours ({$openStr} – {$closeStr} IST).";
        }

        if ($this->manualBlockModel->isRangeBlocked($serviceId, $date, $dbStart, $dbEnd)) {
            return 'This slot is temporarily blocked for maintenance or facility operations.';
        }

        // Practitioner rules take over from the generic grid when a service has any.
        // A professional service is only ever bookable inside configured working hours;
        // with no rules at all it must NOT fall back to the open facility grid.
        if (($service['category'] ?? 'modality') === 'professional' || $this->ruleModel->hasRules($serviceId)) {
            // Inside a working window AND on that window's slot spacing (duration + buffer from its
            // start), exactly the slots the availability page offers, so a hand-made request for
            // 09:07 or for back-to-back sessions without the turnaround gap is refused.
            $insideRule = false;
            $step = $durationMinutes + max(0, BookingRules::bufferMinutes());
            foreach ($this->ruleModel->getAvailableWindows($serviceId, $date) as [$winStart, $winEnd]) {
                $winStartMin = max(((int) substr($winStart, 0, 2)) * 60 + (int) substr($winStart, 3, 2), $openMinutes);
                if ($dbStart >= $winStart && $dbEnd <= $winEnd && $startTotal >= $winStartMin && (($startTotal - $winStartMin) % $step) === 0) {
                    $insideRule = true;
                    break;
                }
            }
            if (!$insideRule) {
                return 'Requested slot is outside practitioner working hours.';
            }
        } else {
            if (!BookingRules::isOnGrid($durationMinutes, $startTime)) {
                return 'That start time is not an available slot for this modality. Please pick a slot from the schedule.';
            }
        }

        // The last session of the day may not START after this time, whichever way it is booked.
        if ($startTotal > BookingRules::lastSlotMinutes()) {
            return 'That start time is after the last bookable slot of the day.';
        }

        if (BookingRules::isTooLate($date, $startTime)) {
            $cutoff = BookingRules::sameDayCutoffMinutes();
            return $cutoff > 0
                ? "Same-day sessions must be booked at least {$cutoff} minutes before they start."
                : 'This time slot has already passed for today.';
        }

        if ($this->bookingModel->hasCustomerOverlap($userId, $date, $dbStart, $dbEnd)) {
            return 'You already have a confirmed booking during this time interval. Choose a slot that does not overlap it.';
        }

        if ($this->holdModel->hasCustomerActiveHold($userId, $date, $dbStart, $dbEnd)) {
            return 'You already have an active payment hold during this time interval. Complete or release it first.';
        }

        $occupied = $this->bookingModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd)
                  + $this->holdModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd);
        if ($occupied >= (int) $service['capacity']) {
            return 'This slot is fully booked. Another athlete just secured the last available capacity.';
        }

        return null;
    }

    /** Validate the Y-m-d date, booking window and closures common to every path. */
    private function validateDate(string $date): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Invalid date format (YYYY-MM-DD required).';
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        if (!checkdate($m, $d, $y)) {
            return 'Invalid calendar date.';
        }
        if (($windowError = BookingRules::dateWindowError($date)) !== null) {
            return $windowError;
        }
        if ($this->closedDateModel->isDateClosed($date)) {
            return 'The facility is closed on this date.';
        }
        return null;
    }

    /**
     * Serialise one athlete's booking requests. The service row lock only orders requests for the
     * same service, so without this a customer sending two requests for different services at the
     * same moment could slip past the overlap check and the per-customer limits. Always taken
     * before the service lock, and before any plain read in the transaction, so later reads see
     * everything committed by the request that held the lock before us.
     */
    private function lockUser(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
    }

    /** Booking reference (SKSL-YYYYMMDD-XXXXXX). */
    private function newReference(): string
    {
        return 'SKSL-' . TimeHelper::now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /** HH:MM end time for a validated HH:MM start. */
    private static function endTime(string $startTime, int $durationMinutes): string
    {
        $total = ((int) substr($startTime, 0, 2) * 60) + (int) substr($startTime, 3, 2) + $durationMinutes;
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    /** Best-effort customer + admin notice for bookings that have no online payment. */
    private function notifyFreeBooking(array $booking, string $heading, string $intro, string $payLine): void
    {
        try {
            $email = new EmailService();
            $name  = (string) ($booking['service_name_snapshot'] ?: ($booking['service_name'] ?? 'Session'));
            $email->sendNotice(
                (string) ($booking['user_email'] ?? ''),
                (string) ($booking['user_name'] ?? 'Athlete'),
                "Confirmed: {$name} on " . date('d M Y', strtotime((string) $booking['booking_date'])) . " ({$booking['booking_reference']})",
                $heading,
                $intro,
                [
                    'Reference' => (string) $booking['booking_reference'],
                    'Service'   => $name,
                    'Date'      => date('l, d F Y', strtotime((string) $booking['booking_date'])),
                    'Time'      => date('h:i A', strtotime((string) $booking['start_time'])) . ' – ' . date('h:i A', strtotime((string) $booking['end_time'])) . ' IST',
                    'Payment'   => $payLine,
                ],
                'booking_confirmation_free',
                (int) $booking['id']
            );
            $email->sendAdminBookingNotification($booking);
        } catch (\Throwable $e) {
            error_log('BookingService notification exception: ' . $e->getMessage());
        }
    }

    /**
     * Create a temporary booking hold with concurrency-safe row locking.
     *
     * @param int|null $durationOptionId  Optional duration option selected
     * @return array{success: bool, message: string, data?: array}
     */
    public function createHold(
        int $userId,
        int $serviceId,
        string $date,
        string $startTime,
        bool $healthDeclared = false,
        ?int $durationOptionId = null
    ): array {
        if (($error = $this->validateDate($date)) !== null) {
            return ['success' => false, 'message' => $error];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            return ['success' => false, 'message' => 'Invalid time format (HH:MM required).'];
        }

        $holdMinutes = BookingRules::holdMinutes();
        $maxHolds    = BookingRules::maxActiveHoldsPerUser();

        $this->db->beginTransaction();

        try {
            // Lock order: athlete, then service. Both locks come before ANY plain read, because the
            // first plain SELECT fixes the transaction's snapshot and a read taken before the service
            // lock would then miss a hold committed by the request we waited for.
            $this->lockUser($userId);

            // Serialise hold creation for this service
            $stmt = $this->db->prepare(
                'SELECT id, name, slug, price, gst_percent, duration_minutes, capacity, status, category, pricing_type
                 FROM services WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();

            if ($this->holdModel->countActiveForUser($userId) >= $maxHolds) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "You already have {$maxHolds} slots awaiting payment. Complete or release one of them before holding another.",
                ];
            }

            if (!$service || ($service['status'] ?? '') !== 'active') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Service not found or currently inactive.'];
            }

            // Practitioner appointments are never paid online.
            if (($service['category'] ?? 'modality') !== 'modality' || ($service['pricing_type'] ?? '') === 'pay_at_centre') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This service is booked as an appointment and has no online payment.'];
            }

            // Duration and price come from the selected option (ex-GST base price, like services.price).
            $durationMinutes = (int) $service['duration_minutes'];
            $basePrice       = (float) $service['price'];

            if ($durationOptionId !== null) {
                $option = $this->durationOptionModel->findById($durationOptionId);
                if (!$option || (int) $option['service_id'] !== $serviceId || $option['status'] !== 'active') {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Invalid duration option selected.'];
                }
                $durationMinutes = (int) $option['duration_minutes'];
                $basePrice       = (float) $option['price'];
            }

            if (($error = $this->validateSlot($service, $userId, $durationMinutes, $date, $startTime)) !== null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $error];
            }

            $endTimeStr  = self::endTime($startTime, $durationMinutes);
            $bookingRef  = $this->newReference();
            $pricing     = ServiceModel::calculatePricing($basePrice, (float) ($service['gst_percent'] ?? 18.0));
            $expiresAt   = TimeHelper::now()->modify("+{$holdMinutes} minutes");
            $expiresAtDb = $expiresAt->format('Y-m-d H:i:s');

            $this->holdModel->create([
                'booking_reference'  => $bookingRef,
                'user_id'            => $userId,
                'service_id'         => $serviceId,
                'duration_option_id' => $durationOptionId,
                'booking_date'       => $date,
                'start_time'         => $startTime . ':00',
                'end_time'           => $endTimeStr . ':00',
                'expires_at'         => $expiresAtDb,
                'health_declared_at' => $healthDeclared ? TimeHelper::now()->format('Y-m-d H:i:s') : null,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Slot temporarily held for {$holdMinutes} minutes. Please proceed to payment.",
                'data'    => [
                    'booking_reference'  => $bookingRef,
                    'service_id'         => (int) $service['id'],
                    'service_name'       => $service['name'],
                    'duration_option_id' => $durationOptionId,
                    'booking_date'       => $date,
                    'start_time'         => $startTime,
                    'end_time'           => $endTimeStr,
                    'display_start'      => TimeHelper::formatTime($startTime),
                    'display_end'        => TimeHelper::formatTime($endTimeStr),
                    'duration_minutes'   => $durationMinutes,
                    'expires_at'         => $expiresAt->format('c'),
                    'expires_at_db'      => $expiresAtDb,
                    'hold_minutes'       => $holdMinutes,
                    'pricing'            => $pricing,
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
     * Create a confirmed Professional Appointment (Physiotherapy / Sports Psychology).
     * No online payment; the fee is assessed and collected at the centre.
     *
     * @return array{success: bool, message: string, booking?: array}
     */
    public function createProfessionalBooking(
        int $userId,
        int $serviceId,
        string $date,
        string $startTime,
        bool $healthDeclared = false,
        ?int $durationOptionId = null
    ): array {
        if (($error = $this->validateDate($date)) !== null) {
            return ['success' => false, 'message' => $error];
        }

        if (!RateLimit::attempt(RateLimit::key('professional_booking', (string) $userId), 10, 3600)) {
            return ['success' => false, 'message' => 'Too many booking attempts. Please try again later.'];
        }

        $this->db->beginTransaction();

        try {
            // Locks first (athlete, then service), plain reads after: see createHold().
            $this->lockUser($userId);

            $stmt = $this->db->prepare('SELECT * FROM services WHERE id = ? FOR UPDATE');
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();

            // Free, instantly-confirmed slots are a hoarding risk: cap what one account may hold.
            $maxActive = max(1, (int) ($_ENV['MAX_ACTIVE_PROFESSIONAL_BOOKINGS'] ?? 3));
            if ($this->bookingModel->countUpcomingProfessionalForUser($userId) >= $maxActive) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => "You already have {$maxActive} upcoming appointments. Please attend or contact SKSL to change one before booking another.",
                ];
            }

            if (!$service || ($service['status'] ?? '') !== 'active') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Professional service is not available.'];
            }
            if (($service['category'] ?? '') !== 'professional') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This service is not a professional appointment.'];
            }

            $durationMinutes = (int) $service['duration_minutes'];
            if ($durationOptionId !== null) {
                $option = $this->durationOptionModel->findById($durationOptionId);
                if (!$option || (int) $option['service_id'] !== $serviceId || $option['status'] !== 'active') {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Invalid duration option selected.'];
                }
                $durationMinutes = (int) $option['duration_minutes'];
            }

            if (($error = $this->validateSlot($service, $userId, $durationMinutes, $date, $startTime)) !== null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $error];
            }

            $endTimeStr = self::endTime($startTime, $durationMinutes);

            $bookingId = $this->bookingModel->create([
                'booking_reference'        => $this->newReference(),
                'user_id'                  => $userId,
                'service_id'               => $serviceId,
                'booking_type'             => 'professional',
                'payment_mode'             => 'payment_not_required',
                'duration_option_id'       => $durationOptionId,
                'booking_date'             => $date,
                'start_time'               => $startTime . ':00',
                'end_time'                 => $endTimeStr . ':00',
                'service_name_snapshot'    => (string) $service['name'],
                'service_duration_minutes' => $durationMinutes,
                'checkin_buffer_minutes'   => 0,
                'checkout_buffer_minutes'  => 0,
                'base_amount'              => 0.00,
                'gst_percent'              => 0.00,
                'gst_amount'               => 0.00,
                'convenience_fee'          => 0.00,
                'total_amount'             => 0.00,
                'booking_status'           => 'confirmed',
                // Nothing is owed online; payment_mode tells reports and invoicing apart from paid sales.
                'payment_status'           => 'paid',
                'visit_status'             => 'pending',
                'health_declared_at'       => $healthDeclared ? TimeHelper::now()->format('Y-m-d H:i:s') : null,
            ]);

            $this->db->commit();

            $booking = $this->bookingModel->findById($bookingId);
            if ($booking) {
                $this->notifyFreeBooking(
                    $booking,
                    'Your appointment is confirmed',
                    'Your appointment is booked. The consultation fee is assessed and collected at the centre.',
                    'Payable at the centre'
                );
            }
            return [
                'success' => true,
                'message' => 'Appointment successfully booked! Consultation fee will be assessed and collected at the centre.',
                'booking' => $booking,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('BookingService::createProfessionalBooking Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not complete this booking. Please try again.'];
        }
    }

    /**
     * Book a session using a prepaid Combo Package credit.
     *
     * @return array{success: bool, message: string, booking?: array}
     */
    public function createPackageBooking(
        int $userId,
        int $customerPackageId,
        int $serviceId,
        string $date,
        string $startTime,
        bool $healthDeclared = false
    ): array {
        if (($error = $this->validateDate($date)) !== null) {
            return ['success' => false, 'message' => $error];
        }

        $this->db->beginTransaction();

        try {
            // Lock order: athlete, then service, and only then any plain read. Under InnoDB's default
            // isolation the first plain SELECT fixes the snapshot, so reading the package earlier
            // would let the capacity and overlap checks below miss a booking committed while we waited.
            $this->lockUser($userId);
            $stmt = $this->db->prepare('SELECT id FROM services WHERE id = ? FOR UPDATE');
            $stmt->execute([$serviceId]);

            $pkg = $this->customerPackageModel->findActiveForUser($userId, $customerPackageId);
            if (!$pkg) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Selected package is not active or has expired.'];
            }

            // Sessions must take place inside the package's validity window.
            if ($date > substr((string) $pkg['expires_at'], 0, 10)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Your package expires before this date. Please choose an earlier date.'];
            }

            $stmt = $this->db->prepare('SELECT * FROM services WHERE id = ? FOR UPDATE');
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();

            if (!$service || ($service['status'] ?? '') !== 'active' || ($service['category'] ?? '') !== 'modality') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Service not found or currently inactive.'];
            }

            // The credit must belong to this package for exactly this modality.
            $inPackage = false;
            $creditMinutes = 0;
            foreach ($pkg['credits'] as $credit) {
                if ((int) $credit['service_id'] === $serviceId) {
                    $inPackage = true;
                    $creditMinutes = (int) $credit['duration_minutes'];
                    break;
                }
            }
            if (!$inPackage) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This modality is not included in the selected package.'];
            }

            // A package credit books the session length the package was sold with (e.g. 10 min).
            $durationMinutes = $creditMinutes > 0 ? $creditMinutes : (int) $service['duration_minutes'];

            if (($error = $this->validateSlot($service, $userId, $durationMinutes, $date, $startTime)) !== null) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $error];
            }

            $creditResult = $this->customerPackageModel->consumeCredit($customerPackageId, $serviceId);
            if (!$creditResult['success']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $creditResult['message']];
            }

            $endTimeStr = self::endTime($startTime, $durationMinutes);

            $bookingId = $this->bookingModel->create([
                'booking_reference'        => $this->newReference(),
                'user_id'                  => $userId,
                'service_id'               => $serviceId,
                'booking_type'             => 'package',
                'payment_mode'             => 'package_credit',
                'package_credit_id'        => (int) $creditResult['credit_id'],
                'booking_date'             => $date,
                'start_time'               => $startTime . ':00',
                'end_time'                 => $endTimeStr . ':00',
                'service_name_snapshot'    => (string) $service['name'],
                'service_duration_minutes' => $durationMinutes,
                'checkin_buffer_minutes'   => 0,
                'checkout_buffer_minutes'  => 0,
                'base_amount'              => 0.00,
                'gst_percent'              => 0.00,
                'gst_amount'               => 0.00,
                'convenience_fee'          => 0.00,
                'total_amount'             => 0.00,
                'booking_status'           => 'confirmed',
                'payment_status'           => 'paid',
                'visit_status'             => 'pending',
                'health_declared_at'       => $healthDeclared ? TimeHelper::now()->format('Y-m-d H:i:s') : null,
            ]);

            $this->db->commit();

            $booking = $this->bookingModel->findById($bookingId);
            if ($booking) {
                $this->notifyFreeBooking(
                    $booking,
                    'Your session is confirmed',
                    'Your session is booked using one credit from your package.',
                    'Package credit'
                );
            }
            return [
                'success' => true,
                'message' => "Session successfully booked using 1 {$service['name']} package credit!",
                'booking' => $booking,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('BookingService::createPackageBooking Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not complete this booking. Please try again.'];
        }
    }

    /**
     * Customer cancels one of their own confirmed bookings (up to the notice period before it starts).
     *
     * A booking paid online keeps its payment record: SKSL refunds the amount minus the cancellation
     * charge manually, and the charge/refund are written to the booking for the admin. A package
     * session returns its credit; a pay-at-centre appointment has nothing to refund.
     *
     * Emails take seconds over SMTP, so a caller that wants to answer the customer first passes
     * $sendNotices = false and calls sendCancellationNotices() with data['notice'] afterwards.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function cancelByCustomer(int $userId, string $reference, bool $sendNotices = true): array
    {
        if (!preg_match('/^[A-Za-z0-9\-]{6,32}$/', $reference)) {
            return ['success' => false, 'message' => 'Booking not found.'];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM bookings WHERE booking_reference = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$reference, $userId]);
            $booking = $stmt->fetch();
            if (!$booking) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Booking not found.'];
            }

            $quote = BookingRules::cancellationQuote($booking);
            if (!$quote['allowed']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $quote['reason']];
            }

            $note = '[' . TimeHelper::now()->format('Y-m-d H:i:s') . '] Cancelled by the customer.'
                . ($quote['paid_online']
                    ? sprintf(' Paid %.2f, cancellation charge %.2f, refund due %.2f (to be refunded manually).', (float) $booking['total_amount'], $quote['fee'], $quote['refund'])
                    : '');
            $upd = $this->db->prepare(
                "UPDATE bookings
                 SET booking_status = 'cancelled', cancelled_at = ?, cancelled_by = 'customer',
                     cancellation_fee = ?, refund_amount = ?, notes = CONCAT_WS('\n', notes, ?)
                 WHERE id = ? AND booking_status IN ('confirmed', 'pending')"
            );
            $upd->execute([
                TimeHelper::now()->format('Y-m-d H:i:s'),
                $quote['paid_online'] ? $quote['fee'] : null,
                $quote['paid_online'] ? $quote['refund'] : null,
                $note,
                (int) $booking['id'],
            ]);
            if ($upd->rowCount() !== 1) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This booking has just changed. Please refresh and check its status.'];
            }

            $creditReturned = false;
            if (($booking['booking_type'] ?? '') === 'package' && !empty($booking['package_credit_id'])) {
                $creditReturned = (bool) $this->customerPackageModel->restoreCredit((int) $booking['package_credit_id'])['success'];
            }
            $this->holdModel->release((string) $booking['booking_reference']);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('BookingService::cancelByCustomer Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not cancel this booking. Please try again or contact SKSL.'];
        }

        $notice = [(int) $booking['id'], $quote, $creditReturned];
        if ($sendNotices) {
            $this->sendCancellationNotices(...$notice);
        }

        return [
            'success' => true,
            'message' => $quote['paid_online']
                ? sprintf('Your session is cancelled. A cancellation charge of ₹%s applies; ₹%s will be refunded by SKSL to your original payment method.', number_format($quote['fee'], 2), number_format($quote['refund'], 2))
                : ($creditReturned ? 'Your session is cancelled and the package credit has been returned.' : 'Your session is cancelled.'),
            'data' => ['fee' => $quote['fee'], 'refund' => $quote['refund'], 'paid_online' => $quote['paid_online'], 'credit_returned' => $creditReturned, 'notice' => $notice],
        ];
    }

    /** Best-effort emails after a customer cancellation (never blocks the cancellation). */
    public function sendCancellationNotices(int $bookingId, array $quote, bool $creditReturned): void
    {
        try {
            $b = $this->bookingModel->findById($bookingId);
            if (!$b) {
                return;
            }
            $name = (string) ($b['service_name_snapshot'] ?: $b['service_name']);
            $rows = [
                'Booking reference' => (string) $b['booking_reference'],
                'Session'           => $name . ', ' . date('d M Y', strtotime((string) $b['booking_date'])) . ', ' . date('g:i A', strtotime((string) $b['start_time'])) . ' IST',
            ];
            if ($quote['paid_online']) {
                $rows['Amount paid']         = '₹' . number_format((float) $b['total_amount'], 2);
                $rows['Cancellation charge'] = '₹' . number_format($quote['fee'], 2) . ' (' . rtrim(rtrim(number_format(BookingRules::cancellationFeePercent(), 2), '0'), '.') . '%)';
                $rows['Refund']              = '₹' . number_format($quote['refund'], 2);
            } elseif ($creditReturned) {
                $rows['Package credit'] = 'Returned to your package';
            }

            $mail = new EmailService();
            $mail->sendNotice(
                (string) $b['user_email'],
                (string) $b['user_name'],
                "Cancelled: {$name} ({$b['booking_reference']})",
                'Your session has been cancelled',
                $quote['paid_online']
                    ? 'We have cancelled your session. SKSL will refund the amount shown below to your original payment method manually; it usually reaches your bank within 5–7 working days.'
                    : 'We have cancelled your session. The slot has been released.',
                $rows,
                'booking_cancelled_customer',
                $bookingId
            );
            $mail->sendAdminAlert(
                $quote['paid_online'] ? '[Action needed] Customer cancelled a paid booking' : 'Customer cancelled a booking',
                'Booking cancelled by the customer',
                $quote['paid_online']
                    ? 'Refund the amount below manually in the Razorpay dashboard. The cancellation charge has already been deducted.'
                    : 'The customer cancelled this booking. No payment was taken online.',
                $rows + ['Customer' => (string) $b['user_name'] . ' <' . (string) $b['user_email'] . '>'],
                'admin_booking_cancelled'
            );
        } catch (\Throwable $e) {
            error_log('Cancellation emails failed: ' . $e->getMessage());
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
