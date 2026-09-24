<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        
        <!-- Header badge & title -->
        <div class="text-center mb-8">
            <img src="<?= asset('images/sksl-logo.png') ?>" alt="SKSL Logo" class="h-14 w-auto object-contain mx-auto mb-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-widest mb-3">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Authorized Personnel Only
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 font-heading">Admin Portal</h1>
            <p class="text-xs text-slate-500 mt-1.5">
                Enter your registered administrator email. A single-use 6-digit passcode will be dispatched for passwordless entry.
            </p>
        </div>

        <!-- Login Card -->
        <div class="bg-white border border-slate-200/90 rounded-3xl p-8 shadow-xs">
            <form method="POST" action="<?= app_url('admin/login') ?>" class="space-y-5">
                <?= \App\Helpers\Csrf::field() ?>

                <div>
                    <label for="admin_email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                        Staff Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                            </svg>
                        </div>
                        <input 
                            type="email" 
                            id="admin_email" 
                            name="email" 
                            required 
                            placeholder="admin@sk-sports-lab.com"
                            class="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-4 px-4 rounded-2xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                    Send One-Time Access Code
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center text-xs text-slate-500">
                Are you an athlete? <a href="<?= app_url('login') ?>" class="text-sky-600 hover:underline font-semibold">Customer Sign In</a>
            </div>
        </div>

    </div>
</div>
