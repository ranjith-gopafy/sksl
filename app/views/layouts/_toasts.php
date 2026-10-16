<?php
/**
 * Shared toast notifications: the container, the flash messages queued by the controllers, and
 * window.showToast(). Included once at the end of <body> by the public layout, the admin dashboard
 * layout and the admin login layout, so every area shows messages the same way.
 */
$flashSuccess = \App\Helpers\Flash::get('success');
$flashError   = \App\Helpers\Flash::get('error');
$flashInfo    = \App\Helpers\Flash::get('info');
?>
<!-- Floating Toast Notification Container -->
<div id="toast-container" class="fixed top-5 left-4 right-4 sm:left-auto sm:right-5 z-[9999] flex flex-col gap-3 pointer-events-none sm:max-w-md sm:w-full" aria-live="polite"></div>
<!-- Global Toast Notification Script -->
    <script nonce="<?= csp_nonce() ?>">
    (function() {
        const toastContainer = document.getElementById('toast-container');

        window.showToast = function(message, type = 'success', duration) {
            if (!toastContainer || !message) return;
            // Errors stay long enough to be read (WCAG 2.2.1); the duration is always a plain number.
            duration = Number(duration) || (type === 'error' ? 9000 : 4500);

            const toast = document.createElement('div');
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.className = 'pointer-events-auto transform translate-x-10 opacity-0 transition-all duration-300 ease-out bg-white rounded-2xl shadow-xl border overflow-hidden relative flex flex-col';

            let borderClass = 'border-emerald-500/80';
            let iconSvg = '';
            let titleText = 'Success';
            let barColor = 'bg-emerald-500';

            if (type === 'error') {
                borderClass = 'border-rose-500/80';
                titleText = 'Attention';
                barColor = 'bg-rose-500';
                iconSvg = `<svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>`;
            } else if (type === 'info') {
                borderClass = 'border-[#075183]/80';
                titleText = 'Notification';
                barColor = 'bg-[#075183]';
                iconSvg = `<svg class="w-5 h-5 text-[#075183] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>`;
            } else {
                iconSvg = `<svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>`;
            }

            toast.classList.add(borderClass);

            toast.innerHTML = `
                <div class="p-4 flex items-start gap-3">
                    <div class="mt-0.5">${iconSvg}</div>
                    <div class="flex-1 min-w-0 pr-2">
                        <div class="text-xs font-bold font-heading uppercase tracking-wider text-slate-800">${titleText}</div>
                        <div class="toast-message text-xs text-slate-600 mt-0.5 break-words font-medium"></div>
                    </div>
                    <button type="button" class="text-slate-500 hover:text-slate-700 p-1 -mr-1 rounded-lg transition-colors cursor-pointer" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="h-1 w-full bg-slate-100 overflow-hidden">
                    <div class="toast-progress h-full ${barColor} transition-all duration-[${duration}ms] ease-linear w-full"></div>
                </div>
            `;

            // Message is always inserted as text — never as HTML — so a flash that
            // echoes user or database content cannot inject markup.
            const messageEl = toast.querySelector('.toast-message');
            if (messageEl) messageEl.textContent = String(message);

            toastContainer.appendChild(toast);

            // Animate In
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-10', 'opacity-0');
                toast.classList.add('translate-x-0', 'opacity-100');
            });

            // Progress bar animation
            const progressBar = toast.querySelector('.toast-progress');
            if (progressBar) {
                requestAnimationFrame(() => {
                    progressBar.style.transition = `width ${duration}ms linear`;
                    progressBar.style.width = '0%';
                });
            }

            let dismissed = false;
            const dismiss = () => {
                if (dismissed) return;
                dismissed = true;
                toast.classList.remove('translate-x-0', 'opacity-100');
                toast.classList.add('translate-x-10', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            };

            const closeBtn = toast.querySelector('button');
            if (closeBtn) closeBtn.addEventListener('click', dismiss);

            const timer = setTimeout(dismiss, duration);
            toast.addEventListener('mouseenter', () => clearTimeout(timer));
        };

        // Render any pending PHP Flash messages as Toasts
        document.addEventListener('DOMContentLoaded', () => {
            <?php $jsFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
            <?php if (!empty($flashSuccess)): ?>
                window.showToast(<?= json_encode((string) $flashSuccess, $jsFlags) ?>, 'success');
            <?php endif; ?>
            <?php if (!empty($flashError)): ?>
                window.showToast(<?= json_encode((string) $flashError, $jsFlags) ?>, 'error');
            <?php endif; ?>
            <?php if (!empty($flashInfo)): ?>
                window.showToast(<?= json_encode((string) $flashInfo, $jsFlags) ?>, 'info');
            <?php endif; ?>
        });
    })();
    </script>
