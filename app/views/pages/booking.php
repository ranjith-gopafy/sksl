<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Header -->
    <div class="mb-8">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Step-by-Step Reservation</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-1">Book Your Recovery Session</h1>
        <p class="text-sm text-slate-600 mt-1">Live slot availability calculated in real-time. Slot capacity is strictly protected.</p>
    </div>

    <!-- Active Hold Banner (Hidden initially) -->
    <div id="hold-banner" class="hidden rounded-3xl bg-gradient-to-r from-sky-50 via-blue-50 to-indigo-50 border border-sky-200 p-6 mb-8 shadow-md">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-sky-600 text-white flex items-center justify-center shrink-0 shadow-md">
                    <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 font-heading flex items-center gap-2">
                        Slot Reserved Under Hold: <span id="hold-ref" class="text-sky-700 font-mono"></span>
                    </h2>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Complete your payment before the timer expires to confirm this booking.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-center bg-white px-5 py-2.5 rounded-2xl border border-sky-200 shadow-xs">
                    <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Time Remaining</div>
                    <div id="hold-timer" class="text-2xl font-mono font-extrabold text-sky-600 tracking-wider">10:00</div>
                </div>
                <button 
                    type="button" 
                    id="release-hold-btn" 
                    class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-3 py-2 rounded-xl hover:bg-rose-50 border border-rose-200 transition-colors"
                >
                    Cancel Hold
                </button>
            </div>
        </div>
    </div>

    <!-- Booking Form Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Service, Date, Slot Picker (2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Service & Date Selection Card -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    <!-- Service Selector -->
                    <div>
                        <label for="service_selector" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            1. Select Modality
                        </label>
                        <select 
                            id="service_selector" 
                            class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all"
                        >
                            <?php foreach ($services as $srv): ?>
                                <option 
                                    value="<?= (int) $srv['id'] ?>" 
                                    <?= ((int) $srv['id'] === (int) ($selectedService['id'] ?? 1)) ? 'selected' : '' ?>
                                    data-duration="<?= (int) $srv['duration_minutes'] ?>"
                                    data-capacity="<?= (int) $srv['capacity'] ?>"
                                    data-base="<?= (float) $srv['price'] ?>"
                                    data-gst="<?= (float) ($srv['pricing']['gst_amount'] ?? 0) ?>"
                                    data-total="<?= (float) ($srv['pricing']['total_amount'] ?? 0) ?>"
                                >
                                    <?= h($srv['name']) ?> (<?= h($srv['duration_minutes']) ?> min &bull; &#8377;<?= number_format((float) ($srv['pricing']['total_amount'] ?? $srv['price']), 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Selector -->
                    <div>
                        <label for="date_selector" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            2. Select Date
                        </label>
                        <input 
                            type="date" 
                            id="date_selector" 
                            min="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                            value="<?= date('Y-m-d') ?>"
                            class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all"
                        >
                    </div>
                </div>

                <!-- Service Quick Specs Bar -->
                <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                    <div>
                        Duration: <strong id="spec-duration" class="text-sky-700 font-bold"><?= h($selectedService['duration_minutes'] ?? 30) ?> min</strong>
                    </div>
                    <div>
                        Max Capacity: <strong id="spec-capacity" class="text-slate-800 font-bold"><?= h($selectedService['capacity'] ?? 4) ?> athletes/slot</strong>
                    </div>
                    <div>
                        Operating Hours: <strong class="text-slate-800 font-bold">06:00 – 22:00 IST</strong>
                    </div>
                </div>
            </div>

            <!-- Dynamic Slot Grid Card -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs">
                <div class="flex items-center justify-between mb-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        3. Choose Available Time Slot
                    </div>
                    <div id="slots-count-badge" class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                        Loading slots...
                    </div>
                </div>

                <!-- Loading State -->
                <div id="slots-loading" class="py-16 text-center">
                    <div class="w-8 h-8 border-2 border-sky-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <div class="text-xs text-slate-500 font-medium">Fetching real-time availability...</div>
                </div>

                <!-- Empty / Closed Message -->
                <div id="slots-empty" class="hidden py-14 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 id="empty-title" class="text-sm font-bold text-slate-900 font-heading">No Available Slots</h3>
                    <p id="empty-subtitle" class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        There are no bookable slots for this date. Please select another date.
                    </p>
                </div>

                <!-- Slots Container -->
                <div id="slots-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[420px] overflow-y-auto pr-1">
                    <!-- Populated via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Right: Booking Summary & Hold Checkout (1 Column) -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h3 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-4 mb-5">
                    Reservation Summary
                </h3>

                <div class="space-y-3.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Modality</span>
                        <span id="sum-service" class="font-bold text-slate-900"><?= h($selectedService['name'] ?? 'Spa') ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Date</span>
                        <span id="sum-date" class="font-semibold text-slate-700"><?= date('d M Y') ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Time Slot</span>
                        <span id="sum-time" class="font-bold text-sky-700">Select a slot</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-500">Duration</span>
                        <span id="sum-duration" class="text-slate-700"><?= h($selectedService['duration_minutes'] ?? 30) ?> Minutes</span>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Base Rate</span>
                            <span>&#8377;<span id="sum-base"><?= number_format((float) ($selectedService['price'] ?? 0), 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>GST (18%)</span>
                            <span>&#8377;<span id="sum-gst"><?= number_format((float) ($selectedService['pricing']['gst_amount'] ?? 0), 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-base font-extrabold text-slate-900 pt-3 border-t border-slate-200 font-heading">
                            <span>Total Payable</span>
                            <span class="text-sky-700">&#8377;<span id="sum-total"><?= number_format((float) ($selectedService['pricing']['total_amount'] ?? 0), 2) ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Terms Acknowledgement -->
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <label class="flex items-start gap-3 cursor-pointer text-xs text-slate-600">
                        <input 
                            type="checkbox" 
                            id="terms-check" 
                            class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                        >
                        <span>
                            I agree to the <a href="<?= app_url('terms') ?>" target="_blank" class="text-sky-600 underline hover:text-sky-700 font-medium">Terms &amp; Health Declaration</a>. I confirm physical fitness for thermal therapy.
                        </span>
                    </label>
                </div>

                <!-- Proceed Button -->
                <div class="mt-6">
                    <button 
                        type="button" 
                        id="proceed-hold-btn" 
                        disabled 
                        class="w-full py-4 px-4 rounded-2xl bg-slate-100 text-slate-400 font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-not-allowed border border-slate-200"
                    >
                        Select Time Slot
                    </button>
                    <p class="text-[11px] text-slate-500 text-center mt-2.5">
                        Slot is held for 10 minutes upon proceeding.
                    </p>
                </div>

                <!-- Error Notice -->
                <div id="booking-error" class="hidden mt-4 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
                    <!-- Dynamic error text -->
                </div>
            </div>
        </div>
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
                <p class="text-xs text-slate-500">Local Simulation &bull; Razorpay keys empty in .env</p>
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
                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
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
<script>
document.addEventListener('DOMContentLoaded', () => {
    const serviceSelect = document.getElementById('service_selector');
    const dateInput     = document.getElementById('date_selector');
    const slotsGrid     = document.getElementById('slots-grid');
    const slotsLoading  = document.getElementById('slots-loading');
    const slotsEmpty    = document.getElementById('slots-empty');
    const emptyTitle    = document.getElementById('empty-title');
    const emptySubtitle = document.getElementById('empty-subtitle');
    const slotsBadge    = document.getElementById('slots-count-badge');
    const termsCheck    = document.getElementById('terms-check');
    const proceedBtn    = document.getElementById('proceed-hold-btn');
    const errorNotice   = document.getElementById('booking-error');

    // Summary elements
    const sumService  = document.getElementById('sum-service');
    const sumDate     = document.getElementById('sum-date');
    const sumTime     = document.getElementById('sum-time');
    const sumDuration = document.getElementById('sum-duration');
    const sumBase     = document.getElementById('sum-base');
    const sumGst      = document.getElementById('sum-gst');
    const sumTotal    = document.getElementById('sum-total');

    // Hold elements
    const holdBanner  = document.getElementById('hold-banner');
    const holdRefText = document.getElementById('hold-ref');
    const holdTimer   = document.getElementById('hold-timer');
    const releaseBtn  = document.getElementById('release-hold-btn');

    let selectedSlot = null;
    let activeHold = null;
    let timerInterval = null;

    // Fetch availability
    async function loadAvailability() {
        const serviceId = serviceSelect.value;
        const date = dateInput.value;

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
                btn.className = `p-3 rounded-2xl border text-left flex flex-col justify-between transition-all ${
                    slot.available
                        ? 'bg-white border-slate-200 hover:border-sky-500 hover:bg-sky-50/40 text-slate-900 cursor-pointer shadow-xs'
                        : 'bg-slate-50 border-slate-100 text-slate-400 cursor-not-allowed opacity-70'
                }`;

                let capLabel = '';
                if (slot.available) {
                    capLabel = `<span class="text-[10px] text-sky-700 font-bold bg-sky-50 px-1.5 py-0.5 rounded">${slot.remaining_capacity} left</span>`;
                } else if (slot.unavailable_reason === 'past') {
                    capLabel = '<span class="text-[10px] text-slate-400 uppercase font-semibold">Passed</span>';
                } else if (slot.unavailable_reason === 'user_conflict') {
                    capLabel = '<span class="text-[10px] text-amber-600 font-semibold">Conflict</span>';
                } else {
                    capLabel = '<span class="text-[10px] text-rose-500 font-semibold">Full</span>';
                }

                btn.innerHTML = `
                    <div class="text-sm font-extrabold font-heading text-slate-900">${slot.display_start}</div>
                    <div class="flex items-center justify-between mt-2 text-[11px] text-slate-500">
                        <span>${slot.duration_minutes}m</span>
                        ${capLabel}
                    </div>
                `;

                if (slot.available) {
                    btn.addEventListener('click', () => {
                        // Deselect other buttons
                        slotsGrid.querySelectorAll('button').forEach(b => {
                            b.classList.remove('border-sky-600', 'bg-sky-50', 'ring-2', 'ring-sky-500/20');
                            b.classList.add('border-slate-200', 'bg-white');
                        });
                        // Highlight this button
                        btn.classList.remove('border-slate-200', 'bg-white');
                        btn.classList.add('border-sky-600', 'bg-sky-50', 'ring-2', 'ring-sky-500/20');

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
        const opt = serviceSelect.options[serviceSelect.selectedIndex];
        sumService.textContent = opt.text.split('(')[0].trim();
        sumDuration.textContent = opt.dataset.duration + ' Minutes';
        document.getElementById('spec-duration').textContent = opt.dataset.duration + ' min';
        document.getElementById('spec-capacity').textContent = opt.dataset.capacity + ' athletes/slot';

        sumBase.textContent = Number(opt.dataset.base).toFixed(2);
        sumGst.textContent = Number(opt.dataset.gst).toFixed(2);
        sumTotal.textContent = Number(opt.dataset.total).toFixed(2);

        // Date format
        const dParts = dateInput.value.split('-');
        if (dParts.length === 3) {
            const dt = new Date(dParts[0], dParts[1] - 1, dParts[2]);
            sumDate.textContent = dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        if (selectedSlot) {
            sumTime.textContent = `${selectedSlot.display_start} – ${selectedSlot.display_end}`;
        } else {
            sumTime.textContent = 'Select a slot';
        }
    }

    function updateProceedButton() {
        const isReady = selectedSlot !== null && termsCheck.checked;
        if (isReady) {
            proceedBtn.disabled = false;
            proceedBtn.className = 'w-full py-4 px-4 rounded-2xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-sky-600/25 hover:shadow-sky-600/35 hover:-translate-y-0.5 transition-all cursor-pointer';
            proceedBtn.textContent = 'Hold Slot & Proceed to Payment';
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

    // Start Hold Countdown Timer
    function startHoldTimer(expiresAtString) {
        if (timerInterval) clearInterval(timerInterval);

        function update() {
            const now = new Date().getTime();
            const expires = new Date(expiresAtString).getTime();
            const diff = Math.max(0, Math.floor((expires - now) / 1000));

            if (diff <= 0) {
                clearInterval(timerInterval);
                holdTimer.textContent = '00:00';
                holdBanner.classList.add('hidden');
                mockModal.classList.add('hidden');
                activeHold = null;
                currentOrder = null;
                showError('Your 10-minute hold has expired. The slot has been released back to capacity.');
                loadAvailability();
                return;
            }

            const m = Math.floor(diff / 60);
            const s = diff % 60;
            holdTimer.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        update();
        timerInterval = setInterval(update, 1000);
    }

    function showError(msg) {
        errorNotice.textContent = msg;
        errorNotice.classList.remove('hidden');
    }

    function hideError() {
        errorNotice.classList.add('hidden');
        errorNotice.textContent = '';
    }

    // Step 1: Secure Hold, Step 2: Checkout
    proceedBtn.addEventListener('click', async () => {
        if (!selectedSlot || !termsCheck.checked) return;

        // If hold is already active, launch checkout
        if (activeHold) {
            await initiatePayment();
            return;
        }

        hideError();
        proceedBtn.disabled = true;
        proceedBtn.textContent = 'Securing Hold...';

        try {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            formData.append('service_id', serviceSelect.value);
            formData.append('booking_date', dateInput.value);
            formData.append('start_time', selectedSlot.start_time);

            const res = await fetch('<?= app_url('api/bookings/hold') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            const data = await res.json();

            if (!data.success || !data.data) {
                showError(data.message || 'Unable to reserve this slot.');
                proceedBtn.disabled = false;
                updateProceedButton();
                loadAvailability();
                return;
            }

            activeHold = data.data;
            holdRefText.textContent = activeHold.booking_reference;
            holdBanner.classList.remove('hidden');
            startHoldTimer(activeHold.expires_at);

            // Re-render button for payment step
            proceedBtn.textContent = 'Proceed to Razorpay Checkout';
            proceedBtn.className = 'w-full py-4 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-emerald-600/25 transition-all cursor-pointer';
            proceedBtn.disabled = false;

            // Scroll banner into view smoothly
            holdBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Refresh availability grid so this slot displays as held/consumed
            loadAvailability();

        } catch (err) {
            showError('Network error while creating hold. Please try again.');
            proceedBtn.disabled = false;
            updateProceedButton();
        }
    });

    // Step 2: Create Gateway Order and Open Razorpay Checkout
    async function initiatePayment() {
        if (!activeHold) return;

        hideError();
        proceedBtn.disabled = true;
        proceedBtn.textContent = 'Opening Gateway...';

        try {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            formData.append('booking_reference', activeHold.booking_reference);

            const res = await fetch('<?= app_url('api/payment/create-order') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            const data = await res.json();

            if (!data.success || !data.data) {
                showError(data.message || 'Unable to initiate payment.');
                proceedBtn.disabled = false;
                proceedBtn.textContent = 'Proceed to Razorpay Checkout';
                return;
            }

            currentOrder = data.data;

            if (currentOrder.is_mock) {
                // Open Mock Gateway Modal for local development
                mockService.textContent = currentOrder.service_name;
                mockRef.textContent     = currentOrder.booking_reference;
                mockOrderId.textContent = currentOrder.razorpay_order_id;
                mockAmount.textContent  = '₹' + Number(currentOrder.amount_rupees).toFixed(2);
                mockModal.classList.remove('hidden');
            } else {
                // Open Real Razorpay Modal
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
                    theme: {
                        color: '#0284c7'
                    },
                    handler: async function (response) {
                        await completeVerification(
                            currentOrder.booking_reference,
                            response.razorpay_order_id,
                            response.razorpay_payment_id,
                            response.razorpay_signature
                        );
                    },
                    modal: {
                        ondismiss: function () {
                            showError('Payment checkout dismissed. Your 10-minute hold remains active.');
                            proceedBtn.disabled = false;
                            proceedBtn.textContent = 'Proceed to Razorpay Checkout';
                        }
                    }
                };

                const rzp = new Razorpay(options);
                rzp.open();
            }

        } catch (e) {
            showError('Network error connecting to payment gateway.');
            proceedBtn.disabled = false;
            proceedBtn.textContent = 'Proceed to Razorpay Checkout';
        }
    }

    // Step 3: Verify Payment Server-Side
    async function completeVerification(reference, orderId, paymentId, signature) {
        hideError();
        mockModal.classList.add('hidden');
        proceedBtn.disabled = true;
        proceedBtn.textContent = 'Verifying Confirmation...';

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
                showError(data.message || 'Payment verification failed.');
                proceedBtn.disabled = false;
                proceedBtn.textContent = 'Retry Payment Verification';
                return;
            }

            // Redirect immediately to confirmed booking voucher
            window.location.href = '<?= app_url('booking-confirmation') ?>?ref=' + encodeURIComponent(reference);

        } catch (e) {
            showError('Verification request failed. Please check network.');
            proceedBtn.disabled = false;
            proceedBtn.textContent = 'Retry Payment Verification';
        }
    }

    // Mock Modal Interactions
    mockSuccessBtn.addEventListener('click', async () => {
        if (!currentOrder) return;
        mockSuccessBtn.disabled = true;
        mockSuccessBtn.textContent = 'Simulating...';

        const mockPaymentId = 'pay_mock_' + Math.random().toString(36).substring(2, 12);
        const mockSignature = 'sig_mock_' + Math.random().toString(36).substring(2, 12);

        await completeVerification(
            currentOrder.booking_reference,
            currentOrder.razorpay_order_id,
            mockPaymentId,
            mockSignature
        );
    });

    mockCancelBtn.addEventListener('click', () => {
        mockModal.classList.add('hidden');
        showError('Payment simulation cancelled. Your 10-minute hold is still active.');
        proceedBtn.disabled = false;
        proceedBtn.textContent = 'Proceed to Razorpay Checkout';
    });

    // Release Hold Button
    releaseBtn.addEventListener('click', async () => {
        if (!activeHold) return;

        try {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= \App\Helpers\Csrf::token() ?>');
            formData.append('booking_reference', activeHold.booking_reference);

            await fetch('<?= app_url('api/bookings/hold/release') ?>', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            if (timerInterval) clearInterval(timerInterval);
            holdBanner.classList.add('hidden');
            mockModal.classList.add('hidden');
            activeHold = null;
            currentOrder = null;
            showError('Hold cancelled and slot capacity released.');
            loadAvailability();
        } catch (e) {
            holdBanner.classList.add('hidden');
        }
    });

    // Event Listeners
    serviceSelect.addEventListener('change', () => {
        updateSummary();
        loadAvailability();
    });

    dateInput.addEventListener('change', () => {
        updateSummary();
        loadAvailability();
    });

    termsCheck.addEventListener('change', () => {
        updateProceedButton();
    });

    // Initial load
    updateSummary();
    loadAvailability();
});
</script>
