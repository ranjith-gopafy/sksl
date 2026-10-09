<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <!-- Header with SKSL Brand Colors -->
    <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-sky-50 border border-sky-200/80 mb-3 shadow-xs">
            <span class="w-2 h-2 rounded-full bg-[#075183]"></span>
            <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Clinical Sports Medicine</span>
        </div>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 font-heading tracking-tight mt-1">
            Physiotherapy &amp; Sports Psychology
        </h1>
        <p class="text-slate-600 text-sm sm:text-base mt-3 leading-relaxed">
            One-on-one professional consultations with licensed sports physiotherapists and performance psychologists to diagnose injuries, accelerate musculoskeletal recovery, and master cognitive conditioning.
        </p>
    </div>

    <!-- Professional Services Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-10 mb-14">
        <?php foreach ($services as $service): 
            $isPhysio = ($service['slug'] === 'physiotherapy');
            $imgSrc = service_image($service['image'] ?? null);
            $duration = (int) $service['duration_minutes'];
        ?>
            <div class="bg-white border border-[#D9DBDA] rounded-3xl overflow-hidden shadow-sm hover:shadow-xl hover:border-[#4A9CC0] transition-all duration-300 flex flex-col group">
                <!-- Service Banner Image -->
                <div class="relative w-full h-64 sm:h-72 overflow-hidden bg-slate-100">
                    <img 
                        src="<?= h($imgSrc) ?>" 
                        alt="<?= h($service['name']) ?>" 
                        width="1200" height="675"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                        loading="lazy" decoding="async"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-slate-950/20 to-transparent" aria-hidden="true"></div>
                    
                    <!-- Duration Badge -->
                    <div class="absolute top-4 left-4">
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-white/95 text-[#075183] shadow-md backdrop-blur-md flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?= (int) $duration ?> Minutes Session
                        </span>
                    </div>

                    <!-- Category Tag -->
                    <div class="absolute top-4 right-4">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#053d63]/90 text-white backdrop-blur-md shadow-xs">
                            <?= $isPhysio ? 'Clinical Rehabilitation' : 'Mental Conditioning' ?>
                        </span>
                    </div>

                    <!-- Service Title Overlay on Image Bottom -->
                    <div class="absolute bottom-4 left-4 right-4 text-white">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-[#D6981E]">
                            <?= $isPhysio ? 'Musculoskeletal Health' : 'Cognitive Performance' ?>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-extrabold font-heading text-white mt-0.5">
                            <?= h($service['name']) ?>
                        </h2>
                    </div>
                </div>

                <!-- Service Content -->
                <div class="p-6 sm:p-8 flex-1 flex flex-col justify-between space-y-6">
                    <div>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            <?= h($service['description']) ?>
                        </p>

                        <!-- Key Pillars -->
                        <div class="mt-6 pt-5 border-t border-slate-100">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Protocol Includes</h3>
                            <ul class="space-y-2 text-xs text-slate-700 font-medium">
                                <?php if ($isPhysio): ?>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Comprehensive clinical movement screening &amp; injury diagnosis</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Targeted myofascial therapy, dry needling &amp; joint mobilization</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Personalized active rehab exercise roadmap</span>
                                    </li>
                                <?php else: ?>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Pre-competition anxiety regulation &amp; stress management</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>High-pressure cognitive focus drills &amp; visualization conditioning</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Post-injury psychological recovery &amp; return-to-sport mental readiness</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Pricing & Booking Action Footer -->
                    <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Consultation Fee</div>
                            <div class="text-base sm:text-lg font-extrabold text-[#075183] font-heading mt-0.5">
                                <?= $isPhysio ? 'Assessed at Centre' : 'Payable at Centre' ?>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                <?= $isPhysio ? 'Fee confirmed by your practitioner at the centre' : 'Flat 1-hour consultation slot' ?>
                            </div>
                        </div>

                        <a 
                            href="<?= app_url('booking?service=' . urlencode($service['slug'])) ?>" 
                            class="inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md hover:-translate-y-0.5 transition-all text-center"
                        >
                            <span>Book Consultation</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Centre Policy Notice Card -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-7 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0 border border-amber-200/70">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 font-heading">Consultation Appointment Guidelines</h3>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed max-w-2xl">
                    Online reservations confirm your practitioner appointment slot directly with ₹0 payment required at booking. For any scheduling adjustments, please contact the centre directly at <?= h((string) config('business.phone')) ?> or <?= h((string) config('business.support_email')) ?>.
                </p>
            </div>
        </div>
        <a href="<?= app_url('contact') ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-xs font-bold text-slate-700 transition-colors shrink-0">
            Contact Centre
        </a>
    </div>
</div>
