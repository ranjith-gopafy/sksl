<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Admin Navigation Header -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold font-heading text-sm shadow-xs">
                AD
            </div>
            <div>
                <h1 class="text-base font-extrabold text-slate-900 font-heading">SKSL Control Center</h1>
                <p class="text-xs text-slate-500">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-1.5 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Hero Banner
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Add Closed Date Form -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h2 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Mark Facility Closure
                </h2>
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    Closed dates automatically block all time slots facility-wide. Customers cannot reserve any modality on these dates.
                </p>

                <form method="POST" action="<?= app_url('admin/closed-dates') ?>" class="space-y-4" id="closed-date-form">
                    <?= \App\Helpers\Csrf::field() ?>

                    <!-- Tailwind Interactive Calendar Date Picker -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Closure Date
                        </label>
                        
                        <!-- Hidden native input preserving backend and form submission -->
                        <input 
                            type="date" 
                            id="closed_date" 
                            name="closed_date" 
                            min="<?= date('Y-m-d') ?>" 
                            required 
                            class="sr-only"
                        >

                        <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-3.5 shadow-2xs">
                            <!-- Calendar Header: Month Navigator -->
                            <div class="flex items-center justify-between mb-3 pb-2.5 border-b border-slate-200/70">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#BF5B2B]"></span>
                                    <span id="closure-cal-month-year" class="text-xs font-extrabold text-slate-900 font-heading tracking-tight"></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button 
                                        type="button" 
                                        id="closure-cal-prev" 
                                        class="p-1 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-white border border-transparent hover:border-slate-200 transition-all cursor-pointer"
                                        title="Previous month"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                                        </svg>
                                    </button>
                                    <button 
                                        type="button" 
                                        id="closure-cal-next" 
                                        class="p-1 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-white border border-transparent hover:border-slate-200 transition-all cursor-pointer"
                                        title="Next month"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Quick Preset Chips -->
                            <div class="flex items-center gap-1.5 mb-2.5 overflow-x-auto pb-0.5">
                                <button type="button" id="closure-preset-today" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-white border border-slate-200 text-slate-600 hover:text-[#075183] hover:border-[#075183] transition-colors cursor-pointer shrink-0">
                                    Today
                                </button>
                                <button type="button" id="closure-preset-tomorrow" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-white border border-slate-200 text-slate-600 hover:text-[#075183] hover:border-[#075183] transition-colors cursor-pointer shrink-0">
                                    Tomorrow
                                </button>
                                <button type="button" id="closure-preset-next-mon" class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-white border border-slate-200 text-slate-600 hover:text-[#075183] hover:border-[#075183] transition-colors cursor-pointer shrink-0">
                                    Next Mon
                                </button>
                            </div>

                            <!-- Day Headers -->
                            <div class="grid grid-cols-7 gap-1 text-center mb-1">
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Mo</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Tu</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">We</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Th</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Fr</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Sa</span>
                                <span class="text-[10px] font-bold text-slate-400 py-0.5">Su</span>
                            </div>

                            <!-- Interactive Days Grid -->
                            <div id="closure-cal-days-grid" class="grid grid-cols-7 gap-1">
                                <!-- Populated dynamically by JavaScript -->
                            </div>

                            <!-- Selected Date Confirmation Display -->
                            <div id="closure-selected-display" class="mt-2.5 pt-2 border-t border-slate-200/70 text-center">
                                <span class="text-[11px] font-medium text-slate-500">Selected: </span>
                                <span id="closure-selected-date-text" class="text-[11px] font-bold text-amber-700">None</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Closure Reason
                        </label>
                        <input 
                            type="text" 
                            id="reason" 
                            name="reason" 
                            placeholder="e.g. Deep Facility Sanitization & Maintenance" 
                            class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-amber-500/20 transition-all cursor-pointer"
                    >
                        Block Date Facility-Wide
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Closed Dates List -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 font-heading uppercase tracking-wider">
                        Scheduled Closures (<?= count($closedDates) ?>)
                    </h3>
                </div>

                <?php if (empty($closedDates)): ?>
                    <div class="text-center py-16 text-slate-400 text-xs">
                        No closed dates recorded. The recovery facility operates normally 7 days a week (06:00 – 22:00 IST).
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="py-3.5 px-6">Closure Date</th>
                                    <th class="py-3.5 px-4">Reason / Notes</th>
                                    <th class="py-3.5 px-4">Added On</th>
                                    <th class="py-3.5 px-6 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($closedDates as $cd): ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="py-4 px-6 font-bold text-slate-900">
                                            <div class="text-sm"><?= date('l, d M Y', strtotime($cd['closed_date'])) ?></div>
                                            <span class="font-mono text-[10px] text-sky-700"><?= h($cd['closed_date']) ?></span>
                                        </td>

                                        <td class="py-4 px-4 text-slate-600">
                                            <?= h($cd['reason'] ?: 'Facility Closed / General Maintenance') ?>
                                        </td>

                                        <td class="py-4 px-4 text-slate-400 text-[11px]">
                                            <?= date('d M Y', strtotime($cd['created_at'])) ?>
                                        </td>

                                        <td class="py-4 px-6 text-right">
                                            <form method="POST" action="<?= app_url('admin/closed-dates/' . (int) $cd['id'] . '/delete') ?>" onsubmit="return confirm('Re-open facility on <?= h($cd['closed_date']) ?>?')">
                                                <?= \App\Helpers\Csrf::field() ?>
                                                <button 
                                                    type="submit" 
                                                    class="px-3 py-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs font-semibold transition-colors cursor-pointer"
                                                >
                                                    Remove &bull; Re-open
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const existingClosedDates = new Set(<?= json_encode(array_values(array_map(fn($d) => (string) $d['closed_date'], $closedDates))) ?>);
    const hiddenInput = document.getElementById('closed_date');
    const monthYearEl = document.getElementById('closure-cal-month-year');
    const daysGrid = document.getElementById('closure-cal-days-grid');
    const selectedDateText = document.getElementById('closure-selected-date-text');
    const prevBtn = document.getElementById('closure-cal-prev');
    const nextBtn = document.getElementById('closure-cal-next');

    const today = new Date();
    const todayStr = formatLocalDate(today);

    // Initial calendar view starts at today's month
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth(); // 0-indexed
    let selectedDate = hiddenInput.value || '';

    function formatLocalDate(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatDateDisplay(dateStr) {
        if (!dateStr) return 'None';
        const parts = dateStr.split('-');
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }

    function selectDate(dateStr) {
        selectedDate = dateStr;
        hiddenInput.value = dateStr;
        if (selectedDateText) {
            selectedDateText.textContent = formatDateDisplay(dateStr);
        }
        renderCalendar();
    }

    function renderCalendar() {
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        monthYearEl.textContent = `${monthNames[viewMonth]} ${viewYear}`;

        daysGrid.innerHTML = '';

        // Determine starting day of week (Monday=0 ... Sunday=6)
        const firstDayObj = new Date(viewYear, viewMonth, 1);
        let firstDayOfWeek = firstDayObj.getDay(); // 0 is Sunday
        let offset = (firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1);

        for (let i = 0; i < offset; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'h-8';
            daysGrid.appendChild(emptyCell);
        }

        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

        for (let day = 1; day <= daysInMonth; day++) {
            const dateObj = new Date(viewYear, viewMonth, day);
            const dateStr = formatLocalDate(dateObj);
            const isPast = dateStr < todayStr;
            const isToday = dateStr === todayStr;
            const isAlreadyClosed = existingClosedDates.has(dateStr);
            const isSelected = (selectedDate === dateStr);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = day;

            let baseClasses = 'h-8 w-full rounded-xl text-xs font-semibold flex items-center justify-center transition-all ';

            if (isPast) {
                baseClasses += 'text-slate-300 cursor-not-allowed';
                btn.disabled = true;
            } else if (isAlreadyClosed) {
                baseClasses += 'bg-amber-100/90 text-amber-800 border border-amber-300 font-bold hover:bg-amber-200 cursor-pointer shadow-2xs';
                btn.title = 'Already marked closed';
                btn.addEventListener('click', () => {
                    if (window.showToast) {
                        window.showToast(`Facility is already marked closed on ${dateStr}.`, 'info');
                    } else {
                        alert(`Facility is already marked closed on ${dateStr}.`);
                    }
                });
            } else if (isSelected) {
                baseClasses += 'bg-[#BF5B2B] text-white font-extrabold shadow-xs scale-105 cursor-pointer';
            } else {
                baseClasses += 'bg-white hover:bg-slate-200 text-slate-800 border border-slate-200/60 hover:border-slate-300 cursor-pointer';
                if (isToday) {
                    baseClasses += ' ring-1.5 ring-[#075183] font-bold text-[#075183]';
                }
            }

            btn.className = baseClasses;

            if (!isPast && !isAlreadyClosed) {
                btn.addEventListener('click', () => {
                    selectDate(dateStr);
                });
            }

            daysGrid.appendChild(btn);
        }
    }

    prevBtn.addEventListener('click', function () {
        viewMonth--;
        if (viewMonth < 0) {
            viewMonth = 11;
            viewYear--;
        }
        renderCalendar();
    });

    nextBtn.addEventListener('click', function () {
        viewMonth++;
        if (viewMonth > 11) {
            viewMonth = 0;
            viewYear++;
        }
        renderCalendar();
    });

    // Preset buttons
    const presetToday = document.getElementById('closure-preset-today');
    if (presetToday) {
        presetToday.addEventListener('click', function () {
            viewYear = today.getFullYear();
            viewMonth = today.getMonth();
            selectDate(todayStr);
        });
    }

    const presetTomorrow = document.getElementById('closure-preset-tomorrow');
    if (presetTomorrow) {
        presetTomorrow.addEventListener('click', function () {
            const tom = new Date(today);
            tom.setDate(today.getDate() + 1);
            viewYear = tom.getFullYear();
            viewMonth = tom.getMonth();
            selectDate(formatLocalDate(tom));
        });
    }

    const presetNextMon = document.getElementById('closure-preset-next-mon');
    if (presetNextMon) {
        presetNextMon.addEventListener('click', function () {
            const mon = new Date(today);
            const dayOfWeek = today.getDay(); // Sunday=0, Monday=1
            const daysUntilNextMon = ((1 + 7 - dayOfWeek) % 7) || 7;
            mon.setDate(today.getDate() + daysUntilNextMon);
            viewYear = mon.getFullYear();
            viewMonth = mon.getMonth();
            selectDate(formatLocalDate(mon));
        });
    }

    const form = document.getElementById('closed-date-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!hiddenInput.value) {
                e.preventDefault();
                if (window.showToast) {
                    window.showToast('Please select a closure date from the calendar first.', 'warning');
                } else {
                    alert('Please select a closure date from the calendar first.');
                }
            }
        });
    }

    // Initial render
    renderCalendar();
});
</script>
