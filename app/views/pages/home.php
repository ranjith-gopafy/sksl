<?php
/**
 * Homepage View - Dribbble-inspired Light Theme with Dynamic Hero Banner & Real Photography
 *
 * @var array<string, mixed>|null $heroBanner
 * @var array<int, array<string, mixed>> $services
 */

// Fallbacks for Hero Banner
$bannerBadge = !empty($heroBanner['badge_text']) ? $heroBanner['badge_text'] : 'Sports Science & High-Performance Lab';
$bannerHeadline = !empty($heroBanner['headline']) ? $heroBanner['headline'] : 'Elite Athletic Recovery Lab';
$bannerSubhead = !empty($heroBanner['subheadline']) ? $heroBanner['subheadline'] : 'Science-backed hot, cold, and hydrotherapy recovery protocols. Optimizing athletic regeneration and physical performance.';
$bannerImage = !empty($heroBanner['image_url']) ? $heroBanner['image_url'] : 'images/hero-banner.jpg';
$bannerCtaText = !empty($heroBanner['cta_text']) ? $heroBanner['cta_text'] : 'Reserve Recovery Session';
$bannerCtaLink = !empty($heroBanner['cta_link']) ? $heroBanner['cta_link'] : '/services';
?>

<!-- Dynamic Hero Banner Section (Admin Controlled) -->
<section class="relative pt-6 pb-12 lg:pt-10 lg:pb-16 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <!-- Admin Banner Quick Editor Trigger -->
            <div class="mb-4 flex items-center justify-between p-3 rounded-2xl bg-sky-50 border border-sky-200 text-xs text-sky-900">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-600 animate-pulse"></span>
                    <span><strong>Admin Notice:</strong> The Hero Banner below is dynamically controlled by your admin portal.</span>
                </div>
                <a href="<?= app_url('admin/banner') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-600 text-white font-semibold hover:bg-sky-700 shadow-xs transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Edit Hero Banner
                </a>
            </div>
        <?php endif; ?>

        <!-- Main Banner Card -->
        <div class="relative rounded-3xl overflow-hidden shadow-2xl bg-slate-900 min-h-[500px] lg:min-h-[560px] flex items-center border border-slate-200/50">
            
            <!-- Hero Photography Background with Gradient Overlays -->
            <img 
                src="<?= asset($bannerImage) ?>" 
                alt="Sara Kinetic Sports Lab Facility" 
                class="absolute inset-0 w-full h-full object-cover object-center transform hover:scale-105 transition-transform duration-1000"
            >
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/70 to-slate-950/30"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent"></div>

            <!-- Content Area -->
            <div class="relative z-10 max-w-2xl px-6 py-12 sm:px-12 sm:py-16 text-white space-y-6">
                
                <!-- Tagline Badge -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white text-xs font-bold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <?= h($bannerBadge) ?>
                </div>

                <!-- Headline -->
                <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] font-heading">
                    <?= nl2br(h($bannerHeadline)) ?>
                </h1>

                <!-- Subheadline -->
                <p class="text-base sm:text-lg text-slate-200 leading-relaxed font-normal">
                    <?= h($bannerSubhead) ?>
                </p>

                <!-- CTA Actions -->
                <div class="pt-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                    <a 
                        href="<?= str_starts_with($bannerCtaLink, 'http') ? h($bannerCtaLink) : app_url(ltrim($bannerCtaLink, '/')) ?>" 
                        class="inline-flex items-center justify-center font-heading font-bold text-sm px-8 py-4 rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white shadow-xl shadow-sky-500/25 hover:shadow-sky-500/40 hover:-translate-y-0.5 transition-all tracking-wide"
                    >
                        <span><?= h($bannerCtaText) ?></span>
                        <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                    <a 
                        href="<?= app_url('services') ?>" 
                        class="inline-flex items-center justify-center font-heading font-semibold text-sm px-7 py-4 rounded-2xl bg-white/10 hover:bg-white/20 text-white backdrop-blur-md border border-white/20 hover:border-white/40 transition-all"
                    >
                        Explore 10 Modalities
                    </a>
                </div>

                <!-- Live Highlight Feature Badges -->
                <div class="pt-6 flex flex-wrap items-center gap-4 text-xs font-medium text-slate-300">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Medical-Grade Water Sanitation
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Instant Slot Holds (10 Min)
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Automated GST Invoicing
                    </span>
                </div>
            </div>
        </div>

        <!-- Metric Counters Row -->
        <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 text-center shadow-xs">
                <div class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading">10</div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Recovery Modalities</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 text-center shadow-xs">
                <div class="text-3xl sm:text-4xl font-extrabold text-sky-600 font-heading">06:00 – 22:00</div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Daily Facility Hours</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 text-center shadow-xs">
                <div class="text-3xl sm:text-4xl font-extrabold text-emerald-600 font-heading">100%</div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Sports Science Certified</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 text-center shadow-xs">
                <div class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading">Razorpay</div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Instant Hold &amp; Confirm</div>
            </div>
        </div>
    </div>
</section>

<!-- Modalities Catalog Showcase (All 10 Modalities with Real Photography) -->
<section class="py-12 lg:py-16 bg-white border-y border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Section Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-12">
            <div>
                <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Recovery Protocols</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-2">
                    Signature Recovery Modalities
                </h2>
                <p class="text-sm text-slate-600 mt-2 max-w-xl">
                    Each session is scheduled with strictly enforced athlete capacity to guarantee hygienic space, optimum water temperature, and zero overcrowding.
                </p>
            </div>
            <a href="<?= app_url('services') ?>" class="mt-4 md:mt-0 inline-flex items-center text-sm font-bold text-sky-600 hover:text-sky-700 transition-colors">
                View Full Pricing Breakdown &rarr;
            </a>
        </div>

        <!-- Services Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($services as $srv): 
                $imgSrc = str_starts_with($srv['image'] ?? '', 'images/') || str_starts_with($srv['image'] ?? '', 'uploads/')
                    ? asset($srv['image'])
                    : asset('images/services/' . ($srv['image'] ?? 'spa.jpg'));
            ?>
                <div class="bg-[#FAFAFC] border border-slate-200/90 rounded-3xl overflow-hidden shadow-xs hover:shadow-xl hover:border-sky-300 transition-all duration-300 flex flex-col justify-between group">
                    
                    <div>
                        <!-- Service Photography with Zoom on Hover -->
                        <div class="relative w-full h-52 overflow-hidden bg-slate-100">
                            <img 
                                src="<?= $imgSrc ?>" 
                                alt="<?= h($srv['name']) ?>" 
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-transparent to-transparent"></div>
                            
                            <!-- Badges on Image -->
                            <div class="absolute top-3 left-3 flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-white/95 text-slate-900 backdrop-blur-md shadow-xs flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= h($srv['duration_minutes']) ?> Min
                                </span>
                            </div>

                            <div class="absolute top-3 right-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-900/80 text-white backdrop-blur-md">
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
                        <div class="p-6">
                            <p class="text-sm text-slate-600 leading-relaxed min-h-[48px]">
                                <?= h($srv['description'] ?: 'High-performance athletic protocol engineered for rapid physical recovery, lactate clearance, and nervous system balance.') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Price & CTA Bar -->
                    <div class="px-6 pb-6 pt-3 border-t border-slate-200/80 flex items-center justify-between">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total with 18% GST</div>
                            <div class="text-xl font-extrabold text-slate-900 font-heading">
                                &#8377;<?= number_format((float) ($srv['pricing']['total_amount'] ?? $srv['price']), 2) ?>
                            </div>
                        </div>

                        <a 
                            href="<?= app_url('booking?service_id=' . $srv['id']) ?>" 
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-sky-600 text-white font-heading font-bold text-xs uppercase tracking-wider transition-colors shadow-xs"
                        >
                            Book Slot
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works & Booking Protocol -->
<section class="py-16 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-xs font-bold uppercase tracking-widest text-sky-600">Frictionless Experience</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 font-heading mt-2">
                How Your Booking Works
            </h2>
            <p class="text-sm text-slate-600 mt-3 leading-relaxed">
                Experience seamless sports lab reservations with dynamic availability, 10-minute temporary holds, and encrypted payments.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Step 1 -->
            <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-sky-100 text-sky-700 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    01
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Pick Protocol &amp; Live Slot</h3>
                <p class="text-sm text-slate-600 mt-2.5 leading-relaxed">
                    Choose from 10 certified sports modalities and view live slot capacity between 6:00 AM and 10:00 PM IST.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    02
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Guaranteed 10-Min Hold</h3>
                <p class="text-sm text-slate-600 mt-2.5 leading-relaxed">
                    Your spot is temporarily reserved under strict capacity protection while you complete payment via Razorpay.
                </p>
            </div>

            <!-- Step 3 -->
            <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-xs hover:shadow-md transition-shadow relative">
                <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-700 font-heading font-extrabold text-xl flex items-center justify-center mb-6">
                    03
                </div>
                <h3 class="text-lg font-bold text-slate-900 font-heading">Automated Tax Invoice</h3>
                <p class="text-sm text-slate-600 mt-2.5 leading-relaxed">
                    Instantly receive your booking voucher and statutory GST invoice via email. Show up 10 minutes prior to recharge.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- High-Conversion Bottom Banner -->
<section class="pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-br from-slate-900 via-sky-950 to-blue-900 text-white p-8 sm:p-14 text-center shadow-xl relative overflow-hidden">
            <div class="relative z-10 max-w-2xl mx-auto space-y-4">
                <h2 class="text-3xl sm:text-4xl font-extrabold font-heading tracking-tight">
                    Elevate Your Recovery Today
                </h2>
                <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                    Whether training for a marathon or recharging after an intense workout, book your customized sports recovery session today.
                </p>
                <div class="pt-4">
                    <a 
                        href="<?= app_url('services') ?>" 
                        class="inline-flex items-center justify-center font-heading font-bold text-sm px-8 py-4 rounded-2xl bg-white text-slate-900 hover:bg-slate-100 shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all"
                    >
                        Book Your Modality Slot Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
