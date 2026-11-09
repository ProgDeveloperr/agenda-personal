
(() => {
  'use strict';

  const MARKER = 'ARK3360_R35E5_DATE_SEGMENTS';
  const initialized = new WeakSet();
  const selector = 'input[data-r35e-date="1"]';

  function dateISO(d, m, y) {
    if (
      !/^\d{2}$/.test(d) ||
      !/^\d{2}$/.test(m) ||
      !/^\d{4}$/.test(y)
    ) return '';

    const day = Number(d);
    const month = Number(m);
    const year = Number(y);

    if (
      day < 1 || day > 31 ||
      month < 1 || month > 12 ||
      year < 1
    ) return '';

    const date = new Date(0);
    date.setUTCFullYear(year, month - 1, day);
    date.setUTCHours(0, 0, 0, 0);

    if (
      date.getUTCFullYear() !== year ||
      date.getUTCMonth() !== month - 1 ||
      date.getUTCDate() !== day
    ) return '';

    return `${y}-${m}-${d}`;
  }

  function install(original) {
    if (
      !original.isConnected ||
      initialized.has(original)
    ) return;

    const picker = original._flatpickr;

    if (
      !picker?.altInput ||
      !original.dataset.r35eDate
    ) return;

    initialized.add(original);

    const visual = picker.altInput;
    const required = visual.required;

    const wrapper = document.createElement('div');
    wrapper.className = 'r35e5-date-segments';
    wrapper.setAttribute('role', 'group');
    wrapper.setAttribute(
      'aria-label',
      'Fecha: día, mes y año'
    );

    const makeInput = (piece, label, digits) => {
      const el = document.createElement('input');

      el.type = 'text';
      el.inputMode = 'numeric';
      el.autocomplete = 'off';
      el.spellcheck = false;
      el.maxLength = digits;
      el.pattern = `[0-9]{${digits}}`;
      el.required = required;
      el.placeholder =
        piece === 'year' ? 'AAAA' :
        piece === 'day' ? 'DD' : 'MM';

      el.dataset.piece = piece;
      el.setAttribute('aria-label', label);
      return el;
    };

    const day = makeInput(
      'day', 'Día de 01 a 31', 2
    );

    const month = makeInput(
      'month', 'Mes de 01 a 12', 2
    );

    const year = makeInput(
      'year', 'Año de cuatro dígitos', 4
    );

    const parts = [day, month, year];
    const baseId = original.id || 'r35e5-fecha';

    day.id = baseId + '__dia';
    month.id = baseId + '__mes';
    year.id = baseId + '__anio';

    const slash = document.createElement('span');
    slash.className = 'r35e5-separator';
    slash.textContent = '/';
    slash.setAttribute('aria-hidden', 'true');

    const calendar = document.createElement('button');
    calendar.type = 'button';
    calendar.className = 'r35e5-calendar-button';
    calendar.title = 'Abrir calendario';
    calendar.setAttribute(
      'aria-label',
      'Abrir calendario'
    );
    calendar.innerHTML =
      '<svg viewBox="0 0 24 24" ' +
      'width="19" height="19" ' +
      'fill="none" stroke="currentColor" ' +
      'stroke-width="1.8" ' +
      'stroke-linecap="round" ' +
      'stroke-linejoin="round" ' +
      'aria-hidden="true">' +
      '<rect x="3" y="5" width="18" ' +
      'height="16" rx="2"/>' +
      '<path d="M7 3v4M17 3v4M3 10h18"/>' +
      '</svg>';

    wrapper.append(
      day,
      slash,
      month,
      slash.cloneNode(true),
      year,
      calendar
    );

    visual.insertAdjacentElement(
      'afterend', wrapper
    );

    visual.classList.add(
      'r35e5-original-visual'
    );
    visual.required = false;
    visual.tabIndex = -1;
    visual.setAttribute('aria-hidden', 'true');
    visual.style.display = 'none';

    if (visual.id) {
      const label = Array.from(
        document.querySelectorAll('label[for]')
      ).find(el => el.htmlFor === visual.id);

      if (label) label.htmlFor = day.id;
    }

    function clearErrors() {
      wrapper.classList.remove('r35e5-invalid');

      parts.forEach(el => {
        el.setCustomValidity('');
        el.removeAttribute('aria-invalid');
      });
    }

    function fromCalendar() {
      const match =
        /^(\d{4})-(\d{2})-(\d{2})$/
        .exec(original.value);

      if (match) {
        year.value = match[1];
        month.value = match[2];
        day.value = match[3];
      } else {
        parts.forEach(el => {
          el.value = '';
        });
      }

      clearErrors();
    }

    const currentISO = () =>
      dateISO(day.value, month.value, year.value);

    const inRange = iso =>
      !!iso &&
      (!original.min || iso >= original.min) &&
      (!original.max || iso <= original.max);

    function toCalendar() {
      const iso = currentISO();

      if (inRange(iso)) {
        if (original.value !== iso) {
          picker.setDate(iso, false, 'Y-m-d');
        }
        return true;
      }

      if (original.value) {
        picker.clear(false);
      }

      return false;
    }

    parts.forEach((el, index) => {
      const length = el.maxLength;
      const upper =
        index === 0 ? 31 :
        index === 1 ? 12 : 9999;

      const next = parts[index + 1];
      const previous = parts[index - 1];

      el.addEventListener('beforeinput', event => {
        if (
          event.data &&
          /\D/.test(event.data) &&
          !event.isComposing
        ) {
          event.preventDefault();
        }
      });

      el.addEventListener('paste', event => {
        const text = (
          event.clipboardData
            ?.getData('text') || ''
        ).trim();

        const full =
          /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/
          .exec(text);

        if (full) {
          event.preventDefault();

          const d = full[1].padStart(2, '0');
          const m = full[2].padStart(2, '0');
          const y = full[3];

          if (!inRange(dateISO(d, m, y))) {
            return;
          }

          day.value = d;
          month.value = m;
          year.value = y;

          clearErrors();
          toCalendar();
          year.focus();
          return;
        }

        if (/\D/.test(text)) {
          event.preventDefault();
        }
      });

      el.addEventListener('input', () => {
        let value = el.value
          .replace(/\D/g, '')
          .slice(0, length);

        if (
          value.length === length &&
          (
            Number(value) < 1 ||
            Number(value) > upper
          )
        ) {
          value = value.slice(0, -1);
        }

        el.value = value;

        clearErrors();
        toCalendar();

        if (
          value.length === length &&
          next
        ) {
          next.focus();
          next.select();
        }
      });

      el.addEventListener('blur', () => {
        if (
          index < 2 &&
          el.value.length === 1
        ) {
          el.value =
            el.value.padStart(2, '0');

          toCalendar();
        }
      });

      el.addEventListener('keydown', event => {
        if (
          event.key === '/' ||
          event.key === '.'
        ) {
          event.preventDefault();
          if (next) next.focus();

        } else if (
          event.key === 'Backspace' &&
          !el.value &&
          previous
        ) {
          previous.focus();
          previous.select();

        } else if (
          event.key === 'ArrowUp' ||
          event.key === 'ArrowDown'
        ) {
          event.preventDefault();

          const delta =
            event.key === 'ArrowUp' ? 1 : -1;

          const value = Math.max(
            1,
            Math.min(
              upper,
              (Number(el.value) || 1) + delta
            )
          );

          el.value = String(value)
            .padStart(length, '0');

          clearErrors();
          toCalendar();
        }
      });
    });

    function validate(event) {
      clearErrors();

      const iso = currentISO();
      const empty = parts.every(
        el => !el.value
      );

      if (
        (empty && !required) ||
        inRange(iso)
      ) {
        toCalendar();
        return;
      }

      const invalidField =
        parts.find(
          el => el.value.length !== el.maxLength
        ) || day;

      invalidField.setCustomValidity(
        empty
          ? 'Ingresá una fecha.'
          : 'Fecha inválida: verificá día, mes y año.'
      );

      invalidField.setAttribute(
        'aria-invalid', 'true'
      );

      wrapper.classList.add(
        'r35e5-invalid'
      );

      event.preventDefault();
      event.stopImmediatePropagation();

      invalidField.focus();
      invalidField.reportValidity();
    }

    if (original.form) {
      original.form.addEventListener(
        'submit', validate, true
      );

      original.form.addEventListener(
        'reset',
        () => {
          setTimeout(fromCalendar, 1);
        }
      );
    }

    calendar.addEventListener('click', () => {
      clearErrors();
      picker.config.positionElement = wrapper;
      picker._positionElement = wrapper;
      picker.open(undefined, wrapper);
    });

    picker.config.onChange.push(
      fromCalendar
    );

    original.addEventListener(
      'change', fromCalendar
    );

    fromCalendar();
  }

  function boot() {
    const scan = () => {
      document.querySelectorAll(
        selector
      ).forEach(install);
    };

    scan();

    new MutationObserver(scan).observe(
      document.body,
      {
        childList: true,
        subtree: true
      }
    );

    console.info(MARKER + ': active');
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
