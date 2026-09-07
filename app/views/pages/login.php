<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-white border border-slate-200/90 rounded-3xl p-8 sm:p-10 shadow-xs">
        <div class="text-center mb-8">
            <img src="<?= asset('images/sksl-logo.png') ?>" alt="SKSL Logo" class="h-14 w-auto object-contain mx-auto mb-4">
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight font-heading">Sign In to SKSL</h1>
            <p class="text-sm text-slate-600 mt-1">Access your bookings and recovery schedule</p>
        </div>

        <form action="<?= app_url('login') ?>" method="POST" class="space-y-5" novalidate>
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Email Address
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?= h($oldEmail ?? '') ?>"
                    placeholder="athlete@example.com"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="password" class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Password
                    </label>
                    <a href="<?= app_url('forgot-password') ?>" class="text-xs text-sky-600 hover:text-sky-700 font-semibold transition-colors">
                        Forgot password?
                    </a>
                </div>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Enter your password"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <button 
                type="submit" 
                class="w-full mt-2 py-4 px-4 rounded-2xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-sm tracking-wide shadow-md shadow-sky-600/20 hover:shadow-sky-600/35 hover:-translate-y-0.5 transition-all cursor-pointer"
            >
                Sign In
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center text-sm text-slate-600">
            Don't have an account yet? 
            <a href="<?= app_url('register') ?>" class="text-sky-600 hover:text-sky-700 font-bold ml-1 transition-colors">
                Create an account
            </a>
        </div>
    </div>
</div>
