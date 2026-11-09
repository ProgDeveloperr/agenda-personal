
(() => {
  'use strict';

  const MARKER = 'ARK3360_R35E7D2_TASKS_DATE_PILOT';
  const selector = 'input[type="date"][name="fecha_limite"]:not([readonly]), input[type="date"][name="fecha_fin"]:not([readonly])';
  const ready = new WeakSet();
  let uniqueCounter = 0;

  if (
    typeof window.flatpickr !== 'function' ||
    !window.flatpickr.l10ns?.es
  ) {
    console.warn(
      MARKER + ': se conserva el selector nativo'
    );
    return;
  }

  function validISO(text) {
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/
      .exec(text.trim());

    if (!m) return null;

    const d = +m[1];
    const month = +m[2];
    const y = +m[3];

    if (!y || month < 1 || month > 12) {
      return null;
    }

    const dt = new Date(0);
    dt.setUTCFullYear(y, month - 1, d);
    dt.setUTCHours(0, 0, 0, 0);

    if (
      dt.getUTCFullYear() !== y ||
      dt.getUTCMonth() !== month - 1 ||
      dt.getUTCDate() !== d
    ) {
      return null;
    }

    return [
      String(y).padStart(4, '0'),
      String(month).padStart(2, '0'),
      String(d).padStart(2, '0')
    ].join('-');
  }

  function initialize(input) {
    if (
      !input.isConnected ||
      ready.has(input) ||
      input._flatpickr
    ) {
      return;
    }

    ready.add(input);

    const required = input.required;
    const initial = input.value;
    // ARK3360_R35E7E_UNIQUE_DATE_IDS
    if (!input.id) {
      let candidate;
      do {
        candidate = 'r35e7e-tarea-fecha-' + (++uniqueCounter);
      } while (document.getElementById(candidate));

      input.id = candidate;
    }

    const id = input.id;

    try {
      const fp = window.flatpickr(input, {
        minDate: input.getAttribute('min') || undefined,
        maxDate: input.getAttribute('max') || undefined,
        locale: {
          ...window.flatpickr.l10ns.es,
          firstDayOfWeek: 1
        },
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        disableMobile: true,
        monthSelectorType: 'dropdown'
      });

      if (!fp.altInput) {
        throw new Error('ALT_INPUT_MISSING');
      }

      const visible = fp.altInput;

      visible.classList.add('r35e-date-visible');
      visible.placeholder = 'dd/mm/aaaa';
      visible.required = required;

      visible.setAttribute(
        'aria-label',
        'Fecha: día, mes y año'
      );

      if (id) {
        visible.id = id + '__visual';

        const label = Array.from(
          document.querySelectorAll('label[for]')
        ).find(el => el.htmlFor === id);

        if (label) {
          label.htmlFor = visible.id;
        }
      }

      visible.addEventListener('input', () => {
        visible.setCustomValidity('');
      });

      if (input.form) {
        input.form.addEventListener('reset', () => {
          setTimeout(() => {
            if (input.isConnected) {
              fp.setDate(initial, false, 'Y-m-d');
              visible.setCustomValidity('');
            }
          }, 0);
        });
      }

      input.dataset.r35eDate = '1';

    } catch (error) {
      ready.delete(input);

      console.warn(
        MARKER + ': no se pudo inicializar el campo',
        error
      );
    }
  }

  function scan() {
    document
      .querySelectorAll(selector)
      .forEach(initialize);
  }

  document.addEventListener('submit', event => {
    if (
      !(event.target instanceof HTMLFormElement)
    ) {
      return;
    }

    const fields = event.target.querySelectorAll(
      'input[data-r35e-date="1"]'
    );

    for (const input of fields) {
      const fp = input._flatpickr;

      if (!fp?.altInput) {
        continue;
      }

      const visible = fp.altInput;
      visible.setCustomValidity('');

      const text = visible.value.trim();

      const iso = text
        ? validISO(text)
        : null;

      const valid =
        (!text && !visible.required) ||
        (
          iso &&
          (!input.min || iso >= input.min) &&
          (!input.max || iso <= input.max)
        );

      if (!valid) {
        visible.setCustomValidity(
          'Ingresá una fecha válida en formato DD/MM/AAAA.'
        );

        visible.reportValidity();
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }

      if (iso) {
        fp.setDate(iso, false, 'Y-m-d');
      } else {
        fp.clear(false);
      }
    }
  }, true);

  function boot() {
    document.body.classList.add('r35e7d-tareas-dates');
    scan();

    new MutationObserver(scan).observe(
      document.body,
      {
        childList: true,
        subtree: true
      }
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener(
      'DOMContentLoaded',
      boot,
      { once: true }
    );
  } else {
    boot();
  }
})();
