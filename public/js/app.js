/**
 * SKSL — global behaviour bindings.
 *
 * The site ships a Content-Security-Policy without 'unsafe-inline' for
 * scripts, so inline `onclick="..."` handlers are not allowed. Views declare
 * behaviour with data attributes instead and this file wires them up:
 *
 *   data-action="fnName"        click  -> window.fnName(event, element)
 *   data-oninput="fnName"       input  -> window.fnName(event, element)
 *   data-confirm="message"      submit -> window.confirm(message) must be true
 *   data-copy="text"            click  -> copies text to clipboard, shows "Copied!"
 *   data-fallback-src="url"     <img>  -> swaps in url if the image fails to load
 */
(function () {
  'use strict';

  function call(name, event, el) {
    var fn = typeof name === 'string' ? window[name] : null;
    if (typeof fn === 'function') {
      return fn.call(el, event, el);
    }
    if (window.console && name) {
      console.warn('[sksl] no handler named "' + name + '"');
    }
    return undefined;
  }

  document.addEventListener('click', function (event) {
    var actionEl = event.target.closest('[data-action]');
    if (actionEl) {
      call(actionEl.getAttribute('data-action'), event, actionEl);
    }

    var copyEl = event.target.closest('[data-copy]');
    if (copyEl && navigator.clipboard) {
      var text = copyEl.getAttribute('data-copy') || '';
      var original = copyEl.textContent;
      navigator.clipboard.writeText(text).then(function () {
        copyEl.textContent = 'Copied!';
        setTimeout(function () { copyEl.textContent = original; }, 2000);
      });
    }
  });

  document.addEventListener('input', function (event) {
    var el = event.target.closest('[data-oninput]');
    if (el) {
      call(el.getAttribute('data-oninput'), event, el);
    }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form && form.hasAttribute && form.hasAttribute('data-confirm')) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    }
  });

  function bindFallbacks(root) {
    var imgs = (root || document).querySelectorAll('img[data-fallback-src]');
    Array.prototype.forEach.call(imgs, function (img) {
      if (img.__sksl_fallback_bound) { return; }
      img.__sksl_fallback_bound = true;
      var swap = function () {
        var fallback = img.getAttribute('data-fallback-src');
        if (fallback && img.src !== fallback) {
          img.src = fallback;
        }
      };
      img.addEventListener('error', swap);
      if (img.complete && img.naturalWidth === 0 && img.src) {
        swap();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bindFallbacks(document); });
  } else {
    bindFallbacks(document);
  }
  window.skslBindFallbacks = bindFallbacks;
})();
