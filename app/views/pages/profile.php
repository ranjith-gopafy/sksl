<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-white font-heading tracking-tight">Account Profile</h1>
        <p class="text-sm text-slate-400 mt-1">Manage your athlete information and account credentials</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Sidebar summary card -->
        <div class="md:col-span-1">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 text-center shadow-lg">
                <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-cyan-600 to-sky-500 text-slate-950 font-heading font-extrabold text-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-cyan-500/20">
                    <?= h(strtoupper(substr($user['name'] ?? 'U', 0, 2))) ?>
                </div>
                <h2 class="text-lg font-bold text-white font-heading"><?= h($user['name'] ?? '') ?></h2>
                <p class="text-xs text-slate-400 mt-0.5"><?= h($user['email'] ?? '') ?></p>
                <div class="mt-4 pt-4 border-t border-slate-800 text-left space-y-2 text-xs text-slate-400">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Status</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">
                            <?= h($user['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Member Since</span>
                        <span class="text-slate-300 font-medium">
                            <?= h(date('d M Y', strtotime($user['created_at'] ?? 'now'))) ?>
                        </span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800">
                    <a href="<?= app_url('my-bookings') ?>" class="block w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-200 text-xs font-semibold text-center border border-slate-700/60 transition-colors">
                        View My Bookings &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Main editing columns -->
        <div class="md:col-span-2 space-y-8">
            <!-- Edit Profile Details Card -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 md:p-8 shadow-lg">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-9 h-9 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white font-heading">Personal Details</h3>
                        <p class="text-xs text-slate-400">Update your name and primary contact number</p>
                    </div>
                </div>

                <form action="<?= app_url('profile') ?>" method="POST" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Full Name
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            value="<?= h($user['name'] ?? '') ?>"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="email_static" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Email Address (Fixed)
                        </label>
                        <input 
                            type="email" 
                            id="email_static" 
                            disabled 
                            value="<?= h($user['email'] ?? '') ?>"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950/50 border border-slate-850 text-slate-500 cursor-not-allowed text-sm"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">To change your email address, please reach out to facility management.</p>
                    </div>

                    <div>
                        <label for="mobile" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Mobile Number
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-slate-500 text-sm font-medium">+91</span>
                            <input 
                                type="tel" 
                                id="mobile" 
                                name="mobile" 
                                required 
                                maxlength="10"
                                value="<?= h($user['mobile'] ?? '') ?>"
                                class="w-full pl-12 pr-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                            >
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="py-2.5 px-6 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider transition-all"
                        >
                            Save Details
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 md:p-8 shadow-lg">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-9 h-9 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white font-heading">Security &amp; Password</h3>
                        <p class="text-xs text-slate-400">Update your account login password</p>
                    </div>
                </div>

                <form action="<?= app_url('profile/password') ?>" method="POST" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Current Password
                        </label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            required 
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="new_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            New Password (min 8 characters)
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required 
                            minlength="8"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Confirm New Password
                        </label>
                        <input 
                            type="password" 
                            id="new_password_confirmation" 
                            name="new_password_confirmation" 
                            required 
                            minlength="8"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm"
                        >
                    </div>

                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="py-2.5 px-6 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-heading font-bold text-xs uppercase tracking-wider border border-slate-700 transition-all"
                        >
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
