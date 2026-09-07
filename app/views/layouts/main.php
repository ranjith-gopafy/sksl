<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Sara Kinetic Sports Lab — Recover. Recharge. Perform.') ?></title>
    <meta name="description" content="Sara Kinetic Sports Lab (SKSL) — Premier athletic recovery and sports performance lab. Recover faster, recharge fully, and perform at your peak.">
    
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
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
<body class="min-h-full flex flex-col bg-slate-950 text-slate-100 selection:bg-cyan-500 selection:text-slate-950">

    <!-- Top Announcement / Facility Bar -->
    <div class="bg-slate-900 border-b border-slate-800 text-xs py-1.5 px-4 text-center text-slate-400">
        <span class="inline-flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Facility Open Today: <strong>6:00 AM – 10:00 PM IST</strong> &bull; Asia/Kolkata
        </span>
    </div>

    <!-- Main Navigation Header -->
    <header class="sticky top-0 z-40 backdrop-blur-lg bg-slate-950/80 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Brand Logo & Tagline -->
                <a href="<?= app_url('') ?>" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-cyan-600 via-sky-500 to-indigo-500 p-0.5 shadow-lg shadow-cyan-500/20 group-hover:shadow-cyan-500/40 transition-all duration-300">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <!-- Kinetic Bolt Icon -->
                            <svg class="w-6 h-6 text-cyan-400 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="font-heading font-extrabold text-xl tracking-tight text-white flex items-center gap-1.5">
                            SKSL <span class="text-xs font-semibold px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 uppercase tracking-wider">Lab</span>
                        </div>
                        <div class="text-[10px] uppercase font-semibold tracking-widest text-slate-400 group-hover:text-cyan-400 transition-colors">
                            Recover &bull; Recharge &bull; Perform
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
                    <a href="<?= app_url('services') ?>" class="hover:text-cyan-400 transition-colors">Services &amp; Pricing</a>
                    <a href="<?= app_url('contact') ?>" class="hover:text-cyan-400 transition-colors">Contact</a>
                </nav>

                <!-- Desktop Auth Buttons -->
                <div class="hidden md:flex items-center gap-4">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="<?= app_url('my-bookings') ?>" class="text-sm font-medium text-slate-300 hover:text-white px-3 py-2 rounded-lg hover:bg-slate-900 transition-all">
                            My Bookings
                        </a>
                        <a href="<?= app_url('profile') ?>" class="inline-flex items-center gap-2 text-sm font-medium text-slate-200 bg-slate-900 hover:bg-slate-800 border border-slate-700/70 px-4 py-2 rounded-lg transition-all">
                            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                            <?= h($_SESSION['user_name'] ?? 'Account') ?>
                        </a>
                        <a href="<?= app_url('logout') ?>" class="text-xs font-semibold text-rose-400 hover:text-rose-300 px-2 py-1">
                            Sign Out
                        </a>
                    <?php else: ?>
                        <a href="<?= app_url('login') ?>" class="text-sm font-semibold text-slate-300 hover:text-white px-3 py-2 transition-colors">
                            Sign In
                        </a>
                        <a href="<?= app_url('services') ?>" class="inline-flex items-center justify-center font-heading font-semibold text-sm px-5 py-2.5 rounded-lg bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 shadow-md shadow-cyan-500/20 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all">
                            Book Now
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex md:hidden">
                    <button type="button" id="mobile-menu-btn" class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-cyan-500" aria-label="Toggle navigation">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-950 px-4 pt-3 pb-6 space-y-3">
            <a href="<?= app_url('services') ?>" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-300 hover:text-white hover:bg-slate-900">Services &amp; Pricing</a>
            <a href="<?= app_url('contact') ?>" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-300 hover:text-white hover:bg-slate-900">Contact</a>
            
            <div class="pt-3 border-t border-slate-800/80 space-y-2">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="px-3 py-1 text-xs uppercase tracking-wider text-slate-500 font-semibold">Signed in as <?= h($_SESSION['user_name'] ?? '') ?></div>
                    <a href="<?= app_url('my-bookings') ?>" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-300 hover:text-white hover:bg-slate-900">My Bookings</a>
                    <a href="<?= app_url('profile') ?>" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-300 hover:text-white hover:bg-slate-900">My Profile</a>
                    <a href="<?= app_url('logout') ?>" class="block px-3 py-2.5 rounded-lg text-base font-semibold text-rose-400 hover:bg-slate-900">Sign Out</a>
                <?php else: ?>
                    <a href="<?= app_url('login') ?>" class="block w-full text-center px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-300 bg-slate-900 hover:bg-slate-800 border border-slate-800">Sign In</a>
                    <a href="<?= app_url('register') ?>" class="block w-full text-center px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-950 bg-cyan-400 hover:bg-cyan-300 shadow-md">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1">
        <!-- Flash Messages -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <?php if ($flashSuccess = \App\Helpers\Flash::get('success')): ?>
                <div class="rounded-xl bg-emerald-500/10 border border-emerald-500/30 p-4 mb-6 flex items-start gap-3 shadow-lg shadow-emerald-500/5">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-medium text-emerald-300"><?= h($flashSuccess) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashError = \App\Helpers\Flash::get('error')): ?>
                <div class="rounded-xl bg-rose-500/10 border border-rose-500/30 p-4 mb-6 flex items-start gap-3 shadow-lg shadow-rose-500/5">
                    <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="text-sm font-medium text-rose-300"><?= h($flashError) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashInfo = \App\Helpers\Flash::get('info')): ?>
                <div class="rounded-xl bg-sky-500/10 border border-sky-500/30 p-4 mb-6 flex items-start gap-3 shadow-lg shadow-sky-500/5">
                    <svg class="w-5 h-5 text-sky-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-medium text-sky-300"><?= h($flashInfo) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Page View File Content -->
        <?php
        if (isset($viewFile) && file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<div class="max-w-7xl mx-auto px-4 py-12 text-center text-slate-400">Page not found.</div>';
        }
        ?>
    </main>

    <!-- Site Footer -->
    <footer class="bg-slate-950 border-t border-slate-800/80 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                
                <!-- Brand Info -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-600 to-sky-500 flex items-center justify-center">
                            <svg class="w-4 h-4 text-slate-950 font-bold" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                        </span>
                        <span class="font-heading font-bold text-lg text-white">SARA KINETIC SPORTS LAB</span>
                    </div>
                    <p class="text-sm text-slate-400 max-w-sm leading-relaxed">
                        Science-backed athletic recovery &amp; performance facilities. Featuring Ice Bath, Cryotherapy, Sauna, Steam, Hydrotherapy, and Endless Pool.
                    </p>
                    <div class="text-xs font-semibold text-cyan-400 tracking-wider uppercase">
                        Recover &bull; Recharge &bull; Perform
                    </div>
                </div>

                <!-- Quick Navigation -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-4 font-heading">Explore</h3>
                    <ul class="space-y-2.5 text-sm text-slate-400">
                        <li><a href="<?= app_url('services') ?>" class="hover:text-cyan-400 transition-colors">Recovery Services</a></li>
                        <li><a href="<?= app_url('my-bookings') ?>" class="hover:text-cyan-400 transition-colors">My Bookings</a></li>
                        <li><a href="<?= app_url('contact') ?>" class="hover:text-cyan-400 transition-colors">Contact &amp; Location</a></li>
                    </ul>
                </div>

                <!-- Legal & Policies -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-4 font-heading">Policies</h3>
                    <ul class="space-y-2.5 text-sm text-slate-400">
                        <li><a href="<?= app_url('privacy-policy') ?>" class="hover:text-cyan-400 transition-colors">Privacy Policy</a></li>
                        <li><a href="<?= app_url('terms') ?>" class="hover:text-cyan-400 transition-colors">Terms of Service</a></li>
                        <li><a href="<?= app_url('cancellation-refund') ?>" class="hover:text-cyan-400 transition-colors">Cancellation &amp; Refund</a></li>
                        <li><a href="<?= app_url('admin/login') ?>" class="text-slate-600 hover:text-slate-400 transition-colors text-xs">Admin Access</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-900 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <div>&copy; <?= date('Y') ?> Sara Kinetic Sports Lab. All rights reserved.</div>
                <div>GST: 18% &bull; Operating Hours: 06:00 – 22:00 IST</div>
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
