/* ARK3360_R35Q_D_MOBILE_THEME_GLOBAL */
/* Relocate the ORIGINAL theme button; never clone it or bind another click. */
(() => {
  'use strict';

  const header = document.querySelector('body.ui-v3:not(.page-dashboard-v3) .app-header-inner');
  const status = header && header.querySelector('.app-header-status');
  if (!header || !status || !window.matchMedia || !window.MutationObserver) return;

  const mobile = window.matchMedia('(width < 1450px), (height < 897px)');
  let scheduled = false;

  function reconcile() {
    const toggle = header.querySelector('[data-theme-toggle]');
    if (!toggle) return;

    if (mobile.matches) {
      const center = header.querySelector(':scope > .notification-center-v3');
      const account = center && center.querySelector(':scope > .r8-account-menu');
      if (!account) return;

      // Keeping the existing element preserves its listener, state and accessible name.
      if (toggle.parentElement !== center || toggle.nextElementSibling !== account) {
        center.insertBefore(toggle, account);
      }
    } else if (toggle.parentElement !== status) {
      status.insertBefore(toggle, status.querySelector('.status-dot'));
    }
  }

  function schedule() {
    if (scheduled) return;
    scheduled = true;
    queueMicrotask(() => {
      scheduled = false;
      reconcile();
    });
  }

  function start() {
    reconcile();
    const observer = new MutationObserver(schedule);
    observer.observe(header, { childList: true, subtree: true });
    if (typeof mobile.addEventListener === 'function') {
      mobile.addEventListener('change', schedule);
    } else if (typeof mobile.addListener === 'function') {
      mobile.addListener(schedule);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
