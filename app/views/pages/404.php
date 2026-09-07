<div class="max-w-md mx-auto px-4 py-24 text-center">
    <div class="w-16 h-16 rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 flex items-center justify-center mx-auto mb-6">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>
    <span class="text-xs font-bold uppercase tracking-widest text-cyan-400">404 Error</span>
    <h1 class="text-3xl font-extrabold text-white font-heading mt-2">Page Not Found</h1>
    <p class="text-sm text-slate-400 mt-3 leading-relaxed">
        The recovery session, page, or link you are looking for does not exist or has been moved.
    </p>
    <div class="mt-8">
        <a 
            href="<?= app_url('') ?>" 
            class="inline-flex items-center justify-center font-heading font-semibold text-sm px-6 py-3 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 shadow-lg shadow-cyan-500/25 transition-all"
        >
            Return to Homepage
        </a>
    </div>
</div>
