<?php
/**
 * Homepage View - Dribbble & Mobile-App Inspired High Performance Experience
 * Full-width Carousel Hero Banner (Edge-to-Edge, Zero Gap, Left-Aligned Editorial Typography)
 * Powered by SKSL Official Brand Colors: #D9DBDA, #075183, #D6981E, #BF5B2B, #4A9CC0
 *
 * @var array<string, mixed>|null $heroBanner
 * @var array<int, array<string, mixed>> $bannerSlides
 * @var array<int, array<string, mixed>> $services
 */

// Normalize slides fallback
if (empty($bannerSlides)) {
    $bannerSlides = !empty($heroBanner) ? [$heroBanner] : [
        [
            'badge_text'  => 'Sports Science & High-Performance Lab',
            'headline'    => "Recover Faster.\nRecharge Fully.\nPerform at Your Peak.",
            'subheadline' => 'Science-backed hot, cold, and hydrotherapy recovery protocols. Optimizing athletic regeneration and physical performance.',
            'image_url'   => 'images/hero-banner.jpg',
            'cta_text'    => 'Reserve Recovery Session',
            'cta_link'    => '/services',
        ]
    ];
}
?>

<!-- Full-Bleed Carousel Hero Banner (End-to-End, Zero Gap, Left-Aligned Typography) -->
<section id="hero-carousel" class="relative w-full overflow-hidden bg-slate-950 select-none h-[460px] sm:h-[520px] lg:h-[580px] flex items-stretch">
    
    <?php if (!empty($_SESSION['admin_id'])): ?>
        <!-- Admin Quick Control Trigger (Floating Top-Right) -->
        <div class="absolute top-4 right-4 z-30">
            <a 
                href="<?= app_url('admin/banner') ?>" 
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900/90 hover:bg-[#075183] text-white text-xs font-semibold backdrop-blur-md border border-white/20 shadow-lg transition-all"
            >
                <span class="w-2 h-2 rounded-full bg-[#D6981E] animate-pulse"></span>
                <span>Edit Hero Banner</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </a>
        </div>
    <?php endif; ?>

    <!-- Slides Wrapper -->
    <div class="relative w-full h-full">
        <?php foreach ($bannerSlides as $idx => $slide): 
            $isActive = ($idx === 0);
            $slideBadge = !empty($slide['badge_text']) ? $slide['badge_text'] : 'Sports Science & Recovery Lab';
            $slideHeadline = !empty($slide['headline']) ? $slide['headline'] : 'Elite Athletic Recovery Lab';
            $slideSubhead = !empty($slide['subheadline']) ? $slide['subheadline'] : 'Science-backed recovery protocols engineered for peak athletic regeneration.';
            $slideImage = !empty($slide['image_url']) ? $slide['image_url'] : 'images/hero-banner.jpg';
            $slideCtaText = !empty($slide['cta_text']) ? $slide['cta_text'] : 'Reserve Recovery Session';
            $slideCtaLink = !empty($slide['cta_link']) ? $slide['cta_link'] : '/services';
        ?>
            <div 
                class="carousel-slide absolute inset-0 w-full h-full transition-opacity duration-700 ease-in-out flex items-center <?= $isActive ? 'opacity-100 z-10 pointer-events-auto active' : 'opacity-0 z-0 pointer-events-none' ?>" 
                data-slide-index="<?= $idx ?>"
            >
                <!-- Full-Bleed Background Photography with Ken Burns effect -->
                <img 
                    src="<?= safe_image($slideImage, 'images/hero-banner.jpg') ?>" 
                    alt="<?= h($slideHeadline) ?>" 
                    class="absolute inset-0 w-full h-full object-cover object-center transform transition-transform duration-[6000ms] ease-out will-change-transform"
                >

                <!-- High-Contrast Cinematic Gradient Overlays with SKSL Navy & Cerulean undertones -->
                <div class="absolute inset-0 bg-gradient-to-r from-[#031426]/95 via-[#075183]/80 sm:via-[#075183]/65 to-slate-950/30"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-slate-950/40"></div>
                <div class="absolute inset-0 pointer-events-none opacity-40 mix-blend-screen" style="background: radial-gradient(circle at 18% 50%, rgba(74, 156, 192, 0.35) 0%, transparent 60%);"></div>

                <!-- Left-Aligned Editorial Content Container (No Center Alignment, End-to-End Section) -->
                <div class="relative z-10 w-full max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 py-12 sm:py-16 text-left flex flex-col justify-center">
                    <div class="max-w-2xl lg:max-w-3xl space-y-3.5 sm:space-y-4">
                        
                        <!-- Protocol Category Badge with #D6981E (Kinetic Gold) -->
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900/80 backdrop-blur-md border border-[#D6981E]/40 text-[#D6981E] text-xs font-bold uppercase tracking-wider shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-[#D6981E] animate-pulse"></span>
                            <?= h($slideBadge) ?>
                        </div>

                        <!-- Left-Aligned Headline -->
                        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-[1.1] font-heading drop-shadow-md">
                            <?= nl2br(h($slideHeadline)) ?>
                        </h1>

                        <!-- Left-Aligned Subheadline (Concise) -->
                        <p class="text-sm sm:text-base text-[#D9DBDA] leading-relaxed font-normal max-w-xl drop-shadow-sm line-clamp-2">
                            <?= h($slideSubhead) ?>
                        </p>

                        <!-- Left-Aligned Action CTAs with Brand Colors -->
                        <div class="pt-2 flex flex-wrap items-center gap-3 sm:gap-4">
                            <a 
                                href="<?= safe_link($slideCtaLink, '/services') ?>" 
                                class="app-touch-target inline-flex items-center justify-center font-heading font-bold text-sm px-6 py-3 sm:px-7 sm:py-3.5 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white shadow-lg shadow-[#053d63]/40 hover:-translate-y-0.5 transition-all"
                            >
                                <span><?= h($slideCtaText) ?></span>
                                <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                            <a 
                                href="<?= app_url('services') ?>" 
                                class="app-touch-target inline-flex items-center justify-center font-heading font-semibold text-sm px-5 py-3 sm:px-6 sm:py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-[#D9DBDA]/40 hover:border-white transition-all"
                            >
                                Explore 10 Modalities
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Carousel Floating Edge Navigation Chevrons (Stable Vertical Centering, No Jumping) -->
    <button 
        type="button" 
        id="carousel-prev" 
        aria-label="Previous Slide" 
        class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-slate-900/70 hover:bg-[#075183] text-white backdrop-blur-md border border-[#D9DBDA]/30 flex items-center justify-center transition-colors shadow-lg cursor-pointer select-none group"
    >
        <span class="flex items-center justify-center transition-transform group-active:scale-90 pointer-events-none">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </span>
    </button>
    <button 
        type="button" 
        id="carousel-next" 
        aria-label="Next Slide" 
        class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 z-20 w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-slate-900/70 hover:bg-[#075183] text-white backdrop-blur-md border border-[#D9DBDA]/30 flex items-center justify-center transition-colors shadow-lg cursor-pointer select-none group"
    >
        <span class="flex items-center justify-center transition-transform group-active:scale-90 pointer-events-none">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </span>
    </button>

    <!-- Carousel Bottom Pagination Bar & Live Index Counter -->
    <div class="absolute bottom-6 left-0 right-0 z-20 pointer-events-none">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 flex items-center justify-between">
            
            <!-- Slide Dots / Active Pill Indicators -->
            <div class="flex items-center gap-2 pointer-events-auto bg-slate-900/60 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-white/10" id="carousel-indicators">
                <?php foreach ($bannerSlides as $idx => $slide): ?>
                    <button 
                        type="button" 
                        class="carousel-indicator-dot transition-all duration-300 rounded-full h-2 <?= ($idx === 0) ? 'w-8 bg-[#D6981E]' : 'w-2 bg-white/40 hover:bg-white/70' ?>" 
                        data-target-index="<?= $idx ?>"
                        aria-label="Go to slide <?= $idx + 1 ?>"
                    ></button>
                <?php endforeach; ?>
            </div>

            <!-- Slide Counter & Mobile Swipe Hint -->
            <div class="hidden sm:flex items-center gap-3 bg-slate-900/60 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-white/10 text-xs font-bold text-white">
                <span class="text-[#4A9CC0] font-mono tracking-wider"><span id="carousel-current-index">01</span> / <span id="carousel-total-count"><?= sprintf('%02d', count($bannerSlides)) ?></span></span>
                <span class="text-slate-400 font-normal border-l border-white/20 pl-2.5">Auto-advance 5s</span>
            </div>
        </div>
    </div>
</section>

<!-- Modalities Catalog Showcase (All 10 Modalities with Real Photography & Mobile Filter Tabs) -->
<section class="py-12 lg:py-16 bg-white border-b border-[#D9DBDA]/80" id="modalities-section">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Section Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 sm:mb-10">
            <div>
                <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Elite Regeneration Protocols</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-1">
                    Signature Recovery Modalities
                </h2>
                <p class="text-sm text-slate-600 mt-2 max-w-xl">
                    Every session is capped by strict athlete limits to guarantee sanitary water conditions, optimum temperatures, and zero waiting.
                </p>
            </div>
            <a href="<?= app_url('services') ?>" class="mt-4 md:mt-0 inline-flex items-center text-sm font-bold text-[#075183] hover:text-[#4A9CC0] transition-colors">
                View Full Pricing Breakdown &rarr;
            </a>
        </div>

        <!-- Mobile App Experience: Interactive Category Filter Bar (Smooth Horizontal Scroll on Mobile) -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-4 mb-6 -mx-4 px-4 sm:mx-0 sm:px-0">
            <button type="button" class="category-filter-btn px-4 py-2 rounded-full text-xs font-bold bg-[#075183] text-white shadow-xs whitespace-nowrap transition-all" data-category="all">
                All Protocols (10)
            </button>
            <button type="button" class="category-filter-btn px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="cryo">
                Cold &amp; Cryo Immersion
            </button>
            <button type="button" class="category-filter-btn px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="thermal">
                Thermal &amp; Sauna
            </button>
            <button type="button" class="category-filter-btn px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="aquatic">
                Aquatic Conditioning
            </button>
            <button type="button" class="category-filter-btn px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all" data-category="hydro">
                Hydrotherapy
            </button>
        </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8" id="services-grid">
            <?php foreach ($services as $srv): 
                $imgSrc = service_image($srv['image'] ?? null); // allow-listed + escaped
                
                // Categorize for quick app filtering
                $nameLower = strtolower($srv['name']);
                $cat = 'hydro';
                if (str_contains($nameLower, 'ice') || str_contains($nameLower, 'cryo') || str_contains($nameLower, 'cold')) {
                    $cat = 'cryo';
                } elseif (str_contains($nameLower, 'sauna') || str_contains($nameLower, 'steam') || str_contains($nameLower, 'hot')) {
                    $cat = 'thermal';
                } elseif (str_contains($nameLower, 'treadmill') || str_contains($nameLower, 'pool') || str_contains($nameLower, 'cycle') || str_contains($nameLower, 'walker')) {
                    $cat = 'aquatic';
                }
                $basePrice = (float) $srv['price'];
            ?>
                <div 
                    class="modality-card bg-white border border-[#D9DBDA] rounded-2xl md:rounded-3xl overflow-hidden shadow-xs hover:shadow-xl hover:border-[#4A9CC0] transition-all duration-300 group"
                    data-category="<?= h($cat) ?>"
                >
                    <!-- MOBILE VIEW: Compact Horizontal App Card (< 768px) -->
                    <div class="flex md:hidden items-center p-3 gap-3.5">
                        <!-- Left: Compact Image Thumbnail -->
                        <div class="relative w-28 h-28 rounded-xl overflow-hidden bg-slate-100 shrink-0">
                            <img 
                                src="<?= $imgSrc ?>" 
                                alt="<?= h($srv['name']) ?>" 
                                class="w-full h-full object-cover"
                                loading="lazy"
                            >
                            <!-- Duration Badge on Thumbnail -->
                            <div class="absolute bottom-1.5 left-1.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white/95 text-[#075183] shadow-xs backdrop-blur-md flex items-center gap-0.5">
                                    <svg class="w-3 h-3 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= h($srv['duration_minutes']) ?>m
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
                                    <?= h($srv['name']) ?>
                                </h3>
                                <div class="text-[11px] text-slate-500 font-medium">
                                    Max <?= h($srv['capacity']) ?> athletes
                                </div>
                            </div>

                            <!-- Price & CTA Bar -->
                            <div class="flex items-center justify-between pt-1.5 border-t border-[#D9DBDA]/60 mt-1">
                                <div>
                                    <div class="text-[8px] uppercase tracking-wider font-bold text-slate-400">Base Rate</div>
                                    <div class="text-sm font-extrabold text-slate-900 font-heading -mt-0.5">
                                        &#8377;<?= number_format($basePrice, 2) ?>
                                    </div>
                                </div>
                                <a 
                                    href="<?= app_url('booking?service_id=' . $srv['id']) ?>" 
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
                            <!-- Service Photography with Subtle Hover Zoom -->
                            <div class="relative w-full h-52 sm:h-56 overflow-hidden bg-slate-100">
                                <img 
                                    src="<?= $imgSrc ?>" 
                                    alt="<?= h($srv['name']) ?>" 
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy"
                                >
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>
                                
                                <!-- Badges on Image with Brand Colors -->
                                <div class="absolute top-3 left-3 flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-white/95 text-[#075183] backdrop-blur-md shadow-xs flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-[#4A9CC0]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?= h($srv['duration_minutes']) ?> Min
                                    </span>
                                </div>

                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-900/80 text-white backdrop-blur-md border border-white/10">
                                        Max <?= h($srv['capacity']) ?> Athletes
                                    </span>
                                </div>

                                <div class="absolute bottom-3 left-4 right-4 text-white">
                                    <h3 class="text-xl font-extrabold font-heading text-white drop-shadow-sm">
                                        <?= h($srv['name']) ?>
                                    </h3>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-5 sm:p-6">
                                <p class="text-sm text-slate-600 leading-relaxed min-h-[48px]">
                                    <?= h($srv['description'] ?: 'High-performance athletic protocol engineered for rapid physical recovery, lactate clearance, and nervous system balance.') ?>
                                </p>
                            </div>
                        </div>

                        <!-- Price & CTA Bar -->
                        <div class="px-5 pb-5 sm:px-6 sm:pb-6 pt-3 border-t border-[#D9DBDA]/80 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Base Rate / Session</div>
                                <div class="text-xl font-extrabold text-slate-900 font-heading">
                                    &#8377;<?= number_format($basePrice, 2) ?>
                                </div>
                            </div>

                            <a 
                                href="<?= app_url('booking?service_id=' . $srv['id']) ?>" 
                                class="app-touch-target inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider transition-all shadow-sm"
                            >
                                Book Slot
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works (Frictionless Booking Protocol) -->
<section class="py-16 lg:py-24 bg-[#F8FAFC]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-2xl mx-auto mb-14">
            <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">Frictionless Booking Protocol</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-2">
                How Your Reservation Works
            </h2>
            <p class="text-sm text-slate-600 mt-2.5 leading-relaxed">
                Experience seamless sports lab reservations with dynamic live capacity, 10-minute slot holds, and automated GST tax vouchers.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
            <!-- Step 1: Pick Slot (#075183) -->
            <div class="bg-white border border-[#D9DBDA] rounded-3xl p-7 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-[#075183]/10 text-[#075183] font-heading font-extrabold text-xl flex items-center justify-center mb-5">
                    01
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Select Protocol &amp; Live Slot</h3>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Choose from 10 certified sports modalities and view live slot capacity between 6:00 AM and 10:00 PM IST.
                </p>
            </div>

            <!-- Step 2: 10-Min Hold (#D6981E) -->
            <div class="bg-white border border-[#D9DBDA] rounded-3xl p-7 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-[#D6981E]/15 text-[#D6981E] font-heading font-extrabold text-xl flex items-center justify-center mb-5">
                    02
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Guaranteed 10-Min Hold</h3>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Your spot is temporarily reserved under strict capacity protection while you complete payment via Razorpay.
                </p>
            </div>

            <!-- Step 3: Tax Invoice (#BF5B2B) -->
            <div class="bg-white border border-[#D9DBDA] rounded-3xl p-7 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-[#BF5B2B]/10 text-[#BF5B2B] font-heading font-extrabold text-xl flex items-center justify-center mb-5">
                    03
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Automated GST Tax Invoice</h3>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Instantly receive your booking voucher and statutory GST invoice. Arrive 10 minutes before your slot to recharge.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- High-Conversion Bottom Banner (Gradient from #075183 to #4A9CC0) -->
<section class="pb-16 sm:pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-[#031426] via-[#075183] to-[#4A9CC0] text-white p-8 sm:p-14 text-center shadow-xl relative overflow-hidden border border-[#D9DBDA]/20">
            <div class="relative z-10 max-w-2xl mx-auto space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-xs font-semibold text-[#D6981E] border border-[#D6981E]/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#D6981E] animate-pulse"></span>
                    Bengaluru's Premier High-Performance Facility
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold font-heading tracking-tight">
                    Elevate Your Recovery Today
                </h2>
                <p class="text-slate-200 text-sm sm:text-base leading-relaxed">
                    Whether training for a marathon or recharging after an intense workout, reserve your science-backed recovery session today.
                </p>
                <div class="pt-3">
                    <a 
                        href="<?= app_url('services') ?>" 
                        class="app-touch-target inline-flex items-center justify-center font-heading font-bold text-sm px-8 py-4 rounded-2xl bg-white text-[#075183] hover:bg-[#F8FAFC] shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all"
                    >
                        Book Your Modality Slot Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Carousel Interaction & Touch Swipe JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.carousel-indicator-dot');
    const prevBtn = document.getElementById('carousel-prev');
    const nextBtn = document.getElementById('carousel-next');
    const currentIndexEl = document.getElementById('carousel-current-index');
    const totalCount = slides.length;
    
    if (totalCount === 0) return;

    let currentIndex = 0;
    let autoPlayTimer = null;
    let isPaused = false;

    function goToSlide(index) {
        if (index < 0) {
            currentIndex = totalCount - 1;
        } else if (index >= totalCount) {
            currentIndex = 0;
        } else {
            currentIndex = index;
        }

        slides.forEach((slide, idx) => {
            if (idx === currentIndex) {
                slide.classList.remove('opacity-0', 'z-0', 'pointer-events-none');
                slide.classList.add('opacity-100', 'z-10', 'pointer-events-auto', 'active');
            } else {
                slide.classList.remove('opacity-100', 'z-10', 'pointer-events-auto', 'active');
                slide.classList.add('opacity-0', 'z-0', 'pointer-events-none');
            }
        });

        dots.forEach((dot, idx) => {
            if (idx === currentIndex) {
                dot.className = 'carousel-indicator-dot transition-all duration-300 rounded-full h-2 w-8 bg-[#D6981E]';
            } else {
                dot.className = 'carousel-indicator-dot transition-all duration-300 rounded-full h-2 w-2 bg-white/40 hover:bg-white/70';
            }
        });

        if (currentIndexEl) {
            currentIndexEl.textContent = String(currentIndex + 1).padStart(2, '0');
        }
    }

    function nextSlide() {
        goToSlide(currentIndex + 1);
    }

    function prevSlide() {
        goToSlide(currentIndex - 1);
    }

    if (nextBtn) nextBtn.addEventListener('click', () => { nextSlide(); resetTimer(); });
    if (prevBtn) prevBtn.addEventListener('click', () => { prevSlide(); resetTimer(); });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            const target = parseInt(dot.getAttribute('data-target-index') || '0', 10);
            goToSlide(target);
            resetTimer();
        });
    });

    // Auto Play Timer (5 seconds)
    function startTimer() {
        if (totalCount <= 1) return;
        stopTimer();
        autoPlayTimer = setInterval(() => {
            if (!isPaused) {
                nextSlide();
            }
        }, 5000);
    }

    function stopTimer() {
        if (autoPlayTimer) {
            clearInterval(autoPlayTimer);
            autoPlayTimer = null;
        }
    }

    function resetTimer() {
        stopTimer();
        startTimer();
    }

    const carouselContainer = document.getElementById('hero-carousel');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', () => { isPaused = true; });
        carouselContainer.addEventListener('mouseleave', () => { isPaused = false; });

        // Mobile Touch Swipe Handling
        let touchStartX = 0;
        let touchEndX = 0;

        carouselContainer.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            isPaused = true;
        }, { passive: true });

        carouselContainer.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            isPaused = false;
            handleSwipe();
            resetTimer();
        }, { passive: true });

        function handleSwipe() {
            const threshold = 40; // minimum swipe distance in px
            if (touchEndX < touchStartX - threshold) {
                // Swipe Left -> Next Slide
                nextSlide();
            } else if (touchEndX > touchStartX + threshold) {
                // Swipe Right -> Prev Slide
                prevSlide();
            }
        }
    }

    // Keyboard Arrow navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            prevSlide();
            resetTimer();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
            resetTimer();
        }
    });

    startTimer();

    // Modality Category Filter (Instant Tab Filtering for Mobile App Feel)
    const filterBtns = document.querySelectorAll('.category-filter-btn');
    const modalityCards = document.querySelectorAll('.modality-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const category = btn.getAttribute('data-category');
            
            filterBtns.forEach(b => {
                b.className = 'category-filter-btn px-4 py-2 rounded-full text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition-all';
            });
            btn.className = 'category-filter-btn px-4 py-2 rounded-full text-xs font-bold bg-[#075183] text-white shadow-xs whitespace-nowrap transition-all';

            modalityCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>
