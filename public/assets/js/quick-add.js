(() => {
    'use strict';


    /*
     * ======================================================
     * Agenda Personal
     * Quick Add Global
     * ======================================================
     */


    const BOOTSTRAP_URL =
        '/api/quick-add.php';

    const SUBMIT_URL =
        '/agenda.php';


    let bootstrapData = null;
    let loadingBootstrap = null;
    let previousFocus = null;


    const overlay =
        document.createElement(
            'div'
        );


    overlay.className =
        'quick-add-overlay-v3';

    overlay.hidden = true;


    overlay.innerHTML = `
        <section
            class="quick-add-dialog-v3"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quickAddTitle"
        >

            <header class="quick-add-header-v3">

                <div class="quick-add-heading-v3">

                    <span class="quick-add-heading-icon-v3">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                    </span>

                    <div>
                        <span>
                            NUEVA FECHA
                        </span>

                        <h2 id="quickAddTitle">
                            Crear evento
                        </h2>

                        <p>
                            Sumá un evento académico
                            sin abandonar lo que estás haciendo.
                        </p>
                    </div>

                </div>


                <button
                    type="button"
                    class="quick-add-close-v3"
                    data-quick-close
                    aria-label="Cerrar"
                >
                    <span>×</span>
                    <kbd>ESC</kbd>
                </button>

            </header>


            <form
                class="quick-add-form-v3"
                novalidate
            >

                <input
                    type="hidden"
                    name="accion"
                    value="crear"
                >

                <input
                    type="hidden"
                    name="ajax"
                    value="1"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value=""
                    data-quick-csrf
                >


                <div
                    class="quick-add-error-v3"
                    data-quick-error
                    hidden
                    role="alert"
                ></div>


                <div class="quick-add-field-v3 quick-add-field-wide-v3">

                    <label for="quick_materia">
                        <span>
                            Materia
                        </span>

                        <small>
                            OBLIGATORIO
                        </small>
                    </label>

                    <div class="quick-add-subject-wrap-v3">

                        <span
                            class="quick-add-subject-color-v3"
                            data-quick-subject-color
                        ></span>

                        <select
                            id="quick_materia"
                            name="materia_id"
                            required
                            data-quick-materia
                        >
                            <option value="">
                                Cargando materias...
                            </option>
                        </select>

                    </div>

                </div>


                <div class="quick-add-grid-v3">

                    <div class="quick-add-field-v3">

                        <label for="quick_tipo">
                            Tipo
                        </label>

                        <select
                            id="quick_tipo"
                            name="tipo"
                            required
                            data-quick-tipo
                        ></select>

                    </div>


                    <div class="quick-add-field-v3">

                        <label for="quick_estado">
                            Estado
                        </label>

                        <select
                            id="quick_estado"
                            name="estado"
                            data-quick-estado
                        ></select>

                    </div>

                </div>


                <div class="quick-add-field-v3 quick-add-field-wide-v3">

                    <label for="quick_titulo">
                        <span>
                            Título
                        </span>

                        <small>
                            OBLIGATORIO
                        </small>
                    </label>

                    <input
                        id="quick_titulo"
                        name="titulo"
                        type="text"
                        maxlength="160"
                        required
                        placeholder="Ej: Parcial #2"
                        data-quick-title
                    >

                </div>


                <div class="quick-add-grid-v3">

                    <div class="quick-add-field-v3">

                        <label for="quick_fecha">
                            <span>
                                Fecha
                            </span>

                            <small>
                                OBLIGATORIO
                            </small>
                        </label>

                        <input
                            id="quick_fecha"
                            name="fecha"
                            type="date"
                            required
                        >

                    </div>


                    <div class="quick-add-field-v3">

                        <label for="quick_hora">
                            Hora
                        </label>

                        <input
                            id="quick_hora"
                            name="hora"
                            type="time"
                        >

                    </div>

                </div>


                <div class="quick-add-grid-v3">

                    <div class="quick-add-field-v3">

                        <label for="quick_lugar">
                            Lugar
                        </label>

                        <input
                            id="quick_lugar"
                            name="lugar"
                            type="text"
                            maxlength="160"
                            placeholder="Ej: Virtual"
                        >

                    </div>


                    <div class="quick-add-field-v3">

                        <label for="quick_nota">
                            Nota
                        </label>

                        <input
                            id="quick_nota"
                            name="nota"
                            type="number"
                            min="0"
                            max="10"
                            step="0.01"
                            placeholder="0 - 10"
                            data-quick-nota
                        >

                        <small
                            class="quick-add-note-help-v3"
                            data-quick-note-help
                        >
                            Disponible cuando el evento
                            tenga resolución.
                        </small>

                    </div>

                </div>


                <div class="quick-add-field-v3 quick-add-field-wide-v3">

                    <label for="quick_descripcion">
                        Descripción
                    </label>

                    <textarea
                        id="quick_descripcion"
                        name="descripcion"
                        rows="4"
                        placeholder="Información adicional, temas, recordatorios..."
                    ></textarea>

                </div>


                <footer class="quick-add-footer-v3">

                    <div class="quick-add-shortcut-v3">
                        <kbd>N</kbd>
                        <span>
                            abrir Quick Add
                        </span>
                    </div>


                    <div class="quick-add-actions-v3">

                        <button
                            type="button"
                            class="quick-add-cancel-v3"
                            data-quick-close
                        >
                            Cancelar
                        </button>


                        <button
                            type="submit"
                            class="quick-add-save-v3"
                            data-quick-save
                        >
                            <span data-quick-save-label>
                                Guardar evento
                            </span>

                            <span
                                class="quick-add-save-arrow-v3"
                                aria-hidden="true"
                            >
                                →
                            </span>
                        </button>

                    </div>

                </footer>

            </form>

        </section>
    `;


    document.body.appendChild(
        overlay
    );


    const form =
        overlay.querySelector(
            '.quick-add-form-v3'
        );

    const materia =
        overlay.querySelector(
            '[data-quick-materia]'
        );

    const materiaColor =
        overlay.querySelector(
            '[data-quick-subject-color]'
        );

    const tipo =
        overlay.querySelector(
            '[data-quick-tipo]'
        );

    const estado =
        overlay.querySelector(
            '[data-quick-estado]'
        );

    const nota =
        overlay.querySelector(
            '[data-quick-nota]'
        );

    const noteHelp =
        overlay.querySelector(
            '[data-quick-note-help]'
        );

    const csrf =
        overlay.querySelector(
            '[data-quick-csrf]'
        );

    const errorBox =
        overlay.querySelector(
            '[data-quick-error]'
        );

    const saveButton =
        overlay.querySelector(
            '[data-quick-save]'
        );

    const saveLabel =
        overlay.querySelector(
            '[data-quick-save-label]'
        );


    const isEditingElement = (
        element
    ) => {

        return element instanceof HTMLInputElement
            || element instanceof HTMLTextAreaElement
            || element instanceof HTMLSelectElement
            || element?.isContentEditable;
    };


    const closeCommandPalette = () => {

        const commandOverlay =
            document.querySelector(
                '.command-overlay-v3'
            );


        if (
            commandOverlay
            && !commandOverlay.hidden
        ) {

            commandOverlay.hidden =
                true;

            document.body.classList.remove(
                'command-open-v3'
            );
        }
    };


    const clearError = () => {

        errorBox.hidden = true;

        errorBox.textContent = '';
    };


    const showError = (
        message
    ) => {

        errorBox.textContent =
            message
            || 'No se pudo guardar el evento.';

        errorBox.hidden = false;
    };


    const option = (
        value,
        label
    ) => {

        const element =
            document.createElement(
                'option'
            );

        element.value =
            String(value);

        element.textContent =
            label;

        return element;
    };


    const populateBootstrap = (
        data
    ) => {

        csrf.value =
            data.csrf_token || '';


        materia.innerHTML = '';

        materia.appendChild(
            option(
                '',
                'Seleccionar materia'
            )
        );


        data.materias.forEach(
            (item) => {

                const code =
                    item.codigo
                        ? `[${item.codigo}] `
                        : '';

                const element =
                    option(
                        item.id,
                        code + item.nombre
                    );

                element.dataset.color =
                    item.color || '#5794ff';

                materia.appendChild(
                    element
                );
            }
        );


        tipo.innerHTML = '';

        Object.entries(
            data.tipos
        ).forEach(
            ([value, label]) => {

                tipo.appendChild(
                    option(
                        value,
                        label
                    )
                );
            }
        );


        estado.innerHTML = '';

        Object.entries(
            data.estados
        ).forEach(
            ([value, label]) => {

                estado.appendChild(
                    option(
                        value,
                        label
                    )
                );
            }
        );


        tipo.value =
            'PARCIAL';

        estado.value =
            'PENDIENTE';


        updateSubjectColor();
        updateNoteAvailability();
    };


    const getBootstrap = async () => {

        if (bootstrapData) {
            return bootstrapData;
        }


        if (loadingBootstrap) {
            return loadingBootstrap;
        }


        loadingBootstrap = fetch(
            BOOTSTRAP_URL,
            {
                method: 'GET',

                headers: {
                    'Accept':
                        'application/json',
                },

                credentials:
                    'same-origin',
            }
        )
        .then(
            async (response) => {

                const data =
                    await response.json();


                if (
                    !response.ok
                    || !data.ok
                ) {

                    throw new Error(
                        data.error
                        || 'No se pudo cargar Quick Add.'
                    );
                }


                bootstrapData =
                    data;

                return data;
            }
        )
        .finally(
            () => {
                loadingBootstrap = null;
            }
        );


        return loadingBootstrap;
    };


    const updateSubjectColor = () => {

        const selected =
            materia.options[
                materia.selectedIndex
            ];


        const color =
            selected?.dataset.color
            || 'transparent';


        materiaColor.style.setProperty(
            '--quick-subject-color',
            color
        );


        materiaColor.classList.toggle(
            'quick-add-subject-selected-v3',
            Boolean(
                selected?.value
            )
        );
    };


    const updateNoteAvailability = () => {

        const canHaveNote =
            estado.value === 'APROBADO'
            || estado.value === 'DESAPROBADO';


        nota.disabled =
            !canHaveNote;


        if (!canHaveNote) {
            nota.value = '';
        }


        noteHelp.textContent =
            canHaveNote
                ? 'Opcional · entre 0 y 10.'
                : 'Sólo Aprobado o Desaprobado pueden llevar nota.';


        noteHelp.classList.toggle(
            'quick-add-note-enabled-v3',
            canHaveNote
        );
    };


    const resetForm = () => {

        form.reset();

        clearError();


        if (bootstrapData) {

            populateBootstrap(
                bootstrapData
            );
        }
    };


    const openQuickAdd = async () => {

        if (!overlay.hidden) {
            return;
        }


        previousFocus =
            document.activeElement;


        closeCommandPalette();

        overlay.hidden =
            false;

        document.body.classList.add(
            'quick-add-open-v3'
        );


        clearError();


        materia.disabled =
            true;

        materia.innerHTML =
            '<option>Cargando materias...</option>';


        try {

            const data =
                await getBootstrap();


            resetForm();

            populateBootstrap(
                data
            );


            materia.disabled =
                false;


            window.setTimeout(
                () => {
                    materia.focus();
                },
                30
            );


        } catch (error) {

            materia.disabled =
                true;

            materia.innerHTML =
                '<option>No disponible</option>';


            showError(
                error instanceof Error
                    ? error.message
                    : 'No se pudo cargar Quick Add.'
            );
        }
    };


    const closeQuickAdd = () => {

        if (overlay.hidden) {
            return;
        }


        overlay.hidden =
            true;

        document.body.classList.remove(
            'quick-add-open-v3'
        );


        clearError();


        if (
            previousFocus
            && typeof previousFocus.focus
                === 'function'
        ) {

            previousFocus.focus();
        }
    };


    const showToast = (
        message
    ) => {

        document.querySelector(
            '.quick-add-toast-v3'
        )?.remove();


        const toast =
            document.createElement(
                'div'
            );


        toast.className =
            'quick-add-toast-v3';


        toast.innerHTML = `
            <span class="quick-add-toast-check-v3">
                ✓
            </span>

            <div>
                <strong>
                    Evento creado
                </strong>

                <span></span>
            </div>

            <a href="/agenda.php">
                Abrir agenda
            </a>
        `;


        toast.querySelector(
            'div span'
        ).textContent =
            message
            || 'El evento se guardó correctamente.';


        document.body.appendChild(
            toast
        );


        requestAnimationFrame(
            () => {
                toast.classList.add(
                    'quick-add-toast-visible-v3'
                );
            }
        );


        window.setTimeout(
            () => {

                toast.classList.remove(
                    'quick-add-toast-visible-v3'
                );


                window.setTimeout(
                    () => toast.remove(),
                    220
                );

            },
            4200
        );
    };


    materia.addEventListener(
        'change',
        updateSubjectColor
    );


    estado.addEventListener(
        'change',
        updateNoteAvailability
    );


    overlay.querySelectorAll(
        '[data-quick-close]'
    ).forEach(
        (button) => {

            button.addEventListener(
                'click',
                closeQuickAdd
            );
        }
    );


    overlay.addEventListener(
        'mousedown',
        (event) => {

            if (
                event.target
                === overlay
            ) {
                closeQuickAdd();
            }
        }
    );


    form.addEventListener(
        'submit',
        async (event) => {

            event.preventDefault();

            clearError();


            if (!form.reportValidity()) {
                return;
            }


            saveButton.disabled =
                true;

            saveButton.classList.add(
                'quick-add-saving-v3'
            );

            saveLabel.textContent =
                'Guardando...';


            try {

                const formData =
                    new FormData(
                        form
                    );


                const response =
                    await fetch(
                        SUBMIT_URL,
                        {
                            method: 'POST',

                            headers: {
                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials:
                                'same-origin',

                            body:
                                formData,
                        }
                    );


                const raw =
                    await response.text();


                let data = null;


                try {
                    data =
                        JSON.parse(raw);
                } catch {
                    data = null;
                }


                if (
                    !response.ok
                    || !data
                    || !data.ok
                ) {

                    throw new Error(
                        data?.message
                        || 'No se pudo guardar el evento.'
                    );
                }


                closeQuickAdd();


                showToast(
                    data.message
                );


                bootstrapData =
                    null;


                document.dispatchEvent(
                    new CustomEvent(
                        'agenda:event-created'
                    )
                );


            } catch (error) {

                showError(
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar el evento.'
                );


            } finally {

                saveButton.disabled =
                    false;

                saveButton.classList.remove(
                    'quick-add-saving-v3'
                );

                saveLabel.textContent =
                    'Guardar evento';
            }
        }
    );


    /*
     * ------------------------------------------------------
     * Command Palette integration
     * ------------------------------------------------------
     */

    document.addEventListener(
        'click',
        (event) => {

            const link =
                event.target.closest(
                    'a[href="/agenda.php#nuevo-evento"]'
                );


            if (!link) {
                return;
            }


            event.preventDefault();

            event.stopPropagation();

            const preselectMateriaId =
                link.dataset.quickMateriaId
                || '';

            const preselectDate =
                link.dataset.quickDate
                || '';


            openQuickAdd().then(
                () => {

                    if (preselectMateriaId) {

                        const materiaExiste =
                            Array.from(
                                materia.options
                            ).some(
                                (item) =>
                                    item.value
                                    === preselectMateriaId
                            );


                        if (materiaExiste) {

                            materia.value =
                                preselectMateriaId;

                            updateSubjectColor();
                        }
                    }


                    if (preselectDate) {

                        const fechaInput =
                            overlay.querySelector(
                                'input[name="fecha"]'
                            );


                        if (fechaInput) {

                            fechaInput.value =
                                preselectDate;
                        }
                    }
                }
            );

        },
        true
    );


    /*
     * ------------------------------------------------------
     * Keyboard
     * ------------------------------------------------------
     */

    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape'
                && !overlay.hidden
            ) {

                event.preventDefault();

                closeQuickAdd();

                return;
            }


            if (
                event.key.toLowerCase()
                    !== 'n'
                || event.ctrlKey
                || event.metaKey
                || event.altKey
                || event.shiftKey
            ) {
                return;
            }


            if (
                isEditingElement(
                    event.target
                )
            ) {
                return;
            }


            if (!overlay.hidden) {
                return;
            }


            event.preventDefault();

            openQuickAdd();
        }
    );


    /*
     * ------------------------------------------------------
     * Header trigger
     * ------------------------------------------------------
     */

    const headerStatus =
        document.querySelector(
            '.app-header-status'
        );


    if (headerStatus) {

        const button =
            document.createElement(
                'button'
            );


        button.type =
            'button';

        button.className =
            'quick-add-trigger-v3';

        button.setAttribute(
            'aria-label',
            'Crear evento'
        );


        button.innerHTML = `
            <svg viewBox="0 0 24 24">
                <path d="M12 5v14M5 12h14"/>
            </svg>

            <span>
                Nuevo
            </span>

            <kbd>
                N
            </kbd>
        `;


        button.addEventListener(
            'click',
            openQuickAdd
        );


        headerStatus.prepend(
            button
        );
    }


    /*
     * ------------------------------------------------------
     * Mobile trigger
     * ------------------------------------------------------
     */

    const mobileButton =
        document.createElement(
            'button'
        );


    mobileButton.type =
        'button';

    mobileButton.className =
        'quick-add-mobile-trigger-v3';

    mobileButton.setAttribute(
        'aria-label',
        'Crear evento'
    );


    mobileButton.innerHTML = `
        <svg viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"/>
        </svg>
    `;


    mobileButton.addEventListener(
        'click',
        openQuickAdd
    );


    document.body.appendChild(
        mobileButton
    );

})();

/* ARK3360_R35E7G_GLOBAL_QUICK_DATE_LOADER */
(() => {
  'use strict';

  const MARKER = 'ARK3360_R35E7G_GLOBAL_QUICK_DATE_LOADER';
  const FIELD = '.quick-add-overlay-v3 input[type="date"][name="fecha"]';
  const BASE = '/assets/vendor/flatpickr-4.6.13/';

  function script(url) {
    return new Promise((resolve, reject) => {
      const tag = document.createElement('script');
      tag.src = url;
      tag.async = false;
      tag.onload = resolve;
      tag.onerror = () => reject(new Error('No se pudo cargar ' + url));
      document.head.appendChild(tag);
    });
  }

  function stylesheet(url) {
    return new Promise((resolve, reject) => {
      if (document.querySelector('link[data-r35e7g-quick-date]')) {
        resolve();
        return;
      }

      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = url;
      link.dataset.r35e7gQuickDate = '1';
      link.onload = resolve;
      link.onerror = () => reject(new Error('No se pudo cargar ' + url));
      document.head.appendChild(link);
    });
  }

  async function boot() {
    const dateInput = document.querySelector(FIELD);

    if (!dateInput) {
      console.warn(MARKER + ': campo quick_fecha no encontrado');
      return;
    }

    try {
      await stylesheet('/assets/css/r35e7g-quick-date.css?v=2');

      if (typeof window.flatpickr !== 'function') {
        await script(BASE + 'flatpickr.min.js?v=1');
      }

      if (!window.flatpickr?.l10ns?.es) {
        await script(BASE + 'es.js?v=1');
      }

      const alreadyHasSegments = Array.from(document.scripts).some(el => {
        try {
          return new URL(el.src).pathname ===
            '/assets/js/r35e5-date-segments.js';
        } catch (_) {
          return false;
        }
      });

      if (!alreadyHasSegments) {
        await script('/assets/js/r35e5-date-segments.js?v=2');
      }

      await script('/assets/js/r35e7g-quick-date-pilot.js?v=1');

      console.info(MARKER + ': assets listos');
    } catch (error) {
      console.warn(MARKER + ': se conserva campo nativo', error);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, {once: true});
  } else {
    boot();
  }
})();
