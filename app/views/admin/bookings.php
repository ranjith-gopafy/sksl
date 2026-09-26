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
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Hero Banner
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <form method="POST" action="<?= app_url('admin/logout') ?>" class="inline">
                <?= \App\Helpers\Csrf::field() ?>
                <button type="submit" class="px-3.5 py-2 rounded-xl text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors cursor-pointer bg-transparent border-0">Sign Out</button>
            </form>
        </nav>
    </div>

    <!-- Filters Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 mb-8 shadow-xs">
        <form method="GET" action="<?= app_url('admin/bookings') ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label for="search" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Athlete Search</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?= h($filters['search'] ?? '') ?>" 
                    placeholder="Search by name, email, mobile, or reference..."
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <!-- Date -->
            <div>
                <label for="date" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Session Date</label>
                <div class="relative">
                    <input 
                        type="date" 
                        id="date" 
                        name="date" 
                        value="<?= h($filters['date'] ?? '') ?>" 
                        class="w-full pl-9 pr-3 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-[#075183] transition-colors"
                    >
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>

            <!-- Service -->
            <div>
                <label for="service_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Modality</label>
                <select 
                    id="service_id" 
                    name="service_id" 
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                >
                    <option value="">All Modalities</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?= (int) $srv['id'] ?>" <?= ((int) ($filters['service_id'] ?? 0) === (int) $srv['id']) ? 'selected' : '' ?>>
                            <?= h($srv['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label for="status" class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Status</label>
                <div class="flex gap-2">
                    <select 
                        id="status" 
                        name="status" 
                        class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-xs focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                        <option value="confirmed" <?= (($filters['status'] ?? 'confirmed') === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                        <option value="completed" <?= (($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= (($filters['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        <option value="pending" <?= (($filters['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="expired" <?= (($filters['status'] ?? '') === 'expired') ? 'selected' : '' ?>>Expired (unpaid)</option>
                        <option value="all" <?= (($filters['status'] ?? '') === 'all') ? 'selected' : '' ?>>All Statuses</option>
                    </select>
                    <button 
                        type="submit" 
                        class="px-4 py-2.5 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer shrink-0 shadow-xs"
                    >
                        Filter
                    </button>
                    <?php if (!empty(array_filter($filters, fn($v, $k) => $k === 'status' ? $v !== 'confirmed' : !empty($v), ARRAY_FILTER_USE_BOTH))): ?>
                        <a 
                            href="<?= app_url('admin/bookings') ?>" 
                            class="px-3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs flex items-center justify-center transition-colors shrink-0"
                            title="Reset filters"
                        >
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Bookings Table -->
    <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <h2 class="text-sm font-bold text-slate-900 font-heading uppercase tracking-wider">
                    Booking Records
                </h2>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                    <?= count($bookings) ?> <?= ucfirst(h($filters['status'] ?? 'confirmed')) ?>
                </span>
            </div>

            <!-- Top Right Corner Status Filter Tabs: All, Confirmed, Completed, Cancelled, Pending -->
            <div class="flex items-center gap-1.5 overflow-x-auto bg-slate-50 p-1.5 rounded-2xl border border-slate-200/80">
                <?php
                $statusTabs = [
                    'all'       => 'All',
                    'confirmed' => 'Confirmed',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'pending'   => 'Pending',
                    'expired'   => 'Expired',
                ];
                $currentStatus = $filters['status'] ?? 'confirmed';
                foreach ($statusTabs as $statusCode => $statusLabel):
                    $isActive = ($currentStatus === $statusCode);
                    // Build query preserving date, service_id, and search
                    $tabQueryParams = array_filter([
                        'date'       => $filters['date'] ?? null,
                        'service_id' => $filters['service_id'] ?? null,
                        'search'     => $filters['search'] ?? null,
                        'status'     => $statusCode,
                    ]);
                    $tabUrl = app_url('admin/bookings' . (!empty($tabQueryParams) ? '?' . http_build_query($tabQueryParams) : ''));
                    $badgeCount = $statusCounts[$statusCode] ?? null;
                ?>
                    <a 
                        href="<?= h($tabUrl) ?>" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap flex items-center gap-1.5 <?= $isActive ? 'bg-[#075183] text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white' ?>"
                    >
                        <span><?= h($statusLabel) ?></span>
                        <?php if ($badgeCount !== null): ?>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-200/70 text-slate-600' ?>">
                                <?= $badgeCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="text-center py-16 text-slate-400 text-xs">
                No bookings match the selected filter criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3.5 px-6">Reference</th>
                            <th class="py-3.5 px-4">Athlete</th>
                            <th class="py-3.5 px-4">Modality</th>
                            <th class="py-3.5 px-4">Schedule (IST)</th>
                            <th class="py-3.5 px-4 text-right">Amount</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-6 text-right">Management</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($bookings as $b): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-4 px-6 font-mono text-sky-700 font-bold">
                                    <?= h($b['booking_reference']) ?>
                                    <span class="block text-[10px] text-slate-400 font-sans font-normal mt-0.5">
                                        <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
                                    </span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-bold text-slate-900"><?= h($b['user_name']) ?></div>
                                    <div class="text-[11px] text-slate-500"><?= h($b['user_email']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= h($b['user_mobile']) ?></div>
                                </td>

                                <td class="py-4 px-4">
                                    <span class="font-bold text-slate-900"><?= h($b['service_name']) ?></span>
                                    <span class="block text-[10px] text-slate-500"><?= (int) $b['service_duration_minutes'] ?> mins</span>
                                </td>

                                <td class="py-4 px-4">
                                    <div class="font-medium text-slate-800"><?= date('D, d M Y', strtotime($b['booking_date'])) ?></div>
                                    <div class="font-mono text-[11px] text-sky-700"><?= date('h:i A', strtotime($b['start_time'])) ?> – <?= date('h:i A', strtotime($b['end_time'])) ?></div>
                                </td>

                                <td class="py-4 px-4 text-right">
                                    <div class="font-extrabold text-slate-900 font-heading">&#8377;<?= number_format((float) $b['total_amount'], 2) ?></div>
                                    <div class="text-[10px] text-slate-400">Base: &#8377;<?= number_format((float) $b['base_amount'], 2) ?></div>
                                </td>

                                <td class="py-4 px-4 text-center">
                                    <?php if ($b['booking_status'] === 'confirmed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 border border-emerald-200 text-emerald-800">
                                            Confirmed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'completed'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 border border-sky-200 text-sky-800">
                                            Completed
                                        </span>
                                    <?php elseif ($b['booking_status'] === 'cancelled'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 border border-rose-200 text-rose-800">
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                                            <?= h($b['booking_status']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($b['razorpay_payment_id'])): ?>
                                        <span class="block text-[9px] font-mono text-slate-400 mt-1 truncate max-w-[120px] mx-auto" title="<?= h($b['razorpay_payment_id']) ?>">
                                            <?= h($b['razorpay_payment_id']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-6 text-right">
                                    <div class="relative inline-block text-left" id="action-wrap-<?= (int) $b['id'] ?>">
                                        <!-- 3-Dots Kebab Button -->
                                        <button 
                                            type="button" 
                                            data-action="toggleActionMenu" data-id="<?= (int) $b['id'] ?>" 
                                            class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 inline-flex items-center justify-center transition-colors border border-slate-200 cursor-pointer shadow-2xs"
                                            title="Management Options"
                                            aria-label="Actions for booking <?= h($b['booking_reference']) ?>"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="5" r="1.75" />
                                                <circle cx="12" cy="12" r="1.75" />
                                                <circle cx="12" cy="19" r="1.75" />
                                            </svg>
                                        </button>

                                        <!-- Dropdown Menu -->
                                        <div 
                                            id="action-dropdown-<?= (int) $b['id'] ?>" 
                                            class="action-dropdown hidden absolute right-0 mt-1.5 w-48 rounded-2xl bg-white border border-slate-200 shadow-xl py-1.5 z-30 text-left text-xs"
                                        >
                                            <!-- 1. View Details (Read-Only View Modal) -->
                                            <button 
                                                type="button"
                                                class="btn-view-booking-details w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2.5 text-slate-700 hover:text-slate-900 font-medium transition-colors cursor-pointer"
                                                data-booking="<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>View Details</span>
                                            </button>

                                            <!-- 2. Tax Invoice PDF (only paid, live bookings can have one) -->
                                            <?php if ($b['payment_status'] === 'paid' && $b['booking_status'] !== 'cancelled' && $b['booking_status'] !== 'pending'): ?>
                                                <a 
                                                    href="<?= app_url('bookings/' . h($b['booking_reference']) . '/invoice') ?>" 
                                                    target="_blank"
                                                    class="w-full px-3.5 py-2 hover:bg-slate-50 flex items-center gap-2.5 text-slate-700 hover:text-slate-900 font-medium transition-colors"
                                                >
                                                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <span>Tax Invoice (PDF)</span>
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                            $canComplete = \App\Models\BookingModel::checkAdminTransition($b, 'completed')['allowed'];
                                            $canCancel   = \App\Models\BookingModel::checkAdminTransition($b, 'cancelled')['allowed'];
                                            ?>
                                            <?php if ($canComplete || $canCancel): ?>
                                            <div class="my-1 border-t border-slate-100"></div>
                                            <div class="px-3.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Change Status</div>
                                            <?php else: ?>
                                            <div class="my-1 border-t border-slate-100"></div>
                                            <div class="px-3.5 py-1.5 text-[11px] text-slate-400 italic">
                                                <?php
                                                echo match ($b['booking_status']) {
                                                    'cancelled' => 'Cancelled — final. Athlete must book again.',
                                                    'expired'   => 'Checkout expired unpaid — final.',
                                                    default     => 'Completed — final.',
                                                };
                                                ?>
                                            </div>
                                            <?php endif; ?>

                                            <!-- 3. Status Transitions (staff only; see BookingModel::ADMIN_TRANSITIONS) -->
                                            <?php if ($canComplete): ?>
                                                <form method="POST" action="<?= app_url('admin/bookings/' . (int) $b['id'] . '/status') ?>">
                                                    <?= \App\Helpers\Csrf::field() ?>
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="w-full px-3.5 py-1.5 hover:bg-sky-50 flex items-center gap-2 text-sky-700 font-medium transition-colors cursor-pointer text-left">
                                                        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>Mark Completed</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($canCancel): ?>
                                                <form method="POST" action="<?= app_url('admin/bookings/' . (int) $b['id'] . '/status') ?>" data-confirm="<?= h('Cancel booking #' . $b['booking_reference'] . '?' . ($b['payment_status'] === 'paid' ? ' The athlete has paid — you will need to refund via Razorpay.' : '')) ?>">
                                                    <?= \App\Helpers\Csrf::field() ?>
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="w-full px-3.5 py-1.5 hover:bg-rose-50 flex items-center gap-2 text-rose-700 font-medium transition-colors cursor-pointer text-left">
                                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        <span>Cancel Booking</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Read-Only Booking Details View Modal -->
<div id="booking-view-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 sm:p-7 shadow-2xl relative my-8">
        <!-- Close Button -->
        <button 
            type="button" 
            data-action="closeBookingViewModal" 
            class="absolute top-5 right-5 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            aria-label="Close modal"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Header -->
        <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-sky-100 text-[#075183] flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-extrabold text-slate-900 font-heading">
                        Booking Record Details
                    </h3>
                    <span id="modal-status-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"></span>
                </div>
                <p class="text-xs text-slate-500 font-mono" id="modal-ref-text">#SKSL-XXXX</p>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="space-y-4 text-xs">
            <!-- Athlete Profile -->
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Athlete Information</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Full Name</span>
                        <span id="modal-user-name" class="font-bold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Email</span>
                        <span id="modal-user-email" class="font-bold text-slate-800 break-all"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Mobile</span>
                        <span id="modal-user-mobile" class="font-bold text-slate-800"></span>
                    </div>
                </div>
            </div>

            <!-- Modality & Schedule -->
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Modality &amp; Schedule</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Modality</span>
                        <span id="modal-service-name" class="font-bold text-[#075183]"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Duration</span>
                        <span id="modal-duration" class="font-bold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Session Date</span>
                        <span id="modal-date" class="font-bold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Time Window</span>
                        <span id="modal-time" class="font-bold text-slate-800 font-mono"></span>
                    </div>
                </div>
            </div>

            <!-- Financial Breakdown -->
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Financial Breakdown</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div>
                        <span class="text-slate-500 block text-[11px]">Base Amount</span>
                        <span id="modal-base-amount" class="font-bold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">GST (18%)</span>
                        <span id="modal-gst-amount" class="font-bold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Convenience Fee</span>
                        <span id="modal-conv-fee" class="font-bold text-slate-800">&#8377;0.00</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[11px]">Total Paid</span>
                        <span id="modal-total-amount" class="font-extrabold text-emerald-600 text-sm"></span>
                    </div>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Payment Verification</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 font-mono text-[11px]">
                    <div>
                        <span class="text-slate-500 block text-[10px] font-sans">Razorpay Payment ID</span>
                        <span id="modal-payment-id" class="text-slate-800 break-all font-semibold"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px] font-sans">Payment Record Status</span>
                        <span id="modal-payment-status" class="text-slate-800 font-semibold uppercase"></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-2">
                <span>Created: <span id="modal-created-at" class="text-slate-600 font-medium"></span></span>
                <span class="text-[10px] uppercase font-bold text-slate-400">SKSL Operations</span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
            <div id="modal-pdf-container">
                <!-- Injected dynamically if valid for PDF -->
            </div>
            <template id="modal-pdf-icon-template"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></template>
            <button 
                type="button" 
                data-action="closeBookingViewModal" 
                class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors cursor-pointer"
            >
                Close View
            </button>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
// Invoked via data-action="toggleActionMenu" data-id="…" (see public/js/app.js)
function toggleActionMenu(event, el) {
    event.stopPropagation();
    const id = (el && el.getAttribute) ? el.getAttribute('data-id') : el;
    const targetDropdown = document.getElementById('action-dropdown-' + id);
    if (!targetDropdown) return;
    const isHidden = targetDropdown.classList.contains('hidden');
    closeAllActionMenus();
    if (isHidden) {
        targetDropdown.classList.remove('hidden');
    }
}

function closeAllActionMenus() {
    document.querySelectorAll('.action-dropdown').forEach(el => el.classList.add('hidden'));
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown') && !e.target.closest('.action-menu-btn')) {
        closeAllActionMenus();
    }
});

function openBookingViewModal(b) {
    document.getElementById('modal-ref-text').textContent = '#' + b.booking_reference;
    document.getElementById('modal-user-name').textContent = b.user_name || 'N/A';
    document.getElementById('modal-user-email').textContent = b.user_email || 'N/A';
    document.getElementById('modal-user-mobile').textContent = b.user_mobile || 'N/A';
    document.getElementById('modal-service-name').textContent = b.service_name || 'N/A';
    document.getElementById('modal-duration').textContent = (b.service_duration_minutes || '30') + ' Minutes';
    document.getElementById('modal-date').textContent = b.booking_date || 'N/A';
    document.getElementById('modal-time').textContent = (b.start_time || '').substring(0, 5) + ' - ' + (b.end_time || '').substring(0, 5);
    document.getElementById('modal-base-amount').textContent = '₹' + parseFloat(b.base_amount || 0).toFixed(2);
    document.getElementById('modal-gst-amount').textContent = '₹' + parseFloat(b.gst_amount || 0).toFixed(2);
    document.getElementById('modal-total-amount').textContent = '₹' + parseFloat(b.total_amount || 0).toFixed(2);
    document.getElementById('modal-payment-id').textContent = b.razorpay_payment_id || 'N/A';
    document.getElementById('modal-payment-status').textContent = b.payment_record_status || b.payment_status || 'Pending';
    document.getElementById('modal-created-at').textContent = b.created_at || 'N/A';

    const statusBadge = document.getElementById('modal-status-badge');
    statusBadge.textContent = (b.booking_status || 'pending').toUpperCase();
    if (b.booking_status === 'confirmed') {
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 border border-emerald-200 text-emerald-800';
    } else if (b.booking_status === 'completed') {
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 border border-sky-200 text-sky-800';
    } else if (b.booking_status === 'cancelled') {
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 border border-rose-200 text-rose-800';
    } else {
        statusBadge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600';
    }

    // Build with DOM APIs (never innerHTML with record data) so a crafted
    // booking reference/status cannot inject markup into the admin page.
    const pdfContainer = document.getElementById('modal-pdf-container');
    pdfContainer.replaceChildren();
    if (b.payment_status === 'paid' && b.booking_status !== 'cancelled' && b.booking_status !== 'pending') {
        const link = document.createElement('a');
        link.href = '<?= app_url('bookings/') ?>' + encodeURIComponent(String(b.booking_reference || '')) + '/invoice';
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'px-3.5 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs flex items-center gap-1.5 transition-colors shadow-2xs';
        const icon = document.getElementById('modal-pdf-icon-template');
        if (icon) link.appendChild(icon.content.cloneNode(true));
        link.appendChild(document.createTextNode('Download PDF Invoice'));
        pdfContainer.appendChild(link);
    } else {
        const note = document.createElement('span');
        note.className = 'text-slate-400 text-xs italic';
        note.textContent = 'Tax invoice unavailable for ' + String(b.booking_status || 'this') + ' booking';
        pdfContainer.appendChild(note);
    }

    document.getElementById('booking-view-modal').classList.remove('hidden');
}

function closeBookingViewModal() {
    document.getElementById('booking-view-modal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-view-booking-details').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var raw = this.getAttribute('data-booking');
            if (raw) {
                try {
                    var booking = JSON.parse(raw);
                    openBookingViewModal(booking);
                } catch(err) {
                    console.error('Failed to parse booking data', err);
                }
            }
            closeAllActionMenus();
        });
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBookingViewModal();
        closeAllActionMenus();
    }
});
</script>
