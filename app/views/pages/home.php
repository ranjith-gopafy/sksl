<!-- Hero Section -->
<section class="relative overflow-hidden pt-12 pb-20 md:pt-20 md:pb-28">
    <!-- Subtle Background Glows -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-cyan-500/15 blur-[120px] rounded-full pointer-events-none"></div>
    <div class="absolute top-1/3 right-10 w-[400px] h-[300px] bg-sky-500/10 blur-[100px] rounded-full pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="text-center max-w-3xl mx-auto">
            <!-- Tagline Badge -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-semibold uppercase tracking-wider mb-6">
                <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                Athletic Science &bull; Cold &amp; Heat Therapy &bull; Chennai
            </div>

            <!-- Main Title -->
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight leading-[1.1] font-heading">
                Recover Faster. <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-sky-300 to-indigo-400">
                    Recharge Deeply.
                </span> <br>
                Perform at Your Peak.
            </h1>

            <p class="mt-6 text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed font-normal">
                Sara Kinetic Sports Lab provides elite athletic recovery modalities including Ice Baths, Infrared Sauna, Steam, Hydrotherapy, and Endless Pool to accelerate muscle restoration and prevent burnout.
            </p>

            <!-- CTA Buttons -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a 
                    href="<?= app_url('services') ?>" 
                    class="w-full sm:w-auto inline-flex items-center justify-center font-heading font-bold text-sm px-8 py-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 shadow-xl shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all tracking-wide"
                >
                    View Services &amp; Book a Slot
                    <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
                <a 
                    href="#how-it-works" 
                    class="w-full sm:w-auto inline-flex items-center justify-center font-heading font-semibold text-sm px-7 py-4 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-slate-200 border border-slate-800 transition-all"
                >
                    How Booking Works
                </a>
            </div>

            <!-- Stats Bar -->
            <div class="mt-16 pt-10 border-t border-slate-800/80 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div>
                    <div class="text-2xl lg:text-3xl font-extrabold text-white font-heading">10</div>
                    <div class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-wider">Recovery Modalities</div>
                </div>
                <div>
                    <div class="text-2xl lg:text-3xl font-extrabold text-cyan-400 font-heading">6 AM – 10 PM</div>
                    <div class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-wider">Operating Hours</div>
                </div>
                <div>
                    <div class="text-2xl lg:text-3xl font-extrabold text-white font-heading">100%</div>
                    <div class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-wider">Hygienic Sanitation</div>
                </div>
                <div>
                    <div class="text-2xl lg:text-3xl font-extrabold text-sky-400 font-heading">Razorpay</div>
                    <div class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-wider">Instant Confirmation</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Modalities Preview -->
<section class="py-16 bg-slate-900/40 border-y border-slate-800/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
            <div>
                <span class="text-xs font-bold uppercase tracking-widest text-cyan-400">Sports Lab Protocols</span>
                <h2 class="text-3xl font-extrabold text-white font-heading mt-2">Signature Recovery Modalities</h2>
            </div>
            <a href="<?= app_url('services') ?>" class="mt-4 md:mt-0 inline-flex items-center text-sm font-semibold text-cyan-400 hover:text-cyan-300 transition-colors">
                View all 10 active services &rarr;
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php 
            $featuredIds = [4, 2, 6]; // Ice Bath, Sauna, Endless Pool
            $displayed = 0;
            foreach ($services as $srv): 
                if (in_array((int)$srv['id'], $featuredIds, true) && $displayed < 3):
                    $displayed++;
            ?>
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 flex flex-col justify-between hover:border-slate-700 hover:shadow-xl hover:shadow-cyan-500/5 transition-all group">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                                <?= h($srv['duration_minutes']) ?> Minutes
                            </span>
                            <span class="text-xs text-slate-400 font-medium">
                                Max Capacity: <?= h($srv['capacity']) ?>
                            </span>
                        </div>
                        <h3 class="text-xl font-bold text-white font-heading group-hover:text-cyan-300 transition-colors">
                            <?= h($srv['name']) ?>
                        </h3>
                        <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                            <?= h($srv['description'] ?: 'Optimized temperature recovery protocol designed to reduce DOMS, eliminate inflammation, and stimulate circulation.') ?>
                        </p>
                    </div>

                    <div class="mt-8 pt-4 border-t border-slate-800/80 flex items-center justify-between">
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Total with 18% GST</div>
                            <div class="text-xl font-extrabold text-white font-heading">
                                &#8377;<?= number_format((float) ($srv['pricing']['total_amount'] ?? $srv['price']), 2) ?>
                            </div>
                        </div>
                        <a 
                            href="<?= app_url('booking?service_id=' . $srv['id']) ?>" 
                            class="py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-cyan-500 hover:text-slate-950 text-slate-200 text-xs font-heading font-bold uppercase tracking-wider transition-all"
                        >
                            Book Slot
                        </a>
                    </div>
                </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-xs font-bold uppercase tracking-widest text-cyan-400">Streamlined Process</span>
            <h2 class="text-3xl font-extrabold text-white font-heading mt-2">How Booking Works</h2>
            <p class="text-sm text-slate-400 mt-3">From appointment to recovery in three seamless steps.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Step 1 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-8 relative">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    01
                </div>
                <h3 class="text-lg font-bold text-white font-heading">Select Service &amp; Time Slot</h3>
                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Pick your recovery modality and choose an available live slot between 6:00 AM and 10:00 PM IST.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-8 relative">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-400 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    02
                </div>
                <h3 class="text-lg font-bold text-white font-heading">Instant Secure Payment</h3>
                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Your slot is held for 10 minutes while you complete fast, encrypted payment through Razorpay (UPI, Cards, NetBanking).
                </p>
            </div>

            <!-- Step 3 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-8 relative">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    03
                </div>
                <h3 class="text-lg font-bold text-white font-heading">Receive Invoice &amp; Arrive</h3>
                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Receive your confirmed booking reference and PDF tax invoice via email immediately. Arrive 10 minutes early to recover.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-cyan-950/40 border border-slate-800 rounded-3xl p-8 md:p-14 text-center relative overflow-hidden shadow-2xl">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white font-heading tracking-tight">
                Ready to Accelerate Your Recovery?
            </h2>
            <p class="text-slate-300 text-sm sm:text-base max-w-xl mx-auto mt-4 leading-relaxed">
                Whether recovering from high-intensity training or recharging for competition, our science-backed sports lab is open daily.
            </p>
            <div class="mt-8">
                <a 
                    href="<?= app_url('services') ?>" 
                    class="inline-flex items-center justify-center font-heading font-bold text-sm px-8 py-3.5 rounded-xl bg-cyan-400 hover:bg-cyan-300 text-slate-950 shadow-lg shadow-cyan-400/20 hover:shadow-cyan-400/30 transition-all"
                >
                    Book Your Recovery Session
                </a>
            </div>
        </div>
    </div>
</section>
