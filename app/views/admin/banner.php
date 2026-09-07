<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    <!-- Admin Navigation Header -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold font-heading text-sm shadow-xs">
                AD
            </div>
            <div>
                <h1 class="text-base font-extrabold text-slate-900 font-heading">SKSL Control Center</h1>
                <p class="text-xs text-slate-500">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-1.5 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3.5 py-2 rounded-xl bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Hero Banner
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3.5 py-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3.5 py-2 rounded-xl text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-xs font-bold tracking-wider uppercase mb-2">
                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Homepage Control
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 font-heading tracking-tight">
                Hero Banner Manager
            </h2>
            <p class="text-sm text-slate-600 mt-1">
                Customize the hero headline, badge tag, announcements, call-to-action button, and background hero photography on the public homepage.
            </p>
        </div>

        <a 
            href="<?= app_url('') ?>" 
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 hover:border-slate-400 bg-white text-slate-700 hover:text-slate-900 font-semibold text-xs transition-all shadow-xs"
        >
            <span>View Public Site</span>
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>
    </div>

    <!-- Main Grid: Form & Preview -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Form Column (7 Cols) -->
        <div class="lg:col-span-7">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs">
                <h3 class="text-lg font-bold text-slate-900 font-heading mb-6 flex items-center gap-2 pb-4 border-b border-slate-100">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                    Banner Configuration
                </h3>

                <form method="POST" action="<?= app_url('admin/banner') ?>" class="space-y-6">
                    <?= \App\Helpers\Csrf::field() ?>

                    <!-- Active Toggle -->
                    <div class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <div>
                            <span class="text-sm font-bold text-slate-900 block">Banner Display Status</span>
                            <span class="text-xs text-slate-500">Enable or temporarily hide the hero banner section on the homepage</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1" 
                                class="sr-only peer"
                                <?= (!empty($banner['is_active'])) ? 'checked' : '' ?>
                            >
                            <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-600"></div>
                        </label>
                    </div>

                    <!-- Badge Text -->
                    <div>
                        <label for="badge_text" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Pill Badge Text
                        </label>
                        <input 
                            type="text" 
                            id="badge_text" 
                            name="badge_text" 
                            value="<?= h($banner['badge_text'] ?? 'Sports Science & High-Performance Lab') ?>" 
                            placeholder="e.g. Sports Science & High-Performance Lab"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            oninput="updateLivePreview()"
                        >
                    </div>

                    <!-- Headline -->
                    <div>
                        <label for="headline" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Primary Headline <span class="text-rose-500">*</span>
                        </label>
                        <textarea 
                            id="headline" 
                            name="headline" 
                            rows="2" 
                            required 
                            placeholder="e.g. Elite Athletic Recovery Lab"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            oninput="updateLivePreview()"
                        ><?= h($banner['headline'] ?? 'Elite Athletic Recovery Lab') ?></textarea>
                    </div>

                    <!-- Subheadline -->
                    <div>
                        <label for="subheadline" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Subheadline / Description
                        </label>
                        <textarea 
                            id="subheadline" 
                            name="subheadline" 
                            rows="3" 
                            placeholder="e.g. Science-backed cold, heat, and hydrotherapy recovery protocols."
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            oninput="updateLivePreview()"
                        ><?= h($banner['subheadline'] ?? 'Science-backed hot, cold, and hydrotherapy recovery protocols. Optimizing athletic regeneration and physical performance.') ?></textarea>
                    </div>

                    <!-- Image URL / Selector -->
                    <div>
                        <label for="image_url" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Background Image Asset Path
                        </label>
                        <input 
                            type="text" 
                            id="image_url" 
                            name="image_url" 
                            value="<?= h($banner['image_url'] ?? 'images/hero-banner.jpg') ?>" 
                            placeholder="images/hero-banner.jpg"
                            class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            oninput="updateLivePreview()"
                        >
                        <p class="text-[11px] text-slate-500 mt-1.5">
                            Default: <code>images/hero-banner.jpg</code> (High-resolution sports facility photography)
                        </p>
                    </div>

                    <!-- CTA Text & Link Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="cta_text" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                CTA Button Label
                            </label>
                            <input 
                                type="text" 
                                id="cta_text" 
                                name="cta_text" 
                                value="<?= h($banner['cta_text'] ?? 'Reserve Recovery Session') ?>" 
                                placeholder="e.g. Reserve Recovery Session"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                oninput="updateLivePreview()"
                            >
                        </div>
                        <div>
                            <label for="cta_link" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                CTA Destination URL
                            </label>
                            <input 
                                type="text" 
                                id="cta_link" 
                                name="cta_link" 
                                value="<?= h($banner['cta_link'] ?? '/services') ?>" 
                                placeholder="e.g. /services or /booking"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-900 text-sm font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                            >
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button 
                            type="submit" 
                            class="py-3.5 px-6 rounded-2xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-heading font-bold text-xs uppercase tracking-wider shadow-md shadow-sky-600/20 hover:shadow-sky-600/35 transition-all cursor-pointer"
                        >
                            Save &amp; Update Hero Banner
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Real-Time Dynamic Preview Column (5 Cols) -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-xs sticky top-24">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Live Interactive Preview
                    </span>
                    <span class="text-[10px] text-slate-500">Desktop Scale Simulation</span>
                </div>

                <!-- Simulation Preview Card -->
                <div class="rounded-2xl overflow-hidden relative shadow-lg bg-slate-900 text-white p-6 min-h-[380px] flex flex-col justify-end border border-slate-800">
                    <!-- Background preview -->
                    <img 
                        id="preview-img" 
                        src="<?= asset($banner['image_url'] ?? 'images/hero-banner.jpg') ?>" 
                        alt="Hero Preview" 
                        class="absolute inset-0 w-full h-full object-cover object-center"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-slate-950/30"></div>

                    <!-- Overlay content -->
                    <div class="relative z-10 space-y-3">
                        <span 
                            id="preview-badge" 
                            class="inline-block text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-white/20 backdrop-blur-md border border-white/20 text-white"
                        >
                            <?= h($banner['badge_text'] ?? 'Sports Science & High-Performance Lab') ?>
                        </span>

                        <h4 
                            id="preview-headline" 
                            class="text-xl font-extrabold font-heading text-white leading-snug drop-shadow"
                        >
                            <?= h($banner['headline'] ?? 'Elite Athletic Recovery Lab') ?>
                        </h4>

                        <p 
                            id="preview-subhead" 
                            class="text-xs text-slate-200 line-clamp-3 leading-relaxed"
                        >
                            <?= h($banner['subheadline'] ?? 'Science-backed hot, cold, and hydrotherapy recovery protocols. Optimizing athletic regeneration and physical performance.') ?>
                        </p>

                        <div class="pt-2">
                            <span 
                                id="preview-cta" 
                                class="inline-block py-2 px-4 rounded-xl bg-sky-500 text-white text-xs font-bold font-heading shadow-md"
                            >
                                <?= h($banner['cta_text'] ?? 'Reserve Recovery Session') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500 mt-4 leading-relaxed text-center">
                    This preview automatically reflects edits in real-time as you type in the fields above.
                </p>
            </div>
        </div>

    </div>
</div>

<script>
function updateLivePreview() {
    const badge = document.getElementById('badge_text').value || 'Sports Science & High-Performance Lab';
    const headline = document.getElementById('headline').value || 'Elite Athletic Recovery Lab';
    const subhead = document.getElementById('subheadline').value || '';
    const imgUrl = document.getElementById('image_url').value || 'images/hero-banner.jpg';
    const ctaText = document.getElementById('cta_text').value || 'Reserve Recovery Session';

    document.getElementById('preview-badge').textContent = badge;
    document.getElementById('preview-headline').textContent = headline;
    document.getElementById('preview-subhead').textContent = subhead;
    document.getElementById('preview-cta').textContent = ctaText;

    const previewImg = document.getElementById('preview-img');
    if (imgUrl.startsWith('http')) {
        previewImg.src = imgUrl;
    } else {
        previewImg.src = '<?= asset('') ?>' + imgUrl.replace(/^\/+/, '');
    }
}
</script>
