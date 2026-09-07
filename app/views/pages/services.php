<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="text-xs font-bold uppercase tracking-widest text-cyan-400">Sara Kinetic Sports Lab</span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-white font-heading mt-2">
            Recovery Modalities &amp; Pricing
        </h1>
        <p class="text-slate-400 text-sm sm:text-base mt-3 leading-relaxed">
            Select a recovery protocol below to choose an available date and time slot. All sessions include facility access, towel service, and sanitized amenities.
        </p>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($services as $service): ?>
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 flex flex-col justify-between hover:border-cyan-500/40 hover:shadow-2xl hover:shadow-cyan-500/5 transition-all group">
                <div>
                    <!-- Badges -->
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?= h($service['duration_minutes']) ?> Minutes
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-slate-400 bg-slate-800/80 border border-slate-700/50">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Cap: <?= h($service['capacity']) ?>
                        </span>
                    </div>

                    <!-- Service Image if provided -->
                    <?php if (!empty($service['image'])): ?>
                        <div class="w-full h-44 rounded-xl overflow-hidden mb-4 bg-slate-950 border border-slate-800">
                            <img 
                                src="<?= asset('uploads/services/' . $service['image']) ?>" 
                                alt="<?= h($service['name']) ?>"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            >
                        </div>
                    <?php endif; ?>

                    <!-- Title & Description -->
                    <h2 class="text-xl font-bold text-white font-heading group-hover:text-cyan-300 transition-colors">
                        <?= h($service['name']) ?>
                    </h2>
                    <p class="text-sm text-slate-400 mt-2.5 leading-relaxed">
                        <?= h($service['description'] ?: 'Optimized protocol engineered for peak athletic recovery, improved circulation, and muscular restoration.') ?>
                    </p>
                </div>

                <!-- Price Breakdown and CTA -->
                <div class="mt-8 pt-5 border-t border-slate-800/80">
                    <div class="flex items-baseline justify-between mb-4">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">
                                Base &#8377;<?= number_format((float) $service['price'], 2) ?> + 18% GST
                            </div>
                            <div class="text-2xl font-extrabold text-white font-heading mt-0.5">
                                &#8377;<?= number_format((float) ($service['pricing']['total_amount'] ?? $service['price']), 2) ?>
                            </div>
                        </div>
                    </div>

                    <a 
                        href="<?= app_url('booking?service_id=' . $service['id']) ?>" 
                        class="block w-full text-center py-3 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-cyan-500/20 hover:shadow-cyan-500/30 transition-all"
                    >
                        Book This Service
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
