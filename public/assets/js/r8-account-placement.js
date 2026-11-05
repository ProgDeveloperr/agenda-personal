(() => {
  'use strict';

  const header = document.querySelector('.app-header-inner');
  if (!header) return;

  let observer;

  function positionAccount() {
    const account = header.querySelector('.r8-account-menu');
    const notifications = header.querySelector('.notification-center-v3');

    if (!account || !notifications) return false;

    if (account.parentElement === notifications) {
      return true;
    }

    if (
      account.parentElement !== header ||
      notifications.parentElement !== header
    ) {
      return false;
    }

    notifications.insertBefore(
      account,
      notifications.firstChild
    );

    return true;
  }

  if (positionAccount()) return;

  observer = new MutationObserver(() => {
    if (positionAccount()) observer.disconnect();
  });

  observer.observe(header, { childList: true });

  document.addEventListener('DOMContentLoaded', () => {
    if (positionAccount()) observer.disconnect();
  }, { once: true });
})();
