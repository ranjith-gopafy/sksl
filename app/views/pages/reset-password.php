<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
        <?php if (!empty($isValid)): ?>
            <div class="text-center mb-8">
                <h1 class="text-2xl font-extrabold text-white tracking-tight font-heading">Choose New Password</h1>
                <p class="text-sm text-slate-400 mt-2">Enter your new secure password below</p>
            </div>

            <form action="<?= app_url('reset-password') ?>" method="POST" class="space-y-5" novalidate>
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="token" value="<?= h($token ?? '') ?>">

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                        New Password <span class="text-cyan-400">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="8"
                        placeholder="Minimum 8 characters"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                        Confirm New Password <span class="text-cyan-400">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        required 
                        minlength="8"
                        placeholder="Re-enter new password"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-sm tracking-wide shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all"
                >
                    Update Password
                </button>
            </form>
        <?php else: ?>
            <div class="text-center py-6">
                <div class="w-14 h-14 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white font-heading">Link Expired or Invalid</h2>
                <p class="text-sm text-slate-400 mt-2 mb-6">
                    This password reset link is invalid, has already been used, or has expired (links expire after 1 hour).
                </p>
                <a 
                    href="<?= app_url('forgot-password') ?>" 
                    class="inline-flex items-center justify-center font-heading font-semibold text-sm px-6 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 transition-all"
                >
                    Request New Reset Link
                </a>
            </div>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-slate-800 text-center text-sm text-slate-400">
            <a href="<?= app_url('login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold transition-colors">
                Back to Sign In
            </a>
        </div>
    </div>
</div>
