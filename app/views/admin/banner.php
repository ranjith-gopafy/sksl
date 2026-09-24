<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Admin Navigation Header -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold font-heading text-xs shadow-xs">
                AD
            </div>
            <div>
                <h1 class="text-sm font-extrabold text-slate-900 font-heading">SKSL Control Center</h1>
                <p class="text-[11px] text-slate-500">Signed in as <?= h($_SESSION['admin_name'] ?? 'Admin') ?> (<?= h($_SESSION['admin_email'] ?? '') ?>)</p>
            </div>
        </div>

        <nav class="flex items-center gap-1.5 overflow-x-auto">
            <a href="<?= app_url('admin/bookings') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Bookings
            </a>
            <a href="<?= app_url('admin/services') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Modalities
            </a>
            <a href="<?= app_url('admin/banner') ?>" class="px-3 py-1.5 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold uppercase tracking-wider">
                Hero Banners
            </a>
            <a href="<?= app_url('admin/closed-dates') ?>" class="px-3 py-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 text-xs font-medium uppercase tracking-wider transition-colors">
                Closed Dates
            </a>
            <a href="<?= app_url('admin/logout') ?>" class="px-3 py-1.5 rounded-lg text-rose-600 hover:text-rose-700 hover:bg-rose-50 text-xs font-semibold uppercase tracking-wider transition-colors">
                Sign Out
            </a>
        </nav>
    </div>

    <!-- Page Header & Action Controls -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-[11px] font-bold tracking-wider uppercase mb-1.5">
                <svg class="w-3 h-3 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Homepage Carousel Control (Max 5 Banners)
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 font-heading tracking-tight">
                Hero Banner Manager
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Manage up to 5 rotating hero slides. Edit headlines, tags, imagery, and target buttons live on the public carousel.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <?php if (count($banners) < 5): ?>
                <button 
                    type="button" 
                    onclick="openCreateBannerModal()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs shadow-xs transition-all cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Banner (<?= count($banners) ?>/5)</span>
                </button>
            <?php else: ?>
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 text-xs font-semibold">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Maximum Limit Reached (5/5)
                </span>
            <?php endif; ?>

            <a 
                href="<?= app_url('') ?>" 
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 hover:border-slate-300 bg-white text-slate-700 hover:text-slate-900 font-semibold text-xs transition-all shadow-xs"
            >
                <span>View Live Site</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            </a>
        </div>
    </div>

    <!-- Banner Slides Strip / Grid Selector -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                <span>Active Carousel Slides (<?= count($banners) ?> Total)</span>
            </h3>
            <span class="text-[11px] text-slate-400">Click a slide below to load into editor</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            <?php foreach ($banners as $idx => $b): 
                $isSelected = ($selectedBanner && (int)$selectedBanner['id'] === (int)$b['id']);
                $slideNum = $idx + 1;
            ?>
                <div class="bg-white rounded-2xl border transition-all overflow-hidden flex flex-col justify-between <?= $isSelected ? 'border-sky-500 ring-2 ring-sky-500/20 shadow-sm' : 'border-slate-200/90 hover:border-slate-300' ?>">
                    <!-- Image Thumbnail & Badges -->
                    <div class="relative h-28 bg-slate-900 overflow-hidden group">
                        <img 
                            src="<?= asset($b['image_url'] ?? 'images/hero-banner.jpg') ?>" 
                            alt="Slide <?= $slideNum ?>" 
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-black/30"></div>
                        
                        <!-- Slide Index Badge -->
                        <div class="absolute top-2 left-2 flex items-center gap-1">
                            <span class="px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md text-[10px] font-bold text-white border border-white/10">
                                #<?= $slideNum ?>
                            </span>
                            <?php if (!empty($b['is_active'])): ?>
                                <span class="px-1.5 py-0.5 rounded-md bg-emerald-500/90 text-[10px] font-bold text-white">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="px-1.5 py-0.5 rounded-md bg-slate-600/90 text-[10px] font-medium text-slate-200">
                                    Hidden
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Toggle Form -->
                        <form method="POST" action="<?= app_url('admin/banner') ?>" class="absolute top-2 right-2">
                            <?= \App\Helpers\Csrf::field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button 
                                type="submit" 
                                title="<?= !empty($b['is_active']) ? 'Hide from carousel' : 'Show on carousel' ?>"
                                class="p-1 rounded-md bg-black/50 hover:bg-black/80 text-white backdrop-blur-md transition-colors cursor-pointer"
                            >
                                <?php if (!empty($b['is_active'])): ?>
                                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </form>

                        <div class="absolute bottom-2 left-2 right-2">
                            <p class="text-[11px] font-bold text-white truncate drop-shadow">
                                <?= h($b['headline']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Slide Details & Quick Action -->
                    <div class="p-2.5 flex items-center justify-between gap-1 bg-slate-50/70 border-t border-slate-100">
                        <a 
                            href="<?= app_url('admin/banner?edit=' . (int)$b['id']) ?>" 
                            class="inline-flex items-center gap-1 text-[11px] font-bold <?= $isSelected ? 'text-sky-700' : 'text-slate-600 hover:text-sky-600' ?>"
                        >
                            <span><?= $isSelected ? '✓ Editing' : 'Edit Slide' ?></span>
                        </a>

                        <?php if (count($banners) > 1): ?>
                            <form 
                                method="POST" 
                                action="<?= app_url('admin/banner') ?>" 
                                onsubmit="return confirm('Are you sure you want to delete this hero banner?');"
                            >
                                <?= \App\Helpers\Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                <button 
                                    type="submit" 
                                    class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                    title="Delete banner"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($selectedBanner): ?>
        <!-- Main Form & Real-time Live Simulator Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Form Column (7 Cols) -->
            <div class="lg:col-span-7">
                <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs">
                    <div class="flex items-center justify-between pb-3.5 mb-5 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 font-heading flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                                Editing Banner #<?= (int) $selectedBanner['id'] ?>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Optimized form controls for fast updates</p>
                        </div>

                        <!-- Active Toggle inside editor -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-600">Active</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    id="is_active_input"
                                    name="is_active" 
                                    form="edit-banner-form"
                                    value="1" 
                                    class="sr-only peer"
                                    <?= (!empty($selectedBanner['is_active'])) ? 'checked' : '' ?>
                                >
                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-600"></div>
                            </label>
                        </div>
                    </div>

                    <form id="edit-banner-form" method="POST" action="<?= app_url('admin/banner') ?>" class="space-y-4">
                        <?= \App\Helpers\Csrf::field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $selectedBanner['id'] ?>">

                        <!-- Badge Text -->
                        <div>
                            <label for="badge_text" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Pill Badge Text
                            </label>
                            <input 
                                type="text" 
                                id="badge_text" 
                                name="badge_text" 
                                value="<?= h($selectedBanner['badge_text'] ?? 'Sports Science & High-Performance Lab') ?>" 
                                placeholder="e.g. Sports Science & High-Performance Lab"
                                class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                oninput="updateLivePreview()"
                            >
                        </div>

                        <!-- Headline -->
                        <div>
                            <label for="headline" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Primary Headline <span class="text-rose-500">*</span>
                            </label>
                            <textarea 
                                id="headline" 
                                name="headline" 
                                rows="2" 
                                required 
                                placeholder="e.g. Elite Athletic Recovery Lab"
                                class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                oninput="updateLivePreview()"
                            ><?= h($selectedBanner['headline'] ?? '') ?></textarea>
                        </div>

                        <!-- Subheadline -->
                        <div>
                            <label for="subheadline" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Subheadline / Protocol Description
                            </label>
                            <textarea 
                                id="subheadline" 
                                name="subheadline" 
                                rows="2" 
                                placeholder="e.g. Science-backed cold, heat, and hydrotherapy recovery protocols."
                                class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                oninput="updateLivePreview()"
                            ><?= h($selectedBanner['subheadline'] ?? '') ?></textarea>
                        </div>

                        <!-- Image URL / Selector -->
                        <div>
                            <label for="image_url" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                Background Photography Asset
                            </label>
                            <input 
                                type="text" 
                                id="image_url" 
                                name="image_url" 
                                value="<?= h($selectedBanner['image_url'] ?? 'images/hero-banner.jpg') ?>" 
                                placeholder="images/hero-banner.jpg"
                                class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                oninput="updateLivePreview()"
                            >
                            <!-- Quick Image Presets -->
                            <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                <span class="text-[10px] text-slate-400">Quick presets:</span>
                                <button type="button" onclick="setPresetImg('images/hero-banner.jpg')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-600 font-mono">hero-banner.jpg</button>
                                <button type="button" onclick="setPresetImg('images/services/ice-bath.jpg')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-600 font-mono">ice-bath.jpg</button>
                                <button type="button" onclick="setPresetImg('images/services/sauna.jpg')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-600 font-mono">sauna.jpg</button>
                                <button type="button" onclick="setPresetImg('images/services/lap-pool.jpg')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-600 font-mono">lap-pool.jpg</button>
                                <button type="button" onclick="setPresetImg('images/services/spa.jpg')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-600 font-mono">spa.jpg</button>
                            </div>
                        </div>

                        <!-- CTA Text & Link Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label for="cta_text" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                    CTA Button Label
                                </label>
                                <input 
                                    type="text" 
                                    id="cta_text" 
                                    name="cta_text" 
                                    value="<?= h($selectedBanner['cta_text'] ?? 'Reserve Recovery Session') ?>" 
                                    placeholder="e.g. Reserve Recovery Session"
                                    class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                    oninput="updateLivePreview()"
                                >
                            </div>
                            <div>
                                <label for="cta_link" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                                    CTA Destination URL
                                </label>
                                <input 
                                    type="text" 
                                    id="cta_link" 
                                    name="cta_link" 
                                    value="<?= h($selectedBanner['cta_link'] ?? '/booking') ?>" 
                                    placeholder="e.g. /services or /booking"
                                    class="w-full px-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 text-xs font-medium focus:bg-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                >
                            </div>
                        </div>

                        <!-- Save Action Button -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                            <button 
                                type="submit" 
                                class="py-2.5 px-5 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition-all cursor-pointer"
                            >
                                Save Changes to Banner #<?= (int) $selectedBanner['id'] ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dynamic Live Preview Simulator (5 Cols) -->
            <div class="lg:col-span-5">
                <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs sticky top-24">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Slide Simulator
                        </span>
                        <span class="text-[10px] text-slate-400">Desktop Scale</span>
                    </div>

                    <!-- Simulator Card -->
                    <div class="rounded-xl overflow-hidden relative shadow-md bg-slate-950 text-white p-5 min-h-[300px] flex flex-col justify-end border border-slate-800">
                        <!-- Background preview -->
                        <img 
                            id="preview-img" 
                            src="<?= asset($selectedBanner['image_url'] ?? 'images/hero-banner.jpg') ?>" 
                            alt="Hero Preview" 
                            class="absolute inset-0 w-full h-full object-cover object-center"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/65 to-slate-950/20"></div>

                        <!-- Overlay content -->
                        <div class="relative z-10 space-y-2.5">
                            <span 
                                id="preview-badge" 
                                class="inline-block text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/20 backdrop-blur-md border border-white/20 text-white"
                            >
                                <?= h($selectedBanner['badge_text'] ?? 'Sports Science & High-Performance Lab') ?>
                            </span>

                            <h4 
                                id="preview-headline" 
                                class="text-lg font-extrabold font-heading text-white leading-snug drop-shadow whitespace-pre-line"
                            >
                                <?= h($selectedBanner['headline'] ?? 'Elite Athletic Recovery Lab') ?>
                            </h4>

                            <p 
                                id="preview-subhead" 
                                class="text-[11px] text-slate-200 line-clamp-3 leading-relaxed"
                            >
                                <?= h($selectedBanner['subheadline'] ?? 'Science-backed hot, cold, and hydrotherapy recovery protocols.') ?>
                            </p>

                            <div class="pt-1.5">
                                <span 
                                    id="preview-cta" 
                                    class="inline-block py-1.5 px-3.5 rounded-lg bg-sky-500 text-white text-[11px] font-bold font-heading shadow-xs"
                                >
                                    <?= h($selectedBanner['cta_text'] ?? 'Reserve Recovery Session') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="text-[10px] text-slate-400 mt-3 text-center">
                        Simulator mirrors text and background live as you type.
                    </p>
                </div>
            </div>

        </div>
    <?php endif; ?>

</div>

<!-- Modal: Add New Banner (Max 5) -->
<div id="create-banner-modal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 animate-scale-up">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 font-heading">Add New Hero Banner</h3>
                <p class="text-xs text-slate-500">Banner <?= count($banners) + 1 ?> of 5 maximum</p>
            </div>
            <button type="button" onclick="closeCreateBannerModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="<?= app_url('admin/banner') ?>" class="space-y-3.5">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                    Pill Badge Text
                </label>
                <input 
                    type="text" 
                    name="badge_text" 
                    value="Sports Science & High-Performance Lab" 
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                    Primary Headline <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="headline" 
                    rows="2" 
                    required 
                    placeholder="e.g. Master Your Athletic Recovery"
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                ></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                    Subheadline
                </label>
                <textarea 
                    name="subheadline" 
                    rows="2" 
                    placeholder="Brief description of the protocol..."
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                ></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                    Background Photography Asset Path
                </label>
                <input 
                    type="text" 
                    name="image_url" 
                    value="images/services/spa.jpg" 
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                >
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                        CTA Text
                    </label>
                    <input 
                        type="text" 
                        name="cta_text" 
                        value="Reserve Recovery Session" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
                        CTA Link
                    </label>
                    <input 
                        type="text" 
                        name="cta_link" 
                        value="/booking" 
                        class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-900 focus:bg-white focus:outline-none focus:border-sky-500"
                    >
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="modal_is_active" name="is_active" value="1" checked class="rounded text-sky-600 focus:ring-sky-500">
                <label for="modal_is_active" class="text-xs font-semibold text-slate-700">Display immediately in carousel</label>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button 
                    type="button" 
                    onclick="closeCreateBannerModal()"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-4 py-2 rounded-xl bg-[#053d63] hover:bg-[#075183] text-white font-heading font-bold text-xs uppercase tracking-wider shadow-sm transition-all"
                >
                    Create Banner
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function setPresetImg(path) {
    const input = document.getElementById('image_url');
    if (input) {
        input.value = path;
        updateLivePreview();
    }
}

function updateLivePreview() {
    const badge = document.getElementById('badge_text')?.value || 'Sports Science & High-Performance Lab';
    const headline = document.getElementById('headline')?.value || 'Elite Athletic Recovery Lab';
    const subhead = document.getElementById('subheadline')?.value || '';
    const imgUrl = document.getElementById('image_url')?.value || 'images/hero-banner.jpg';
    const ctaText = document.getElementById('cta_text')?.value || 'Reserve Recovery Session';

    const pBadge = document.getElementById('preview-badge');
    const pHead = document.getElementById('preview-headline');
    const pSub = document.getElementById('preview-subhead');
    const pCta = document.getElementById('preview-cta');
    const previewImg = document.getElementById('preview-img');

    if (pBadge) pBadge.textContent = badge;
    if (pHead) pHead.textContent = headline;
    if (pSub) pSub.textContent = subhead;
    if (pCta) pCta.textContent = ctaText;

    if (previewImg) {
        if (imgUrl.startsWith('http')) {
            previewImg.src = imgUrl;
        } else {
            previewImg.src = '<?= asset('') ?>' + imgUrl.replace(/^\/+/, '');
        }
    }
}

function openCreateBannerModal() {
    const modal = document.getElementById('create-banner-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeCreateBannerModal() {
    const modal = document.getElementById('create-banner-modal');
    if (modal) modal.classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateBannerModal();
    }
});
</script>
