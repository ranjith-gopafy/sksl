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
                    autocomplete="new-password"
                    aria-describedby="password-hint"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
                <p id="password-hint" class="text-[11px] text-slate-500 mt-1"><?= h(\App\Helpers\PasswordPolicy::HINT) ?></p>
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
                    autocomplete="new-password"
                    class="w-full px-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition-all text-sm"
                >
            </div>

            <div class="flex items-start gap-3 pt-1">
                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    required
                    class="mt-0.5 w-4 h-4 rounded border-slate-300 text-[#075183] focus:ring-sky-500 cursor-pointer"
                >
                <label for="terms" class="text-xs text-slate-600 leading-relaxed cursor-pointer">
                    I agree to the
                    <a href="<?= app_url('terms') ?>" class="font-semibold text-[#075183] hover:underline" target="_blank" rel="noopener">Terms of Service</a>
                    and the
                    <a href="<?= app_url('privacy-policy') ?>" class="font-semibold text-[#075183] hover:underline" target="_blank" rel="noopener">Privacy Policy</a>,
                    and I understand that my booking and contact details are processed as described there.
                </label>
            </div>

            <button 
                type="submit" 
                class="w-full mt-2 py-4 px-4 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-sm tracking-wide shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
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
