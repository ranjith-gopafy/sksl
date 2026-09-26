<?php
/**
 * 404 — rendered inside layouts/main.php by Router::notFound() so the page
 * keeps the site header, navigation and footer (light surface: dark text).
 */
?>
<div class="max-w-md mx-auto px-4 py-20 sm:py-24 text-center">
    <div class="w-16 h-16 rounded-2xl bg-sky-50 border border-sky-200 text-[#075183] flex items-center justify-center mx-auto mb-6">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </div>
    <span class="text-xs font-bold uppercase tracking-widest text-[#075183]">404 &middot; Not Found</span>
    <h1 class="text-3xl font-extrabold text-slate-900 font-heading mt-2">Page Not Found</h1>
    <p class="text-sm text-slate-600 mt-3 leading-relaxed">
        The recovery session, page, or link you are looking for does not exist or has been moved.
    </p>
    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
        <a href="<?= app_url('') ?>"
           class="inline-flex items-center justify-center font-heading font-bold text-sm px-6 py-3 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white shadow-md transition-all">
            Return to Homepage
        </a>
        <a href="<?= app_url('services') ?>"
           class="inline-flex items-center justify-center font-heading font-semibold text-sm px-6 py-3 rounded-xl bg-white hover:bg-slate-50 text-slate-800 border border-slate-200 transition-all">
            Browse Modalities
        </a>
    </div>
</div>
