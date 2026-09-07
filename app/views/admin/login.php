<div class="min-h-[75vh] flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        
        <!-- Header badge & title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/30 text-cyan-400 text-xs font-bold uppercase tracking-widest mb-3">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Authorized Personnel Only
            </div>
            <h1 class="text-3xl font-extrabold text-white font-heading">Admin Portal</h1>
            <p class="text-xs text-slate-400 mt-1.5">
                Enter your registered administrator email. A single-use 6-digit passcode will be dispatched for passwordless entry.
            </p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-cyan-500/5 backdrop-blur-xl">
            <form method="POST" action="<?= app_url('admin/login') ?>" class="space-y-5">
                <?= \App\Helpers\Csrf::field() ?>

                <div>
                    <label for="admin_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                        Staff Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
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
                            class="w-full pl-11 pr-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-white placeholder-slate-600 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm transition-all"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-sky-500 hover:from-cyan-400 hover:to-sky-400 text-slate-950 font-heading font-bold text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/25 hover:shadow-cyan-500/40 hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                    Send One-Time Access Code
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-800/80 text-center text-xs text-slate-500">
                Are you an athlete? <a href="<?= app_url('login') ?>" class="text-cyan-400 hover:underline">Customer Sign In</a>
            </div>
        </div>

    </div>
</div>
