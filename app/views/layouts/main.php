<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Sara Kinetic Sports Lab — Recover. Recharge. Perform.') ?></title>
    <meta name="description" content="Sara Kinetic Sports Lab (SKSL) — Premier athletic recovery and sports performance lab in Chennai. Science-backed ice baths, infrared sauna, hydrotherapy, and endless pools.">
    
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind Compiled CSS -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        h1, h2, h3, h4, .font-heading {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-[#F8FAFC] text-slate-800 selection:bg-sky-500 selection:text-white">

    <!-- Top Announcement Bar -->
    <div class="bg-slate-900 text-slate-200 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
            <div class="inline-flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Open Today: <strong class="text-white">6:00 AM – 10:00 PM IST</strong> &bull; Sports Science &amp; Athletic Recovery</span>
            </div>
            <div class="flex items-center gap-4 text-slate-300">
                <span>📍 Chennai, India</span>
                <?php if (!empty($_SESSION['admin_id'])): ?>
                    <a href="<?= app_url('admin/bookings') ?>" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 border border-sky-400/30 hover:bg-sky-500/30 font-semibold transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span> Admin Control Center
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 backdrop-blur-md bg-white/95 border-b border-slate-200/80 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Brand Logo & Tagline -->
                <a href="<?= app_url('') ?>" class="flex items-center gap-3.5 group">
                    <img 
                        src="<?= asset('images/sksl-logo.png') ?>" 
                        alt="Sara Kinetic Sports Lab" 
                        class="h-12 w-auto object-contain transition-transform duration-300 group-hover:scale-[1.02]"
                    >
                    <div class="hidden sm:block border-l border-slate-200 pl-3">
                        <div class="font-heading font-extrabold text-sm uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                            Sara Kinetic <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 border border-sky-200 uppercase tracking-widest">Lab</span>
                        </div>
                        <div class="text-[11px] font-medium tracking-wide text-slate-500">
                            Recover &bull; Recharge &bull; Perform
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-600">
                    <a href="<?= app_url('') ?>" class="hover:text-sky-600 transition-colors">Home</a>
                    <a href="<?= app_url('services') ?>" class="hover:text-sky-600 transition-colors">Services &amp; Pricing</a>
                    <a href="<?= app_url('contact') ?>" class="hover:text-sky-600 transition-colors">Contact</a>
                    <?php if (!empty($_SESSION['admin_id'])): ?>
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs">
                            <a href="<?= app_url('admin/bookings') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-sky-700 font-semibold transition">Bookings</a>
                            <a href="<?= app_url('admin/services') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-sky-700 font-semibold transition">Modalities</a>
                            <a href="<?= app_url('admin/banner') ?>" class="px-2.5 py-1 rounded-lg hover:bg-white text-slate-700 hover:text-sky-700 font-semibold transition">Hero Banner</a>
                        </div>
                    <?php endif; ?>
                </nav>

                <!-- Desktop Auth & Booking Buttons -->
                <div class="hidden md:flex items-center gap-3">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="<?= app_url('my-bookings') ?>" class="text-sm font-semibold text-slate-700 hover:text-sky-600 px-3 py-2 rounded-xl hover:bg-slate-100 transition-all">
                            My Bookings
                        </a>
                        <a href="<?= app_url('profile') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 bg-slate-100 hover:bg-slate-200/80 border border-slate-200 px-3.5 py-2 rounded-xl transition-all">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <?= h($_SESSION['user_name'] ?? 'Account') ?>
                        </a>
                        <a href="<?= app_url('logout') ?>" class="text-xs font-semibold text-rose-600 hover:text-rose-700 px-2 py-1">
                            Sign Out
                        </a>
                    <?php else: ?>
                        <a href="<?= app_url('login') ?>" class="text-sm font-semibold text-slate-700 hover:text-sky-600 px-3.5 py-2 rounded-xl hover:bg-slate-100 transition-colors">
                            Sign In
                        </a>
                        <a href="<?= app_url('services') ?>" class="inline-flex items-center justify-center font-heading font-bold text-sm px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white shadow-md shadow-sky-600/20 hover:shadow-sky-600/30 hover:-translate-y-0.5 transition-all">
                            Book Session
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex md:hidden">
                    <button type="button" id="mobile-menu-btn" class="p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500" aria-label="Toggle navigation">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3 shadow-lg">
            <a href="<?= app_url('') ?>" class="block px-3 py-2 rounded-xl text-base font-medium text-slate-700 hover:bg-slate-50">Home</a>
            <a href="<?= app_url('services') ?>" class="block px-3 py-2 rounded-xl text-base font-medium text-slate-700 hover:bg-slate-50">Services &amp; Pricing</a>
            <a href="<?= app_url('contact') ?>" class="block px-3 py-2 rounded-xl text-base font-medium text-slate-700 hover:bg-slate-50">Contact</a>
            
            <?php if (!empty($_SESSION['admin_id'])): ?>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Admin Controls</div>
                    <a href="<?= app_url('admin/bookings') ?>" class="block px-2 py-1.5 text-sm font-semibold text-slate-700 hover:text-sky-600">Bookings Management</a>
                    <a href="<?= app_url('admin/services') ?>" class="block px-2 py-1.5 text-sm font-semibold text-slate-700 hover:text-sky-600">Modalities Catalog</a>
                    <a href="<?= app_url('admin/banner') ?>" class="block px-2 py-1.5 text-sm font-semibold text-slate-700 hover:text-sky-600">Hero Banner Editor</a>
                    <a href="<?= app_url('admin/closed-dates') ?>" class="block px-2 py-1.5 text-sm font-semibold text-slate-700 hover:text-sky-600">Closed Dates</a>
                </div>
            <?php endif; ?>

            <div class="pt-3 border-t border-slate-200 space-y-2">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="px-3 py-1 text-xs uppercase tracking-wider text-slate-500 font-semibold">Signed in as <?= h($_SESSION['user_name'] ?? '') ?></div>
                    <a href="<?= app_url('my-bookings') ?>" class="block px-3 py-2 rounded-xl text-base font-medium text-slate-700 hover:bg-slate-50">My Bookings</a>
                    <a href="<?= app_url('profile') ?>" class="block px-3 py-2 rounded-xl text-base font-medium text-slate-700 hover:bg-slate-50">My Profile</a>
                    <a href="<?= app_url('logout') ?>" class="block px-3 py-2 rounded-xl text-base font-semibold text-rose-600 hover:bg-rose-50">Sign Out</a>
                <?php else: ?>
                    <a href="<?= app_url('login') ?>" class="block w-full text-center px-4 py-2.5 rounded-xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition">Sign In</a>
                    <a href="<?= app_url('services') ?>" class="block w-full text-center px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-sky-600 to-blue-600 shadow-md">Book a Recovery Session</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1">
        <!-- Flash Messages -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <?php if ($flashSuccess = \App\Helpers\Flash::get('success')): ?>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-semibold text-emerald-900"><?= h($flashSuccess) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashError = \App\Helpers\Flash::get('error')): ?>
                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-sm font-semibold text-rose-900"><?= h($flashError) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashInfo = \App\Helpers\Flash::get('info')): ?>
                <div class="rounded-2xl bg-sky-50 border border-sky-200 p-4 mb-6 flex items-start gap-3 shadow-xs">
                    <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-semibold text-sky-900"><?= h($flashInfo) ?></div>
                </div>
            <?php endif; ?>
        </div>

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
    <footer class="bg-white border-t border-slate-200/90 mt-20">
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
                            <span class="font-heading font-extrabold text-lg text-slate-900 tracking-tight block">SARA KINETIC SPORTS LAB</span>
                            <span class="text-xs font-semibold text-sky-600 tracking-wider uppercase">Recover &bull; Recharge &bull; Perform</span>
                        </div>
                    </div>
                    <p class="text-sm text-slate-600 max-w-md leading-relaxed">
                        Science-backed sports recovery facility providing athletes, runners, and fitness enthusiasts with cold immersion, heat therapy, underwater treadmill conditioning, and specialized recovery protocols.
                    </p>
                    <div class="inline-flex items-center gap-3 pt-2 text-xs font-medium text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> 10 Certified Modalities</span>
                        <span>&bull;</span>
                        <span>Razorpay Encrypted Payments</span>
                        <span>&bull;</span>
                        <span>GST Registered</span>
                    </div>
                </div>

                <!-- Quick Navigation -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-4 font-heading">Recovery Services</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li><a href="<?= app_url('services') ?>" class="hover:text-sky-600 transition-colors">Catalog &amp; Pricing</a></li>
                        <li><a href="<?= app_url('booking') ?>" class="hover:text-sky-600 transition-colors">Book a Time Slot</a></li>
                        <li><a href="<?= app_url('my-bookings') ?>" class="hover:text-sky-600 transition-colors">Manage Reservations</a></li>
                        <li><a href="<?= app_url('contact') ?>" class="hover:text-sky-600 transition-colors">Facility Location</a></li>
                    </ul>
                </div>

                <!-- Legal & Policies -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-4 font-heading">Facility Policies</h3>
                    <ul class="space-y-2.5 text-sm text-slate-600">
                        <li><a href="<?= app_url('privacy-policy') ?>" class="hover:text-sky-600 transition-colors">Privacy Policy</a></li>
                        <li><a href="<?= app_url('terms') ?>" class="hover:text-sky-600 transition-colors">Terms of Service</a></li>
                        <li><a href="<?= app_url('cancellation-refund') ?>" class="hover:text-sky-600 transition-colors">Cancellation &amp; Refund</a></li>
                        <li><a href="<?= app_url('admin/login') ?>" class="text-slate-400 hover:text-slate-600 transition-colors text-xs inline-flex items-center gap-1 mt-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Admin Access
                        </a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <div>&copy; <?= date('Y') ?> Sara Kinetic Sports Lab. All rights reserved.</div>
                <div>Statutory GST: 18% &bull; Facility Hours: 06:00 – 22:00 IST &bull; Chennai, Tamil Nadu</div>
            </div>
        </div>
    </footer>

    <!-- Mobile Menu Toggle Script -->
    <script>
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>
