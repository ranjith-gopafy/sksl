<?php
/**
 * SKSL Admin — Professional Availability & Schedules
 */
$daysOfWeek = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];
?>

<div class="w-full max-w-7xl mx-auto">
    <!-- Service Switcher -->
    <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-600 mr-2">Practitioner:</span>
        <?php foreach ($professionalServices as $srv): ?>
            <a 
                href="<?= app_url('admin/availability?service_id=' . (int) $srv['id']) ?>" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap <?= (int) $srv['id'] === $selectedServiceId ? 'bg-[#075183] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' ?>"
            >
                <?= h($srv['name']) ?> (<?= (int) $srv['duration_minutes'] ?>m)
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Add Availability Rule -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h2 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Working Schedule
                </h2>
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    Configure recurring weekly practitioner hours or set a specific calendar date override.
                </p>

                <form method="POST" action="<?= app_url('admin/availability') ?>" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>
                    <input type="hidden" name="service_id" value="<?= $selectedServiceId ?>">

                    <div>
                        <label for="rule_type_select" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Schedule Type</label>
                        <select name="rule_type" id="rule_type_select" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <option value="recurring_weekday">Recurring Weekly Day</option>
                            <option value="specific_date">Specific Date Exception</option>
                        </select>
                    </div>

                    <div id="weekday_group">
                        <label for="f_weekday_1" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Day of the Week</label>
                        <select id="f_weekday_1" name="weekday" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <?php foreach ($daysOfWeek as $num => $dayName): ?>
                                <option value="<?= $num ?>"><?= $dayName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="date_group" class="hidden">
                        <label for="f_specific_date_2" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Specific Calendar Date</label>
                        <input id="f_specific_date_2" type="date" name="specific_date" min="<?= date('Y-m-d') ?>" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="f_start_time_3" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Start Time</label>
                            <input id="f_start_time_3" type="time" name="start_time" value="09:00" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                        <div>
                            <label for="f_end_time_4" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">End Time</label>
                            <input id="f_end_time_4" type="time" name="end_time" value="13:00" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                    </div>

                    <div>
                        <label for="f_is_available_5" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Availability Status</label>
                        <select id="f_is_available_5" name="is_available" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <option value="1">Available (Accept Appointments)</option>
                            <option value="0">Off / Unavailable (Day Off)</option>
                        </select>
                    </div>

                    <div>
                        <label for="f_notes_6" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Notes / Label (Optional)</label>
                        <input id="f_notes_6" type="text" name="notes" placeholder="e.g. Morning Clinical Shift" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-md">
                        Save Schedule Rule
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Rules List -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 font-heading">
                            Active Practitioner Rules
                        </h2>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Specific date exceptions automatically take precedence over recurring rules.
                        </p>
                    </div>
                    <span class="text-xs font-bold bg-slate-100 text-slate-700 px-3 py-1 rounded-full">
                        <?= count($rules) ?> Rules Configured
                    </span>
                </div>

                <?php if (empty($rules)): ?>
                    <div class="text-center py-12 text-slate-500 text-xs">
                        No availability rules configured yet. Standard facility hours will be used by default until custom rules are added.
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($rules as $r): 
                            $isAvail = !empty($r['is_available']);
                            $isRecurring = $r['rule_type'] === 'recurring_weekday';
                        ?>
                            <div class="p-4 rounded-2xl border <?= $isAvail ? 'bg-white border-slate-200' : 'bg-rose-50/50 border-rose-200' ?> flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?= $isRecurring ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' ?>">
                                            <?= $isRecurring ? 'Recurring Weekly' : 'Specific Date' ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $isAvail ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>">
                                            <?= $isAvail ? 'Working Hours' : 'Day Off / Closed' ?>
                                        </span>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900">
                                        <?= $isRecurring ? ($daysOfWeek[(int)$r['weekday']] ?? 'Weekday') : date('d M Y (D)', strtotime($r['specific_date'])) ?>
                                        &bull;
                                        <span class="text-[#075183]">
                                            <?= date('h:i A', strtotime($r['start_time'])) ?> – <?= date('h:i A', strtotime($r['end_time'])) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($r['notes'])): ?>
                                        <div class="text-xs text-slate-600 mt-0.5"><?= h($r['notes']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="<?= app_url('admin/availability/' . (int) $r['id'] . '/delete') ?>" data-confirm="Delete this schedule rule?">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <button type="submit" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Rule">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('rule_type_select');
    const weekdayGrp = document.getElementById('weekday_group');
    const dateGrp    = document.getElementById('date_group');

    typeSelect.addEventListener('change', () => {
        if (typeSelect.value === 'specific_date') {
            weekdayGrp.classList.add('hidden');
            dateGrp.classList.remove('hidden');
        } else {
            weekdayGrp.classList.remove('hidden');
            dateGrp.classList.add('hidden');
        }
    });
});
</script>
