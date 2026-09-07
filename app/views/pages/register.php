<div class="max-w-md mx-auto px-4 py-12">
    <div class="bg-white border border-slate-200/90 rounded-3xl p-8 sm:p-10 shadow-xs">
        <div class="text-center mb-8">
            <img src="<?= asset('images/sksl-logo.png') ?>" alt="SKSL Logo" class="h-14 w-auto object-contain mx-auto mb-4">
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight font-heading">Create Your Account</h1>
            <p class="text-sm text-slate-600 mt-1">Join Sara Kinetic Sports Lab to book recovery sessions</p>
        </div>

        <form action="<?= app_url('register') ?>" method="POST" class="space-y-5" novalidate>
            <?= \App\Helpers\Csrf::field() ?>

            <div>
                <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Full Name <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    required 
                    value="<?= h($old['name'] ?? '') ?>"
                    placeholder="John Doe"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Email Address <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?= h($old['email'] ?? '') ?>"
                    placeholder="athlete@example.com"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <div>
                <label for="mobile" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Mobile Number <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400 text-sm font-semibold">+91</span>
                    <input 
                        type="tel" 
                        id="mobile" 
                        name="mobile" 
                        required 
                        maxlength="10"
                        value="<?= h($old['mobile'] ?? '') ?>"
                        placeholder="9876543210"
                        class="w-full pl-14 pr-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                    >
                </div>
                <p class="text-[11px] text-slate-500 mt-1">10-digit Indian mobile number</p>
            </div>

            <div>
                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Password <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    minlength="8"
                    placeholder="Minimum 8 characters"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Confirm Password <span class="text-rose-500">*</span>
                </label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    required 
                    minlength="8"
                    placeholder="Repeat your password"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <button 
                type="submit" 
                class="w-full mt-2 py-4 px-4 rounded-2xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-sm tracking-wide shadow-md shadow-sky-600/20 hover:shadow-sky-600/35 hover:-translate-y-0.5 transition-all cursor-pointer"
            >
                Create Account
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center text-sm text-slate-600">
            Already have an account? 
            <a href="<?= app_url('login') ?>" class="text-sky-600 hover:text-sky-700 font-bold ml-1 transition-colors">
                Sign in here
            </a>
        </div>
    </div>
</div>
