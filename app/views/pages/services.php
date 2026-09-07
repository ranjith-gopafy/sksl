<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Sara Kinetic Sports Lab</span>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 font-heading mt-2">
            Recovery Modalities &amp; Pricing
        </h1>
        <p class="text-slate-600 text-sm sm:text-base mt-3 leading-relaxed">
            Select a recovery protocol below to choose an available date and time slot. All sessions include facility access, towel service, and sanitized amenities.
        </p>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($services as $service): 
            $imgSrc = str_starts_with($service['image'] ?? '', 'images/') || str_starts_with($service['image'] ?? '', 'uploads/')
                ? asset($service['image'])
                : asset('images/services/' . ($service['image'] ?? 'spa.jpg'));
        ?>
            <div class="bg-white border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs hover:shadow-xl hover:border-sky-300 transition-all duration-300 flex flex-col justify-between group">
                <div>
                    <!-- Service Image -->
                    <div class="relative w-full h-52 overflow-hidden bg-slate-100">
                        <img 
                            src="<?= $imgSrc ?>" 
                            alt="<?= h($service['name']) ?>"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>

                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/95 text-slate-900 shadow-xs backdrop-blur-md">
                                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <?= h($service['duration_minutes']) ?> Minutes
                            </span>
                        </div>

                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900/80 text-white backdrop-blur-md">
                                <svg class="w-3.5 h-3.5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Max <?= h($service['capacity']) ?>
                            </span>
                        </div>

                        <div class="absolute bottom-3 left-4 right-4 text-white">
                            <h2 class="text-xl font-extrabold font-heading text-white drop-shadow-sm">
                                <?= h($service['name']) ?>
                            </h2>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="p-6">
                        <p class="text-sm text-slate-600 leading-relaxed min-h-[50px]">
                            <?= h($service['description'] ?: 'Optimized protocol engineered for peak athletic recovery, improved circulation, and muscular restoration.') ?>
                        </p>
                    </div>
                </div>

                <!-- Price Breakdown and CTA -->
                <div class="px-6 pb-6 pt-4 border-t border-slate-100 flex flex-col gap-4">
                    <div class="flex items-baseline justify-between">
                        <div>
                            <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">
                                Base &#8377;<?= number_format((float) $service['price'], 2) ?> + 18% GST
                            </div>
                            <div class="text-2xl font-extrabold text-slate-900 font-heading mt-0.5">
                                &#8377;<?= number_format((float) ($service['pricing']['total_amount'] ?? $service['price']), 2) ?>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                            Available Daily
                        </span>
                    </div>

                    <a 
                        href="<?= app_url('booking?service_id=' . $service['id']) ?>" 
                        class="block w-full text-center py-3.5 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-sky-600/20 hover:shadow-sky-600/30 hover:-translate-y-0.5 transition-all"
                    >
                        Book This Modality
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
