<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= h($title ?? 'Sara Kinetic Sports Lab — Recover. Recharge. Perform.') ?></title>
    <meta name="description" content="Sara Kinetic Sports Lab (SKSL) — Premier athletic recovery and sports performance lab in Chennai. Science-backed ice baths, infrared sauna, hydrotherapy, and endless pools.">
    
    <!-- Mobile App Capabilities & Theme Colors -->
    <meta name="theme-color" content="#075183">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SKSL Sports Lab">

    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind Compiled CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <style>
        :root {
            --color-sksl-navy: #075183;
            --color-sksl-cerulean: #4A9CC0;
            --color-sksl-gold: #D6981E;
            --color-sksl-orange: #BF5B2B;
            --color-sksl-platinum: #D9DBDA;
        }
        body {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        h1, h2, h3, h4, .font-heading {
            font-family: 'Outfit', sans-serif;
        }
        /* Touch feedback on app items */
        .app-touch-target:active {
            transform: scale(0.94);
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-[#F8FAFC] text-slate-800 selection:bg-[#075183] selection:text-white pb-20 md:pb-0">

    <!-- Top Facility Info Bar (Desktop & Tablet) -->
    <div class="bg-slate-900 text-slate-200 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
            <div class="inline-flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#D6981E] animate-pulse"></span>
                <span>Open Daily: <strong class="text-white">6:00 AM – 10:00 PM IST</strong> &bull; Sports Science &amp; Athletic Recovery</span>
            </div>
            <div class="flex items-center gap-4 text-slate-300">
                <span class="inline-flex items-center gap-1.5"><span class="text-[#4A9CC0]">📍</span> Chennai, India</span>
                <?php if (!empty($_SESSION['admin_id'])): ?>
                    <a href="<?= app_url('admin/bookings') ?>" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#075183]/40 text-[#4A9CC0] border border-[#4A9CC0]/40 hover:bg-[#075183]/60 font-semibold transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#4A9CC0]"></span> Admin Control Center
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 backdrop-blur-md bg-white/95 border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Brand Logo & Client Name in Two Lines -->
                <a href="<?= app_url('') ?>" class="flex items-center gap-2.5 sm:gap-3.5 group">
                    <img 
                        src="<?= asset('images/sksl-logo.png') ?>" 
                        alt="Sara Kinetic Sports Lab" 
                        class="h-10 sm:h-12 w-auto object-contain transition-transform duration-300 group-hover:scale-[1.02]"
                    >
                    <div class="border-l border-[#D9DBDA] pl-2.5 sm:pl-3 leading-none">
                        <div class="font-heading font-extrabold text-xs sm:text-sm uppercase tracking-wider text-[#075183]">
                            Sara Kinetic
                        </div>
                        <div class="font-heading font-bold text-[10px] sm:text-xs uppercase tracking-widest text-[#BF5B2B] mt-0.5">
                            Sports Lab
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                    <a href="<?= app_url('') ?>" class="hover:text-[#075183] transition-colors <?= (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/sksl/public/' || parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/') ? 'text-[#075183] font-bold' : '' ?>">Home</a>
                    <a href="<?= app_url('services') ?>" class="hover:text-[#075183] transition-colors <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'services') ? 'text-[#075183] font-bold' : '' ?>">Services &amp; Pricing</a>
                    <a href="<?= app_url('contact') ?>" class="hover:text-[#075183] transition-colors <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'contact') ? 'text-[#075183] font-bold' : '' ?>">Contact</a>
                    <?php if (!empty($_SESSION['admin_id'])): ?>
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs">
                            <a href="<?= app_url('admin/bookings') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-[#075183] font-semibold transition">Bookings</a>
                            <a href="<?= app_url('admin/services') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-[#075183] font-semibold transition">Modalities</a>
                            <a href="<?= app_url('admin/banner') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-[#075183] font-semibold transition">Hero Banner</a>
                        </div>
                    <?php endif; ?>
                </nav>

                <!-- Desktop Auth & Booking Buttons -->
                <div class="hidden md:flex items-center gap-3">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="<?= app_url('my-bookings') ?>" class="text-sm font-semibold text-slate-700 hover:text-[#075183] px-3 py-2 rounded-xl hover:bg-slate-100 transition-all">
                            My Bookings
                        </a>
                        <a href="<?= app_url('profile') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200/80 border border-slate-200 px-3.5 py-2 rounded-xl transition-all">
                            <span class="w-2 h-2 rounded-full bg-[#10B981]"></span>
                            <?= h($_SESSION['user_name'] ?? 'Account') ?>
                        </a>
                        <a href="<?= app_url('logout') ?>" class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-2 py-1">
                            Sign Out
                        </a>
                    <?php else: ?>
                        <a href="<?= app_url('login') ?>" class="text-sm font-semibold text-slate-700 hover:text-[#075183] px-3.5 py-2 rounded-xl hover:bg-slate-100 transition-colors">
                            Sign In
                        </a>
                        <a href="<?= app_url('services') ?>" class="inline-flex items-center justify-center font-heading font-bold text-sm px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#075183] to-[#4A9CC0] hover:from-[#053d63] hover:to-[#3888ab] text-white shadow-md shadow-[#075183]/20 hover:shadow-[#075183]/30 hover:-translate-y-0.5 transition-all">
                            Book Session
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Top Bar Right (Notifications / Profile quick link) -->
                <div class="flex items-center gap-2 md:hidden">
                    <a href="<?= !empty($_SESSION['user_id']) ? app_url('profile') : app_url('login') ?>" class="p-2 rounded-xl text-slate-700 hover:bg-slate-100">
                        <svg class="w-6 h-6 text-[#075183]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1">
        <!-- Flash Messages -->
        <?php
        $flashSuccess = \App\Helpers\Flash::get('success');
        $flashError = \App\Helpers\Flash::get('error');
        $flashInfo = \App\Helpers\Flash::get('info');
        if ($flashSuccess || $flashError || $flashInfo):
        ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <?php if ($flashSuccess): ?>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-semibold text-emerald-900"><?= h($flashSuccess) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-sm font-semibold text-rose-900"><?= h($flashError) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashInfo): ?>
                <div class="rounded-2xl bg-[#4A9CC0]/10 border border-[#4A9CC0]/30 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-[#075183] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-semibold text-[#075183]"><?= h($flashInfo) ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Page View File Content -->
        <?php
        if (isset($viewFile) && file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<div class="max-w-7xl mx-auto px-4 py-12 text-center text-slate-500">Page not found.</div>';
        }
        ?>
    </main>

    <!-- Site Footer -->
    <footer class="bg-white border-t border-slate-200/90 mt-16 md:mt-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                
                <!-- Brand Info with Official Logo -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <img 
                            src="<?= asset('images/sksl-logo.png') ?>" 
                            alt="SKSL Logo" 
                            class="h-12 w-auto object-contain"
                        >
                        <div>
                            <span class="font-heading font-extrabold text-lg text-[#075183] tracking-tight block">SARA KINETIC SPORTS LAB</span>
                            <span class="text-xs font-semibold text-[#BF5B2B] tracking-wider uppercase">Recover &bull; Recharge &bull; Perform</span>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 max-w-md leading-relaxed">
                        Science-backed sports recovery facility providing athletes, runners, and fitness enthusiasts with cold immersion, heat therapy, underwater treadmill conditioning, and specialized recovery protocols.
                    </p>
                    <div class="inline-flex items-center gap-3 pt-2 text-xs font-medium text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#10B981]"></span> 10 Certified Modalities</span>
                        <span>&bull;</span>
                        <span>Razorpay Encrypted Payments</span>
                        <span>&bull;</span>
                        <span>GST Registered</span>
                    </div>
                </div>

                <!-- Quick Navigation -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#075183] mb-4 font-heading">Recovery Services</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li><a href="<?= app_url('services') ?>" class="hover:text-[#075183] transition-colors">Catalog &amp; Pricing</a></li>
                        <li><a href="<?= app_url('booking') ?>" class="hover:text-[#075183] transition-colors">Book a Time Slot</a></li>
                        <li><a href="<?= app_url('my-bookings') ?>" class="hover:text-[#075183] transition-colors">Manage Reservations</a></li>
                        <li><a href="<?= app_url('contact') ?>" class="hover:text-[#075183] transition-colors">Facility Location</a></li>
                    </ul>
                </div>

                <!-- Legal & Policies -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#075183] mb-4 font-heading">Facility Policies</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li><a href="<?= app_url('privacy-policy') ?>" class="hover:text-[#075183] transition-colors">Privacy Policy</a></li>
                        <li><a href="<?= app_url('terms') ?>" class="hover:text-[#075183] transition-colors">Terms of Service</a></li>
                        <li><a href="<?= app_url('cancellation-refund') ?>" class="hover:text-[#075183] transition-colors">Cancellation &amp; Refund</a></li>
                        <li><a href="<?= app_url('admin/login') ?>" class="text-slate-400 hover:text-[#075183] transition-colors text-xs inline-flex items-center gap-1 mt-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Admin Portal
                        </a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-[#D9DBDA]/60 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <div>&copy; <?= date('Y') ?> Sara Kinetic Sports Lab. All rights reserved.</div>
                <div>Statutory GST: 18% &bull; Facility Hours: 06:00 – 22:00 IST &bull; Chennai, Tamil Nadu</div>
            </div>
        </div>
    </footer>

    <!-- Native-like Mobile Bottom Navigation Bar (App Experience) -->
    <?php
    $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
    $isHome = ($currentUri === '/sksl/public/' || $currentUri === '/sksl/public' || $currentUri === '/' || $currentUri === '');
    $isServices = str_contains($currentUri, '/services');
    $isBooking = str_contains($currentUri, '/booking') && !str_contains($currentUri, 'my-bookings');
    $isMyBookings = str_contains($currentUri, 'my-bookings');
    $isProfile = str_contains($currentUri, 'profile') || str_contains($currentUri, 'login') || str_contains($currentUri, 'register');
    ?>
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-lg border-t border-slate-200/90 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] px-3 py-1.5 flex items-center justify-around">
        
        <!-- Tab 1: Home -->
        <a href="<?= app_url('') ?>" class="app-touch-target flex flex-col items-center justify-center py-1 px-2.5 transition-colors <?= $isHome ? 'text-[#075183]' : 'text-slate-400 hover:text-slate-600' ?>">
            <svg class="w-5 h-5 <?= $isHome ? 'stroke-[2.5]' : 'stroke-2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            <span class="text-[10px] font-bold mt-1 tracking-tight">Home</span>
        </a>

        <!-- Tab 2: Modalities Catalog -->
        <a href="<?= app_url('services') ?>" class="app-touch-target flex flex-col items-center justify-center py-1 px-2.5 transition-colors <?= $isServices ? 'text-[#075183]' : 'text-slate-400 hover:text-slate-600' ?>">
            <svg class="w-5 h-5 <?= $isServices ? 'stroke-[2.5]' : 'stroke-2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            <span class="text-[10px] font-bold mt-1 tracking-tight">Modalities</span>
        </a>

        <!-- Tab 3: Center Elevated Book Action Pill -->
        <a href="<?= app_url('booking') ?>" class="app-touch-target -mt-5 flex flex-col items-center justify-center">
            <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-[#075183] to-[#4A9CC0] text-white flex items-center justify-center shadow-lg shadow-[#075183]/35 border-2 border-white transition-transform">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                </svg>
            </div>
            <span class="text-[10px] font-extrabold text-[#075183] mt-1 tracking-tight">Book Now</span>
        </a>

        <!-- Tab 4: My Bookings -->
        <a href="<?= app_url('my-bookings') ?>" class="app-touch-target flex flex-col items-center justify-center py-1 px-2.5 transition-colors <?= $isMyBookings ? 'text-[#075183]' : 'text-slate-400 hover:text-slate-600' ?>">
            <svg class="w-5 h-5 <?= $isMyBookings ? 'stroke-[2.5]' : 'stroke-2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
            </svg>
            <span class="text-[10px] font-bold mt-1 tracking-tight">Bookings</span>
        </a>

        <!-- Tab 5: Account / Profile -->
        <a href="<?= !empty($_SESSION['user_id']) ? app_url('profile') : app_url('login') ?>" class="app-touch-target flex flex-col items-center justify-center py-1 px-2.5 transition-colors <?= $isProfile ? 'text-[#075183]' : 'text-slate-400 hover:text-slate-600' ?>">
            <svg class="w-5 h-5 <?= $isProfile ? 'stroke-[2.5]' : 'stroke-2' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <span class="text-[10px] font-bold mt-1 tracking-tight"><?= !empty($_SESSION['user_id']) ? 'Profile' : 'Sign In' ?></span>
        </a>

    </nav>
</body>
</html>
