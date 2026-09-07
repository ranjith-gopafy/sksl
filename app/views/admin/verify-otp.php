<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center mx-auto mb-3 shadow-lg shadow-cyan-500/10">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold text-white font-heading">Enter Verification Code</h1>
            <p class="text-xs text-slate-400 mt-1.5">
                We sent a 6-digit access code to <strong class="text-cyan-400 font-mono"><?= h($email) ?></strong>.
                <br>This code expires in <strong>5 minutes</strong>.
            </p>
        </div>

        <!-- Verification Form Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-cyan-500/5 backdrop-blur-xl">
            <form method="POST" action="<?= app_url('admin/verify-otp') ?>" class="space-y-6">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="email" value="<?= h($email) ?>">

                <div>
                    <label for="admin_otp" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2 text-center">
                        6-Digit One-Time Passcode
                    </label>
                    <input 
                        type="text" 
                        id="admin_otp" 
                        name="otp" 
                        required 
                        maxlength="6"
                        pattern="\d{6}"
                        autofocus
                        inputmode="numeric"
                        placeholder="&bull;&bull;&bull;&bull;&bull;&bull;"
                        class="w-full py-4 text-center font-mono font-black text-2xl tracking-[0.5em] rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-700 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all"
                    >
                    <p class="text-[11px] text-slate-500 text-center mt-2">
                        Single-use only. Maximum 5 attempts allowed.
                    </p>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                    Verify &amp; Enter Dashboard
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                <a href="<?= app_url('admin/login') ?>" class="text-slate-400 hover:text-white transition-colors">
                    &larr; Use another email
                </a>
                <form method="POST" action="<?= app_url('admin/login') ?>" class="inline">
                    <?= \App\Helpers\Csrf::field() ?>
                    <input type="hidden" name="email" value="<?= h($email) ?>">
                    <button type="submit" class="text-cyan-400 hover:underline cursor-pointer">
                        Resend Code
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
