<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-12">
    <!-- Header with SKSL Brand Colors -->
    <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Sara Kinetic Sports Lab</span>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 font-heading mt-2">
            Recovery Modalities &amp; Pricing
        </h1>
        <p class="text-slate-600 text-sm sm:text-base mt-2.5 leading-relaxed">
            Select an athletic recovery protocol below to choose an available date and live slot. All sessions include facility access, towel service, and sanitized medical-grade amenities.
        </p>
    </div>

    <!-- Mobile Category Filter Bar (Smooth Horizontal Scroll on Mobile) -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-3 mb-8 -mx-4 px-4 sm:mx-0 sm:px-0">
        <button type="button" class="service-cat-filter px-4 py-2 rounded-full text-xs font-bold bg-[#075183] text-white shadow-xs whitespace-nowrap transition-all" data-category="all">
            All Modalities (<?= count($services) ?>)
        </button>
        <button type="button" class="service-cat-filter px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="cryo">
            Cold &amp; Cryo Immersion
        </button>
        <button type="button" class="service-cat-filter px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="thermal">
            Thermal &amp; Sauna
        </button>
        <button type="button" class="service-cat-filter px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="aquatic">
            Aquatic Conditioning
        </button>
        <button type="button" class="service-cat-filter px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="hydro">
            Hydrotherapy
        </button>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8" id="services-page-grid">
        <?php foreach ($services as $service): 
            $imgSrc = service_image($service['image'] ?? null); // allow-listed + escaped

            $nameLower = strtolower($service['name']);
            $cat = 'hydro';
            if (str_contains($nameLower, 'ice') || str_contains($nameLower, 'cryo') || str_contains($nameLower, 'cold')) {
                $cat = 'cryo';
            } elseif (str_contains($nameLower, 'sauna') || str_contains($nameLower, 'steam') || str_contains($nameLower, 'hot')) {
                $cat = 'thermal';
            } elseif (str_contains($nameLower, 'treadmill') || str_contains($nameLower, 'pool') || str_contains($nameLower, 'cycle') || str_contains($nameLower, 'walker')) {
                $cat = 'aquatic';
            }
            $basePrice = (float) $service['price'];
        ?>
            <div 
                class="service-item-card bg-white border border-[#D9DBDA] rounded-2xl md:rounded-3xl overflow-hidden shadow-xs hover:shadow-xl hover:border-[#4A9CC0] transition-all duration-300 group"
                data-category="<?= h($cat) ?>"
            >
                <!-- MOBILE VIEW: Compact Horizontal App Card (< 768px) -->
                <div class="flex md:hidden items-center p-3 gap-3.5">
                    <!-- Left: Compact Image Thumbnail -->
                    <div class="relative w-28 h-28 rounded-xl overflow-hidden bg-slate-100 shrink-0">
                        <img 
                            src="<?= $imgSrc ?>" 
                            alt="<?= h($service['name']) ?>" 
                            width="1200" height="896"
                            class="w-full h-full object-cover"
                            loading="lazy" decoding="async"
                        >
                        <!-- Duration Badge on Thumbnail -->
                        <div class="absolute bottom-1.5 left-1.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white/95 text-[#075183] shadow-xs backdrop-blur-md flex items-center gap-0.5">
                                <svg class="w-3 h-3 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <?= h($service['duration_minutes']) ?>m
                            </span>
                        </div>
                    </div>

                    <!-- Right Side of Image: Client Name in Two Lines + Modality & Price -->
                    <div class="flex-1 min-w-0 flex flex-col justify-between self-stretch py-0.5">
                        <div>
                            <!-- Client Name in Two Lines -->
                            <div class="border-l-2 border-[#D6981E] pl-2 leading-none">
                                <div class="font-heading font-extrabold text-[10px] tracking-wider text-[#075183] uppercase">
                                    Sara Kinetic
                                </div>
                                <div class="font-heading font-bold text-[9px] tracking-widest text-[#BF5B2B] uppercase mt-0.5">
                                    Sports Lab
                                </div>
                            </div>

                            <!-- Modality Name -->
                            <h3 class="text-sm font-extrabold font-heading text-slate-900 leading-snug mt-1 truncate">
                                <?= h($service['name']) ?>
                            </h3>
                            <div class="text-[11px] text-slate-500 font-medium">
                                Max <?= h($service['capacity']) ?> athletes
                            </div>
                        </div>

                        <!-- Price & CTA Bar -->
                        <div class="flex items-center justify-between pt-1.5 border-t border-[#D9DBDA]/60 mt-1">
                            <div>
                                <div class="text-[8px] uppercase tracking-wider font-bold text-slate-500">Base Rate</div>
                                <div class="text-sm font-extrabold text-slate-900 font-heading -mt-0.5">
                                    &#8377;<?= number_format($basePrice, 2) ?>
                                </div>
                            </div>
                            <a 
                                href="<?= app_url('booking?service_id=' . $service['id']) ?>" 
                                class="app-touch-target px-3.5 py-1.5 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-[11px] uppercase tracking-wider shadow-xs"
                            >
                                Book
                            </a>
                        </div>
                    </div>
                </div>

                <!-- DESKTOP VIEW: Full 3-Column Showcase Card (>= 768px) -->
                <div class="hidden md:flex md:flex-col justify-between h-full">
                    <div>
                        <!-- Service Image with Zoom on Hover -->
                        <div class="relative w-full h-52 sm:h-56 overflow-hidden bg-slate-100">
                            <img 
                                src="<?= $imgSrc ?>" 
                                alt="<?= h($service['name']) ?>" 
                                width="1200" height="896"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                loading="lazy" decoding="async"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>

                            <!-- Badges with Brand Colors -->
                            <div class="absolute top-3 left-3 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/95 text-[#075183] shadow-xs backdrop-blur-md">
                                    <svg class="w-3.5 h-3.5 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= h($service['duration_minutes']) ?> Minutes
                                </span>
                            </div>

                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-900/80 text-white backdrop-blur-md border border-white/10">
                                    <svg class="w-3.5 h-3.5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
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
                        <div class="p-5 sm:p-6">
                            <p class="text-sm text-slate-600 leading-relaxed min-h-[50px]">
                                <?= h($service['description'] ?: 'Optimized protocol engineered for peak athletic recovery, improved circulation, and muscular restoration.') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Price Breakdown and CTA with Brand Colors -->
                    <div class="px-5 pb-5 sm:px-6 sm:pb-6 pt-4 border-t border-[#D9DBDA]/80 flex flex-col gap-4">
                        <div class="flex items-baseline justify-between">
                            <div>
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                    Base Rate / Session
                                </div>
                                <div class="text-2xl font-extrabold text-slate-900 font-heading mt-0.5">
                                    &#8377;<?= number_format($basePrice, 2) ?>
                                </div>
                            </div>
                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                                Available Daily
                            </span>
                        </div>

                        <a 
                            href="<?= app_url('booking?service_id=' . $service['id']) ?>" 
                            class="app-touch-target block w-full text-center py-3.5 px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md hover:-translate-y-0.5 transition-all"
                        >
                            Book This Modality
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.service-cat-filter');
    const cards = document.querySelectorAll('.service-item-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const cat = btn.getAttribute('data-category');
            filterBtns.forEach(b => {
                b.className = 'service-cat-filter px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all';
            });
            btn.className = 'service-cat-filter px-4 py-2 rounded-full text-xs font-bold bg-[#075183] text-white shadow-xs whitespace-nowrap transition-all';

            cards.forEach(card => {
                if (cat === 'all' || card.getAttribute('data-category') === cat) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>
