<div class="max-w-md mx-auto px-4 py-12">
    <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-8 shadow-2xl backdrop-blur-xl">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-extrabold text-white tracking-tight font-heading">Create Your Account</h1>
            <p class="text-sm text-slate-400 mt-2">Join Sara Kinetic Sports Lab to book recovery sessions</p>
        </div>

        <form action="<?= app_url('register') ?>" method="POST" class="space-y-5" novalidate>
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Full Name <span class="text-cyan-400">*</span>
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    required 
                    value="<?= h($old['name'] ?? '') ?>"
                    placeholder="John Doe"
                    class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                >
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Email Address <span class="text-cyan-400">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?= h($old['email'] ?? '') ?>"
                    placeholder="athlete@example.com"
                    class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                >
            </div>

            <div>
                <label for="mobile" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Mobile Number <span class="text-cyan-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-3 text-slate-500 text-sm font-medium">+91</span>
                    <input 
                        type="tel" 
                        id="mobile" 
                        name="mobile" 
                        required 
                        maxlength="10"
                        value="<?= h($old['mobile'] ?? '') ?>"
                        placeholder="9876543210"
                        class="w-full pl-14 pr-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                    >
                </div>
                <p class="text-[11px] text-slate-500 mt-1">10-digit Indian mobile number</p>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Password <span class="text-cyan-400">*</span>
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
                    Confirm Password <span class="text-cyan-400">*</span>
                </label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    required 
                    minlength="8"
                    placeholder="Repeat your password"
                    class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm"
                >
            </div>

            <button 
                type="submit" 
                class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-sm tracking-wide shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all"
            >
                Create Account
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-800 text-center text-sm text-slate-400">
            Already have an account? 
            <a href="<?= app_url('login') ?>" class="text-cyan-400 hover:text-cyan-300 font-semibold ml-1 transition-colors">
                Sign in here
            </a>
        </div>
    </div>
</div>
