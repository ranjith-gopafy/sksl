<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <span class="text-xs font-bold uppercase tracking-widest text-cyan-400">Step-by-Step Reservation</span>
        <h1 class="text-3xl font-extrabold text-white font-heading mt-1">Book Your Recovery Session</h1>
        <p class="text-sm text-slate-400 mt-1">Live slot availability calculated in real-time. Slot capacity is strictly protected.</p>
    </div>

    <!-- Active Hold Banner (Hidden initially) -->
    <div id="hold-banner" class="hidden rounded-2xl bg-gradient-to-r from-cyan-950/80 to-slate-900 border border-cyan-500/40 p-5 mb-8 shadow-xl shadow-cyan-500/10 backdrop-blur-md">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white font-heading flex items-center gap-2">
                        Slot Reserved Under Hold: <span id="hold-ref" class="text-cyan-400 font-mono"></span>
                    </h2>
                    <p class="text-xs text-slate-300">
                        Complete your payment before the timer expires to confirm this booking.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-center bg-slate-950/80 px-4 py-2 rounded-xl border border-cyan-500/30">
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Time Remaining</div>
                    <div id="hold-timer" class="text-xl font-mono font-extrabold text-cyan-400 tracking-wider">10:00</div>
                </div>
                <button 
                    type="button" 
                    id="release-hold-btn" 
                    class="text-xs font-semibold text-rose-400 hover:text-rose-300 px-3 py-2 rounded-lg hover:bg-slate-900 transition-colors"
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
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    
                    <!-- Service Selector -->
                    <div>
                        <label for="service_selector" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            1. Select Modality
                        </label>
                        <select 
                            id="service_selector" 
                            class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white font-medium focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm transition-all"
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
                        <label for="date_selector" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            2. Select Date
                        </label>
                        <input 
                            type="date" 
                            id="date_selector" 
                            min="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                            value="<?= date('Y-m-d') ?>"
                            class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white font-medium focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm transition-all"
                        >
                    </div>
                </div>

                <!-- Service Quick Specs Bar -->
                <div class="mt-5 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                    <div>
                        Duration: <strong id="spec-duration" class="text-cyan-400"><?= h($selectedService['duration_minutes'] ?? 30) ?> min</strong>
                    </div>
                    <div>
                        Max Capacity: <strong id="spec-capacity" class="text-slate-200"><?= h($selectedService['capacity'] ?? 4) ?> athletes/slot</strong>
                    </div>
                    <div>
                        Operating Hours: <strong class="text-slate-200">06:00 – 22:00 IST</strong>
                    </div>
                </div>
            </div>

            <!-- Dynamic Slot Grid Card -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-300">
                        3. Choose Available Time Slot
                    </div>
                    <div id="slots-count-badge" class="text-xs font-medium text-slate-400">
                        Loading slots...
                    </div>
                </div>

                <!-- Loading State -->
                <div id="slots-loading" class="py-16 text-center">
                    <div class="w-8 h-8 border-2 border-cyan-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                    <div class="text-xs text-slate-400 font-medium">Fetching real-time availability...</div>
                </div>

                <!-- Empty / Closed Message -->
                <div id="slots-empty" class="hidden py-14 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-800 text-slate-500 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 id="empty-title" class="text-sm font-bold text-white font-heading">No Available Slots</h3>
                    <p id="empty-subtitle" class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
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
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl sticky top-24">
                <h3 class="text-lg font-bold text-white font-heading border-b border-slate-800 pb-4 mb-5">
                    Reservation Summary
                </h3>

                <div class="space-y-3.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Modality</span>
                        <span id="sum-service" class="font-semibold text-white"><?= h($selectedService['name'] ?? 'Spa') ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-400">Date</span>
                        <span id="sum-date" class="font-semibold text-slate-200"><?= date('d M Y') ?></span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-400">Time Slot</span>
                        <span id="sum-time" class="font-semibold text-cyan-400">Select a slot</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-slate-400">Duration</span>
                        <span id="sum-duration" class="text-slate-300"><?= h($selectedService['duration_minutes'] ?? 30) ?> Minutes</span>
                    </div>

                    <div class="pt-4 border-t border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Base Rate</span>
                            <span>&#8377;<span id="sum-base"><?= number_format((float) ($selectedService['price'] ?? 0), 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>GST (18%)</span>
                            <span>&#8377;<span id="sum-gst"><?= number_format((float) ($selectedService['pricing']['gst_amount'] ?? 0), 2) ?></span></span>
                        </div>
                        <div class="flex justify-between text-base font-extrabold text-white pt-2 border-t border-slate-800/80 font-heading">
                            <span>Total Payable</span>
                            <span class="text-cyan-400">&#8377;<span id="sum-total"><?= number_format((float) ($selectedService['pricing']['total_amount'] ?? 0), 2) ?></span></span>
                        </div>
                    </div>
                </div>

                <!-- Terms Acknowledgement -->
                <div class="mt-6 pt-5 border-t border-slate-800">
                    <label class="flex items-start gap-3 cursor-pointer text-xs text-slate-300">
                        <input 
                            type="checkbox" 
                            id="terms-check" 
                            class="mt-0.5 rounded bg-slate-950 border-slate-700 text-cyan-500 focus:ring-cyan-500"
                        >
                        <span>
                            I agree to the <a href="<?= app_url('terms') ?>" target="_blank" class="text-cyan-400 hover:underline">Terms &amp; Health Declaration</a>. I confirm I am physically fit for thermal therapy.
                        </span>
                    </label>
                </div>

                <!-- Proceed Button -->
                <div class="mt-6">
                    <button 
                        type="button" 
                        id="proceed-hold-btn" 
                        disabled 
                        class="w-full py-3.5 px-4 rounded-xl bg-slate-800 text-slate-500 font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-not-allowed"
                    >
                        Select Time Slot
                    </button>
                    <p class="text-[11px] text-slate-500 text-center mt-2.5">
                        Slot is held for 10 minutes upon proceeding.
                    </p>
                </div>

                <!-- Error Notice -->
                <div id="booking-error" class="hidden mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-medium">
                    <!-- Dynamic error text -->
                </div>
            </div>
        </div>
    </div>
</div>

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
                btn.className = `p-3 rounded-xl border text-left flex flex-col justify-between transition-all ${
                    slot.available
                        ? 'bg-slate-950 border-slate-800 hover:border-cyan-500 text-slate-200 cursor-pointer'
                        : 'bg-slate-950/40 border-slate-900 text-slate-600 cursor-not-allowed opacity-60'
                }`;

                let capLabel = '';
                if (slot.available) {
                    capLabel = `<span class="text-[10px] text-cyan-400 font-medium">${slot.remaining_capacity} left</span>`;
                } else if (slot.unavailable_reason === 'past') {
                    capLabel = '<span class="text-[10px] text-slate-600 uppercase">Passed</span>';
                } else if (slot.unavailable_reason === 'user_conflict') {
                    capLabel = '<span class="text-[10px] text-amber-500 font-medium">Conflict</span>';
                } else {
                    capLabel = '<span class="text-[10px] text-rose-400 font-medium">Full</span>';
                }

                btn.innerHTML = `
                    <div class="text-sm font-bold font-heading">${slot.display_start}</div>
                    <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500">
                        <span>${slot.duration_minutes}m</span>
                        ${capLabel}
                    </div>
                `;

                if (slot.available) {
                    btn.addEventListener('click', () => {
                        // Deselect other buttons
                        slotsGrid.querySelectorAll('button').forEach(b => {
                            b.classList.remove('border-cyan-400', 'bg-cyan-500/10', 'ring-2', 'ring-cyan-500');
                            b.classList.add('border-slate-800', 'bg-slate-950');
                        });
                        // Highlight this button
                        btn.classList.remove('border-slate-800', 'bg-slate-950');
                        btn.classList.add('border-cyan-400', 'bg-cyan-500/10', 'ring-2', 'ring-cyan-500');

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
            proceedBtn.className = 'w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all cursor-pointer';
            proceedBtn.textContent = 'Hold Slot & Proceed to Payment';
        } else {
            proceedBtn.disabled = true;
            proceedBtn.className = 'w-full py-3.5 px-4 rounded-xl bg-slate-800 text-slate-500 font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-not-allowed';
            proceedBtn.textContent = selectedSlot ? 'Agree to Terms to Continue' : 'Select Time Slot';
        }
    }

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
                activeHold = null;
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

    // Create Hold Submission
    proceedBtn.addEventListener('click', async () => {
        if (!selectedSlot || !termsCheck.checked) return;

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
            proceedBtn.className = 'w-full py-3.5 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/25 transition-all cursor-pointer';
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
            activeHold = null;
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
