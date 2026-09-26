<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceModel;
use App\Models\ClosedDateModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Helpers\TimeHelper;
use App\Helpers\BookingRules;

/**
 * Dynamic Availability Service
 *
 * Generates dynamic time slots between facility operating hours (06:00 – 22:00 IST),
 * evaluates active confirmed bookings and non-expired holds against service capacity,
 * checks date closures, and enforces past-date/time protections.
 */
class AvailabilityService
{
    private ServiceModel $serviceModel;
    private ClosedDateModel $closedDateModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;

    public function __construct(
        ?ServiceModel $serviceModel = null,
        ?ClosedDateModel $closedDateModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null
    ) {
        $this->serviceModel     = $serviceModel ?? new ServiceModel();
        $this->closedDateModel  = $closedDateModel ?? new ClosedDateModel();
        $this->bookingModel     = $bookingModel ?? new BookingModel();
        $this->holdModel        = $holdModel ?? new BookingHoldModel();
    }

    /**
     * Calculate and return dynamic available slots for a given service and date.
     *
     * @param int      $serviceId
     * @param string   $date           Format: Y-m-d
     * @param int|null $currentUserId  Optional authenticated customer ID to detect user conflicts
     * @return array{success: bool, message?: string, date?: string, service?: array, slots?: array}
     */
    public function getAvailableSlots(int $serviceId, string $date, ?int $currentUserId = null): array
    {
        // 1. Validate date format (YYYY-MM-DD) and calendar validity
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [
                'success' => false,
                'message' => 'Invalid date format. Expected YYYY-MM-DD.',
                'slots'   => [],
            ];
        }

        [$y, $m, $d] = array_map('intval', explode('-', $date));
        if (!checkdate($m, $d, $y)) {
            return [
                'success' => false,
                'message' => 'Invalid calendar date.',
                'slots'   => [],
            ];
        }

        // 2./3. Booking window: not in the past, not beyond ADVANCE_BOOKING_DAYS.
        //       Same rule object is used by the hold endpoint (BookingRules).
        if (($windowError = BookingRules::dateWindowError($date)) !== null) {
            return [
                'success' => false,
                'message' => $windowError,
                'slots'   => [],
            ];
        }

        // 4. Check if date is globally closed
        if ($this->closedDateModel->isDateClosed($date)) {
            return [
                'success' => false,
                'message' => 'The facility is closed on this date.',
                'slots'   => [],
            ];
        }

        // 5. Load and verify active service
        $service = $this->serviceModel->findActiveById($serviceId);
        if ($service === null) {
            return [
                'success' => false,
                'message' => 'Requested service is not active or does not exist.',
                'slots'   => [],
            ];
        }

        $durationMinutes = (int) $service['duration_minutes'];
        $capacity        = (int) $service['capacity'];

        if ($durationMinutes <= 0 || $capacity <= 0) {
            return [
                'success' => false,
                'message' => 'Service configuration error: invalid duration or capacity.',
                'slots'   => [],
            ];
        }

        // 6. Operating hours, buffer and grid all come from BookingRules so the
        //    hold endpoint validates against exactly the same list of start times.
        $totalBuffer = BookingRules::bufferMinutes();
        $isToday     = ($date === TimeHelper::today());
        $cutoffStart = BookingRules::earliestStartToday(); // now + SAME_DAY_CUTOFF_MINUTES

        $slots = [];

        // 7. Dynamic candidate slot generation loop
        foreach (BookingRules::gridStarts($durationMinutes) as $slotStartStr) {
            $slotEndStr = TimeHelper::addMinutes($slotStartStr, $durationMinutes);

            $available = true;
            $unavailableReason = null;

            // Same-day: slot must start after now + cutoff lead time
            if ($isToday && $slotStartStr <= $cutoffStart) {
                $available = false;
                $unavailableReason = 'past';
            }

            // Calculate active usage (confirmed bookings + active non-expired holds)
            $dbStart = $slotStartStr . ':00';
            $dbEnd   = $slotEndStr . ':00';

            $confirmedCount = $this->bookingModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd);
            $holdCount      = $this->holdModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd);
            $totalOccupied  = $confirmedCount + $holdCount;
            $remainingCap   = max(0, $capacity - $totalOccupied);

            if ($totalOccupied >= $capacity) {
                $available = false;
                if ($unavailableReason === null) {
                    $unavailableReason = 'fully_booked';
                }
            }

            // Customer conflict: one athlete cannot be in two sessions at once,
            // whatever the modality (matches BookingService::createHold()).
            if ($available && $currentUserId !== null) {
                if ($this->bookingModel->hasCustomerOverlap($currentUserId, $date, $dbStart, $dbEnd)) {
                    $available = false;
                    $unavailableReason = 'user_conflict';
                }
            }

            $slots[] = [
                'start_time'         => $slotStartStr,
                'end_time'           => $slotEndStr,
                'display_start'      => TimeHelper::formatTime($slotStartStr),
                'display_end'        => TimeHelper::formatTime($slotEndStr),
                'duration_minutes'   => $durationMinutes,
                'buffer_minutes'     => $totalBuffer,
                'capacity'           => $capacity,
                'occupied'           => $totalOccupied,
                'remaining_capacity' => $remainingCap,
                'available'          => $available,
                'unavailable_reason' => $unavailableReason,
            ];
        }

        return [
            'success' => true,
            'date'    => $date,
            'rules'   => [
                'advance_booking_days'    => BookingRules::advanceDays(),
                'max_date'                => BookingRules::maxDate(),
                'same_day_cutoff_minutes' => BookingRules::sameDayCutoffMinutes(),
                'buffer_minutes'          => $totalBuffer,
            ],
            'service' => [
                'id'               => (int) $service['id'],
                'name'             => $service['name'],
                'slug'             => $service['slug'],
                'duration_minutes' => $durationMinutes,
                'capacity'         => $capacity,
                'pricing'          => ServiceModel::calculatePricing(
                    (float) $service['price'],
                    (float) ($service['gst_percent'] ?? 18.0)
                ),
            ],
            'slots'   => $slots,
        ];
    }
}
