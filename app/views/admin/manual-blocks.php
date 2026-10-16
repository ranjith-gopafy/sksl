<?php
/**
 * SKSL Admin — Manual Time Blocking & Maintenance
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Create Manual Block Form -->
        <div class="lg:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs sticky top-24">
                <h2 class="text-lg font-bold text-slate-900 font-heading border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Add Manual Time Block
                </h2>
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    Block a specific time window for equipment maintenance, staff cleaning, or private athletic events.
                </p>

                <form method="POST" action="<?= app_url('admin/manual-blocks') ?>" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="f_service_id_1" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Affected Modality</label>
                        <select id="f_service_id_1" name="service_id" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <option value="">Universal (All Modalities & Facility)</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="block_type_select" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Block Frequency</label>
                        <select name="block_type" id="block_type_select" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <option value="specific_date">Specific Date</option>
                            <option value="recurring_weekday">Recurring Weekly Day</option>
                        </select>
                    </div>

                    <div id="block_date_group">
                        <label for="f_specific_date_2" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Calendar Date</label>
                        <input id="f_specific_date_2" type="date" name="specific_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <div id="block_weekday_group" class="hidden">
                        <label for="f_weekday_3" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Day of the Week</label>
                        <select id="f_weekday_3" name="weekday" class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                            <?php foreach ($daysOfWeek as $num => $dayName): ?>
                                <option value="<?= $num ?>"><?= $dayName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="f_start_time_4" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Start Time</label>
                            <input id="f_start_time_4" type="time" name="start_time" value="12:00" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                        <div>
                            <label for="f_end_time_5" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">End Time</label>
                            <input id="f_end_time_5" type="time" name="end_time" value="14:00" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                        </div>
                    </div>

                    <div>
                        <label for="f_reason_6" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Reason / Description</label>
                        <input id="f_reason_6" type="text" name="reason" placeholder="e.g. Water filtration & sanitization" required class="w-full text-xs rounded-xl border border-slate-300 py-2.5 px-3 bg-white font-medium">
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-[#075183] hover:bg-[#053d63] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-md">
                        Enforce Time Block
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Blocks List -->
        <div class="lg:col-span-2">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-7 shadow-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 font-heading">
                            Active Time Blocks
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Any athlete slot overlapping these intervals will show as blocked.
                        </p>
                    </div>
                    <span class="text-xs font-bold bg-slate-100 text-slate-700 px-3 py-1 rounded-full">
                        <?= count($blocks) ?> Configured
                    </span>
                </div>

                <?php if (empty($blocks)): ?>
                    <div class="text-center py-12 text-slate-500 text-xs">
                        No manual time blocks active. All modalities are available according to standard schedules.
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($blocks as $b): 
                            $isRecurring = $b['block_type'] === 'recurring_weekday';
                        ?>
                            <div class="p-4 rounded-2xl border border-slate-200 bg-white flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                                            <?= $b['service_name'] ? h($b['service_name']) : 'Facility-Wide (Universal)' ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                                            <?= $isRecurring ? 'Recurring ' . ($daysOfWeek[(int)$b['weekday']] ?? '') : date('d M Y (D)', strtotime($b['specific_date'])) ?>
                                        </span>
                                    </div>
                                    <div class="text-sm font-bold text-slate-900">
                                        <span class="text-rose-600">Blocked:</span>
                                        <?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?>
                                    </div>
                                    <div class="text-xs text-slate-600 mt-0.5">
                                        <?= h($b['reason']) ?>
                                    </div>
                                </div>

                                <form method="POST" action="<?= app_url('admin/manual-blocks/' . (int) $b['id'] . '/delete') ?>" data-confirm="Remove this time block?">
                                    <?= \App\Helpers\Csrf::field() ?>
                                    <button type="submit" class="p-2 rounded-xl text-rose-600 hover:bg-rose-50 transition-colors" title="Delete Block">
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
    const typeSelect = document.getElementById('block_type_select');
    const dateGrp    = document.getElementById('block_date_group');
    const weekdayGrp = document.getElementById('block_weekday_group');

    typeSelect.addEventListener('change', () => {
        if (typeSelect.value === 'recurring_weekday') {
            dateGrp.classList.add('hidden');
            weekdayGrp.classList.remove('hidden');
        } else {
            dateGrp.classList.remove('hidden');
            weekdayGrp.classList.add('hidden');
        }
    });
});
</script>
