<?php
/**
 * SKSL — Customer Booking Page
 *
 * Streamlined 1-Click Hold & Gateway Launch:
 * - Locked Selected Modality Showcase Card (captured from Services or Home page)
 * - Real-time slot availability for chosen date
 * - Direct "Proceed to Payment" without separate hold countdown
 * - Full-screen blocking loader during payment confirmation
 * - Auto-release hold if payment is dismissed/cancelled
 */

$modalityImg = service_image($selectedService['image'] ?? null); // allow-listed + escaped

$basePrice = (float) ($selectedService['price'] ?? 0);
$gstAmount = (float) ($selectedService['pricing']['gst_amount'] ?? ($basePrice * 0.18));
$totalAmount = (float) ($selectedService['pricing']['total_amount'] ?? ($basePrice + $gstAmount));
$duration = (int) ($selectedService['duration_minutes'] ?? 30);
$capacity = (int) ($selectedService['capacity'] ?? 4);
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-7">
    <!-- Header -->
    <div class="mb-5">
        <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Seamless Reservation</span>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-heading mt-0.5">Book Your Recovery Session</h1>
        <p class="text-xs sm:text-sm text-slate-600 mt-0.5">Pick a date on the calendar, choose your time slot, and reserve your session instantly.</p>
    </div>

    <!-- Hidden compatibility select & inputs for tests / form posting -->
    <select id="service_selector" class="sr-only" aria-hidden="true" tabindex="-1">
        <?php foreach ($services as $srv): ?>
            <option 
                value="<?= (int) $srv['id'] ?>" 
                <?= ((int) $srv['id'] === (int) $selectedService['id']) ? 'selected' : '' ?>
                data-duration="<?= (int) $srv['duration_minutes'] ?>"
                data-capacity="<?= (int) $srv['capacity'] ?>"
                data-base="<?= (float) $srv['price'] ?>"
                data-gst="<?= (float) ($srv['pricing']['gst_amount'] ?? 0) ?>"
                data-total="<?= (float) ($srv['pricing']['total_amount'] ?? 0) ?>"
            >
                <?= h($srv['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input 
        type="hidden" 
        id="service_id" 
        value="<?= (int) $selectedService['id'] ?>"
        data-name="<?= h($selectedService['name']) ?>"
        data-duration="<?= $duration ?>"
        data-capacity="<?= $capacity ?>"
        data-base="<?= $basePrice ?>"
        data-gst="<?= $gstAmount ?>"
        data-total="<?= $totalAmount ?>"
    >

    <!-- Hidden date compatibility inputs for forms & automated Playwright tests -->
    <input 
        type="date" 
        id="date_selector" 
        min="<?= date('Y-m-d') ?>"
        max="<?= h(\App\Helpers\BookingRules::maxDate()) ?>"
        value="<?= date('Y-m-d') ?>"
        class="sr-only" 
        tabindex="-1"
    >
    <input 
        type="date" 
        id="date_input" 
        min="<?= date('Y-m-d') ?>"
        max="<?= h(\App\Helpers\BookingRules::maxDate()) ?>"
        value="<?= date('Y-m-d') ?>"
        class="sr-only" 
        tabindex="-1"
    >

    <!-- Booking Form Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Left: Selected Modality & Slot Picker (2 Columns) -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- 1. Selected Modality Showcase Card (Locked & Compact) -->
            <div id="selected-service-card" class="bg-white border border-[#D9DBDA] rounded-2xl p-4 sm:p-5 shadow-xs relative overflow-hidden transition-all hover:border-[#075183]/40">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden shrink-0 shadow-xs border border-slate-200/80 bg-slate-100">
                        <img 
                            src="<?= $modalityImg ?>" 
                            alt="<?= h($selectedService['name']) ?>" 
                            class="w-full h-full object-cover"
                            data-fallback-src="<?= h(asset('images/services/spa.jpg')) ?>"
                        >
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#075183]/10 text-[#075183]">
                                <?= h($selectedService['category'] ?? 'Active Modality') ?>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500">
                                <?= $duration ?> Min Session
                            </span>
                            <span class="text-[11px] font-semibold text-slate-400">&bull;</span>
                            <span class="text-[11px] font-semibold text-slate-500">
                                Max <?= $capacity ?> Athletes
                            </span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-extrabold text-slate-900 font-heading leading-tight">
                            <?= h($selectedService['name']) ?>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">
                            <?= h($selectedService['description'] ?? 'Science-backed athletic recovery modality designed to accelerate muscle repair and elevate performance.') ?>
                        </p>
                    </div>
                    <div class="flex sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto pt-2.5 sm:pt-0 border-t sm:border-t-0 border-slate-100 gap-2 shrink-0">
                        <div class="text-left sm:text-right">
                            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Total with 18% GST</div>
                            <div class="text-base font-extrabold text-[#075183] font-heading leading-none mt-0.5">
                                &#8377;<?= number_format($totalAmount, 2) ?>
                            </div>
                        </div>
                        <a 
                            href="<?= app_url('services') ?>" 
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-[#075183] hover:text-[#4A9CC0] transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Change Modality
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Integrated Interactive Tailwind Calendar & Live Slot Grid Card -->
            <div class="bg-white border border-[#D9DBDA] rounded-2xl p-4 sm:p-5 shadow-xs">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    
                    <!-- Left: Interactive Tailwind Calendar (5 cols on md) -->
                    <div class="md:col-span-5 md:border-r md:border-slate-100 md:pr-5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2.5">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Pick Session Date</span>
                                </div>
                            </div>

                            <!-- Quick Presets -->
                            <div class="flex items-center gap-1.5 mb-2.5 text-[11px]">
                                <button type="button" id="cal-preset-today" class="cal-preset-btn px-2.5 py-1 rounded-lg border border-[#D9DBDA] bg-slate-50 hover:bg-slate-100 font-semibold text-slate-700 transition-all cursor-pointer">
                                    Today
                                </button>
                                <button type="button" id="cal-preset-tomorrow" class="cal-preset-btn px-2.5 py-1 rounded-lg border border-[#D9DBDA] bg-slate-50 hover:bg-slate-100 font-semibold text-slate-700 transition-all cursor-pointer">
                                    Tomorrow
                                </button>
                                <button type="button" id="cal-preset-weekend" class="cal-preset-btn px-2.5 py-1 rounded-lg border border-[#D9DBDA] bg-slate-50 hover:bg-slate-100 font-semibold text-slate-700 transition-all cursor-pointer">
                                    Weekend
                                </button>
                            </div>

                            <!-- Tailwind Interactive Calendar Container -->
                            <div class="bg-slate-50/80 border border-slate-200/80 rounded-xl p-3">
                                <!-- Month Nav Header -->
                                <div class="flex items-center justify-between mb-2">
                                    <button type="button" id="cal-prev-month" aria-label="Previous Month" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:bg-white hover:shadow-xs transition-all disabled:opacity-20 disabled:pointer-events-none cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                        </svg>
                                    </button>
                                    <span id="cal-month-year" class="text-xs font-extrabold font-heading text-slate-900 tracking-wide">
                                        September 2026
                                    </span>
                                    <button type="button" id="cal-next-month" aria-label="Next Month" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:bg-white hover:shadow-xs transition-all disabled:opacity-20 disabled:pointer-events-none cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Day Headers -->
                                <div class="grid grid-cols-7 gap-1 text-center mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <div>Mo</div>
                                    <div>Tu</div>
                                    <div>We</div>
                                    <div>Th</div>
                                    <div>Fr</div>
                                    <div>Sa</div>
                                    <div>Su</div>
                                </div>

                                <!-- Calendar Days Grid -->
                                <div id="cal-days-grid" class="grid grid-cols-7 gap-1 text-center text-xs">
                                    <!-- Populated via JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- Selected Date Display Footer -->
                        <div class="mt-2.5 pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
                            <span>Selected Date:</span>
                            <span id="cal-selected-label" class="font-bold text-[#075183]"><?= date('D, d M Y') ?></span>
                        </div>
                    </div>

                    <!-- Right: Live Time Slots (7 cols on md) -->
                    <div class="md:col-span-7 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    Available Time Slots
                                </div>
                                <div id="slots-count-badge" class="text-[11px] font-semibold text-slate-600 bg-slate-100 px-2.5 py-0.5 rounded-full">
                                    Checking availability...
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div id="slots-loading" class="py-12 text-center">
                                <div class="w-7 h-7 border-2 border-[#075183] border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                                <div class="text-xs text-slate-500 font-medium">Fetching real-time slots...</div>
                            </div>

                            <!-- Empty / Closed Message -->
                            <div id="slots-empty" class="hidden py-10 text-center">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 id="empty-title" class="text-xs font-bold text-slate-900 font-heading">No Available Slots</h3>
                                <p id="empty-subtitle" class="text-[11px] text-slate-500 mt-0.5 max-w-xs mx-auto">
                                    No bookable slots for this date. Please pick another date on the calendar.
                                </p>
                            </div>

                            <!-- Slots Container -->
                            <div id="slots-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[300px] overflow-y-auto pr-1">
                                <!-- Populated via JavaScript -->
                            </div>
                        </div>

                        <!-- Facility Operational Footer -->
                        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-400">
                            <div>Facility: <strong class="text-slate-600 font-semibold">06:00 – 22:00 IST</strong></div>
                            <div>Session: <strong class="text-[#075183] font-semibold"><?= $duration ?> Min</strong></div>
                            <div>Check-in: <strong class="text-slate-600 font-semibold">10 min prior</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Booking Summary & 1-Click Payment (1 Column) -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-[#D9DBDA] rounded-2xl p-4 sm:p-5 shadow-xs sticky top-20">
                <h3 class="text-base font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-3.5">
                    Reservation Summary
                </h3>

                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Modality</span>
                        <span id="sum-service" class="font-bold text-slate-900"><?= h($selectedService['name']) ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Date</span>
                        <span id="sum-date" class="font-semibold text-slate-700"><?= date('d M Y') ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Time Slot</span>
                        <span id="sum-time" class="font-bold text-[#075183]">Select a slot</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Duration</span>
                        <span id="sum-duration" class="text-slate-700"><?= $duration ?> Minutes</span>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Base Rate</span>
                            <span>&#8377;<span id="sum-base"><?= number_format($basePrice, 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>GST (18%)</span>
                            <span>&#8377;<span id="sum-gst"><?= number_format($gstAmount, 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-sm font-extrabold text-slate-900 pt-2 border-t border-slate-200 font-heading">
                            <span>Total Payable</span>
                            <span class="text-[#075183] font-black">&#8377;<span id="sum-total"><?= number_format($totalAmount, 2) ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Terms Acknowledgement -->
                <div class="mt-4 pt-3.5 border-t border-slate-100">
                    <label class="flex items-start gap-2.5 cursor-pointer text-xs text-slate-600">
                        <input 
                            type="checkbox" 
                            id="terms-check" 
                            class="mt-0.5 rounded border-slate-300 text-[#075183] focus:ring-[#075183]"
                        >
                        <span class="text-[11px] leading-snug">
                            I agree to the <a href="<?= app_url('terms') ?>" target="_blank" class="text-[#075183] underline hover:text-[#4A9CC0] font-medium">Terms &amp; Health Declaration</a>. I confirm physical fitness.
                        </span>
                    </label>
                </div>

                <!-- 1-Click Proceed to Payment Button -->
                <div class="mt-4">
                    <button 
                        type="button" 
                        id="proceed-hold-btn" 
                        disabled 
                        class="app-touch-target w-full py-3.5 px-4 rounded-xl bg-slate-100 text-slate-400 font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-not-allowed border border-slate-200"
                    >
                        Select Time Slot
                    </button>
                    <p class="text-[10px] text-slate-400 text-center mt-2">
                        10-min slot hold auto-assigned on gateway launch.
                    </p>
                </div>

                <!-- Error Notice -->
                <div id="booking-error" class="hidden mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
                    <!-- Dynamic error text -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full-Screen Payment Processing Overlay -->
<div id="payment-processing-overlay" class="hidden fixed inset-0 z-[99999] bg-slate-950/85 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center select-none" aria-modal="true" role="dialog">
    <div class="relative w-20 h-20 mb-6">
        <div class="absolute inset-0 rounded-full border-4 border-white/10"></div>
        <div class="absolute inset-0 rounded-full border-4 border-[#4A9CC0] border-t-transparent animate-spin"></div>
        <div class="absolute inset-2 rounded-full border-4 border-[#D6981E] border-b-transparent animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
        <div class="absolute inset-0 flex items-center justify-center">
            <img src="<?= asset('images/sksl-logo.png') ?>" alt="SKSL" class="w-8 h-8 object-contain">
        </div>
    </div>
    <h3 class="text-xl sm:text-2xl font-black text-white font-heading tracking-wide">
        Confirming Your Reservation...
    </h3>
    <p class="text-sm text-slate-300 mt-2 max-w-md">
        Verifying payment and securing your session slot with Sara Kinetic Sports Lab.
    </p>
    <div class="mt-4 px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-xs font-bold text-[#D6981E] tracking-wider uppercase">
        Please do not close or refresh this window
    </div>
</div>

<!-- Mock Payment Modal (Local Dev Mode) -->
<div id="mock-payment-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 font-heading">Razorpay Gateway (Test Mode)</h3>
                <p class="text-xs text-slate-500">Local Simulation &bull; Test credentials active</p>
            </div>
        </div>

        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 space-y-2.5 text-xs mb-6">
            <div class="flex justify-between text-slate-600">
                <span>Modality</span>
                <span id="mock-service" class="font-bold text-slate-900"></span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Order Reference</span>
                <span id="mock-ref" class="font-mono font-bold text-sky-700"></span>
            </div>
            <div class="flex justify-between text-slate-600">
                <span>Order ID</span>
                <span id="mock-order-id" class="font-mono text-slate-500 text-[11px]"></span>
            </div>
            <div class="pt-2 border-t border-slate-200 flex justify-between text-sm font-bold text-slate-900 font-heading">
                <span>Total Amount</span>
                <span id="mock-amount" class="text-emerald-600 font-extrabold"></span>
            </div>
        </div>

        <div class="space-y-3">
            <button 
                type="button" 
                id="mock-success-btn" 
                class="w-full py-3.5 px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md transition-all cursor-pointer"
            >
                Simulate Successful Payment
            </button>
            <button 
                type="button" 
                id="mock-cancel-btn" 
                class="w-full py-2.5 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition-colors cursor-pointer"
            >
                Cancel Payment Simulation
            </button>
        </div>
    </div>
</div>

<!-- Razorpay Checkout SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<!-- Booking Engine JavaScript -->
<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const serviceInput  = document.getElementById('service_id');
    const dateInput     = document.getElementById('date_selector');
    const dateInputCompat = document.getElementById('date_input');
    const slotsGrid     = document.getElementById('slots-grid');
    const slotsLoading  = document.getElementById('slots-loading');
    const slotsEmpty    = document.getElementById('slots-empty');
    const emptyTitle    = document.getElementById('empty-title');
    const emptySubtitle = document.getElementById('empty-subtitle');
    const slotsBadge    = document.getElementById('slots-count-badge');
    const termsCheck    = document.getElementById('terms-check');
    const proceedBtn    = document.getElementById('proceed-hold-btn');
    const errorNotice   = document.getElementById('booking-error');
    const overlay       = document.getElementById('payment-processing-overlay');

    // Summary elements
    const sumService  = document.getElementById('sum-service');
    const sumDate     = document.getElementById('sum-date');
    const sumTime     = document.getElementById('sum-time');
    const sumDuration = document.getElementById('sum-duration');
    const sumBase     = document.getElementById('sum-base');
    const sumGst      = document.getElementById('sum-gst');
    const sumTotal    = document.getElementById('sum-total');

    // Tailwind Calendar elements
    const calMonthYear     = document.getElementById('cal-month-year');
    const calDaysGrid      = document.getElementById('cal-days-grid');
    const calPrevMonth     = document.getElementById('cal-prev-month');
    const calNextMonth     = document.getElementById('cal-next-month');
    const calSelectedLabel = document.getElementById('cal-selected-label');
    const presetToday      = document.getElementById('cal-preset-today');
    const presetTomorrow   = document.getElementById('cal-preset-tomorrow');
    const presetWeekend    = document.getElementById('cal-preset-weekend');

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Booking window comes from the server (ADVANCE_BOOKING_DAYS) so the picker,
    // the availability API and the hold endpoint always agree.
    const advanceBookingDays = <?= (int) \App\Helpers\BookingRules::advanceDays() ?>;
    const maxBookingDate = new Date(today);
    maxBookingDate.setDate(maxBookingDate.getDate() + advanceBookingDays);

    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth(); // 0-11
    let selectedDate = new Date(today);

    function formatDateIso(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function formatDisplayDate(d) {
        return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }

    function updateSelectedDate(newDate, triggerReload = true) {
        selectedDate = new Date(newDate);
        selectedDate.setHours(0, 0, 0, 0);
        viewYear = selectedDate.getFullYear();
        viewMonth = selectedDate.getMonth();

        const isoStr = formatDateIso(selectedDate);
        if (dateInput) {
            dateInput.value = isoStr;
        }
        if (dateInputCompat) {
            dateInputCompat.value = isoStr;
        }

        if (calSelectedLabel) {
            calSelectedLabel.textContent = formatDisplayDate(selectedDate);
        }
        if (sumDate) {
            sumDate.textContent = selectedDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        renderCalendar();

        if (triggerReload) {
            loadAvailability();
        }
    }

    function renderCalendar() {
        if (!calDaysGrid || !calMonthYear) return;

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        calMonthYear.textContent = `${monthNames[viewMonth]} ${viewYear}`;

        // Disable prev button if viewMonth/viewYear is current month
        if (calPrevMonth) {
            const isCurrentMonth = (viewYear === today.getFullYear() && viewMonth === today.getMonth());
            calPrevMonth.disabled = isCurrentMonth;
        }
        // Disable next button if viewMonth/viewYear is on or after max date's month
        if (calNextMonth) {
            const isMaxMonth = (viewYear === maxBookingDate.getFullYear() && viewMonth === maxBookingDate.getMonth());
            calNextMonth.disabled = isMaxMonth;
        }

        calDaysGrid.innerHTML = '';

        // Calculate days in month and starting day of week
        // Monday = 0, Sunday = 6
        const firstDayOfMonth = new Date(viewYear, viewMonth, 1);
        let startDay = firstDayOfMonth.getDay() - 1;
        if (startDay < 0) startDay = 6;

        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

        // Empty cells for preceding days of the week
        for (let i = 0; i < startDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'w-7 h-7 sm:w-8 sm:h-8';
            calDaysGrid.appendChild(emptyCell);
        }

        const selIso = formatDateIso(selectedDate);
        const todayIso = formatDateIso(today);
        const maxIso = formatDateIso(maxBookingDate);

        // Days of current month
        for (let day = 1; day <= daysInMonth; day++) {
            const cellDate = new Date(viewYear, viewMonth, day);
            cellDate.setHours(0, 0, 0, 0);
            const cellIso = formatDateIso(cellDate);

            const isPast = cellIso < todayIso;
            const isBeyond = cellIso > maxIso;
            const isSelected = cellIso === selIso;
            const isToday = cellIso === todayIso;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-date', cellIso);

            if (isPast || isBeyond) {
                btn.disabled = true;
                btn.className = 'w-7 h-7 sm:w-8 sm:h-8 mx-auto rounded-lg flex items-center justify-center text-slate-300 cursor-not-allowed text-[11px]';
                btn.textContent = day;
            } else if (isSelected) {
                btn.className = 'w-7 h-7 sm:w-8 sm:h-8 mx-auto rounded-lg flex items-center justify-center bg-[#075183] text-white font-extrabold text-[11px] shadow-xs cursor-pointer transition-transform scale-105';
                btn.textContent = day;
            } else {
                btn.className = `w-7 h-7 sm:w-8 sm:h-8 mx-auto rounded-lg flex items-center justify-center text-slate-700 hover:bg-[#075183]/10 hover:text-[#075183] font-semibold text-[11px] transition-all cursor-pointer ${
                    isToday ? 'border border-[#075183]/50 text-[#075183] font-bold' : ''
                }`;
                btn.textContent = day;
                btn.addEventListener('click', () => {
                    updateSelectedDate(cellDate, true);
                });
            }

            calDaysGrid.appendChild(btn);
        }
    }

    if (calPrevMonth) {
        calPrevMonth.addEventListener('click', () => {
            viewMonth--;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear--;
            }
            renderCalendar();
        });
    }

    if (calNextMonth) {
        calNextMonth.addEventListener('click', () => {
            viewMonth++;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear++;
            }
            renderCalendar();
        });
    }

    if (presetToday) {
        presetToday.addEventListener('click', () => updateSelectedDate(today, true));
    }
    if (presetTomorrow) {
        presetTomorrow.addEventListener('click', () => {
            const tom = new Date(today);
            tom.setDate(tom.getDate() + 1);
            updateSelectedDate(tom, true);
        });
    }
    if (presetWeekend) {
        presetWeekend.addEventListener('click', () => {
            const sat = new Date(today);
            const dayOfWeek = today.getDay(); // 0 is Sun, 6 is Sat
            const daysUntilSat = (6 - dayOfWeek + 7) % 7;
            sat.setDate(sat.getDate() + (daysUntilSat === 0 ? 0 : daysUntilSat));
            updateSelectedDate(sat, true);
        });
    }

    let selectedSlot = null;
    let activeHold = null;

    // Fetch availability
    async function loadAvailability() {
        const serviceId = serviceInput ? serviceInput.value : '<?= (int) $selectedService['id'] ?>';
        const date = dateInput ? dateInput.value : formatDateIso(selectedDate);

        if (!serviceId || !date) return;

        // Reset slot selection
        selectedSlot = null;
        updateSummary();
        updateProceedButton();

        slotsGrid.classList.add('hidden');
        slotsEmpty.classList.add('hidden');
        slotsLoading.classList.remove('hidden');
        slotsBadge.textContent = 'Checking availability...';

        try {
            const res = await fetch(`<?= app_url('api/availability') ?>?service_id=${serviceId}&date=${date}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            slotsLoading.classList.add('hidden');

            if (!data.success || !data.data || !data.data.slots || data.data.slots.length === 0) {
                slotsEmpty.classList.remove('hidden');
                emptyTitle.textContent = data.message || 'Facility Closed';
                emptySubtitle.textContent = 'No slots available for this date. Please pick another date.';
                slotsBadge.textContent = '0 slots available';
                return;
            }

            const slots = data.data.slots;
            slotsGrid.innerHTML = '';
            let availableCount = 0;

            slots.forEach(slot => {
                if (slot.available) availableCount++;

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `slot-btn app-touch-target p-2 sm:p-2.5 rounded-xl border text-left flex flex-col justify-between transition-all ${
                    slot.available
                        ? 'bg-white border-[#D9DBDA] hover:border-[#075183] hover:bg-[#075183]/5 text-slate-900 cursor-pointer shadow-xs'
                        : 'bg-slate-50 border-slate-100 text-slate-400 cursor-not-allowed opacity-70'
                }`;

                let capLabel = '';
                if (slot.available) {
                    capLabel = `<span class="text-[9px] sm:text-[10px] text-[#075183] font-bold bg-[#075183]/10 px-1.5 py-0.5 rounded">${slot.remaining_capacity} left</span>`;
                } else if (slot.unavailable_reason === 'past') {
                    capLabel = '<span class="text-[9px] sm:text-[10px] text-slate-400 uppercase font-semibold">Passed</span>';
                } else if (slot.unavailable_reason === 'user_conflict') {
                    capLabel = '<span class="text-[9px] sm:text-[10px] text-[#D6981E] font-semibold">Conflict</span>';
                } else {
                    capLabel = '<span class="text-[9px] sm:text-[10px] text-rose-500 font-semibold">Full</span>';
                }

                btn.innerHTML = `
                    <div class="text-xs sm:text-sm font-extrabold font-heading text-slate-900 leading-tight">${slot.display_start}</div>
                    <div class="flex items-center justify-between mt-1 text-[10px] sm:text-[11px] text-slate-500">
                        <span>${slot.duration_minutes}m</span>
                        ${capLabel}
                    </div>
                `;

                if (slot.available) {
                    btn.addEventListener('click', () => {
                        // Deselect other buttons
                        slotsGrid.querySelectorAll('button').forEach(b => {
                            b.classList.remove('border-[#075183]', 'bg-[#075183]/10', 'ring-2', 'ring-[#075183]/20', 'slot-selected');
                            b.classList.add('border-[#D9DBDA]', 'bg-white');
                        });
                        // Highlight this button
                        btn.classList.remove('border-[#D9DBDA]', 'bg-white');
                        btn.classList.add('border-[#075183]', 'bg-[#075183]/10', 'ring-2', 'ring-[#075183]/20', 'slot-selected');

                        selectedSlot = slot;
                        updateSummary();
                        updateProceedButton();
                    });
                } else {
                    btn.disabled = true;
                }

                slotsGrid.appendChild(btn);
            });

            slotsGrid.classList.remove('hidden');
            slotsBadge.textContent = `${availableCount} available / ${slots.length} total`;

        } catch (err) {
            slotsLoading.classList.add('hidden');
            slotsEmpty.classList.remove('hidden');
            emptyTitle.textContent = 'Connection Error';
            emptySubtitle.textContent = 'Could not retrieve availability. Please try again.';
            slotsBadge.textContent = 'Error';
        }
    }

    function updateSummary() {
        if (!serviceInput) return;

        sumService.textContent = serviceInput.dataset.name || 'Modality';
        sumDuration.textContent = `${serviceInput.dataset.duration || '30'} Minutes`;

        const d = new Date(dateInput.value + 'T00:00:00');
        sumDate.textContent = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

        const base = parseFloat(serviceInput.dataset.base || '0');
        const gst = parseFloat(serviceInput.dataset.gst || '0');
        const total = parseFloat(serviceInput.dataset.total || '0');

        sumBase.textContent = base.toFixed(2);
        sumGst.textContent = gst.toFixed(2);
        sumTotal.textContent = total.toFixed(2);

        if (selectedSlot) {
            sumTime.textContent = selectedSlot.display_start;
        } else {
            sumTime.textContent = 'Select a slot';
        }
    }

    function updateProceedButton() {
        const isReady = selectedSlot !== null && termsCheck.checked;
        const total = parseFloat(serviceInput?.dataset?.total || '<?= $totalAmount ?>');

        if (isReady) {
            proceedBtn.disabled = false;
            proceedBtn.className = 'app-touch-target w-full py-4 px-4 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer';
            proceedBtn.textContent = `Proceed to Payment • ₹${total.toFixed(2)}`;
        } else {
            proceedBtn.disabled = true;
            proceedBtn.className = 'w-full py-4 px-4 rounded-2xl bg-slate-100 text-slate-400 font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-not-allowed border border-slate-200';
            proceedBtn.textContent = selectedSlot ? 'Agree to Terms to Continue' : 'Select Time Slot';
        }
    }

    // Mock modal elements
    const mockModal     = document.getElementById('mock-payment-modal');
    const mockService   = document.getElementById('mock-service');
    const mockRef       = document.getElementById('mock-ref');
    const mockOrderId   = document.getElementById('mock-order-id');
    const mockAmount    = document.getElementById('mock-amount');
    const mockSuccessBtn= document.getElementById('mock-success-btn');
    const mockCancelBtn = document.getElementById('mock-cancel-btn');
    let currentOrder    = null;

    function showError(msg) {
        errorNotice.textContent = msg;
        errorNotice.classList.remove('hidden');
        if (window.showToast) {
            window.showToast(msg, 'error');
        }
    }

    function hideError() {
        errorNotice.classList.add('hidden');
        errorNotice.textContent = '';
    }

    // Release hold silently
    async function releaseHoldSilently(reference) {
        if (!reference) return;
        try {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            formData.append('booking_reference', reference);
            await fetch('<?= app_url('api/bookings/hold/release') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
        } catch (e) {
            console.error('Silent release error:', e);
        }
    }

    // Handle payment dismissal / cancellation
    async function handlePaymentCancelled() {
        if (activeHold) {
            await releaseHoldSilently(activeHold.booking_reference);
            activeHold = null;
        }
        currentOrder = null;
        if (window.showToast) {
            window.showToast('Payment was cancelled. Slot hold released.', 'info');
        }
        proceedBtn.disabled = false;
        updateProceedButton();
        loadAvailability();
    }

    // 1-Click Streamlined Proceed: Create Hold + Launch Payment Gateway Immediately
    proceedBtn.addEventListener('click', async () => {
        if (!selectedSlot || !termsCheck.checked) return;

        hideError();
        proceedBtn.disabled = true;
        proceedBtn.textContent = 'Initiating Secure Payment...';

        try {
            // Step 1: Create 10-min backend hold
            const holdForm = new FormData();
            holdForm.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            holdForm.append('service_id', serviceInput.value);
            holdForm.append('booking_date', dateInput.value);
            holdForm.append('start_time', selectedSlot.start_time);

            const holdRes = await fetch('<?= app_url('api/bookings/hold') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: holdForm
            });
            const holdData = await holdRes.json();

            if (!holdData.success || !holdData.data) {
                showError(holdData.message || 'Unable to reserve this slot.');
                proceedBtn.disabled = false;
                updateProceedButton();
                loadAvailability();
                return;
            }

            activeHold = holdData.data;

            // Step 2: Immediately create Razorpay Gateway Order
            const orderForm = new FormData();
            orderForm.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            orderForm.append('booking_reference', activeHold.booking_reference);

            const orderRes = await fetch('<?= app_url('api/payment/create-order') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: orderForm
            });
            const orderData = await orderRes.json();

            if (!orderData.success || !orderData.data) {
                await releaseHoldSilently(activeHold.booking_reference);
                activeHold = null;
                showError(orderData.message || 'Unable to initiate payment.');
                proceedBtn.disabled = false;
                updateProceedButton();
                return;
            }

            currentOrder = orderData.data;

            // Step 3: Launch Gateway (Mock in Dev or Real Razorpay)
            if (currentOrder.is_mock) {
                mockService.textContent = currentOrder.service_name;
                mockRef.textContent     = currentOrder.booking_reference;
                mockOrderId.textContent = currentOrder.razorpay_order_id;
                mockAmount.textContent  = '₹' + Number(currentOrder.amount_rupees).toFixed(2);
                mockModal.classList.remove('hidden');
            } else {
                const options = {
                    key: currentOrder.key_id,
                    amount: currentOrder.amount_paise,
                    currency: currentOrder.currency || 'INR',
                    name: 'Sara Kinetic Sports Lab',
                    description: currentOrder.service_name + ' Recovery Session',
                    order_id: currentOrder.razorpay_order_id,
                    prefill: {
                        name: currentOrder.customer_name,
                        email: currentOrder.customer_email,
                        contact: currentOrder.customer_mobile
                    },
                    theme: { color: '#075183' },
                    handler: async function (response) {
                        await completeVerification(
                            currentOrder.booking_reference,
                            response.razorpay_order_id,
                            response.razorpay_payment_id,
                            response.razorpay_signature
                        );
                    },
                    modal: {
                        ondismiss: async function () {
                            await handlePaymentCancelled();
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.open();
            }

        } catch (err) {
            showError('Network error connecting to payment gateway.');
            if (activeHold) {
                await releaseHoldSilently(activeHold.booking_reference);
                activeHold = null;
            }
            proceedBtn.disabled = false;
            updateProceedButton();
        }
    });

    // Step 4: Verify Payment & Show Blocking Overlay
    async function completeVerification(reference, orderId, paymentId, signature) {
        hideError();
        mockModal.classList.add('hidden');

        // Full-screen blocking loader so user cannot click away
        if (overlay) overlay.classList.remove('hidden');

        try {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            formData.append('booking_reference', reference);
            formData.append('razorpay_order_id', orderId);
            formData.append('razorpay_payment_id', paymentId);
            formData.append('razorpay_signature', signature);

            const res = await fetch('<?= app_url('api/payment/verify') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            const data = await res.json();

            if (!data.success) {
                if (overlay) overlay.classList.add('hidden');
                showError(data.message || 'Payment verification failed.');
                proceedBtn.disabled = false;
                updateProceedButton();
                return;
            }

            // Redirect to booking confirmation voucher
            window.location.href = '<?= app_url('booking-confirmation') ?>?ref=' + encodeURIComponent(reference);

        } catch (e) {
            if (overlay) overlay.classList.add('hidden');
            showError('Verification request failed. Please check network.');
            proceedBtn.disabled = false;
            updateProceedButton();
        }
    }

    // Mock Modal Interactions
    mockSuccessBtn.addEventListener('click', async () => {
        if (!currentOrder) return;
        mockSuccessBtn.disabled = true;
        mockSuccessBtn.textContent = 'Simulating...';

        // Local mock mode: the server issued a payment id and a signature that the
        // regular verification path checks. Nothing is skipped server-side.
        await completeVerification(
            currentOrder.booking_reference,
            currentOrder.razorpay_order_id,
            currentOrder.mock_payment_id,
            currentOrder.mock_signature
        );
    });

    mockCancelBtn.addEventListener('click', async () => {
        mockModal.classList.add('hidden');
        await handlePaymentCancelled();
    });

    // Date change listener for two-way sync with hidden inputs
    dateInput.addEventListener('change', () => {
        if (dateInput.value) {
            const parts = dateInput.value.split('-');
            if (parts.length === 3) {
                const parsed = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                updateSelectedDate(parsed, false);
            }
        }
        loadAvailability();
    });
    if (dateInputCompat) {
        dateInputCompat.addEventListener('change', () => {
            if (dateInputCompat.value) {
                const parts = dateInputCompat.value.split('-');
                if (parts.length === 3) {
                    const parsed = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    updateSelectedDate(parsed, false);
                }
            }
            loadAvailability();
        });
    }
    termsCheck.addEventListener('change', updateProceedButton);

    // Initial load
    renderCalendar();
    updateSummary();
    loadAvailability();
});
</script>
