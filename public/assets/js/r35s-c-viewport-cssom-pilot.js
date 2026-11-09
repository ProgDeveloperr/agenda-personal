/* ARK3360_R35S_C_RESPONSIVE_INDEX_PILOT
 * IN-MEMORY CSSOM MEDIA CONDITION BRIDGE. Does not rewrite source files.
 * Compact condition: CSS viewport width < 1450 OR height < 897.
 * Never changes declarations, DOM functionality, or the existing narrow breakpoints.
 */
(() => {
  'use strict';
  const compact = '(width < 1450px), (height < 897px)';
  const wide = '(width >= 1450px) and (height >= 897px)';
  const mobile760 = /\(\s*max-width\s*:\s*760px\s*\)/i;
  const minWidth = /\(\s*min-width\s*:\s*(\d+(?:\.\d+)?)px\s*\)/gi;
  const visited = new WeakSet();
  const counts = { mobile: 0, desktop: 0, stylesheets: 0, errors: 0 };
  let pending = false;

  // Preserve ALL other media constraints (e.g. height <= 650) and query-list alternatives.
  function adapt(source) {
    const output = [];
    let kind = null;
    for (const item of source.split(',')) {
      const query = item.trim();
      if (!query) continue;
      if (mobile760.test(query)) {
        output.push(query.replace(mobile760, '(width < 1450px)'));
        output.push(query.replace(mobile760, '(height < 897px)'));
        kind = 'mobile';
      } else {
        let hasDesktopMinimum = false;
        minWidth.lastIndex = 0;
        for (const match of query.matchAll(minWidth)) {
          if (Number(match[1]) >= 761) hasDesktopMinimum = true;
        }
        if (hasDesktopMinimum) {
          output.push(query + ' and ' + wide);
          kind = 'desktop';
        } else {
          output.push(query);
        }
      }
    }
    return kind ? { condition: output.join(', '), kind } : null;
  }

  function walk(rules) {
    for (const rule of rules) {
      if (rule.type === CSSRule.MEDIA_RULE && !visited.has(rule)) {
        visited.add(rule);
        const proposed = adapt(rule.media.mediaText);
        if (proposed) {
          try {
            rule.media.mediaText = proposed.condition;
            if (!rule.media.mediaText) throw new Error('empty media query');
            counts[proposed.kind]++;
          } catch (error) {
            counts.errors++;
            console.error('[R35S-C] Unable to adapt CSS media:', error);
          }
        }
      }
      if (rule.cssRules) walk(rule.cssRules);
      if (rule.type === CSSRule.IMPORT_RULE && rule.styleSheet) {
        try { walk(rule.styleSheet.cssRules); } catch (_error) { /* not our stylesheet */ }
      }
    }
  }

  function reconcileStylesheets() {
    pending = false;
    const pageOrigin = window.location.origin;
    for (const sheet of document.styleSheets) {
      if (sheet.href) {
        try {
          if (new URL(sheet.href, document.baseURI).origin !== pageOrigin) continue;
        } catch (_error) { continue; }
      }
      try {
        walk(sheet.cssRules);
      } catch (_error) { /* vendor or cross-origin stylesheet; ignore */ }
    }
    document.documentElement.dataset.r35sPilot =
      counts.mobile >= 15 ? 'ready' : 'needs-review';
    document.documentElement.dataset.r35sMobileRules = String(counts.mobile);
    document.documentElement.dataset.r35sDesktopRules = String(counts.desktop);
  }

  function schedule() {
    if (pending) return;
    pending = true;
    requestAnimationFrame(reconcileStylesheets);
  }

  // The script is intentionally loaded at the end of HEAD after blocking stylesheets,
  // before the BODY is rendered. Dynamic CSS (Quick Add, search) is handled on load.
  reconcileStylesheets();
  document.head.addEventListener('load', schedule, true);
  new MutationObserver(schedule).observe(document.head, { childList: true, subtree: true });
  window.addEventListener('load', schedule, { once: true });
  window.addEventListener('pageshow', schedule);
})();
