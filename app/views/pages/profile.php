<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 font-heading tracking-tight">Account Profile</h1>
        <p class="text-sm text-slate-600 mt-1">Manage your athlete information and account credentials</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Sidebar summary card -->
        <div class="md:col-span-1">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 text-center shadow-xs">
                <div class="w-20 h-20 rounded-2xl bg-[#053d63] text-white font-heading font-extrabold text-2xl flex items-center justify-center mx-auto mb-4 shadow-md">
                    <?= h(strtoupper(substr($user['name'] ?? 'U', 0, 2))) ?>
                </div>
                <h2 class="text-lg font-extrabold text-slate-900 font-heading"><?= h($user['name'] ?? '') ?></h2>
                <p class="text-xs text-slate-500 mt-0.5"><?= h($user['email'] ?? '') ?></p>
                
                <div class="mt-5 pt-5 border-t border-slate-100 text-left space-y-2.5 text-xs text-slate-600">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Account Status</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase">
                            <?= h($user['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Member Since</span>
                        <span class="text-slate-800 font-semibold">
                            <?= h(date('d M Y', strtotime($user['created_at'] ?? 'now'))) ?>
                        </span>
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-100">
                    <a href="<?= app_url('my-bookings') ?>" class="block w-full py-3 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold text-center border border-slate-200 transition-colors">
                        View My Bookings &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Main editing columns -->
        <div class="md:col-span-2 space-y-8">
            <!-- Edit Profile Details Card -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 md:p-8 shadow-xs">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-sky-50 border border-sky-200 text-sky-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-heading">Personal Details</h3>
                        <p class="text-xs text-slate-500">Update your name and primary contact number</p>
                    </div>
                </div>

                <form action="<?= app_url('profile') ?>" method="POST" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Full Name
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            required 
                            value="<?= h($user['name'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="email_static" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Email Address (Fixed)
                        </label>
                        <input 
                            type="email" 
                            id="email_static" 
                            disabled 
                            value="<?= h($user['email'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed text-sm"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">To change your email address, please reach out to facility management.</p>
                    </div>

                    <div>
                        <label for="mobile" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Mobile Number
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-slate-400 text-sm font-semibold">+91</span>
                            <input 
                                type="tel" 
                                id="mobile" 
                                name="mobile" 
                                required 
                                maxlength="10"
                                value="<?= h($user['mobile'] ?? '') ?>"
                                class="w-full pl-14 pr-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm"
                            >
                        </div>
                    </div>

                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="py-3 px-6 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm transition-all cursor-pointer"
                        >
                            Save Details
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 md:p-8 shadow-xs">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-700 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 font-heading">Security &amp; Password</h3>
                        <p class="text-xs text-slate-500">Update your account login password</p>
                    </div>
                </div>

                <form action="<?= app_url('profile/password') ?>" method="POST" class="space-y-4">
                    <?= \App\Helpers\Csrf::field() ?>

                    <div>
                        <label for="current_password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Current Password
                        </label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            required 
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="new_password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            New Password (min 8 characters)
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required 
                            minlength="8"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Confirm New Password
                        </label>
                        <input 
                            type="password" 
                            id="new_password_confirmation" 
                            name="new_password_confirmation" 
                            required 
                            minlength="8"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm"
                        >
                    </div>

                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="py-3 px-6 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-heading font-bold text-xs uppercase tracking-wider transition-all cursor-pointer"
                        >
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
