<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ServiceModel;
use App\Models\ClosedDateModel;
use App\Models\BookingModel;
use App\Models\BookingHoldModel;
use App\Helpers\TimeHelper;

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

        // 2. Reject past dates (in Asia/Kolkata timezone)
        if (TimeHelper::isPastDate($date)) {
            return [
                'success' => false,
                'message' => 'Appointments cannot be booked for past dates.',
                'slots'   => [],
            ];
        }

        // 3. Check advance booking limit (default: 30 days ahead)
        $maxAdvanceDays = (int) ($_ENV['MAX_ADVANCE_DAYS'] ?? 30);
        $maxDate = TimeHelper::now()->modify("+{$maxAdvanceDays} days")->format('Y-m-d');
        if ($date > $maxDate) {
            return [
                'success' => false,
                'message' => "Bookings can only be made up to {$maxAdvanceDays} days in advance.",
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

        // 6. Operating hours boundaries (06:00 to 22:00)
        $facilityOpenStr  = $_ENV['FACILITY_OPEN'] ?? '06:00';
        $facilityCloseStr = $_ENV['FACILITY_CLOSE'] ?? '22:00';

        [$openH, $openM]   = array_map('intval', explode(':', $facilityOpenStr));
        [$closeH, $closeM] = array_map('intval', explode(':', $facilityCloseStr));

        $startMinutesLimit = ($openH * 60) + $openM;
        $closeMinutesLimit = ($closeH * 60) + $closeM;

        // Turnaround / operational buffer between consecutive sessions
        $checkinBuffer  = (int) ($_ENV['CHECKIN_BUFFER_MINUTES'] ?? 0);
        $checkoutBuffer = (int) ($_ENV['CHECKOUT_BUFFER_MINUTES'] ?? 0);
        $generalBuffer  = (int) ($_ENV['BUFFER_MINUTES'] ?? 0);
        $totalBuffer    = $generalBuffer > 0 ? $generalBuffer : ($checkinBuffer + $checkoutBuffer);

        $isToday = ($date === TimeHelper::today());
        $currentTimeStr = TimeHelper::currentTime();

        $slots = [];
        $cursorMinutes = $startMinutesLimit;

        // Slot step interval: session duration + turnaround cleaning/buffer minutes
        $slotStep = $durationMinutes + max(0, $totalBuffer);

        // 7. Dynamic candidate slot generation loop
        while (($cursorMinutes + $durationMinutes) <= $closeMinutesLimit) {
            $slotStartH = intdiv($cursorMinutes, 60);
            $slotStartM = $cursorMinutes % 60;
            $slotEndMinutes = $cursorMinutes + $durationMinutes;
            $slotEndH   = intdiv($slotEndMinutes, 60);
            $slotEndM   = $slotEndMinutes % 60;

            $slotStartStr = sprintf('%02d:%02d', $slotStartH, $slotStartM);
            $slotEndStr   = sprintf('%02d:%02d', $slotEndH, $slotEndM);

            $available = true;
            $unavailableReason = null;

            // Past time check for same-day slots
            if ($isToday && $slotStartStr <= $currentTimeStr) {
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

            // Customer overlap conflict check
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

            // Advance cursor by service duration + buffer minutes
            $cursorMinutes += $slotStep;
        }

        return [
            'success' => true,
            'date'    => $date,
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
