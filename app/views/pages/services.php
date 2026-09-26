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
                <!-- One card. Row on phones, column from md up. Name, price and link exist once. -->
                <div class="flex items-stretch md:flex-col h-full">
                    <div class="relative w-28 h-28 md:w-full md:h-56 shrink-0 overflow-hidden bg-slate-100 rounded-xl md:rounded-none m-3 md:m-0">
                        <img
                            src="<?= $imgSrc ?>"
                            alt=""
                            width="1200" height="896"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            loading="lazy" decoding="async"
                        >
                        <div class="hidden md:block absolute inset-0 bg-gradient-to-t from-slate-950/40 via-transparent to-transparent" aria-hidden="true"></div>
                        <div class="absolute bottom-1.5 left-1.5 md:top-3 md:bottom-auto md:left-3">
                            <span class="px-2 py-0.5 md:px-2.5 md:py-1 rounded-full text-[10px] md:text-xs font-extrabold bg-white/95 text-[#075183] shadow-xs backdrop-blur-md flex items-center gap-0.5">
                                <svg class="w-3 h-3 md:w-3.5 md:h-3.5 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <?= h($service['duration_minutes']) ?> min
                            </span>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col justify-between py-3 pr-3 md:p-0">
                        <div class="md:p-5 md:pb-3">
                            <div class="md:hidden border-l-2 border-[#D6981E] pl-2 leading-none mb-1">
                                <div class="font-heading font-extrabold text-[10px] tracking-wider text-[#075183] uppercase">Sara Kinetic</div>
                                <div class="font-heading font-bold text-[9px] tracking-widest text-[#BF5B2B] uppercase mt-0.5">Sports Lab</div>
                            </div>
                            <h2 class="text-sm md:text-xl font-extrabold font-heading text-slate-900 leading-snug truncate">
                                <?= h($service['name']) ?>
                            </h2>
                            <div class="text-[11px] md:text-xs text-slate-500 font-medium mt-0.5">
                                Max <?= h($service['capacity']) ?> athletes
                            </div>
                            <p class="hidden md:block text-sm text-slate-600 leading-relaxed mt-3 min-h-[48px]">
                                <?= h($service['description'] ?: 'Optimized protocol engineered for peak athletic recovery, improved circulation, and muscular restoration.') ?>
                            </p>
                        </div>
                        <div class="flex items-center justify-between md:flex-col md:items-stretch gap-2 pt-1.5 mt-1 border-t border-[#D9DBDA]/60 md:mx-5 md:mb-5 md:pt-4 md:border-[#D9DBDA]/80">
                            <div class="flex items-center justify-between md:mb-1">
                                <div>
                                    <div class="text-[8px] md:text-[10px] uppercase tracking-wider font-bold text-slate-500">Base rate</div>
                                    <div class="text-sm md:text-2xl font-extrabold text-slate-900 font-heading">
                                        &#8377;<?= number_format($basePrice, 2) ?>
                                    </div>
                                </div>
                                <span class="hidden md:inline-flex text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
                                    Available daily
                                </span>
                            </div>
                            <a
                                href="<?= app_url('booking?service_id=' . $service['id']) ?>"
                                class="app-touch-target shrink-0 inline-flex md:flex md:w-full items-center justify-center px-3.5 py-1.5 md:py-3.5 md:px-4 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-[11px] md:text-xs uppercase tracking-wider shadow-xs md:shadow-md"
                            >
                                Book
                            </a>
                        </div>
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
