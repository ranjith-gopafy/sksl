<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-extrabold text-white tracking-tight font-heading">Reset Password</h1>
            <p class="text-sm text-slate-400 mt-2">
                Enter your registered email address and we will send you a secure link to reset your password.
            </p>
        </div>

        <form action="<?= app_url('forgot-password') ?>" method="POST" class="space-y-5" novalidate>
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Email Address
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    placeholder="athlete@example.com"
                    class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                >
            </div>

            <button 
                type="submit" 
                class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-sm tracking-wide shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all"
            >
                Send Reset Link
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-800 text-center text-sm text-slate-400">
            Remembered your password? 
            <a href="<?= app_url('login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold ml-1 transition-colors">
                Back to Sign In
            </a>
        </div>
    </div>
</div>
