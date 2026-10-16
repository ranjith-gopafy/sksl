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
use App\Helpers\TimeHelper;
use App\Helpers\BookingRules;

/**
 * Dynamic Availability Service — Phase 2 Extended
 *
 * Generates dynamic time slots between facility operating hours (06:00 – 22:00 IST)
 * or practitioner availability rules, evaluates active confirmed bookings and non-expired holds,
 * checks manual maintenance blocks and global date closures, and enforces past-date/time protections.
 */
class AvailabilityService
{
    private ServiceModel $serviceModel;
    private ClosedDateModel $closedDateModel;
    private BookingModel $bookingModel;
    private BookingHoldModel $holdModel;
    private DurationOptionModel $durationOptionModel;
    private AvailabilityRuleModel $ruleModel;
    private ManualBlockModel $manualBlockModel;

    public function __construct(
        ?ServiceModel $serviceModel = null,
        ?ClosedDateModel $closedDateModel = null,
        ?BookingModel $bookingModel = null,
        ?BookingHoldModel $holdModel = null,
        ?DurationOptionModel $durationOptionModel = null,
        ?AvailabilityRuleModel $ruleModel = null,
        ?ManualBlockModel $manualBlockModel = null
    ) {
        $this->serviceModel         = $serviceModel ?? new ServiceModel();
        $this->closedDateModel      = $closedDateModel ?? new ClosedDateModel();
        $this->bookingModel         = $bookingModel ?? new BookingModel();
        $this->holdModel            = $holdModel ?? new BookingHoldModel();
        $this->durationOptionModel  = $durationOptionModel ?? new DurationOptionModel();
        $this->ruleModel            = $ruleModel ?? new AvailabilityRuleModel();
        $this->manualBlockModel     = $manualBlockModel ?? new ManualBlockModel();
    }

    /**
     * Calculate and return dynamic available slots for a given service, date, and duration option.
     *
     * @param int      $serviceId
     * @param string   $date              Format: Y-m-d
     * @param int|null $currentUserId     Optional authenticated customer ID to detect user conflicts
     * @param int|null $durationOptionId  Optional duration option ID for variable duration services
     * @return array{success: bool, message?: string, date?: string, service?: array, slots?: array}
     */
    public function getAvailableSlots(
        int $serviceId,
        string $date,
        ?int $currentUserId = null,
        ?int $durationOptionId = null,
        ?int $creditMinutes = null
    ): array {
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

        // 2./3. Booking window: not in the past, not beyond ADVANCE_BOOKING_DAYS
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

        $durationOptions = $this->durationOptionModel->getByServiceId($serviceId, true);
        $selectedOption = null;

        if ($durationOptionId !== null) {
            $option = $this->durationOptionModel->findById($durationOptionId);
            if ($option && (int) $option['service_id'] === $serviceId && $option['status'] === 'active') {
                $selectedOption = $option;
            }
        }

        // Fallback: if not selected but service has duration options, pick first or match default
        if ($selectedOption === null && !empty($durationOptions)) {
            foreach ($durationOptions as $opt) {
                if ((int) $opt['duration_minutes'] === (int) $service['duration_minutes']) {
                    $selectedOption = $opt;
                    break;
                }
            }
            if ($selectedOption === null) {
                $selectedOption = $durationOptions[0];
            }
        }

        $durationMinutes = $selectedOption !== null
            ? (int) $selectedOption['duration_minutes']
            : (int) $service['duration_minutes'];

        // A package credit fixes the session length, whatever options the service normally offers.
        if ($creditMinutes !== null && $creditMinutes > 0) {
            $durationMinutes = $creditMinutes;
            $selectedOption  = null;
        }

        $price = $selectedOption !== null
            ? (float) $selectedOption['price']
            : (float) $service['price'];

        $capacity = (int) $service['capacity'];

        if ($durationMinutes <= 0 || $capacity <= 0) {
            return [
                'success' => false,
                'message' => 'Service configuration error: invalid duration or capacity.',
                'slots'   => [],
            ];
        }

        $totalBuffer = BookingRules::bufferMinutes();
        $isToday     = ($date === TimeHelper::today());
        $cutoffStart = BookingRules::earliestStartToday();

        // 6. Generate candidate start times
        // Professional service / service-specific availability rules check:
        $candidateStarts = [];
        // Professional services (and any service with rules) are bookable only inside their
        // configured windows; with no rules a professional service has no availability at all.
        $hasCustomRules = $this->ruleModel->hasRules($serviceId);

        if ($hasCustomRules || ($service['category'] ?? 'modality') === 'professional') {
            $windows = $this->ruleModel->getAvailableWindows($serviceId, $date);
            if (empty($windows)) {
                return [
                    'success' => true,
                    'date'    => $date,
                    'service' => $this->buildServicePayload($service, $durationMinutes, $price, $durationOptions, $selectedOption),
                    'slots'   => [],
                    'message' => 'No appointments are scheduled for this date.',
                ];
            }

            [$facilityOpen, $facilityClose] = BookingRules::facilityMinutes();
            $step = $durationMinutes + max(0, $totalBuffer);

            foreach ($windows as [$winStart, $winEnd]) {
                [$rHStart, $rMStart] = array_map('intval', explode(':', $winStart));
                [$rHEnd, $rMEnd]     = array_map('intval', explode(':', $winEnd));
                // Never offer a slot the booking endpoint would refuse for being outside facility hours.
                $windowStart = max(($rHStart * 60) + $rMStart, $facilityOpen);
                $windowEnd   = min(($rHEnd * 60) + $rMEnd, $facilityClose);

                $lastStart = BookingRules::lastSlotMinutes();
                for ($cursor = $windowStart; $cursor <= $lastStart && $cursor + $durationMinutes <= $windowEnd; $cursor += $step) {
                    $slotStart = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
                    if (!in_array($slotStart, $candidateStarts, true)) {
                        $candidateStarts[] = $slotStart;
                    }
                }
            }
            sort($candidateStarts);
        } else {
            $candidateStarts = BookingRules::gridStarts($durationMinutes);
        }

        $slots = [];

        // 7. Dynamic candidate slot evaluation loop
        foreach ($candidateStarts as $slotStartStr) {
            $slotEndStr = TimeHelper::addMinutes($slotStartStr, $durationMinutes);

            $available = true;
            $unavailableReason = null;

            // Past, or inside the same-day lead time (same rule object the hold endpoint uses)
            if (BookingRules::isTooLate($date, $slotStartStr)) {
                $available = false;
                $unavailableReason = 'past';
            }

            $dbStart = $slotStartStr . ':00';
            $dbEnd   = $slotEndStr . ':00';

            // Check manual administrative blocks
            if ($available && $this->manualBlockModel->isRangeBlocked($serviceId, $date, $dbStart, $dbEnd)) {
                $available = false;
                $unavailableReason = 'maintenance_block';
            }

            // Calculate active usage (confirmed bookings + active non-expired holds)
            $confirmedCount = $this->bookingModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd);
            $holdCount      = $this->holdModel->countActiveOverlapping($serviceId, $date, $dbStart, $dbEnd);
            $totalOccupied  = $confirmedCount + $holdCount;
            $remainingCap   = max(0, $capacity - $totalOccupied);

            if ($available && $totalOccupied >= $capacity) {
                $available = false;
                $unavailableReason = 'fully_booked';
            }

            // Customer conflict check
            if ($available && $currentUserId !== null) {
                if ($this->bookingModel->hasCustomerOverlap($currentUserId, $date, $dbStart, $dbEnd)
                    || $this->holdModel->hasCustomerActiveHold($currentUserId, $date, $dbStart, $dbEnd)) {
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
            'service' => $this->buildServicePayload($service, $durationMinutes, $price, $durationOptions, $selectedOption),
            'slots'   => $slots,
        ];
    }

    /**
     * Build rich service payload including Phase 2 fields.
     */
    private function buildServicePayload(
        array $service,
        int $durationMinutes,
        float $price,
        array $durationOptions,
        ?array $selectedOption
    ): array {
        $category    = $service['category'] ?? 'modality';
        $pricingType = $service['pricing_type'] ?? 'fixed';

        $pricing = $pricingType === 'pay_at_centre'
            ? [
                'base_price'      => 0.00,
                'gst_amount'      => 0.00,
                'total_amount'    => 0.00,
                'formatted_total' => '₹0.00',
                'pay_at_centre'   => true,
                'display_badge'   => 'Assessed & Paid at Centre',
              ]
            : ServiceModel::calculatePricing($price, (float) ($service['gst_percent'] ?? 18.0));

        return [
            'id'                        => (int) $service['id'],
            'name'                      => $service['name'],
            'slug'                      => $service['slug'],
            'category'                  => $category,
            'pricing_type'              => $pricingType,
            'duration_mode'             => $service['duration_mode'] ?? 'single',
            'duration_minutes'          => $durationMinutes,
            'capacity'                  => (int) $service['capacity'],
            'pricing'                   => $pricing,
            'duration_options'          => array_map(function ($opt) {
                return [
                    'id'               => (int) $opt['id'],
                    'duration_minutes' => (int) $opt['duration_minutes'],
                    'price'            => (float) $opt['price'],
                    'label'            => $opt['label'] ?: ((int) $opt['duration_minutes'] . ' mins'),
                ];
            }, $durationOptions),
            'selected_duration_option'  => $selectedOption ? [
                'id'               => (int) $selectedOption['id'],
                'duration_minutes' => (int) $selectedOption['duration_minutes'],
                'price'            => (float) $selectedOption['price'],
                'label'            => $selectedOption['label'],
            ] : null,
        ];
    }
}
