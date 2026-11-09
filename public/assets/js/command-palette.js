(() => {
    'use strict';


    /*
     * ======================================================
     * Agenda Personal
     * Command Palette
     * ======================================================
     */


    const staticCommands = [
        {
            type: 'home',
            title: 'Inicio',
            subtitle:
                'Abrir el dashboard principal',
            badge: 'Navegación',
            url: '/',
            keywords:
                'inicio home dashboard principal',
        },

        {
            type: 'week',
            title: 'Hoy',
            subtitle:
                'Focus diario y siguiente acción',
            badge: 'Navegación',
            url: '/hoy.php',
            keywords:
                'hoy focus foco diario siguiente accion prioridad ejecutar',
        },

        {
            type: 'add',
            title: 'Nuevo evento',
            subtitle:
                'Crear parcial, final, TP o entrega',
            badge: 'Acción rápida',
            url: '/agenda.php#nuevo-evento',
            keywords:
                'nuevo crear evento parcial final tp entrega agenda',
        },

        {
            type: 'agenda',
            title: 'Agenda académica',
            subtitle:
                'Ver próximos eventos e historial',
            badge: 'Navegación',
            url: '/agenda.php',
            keywords:
                'agenda eventos parcial final entregas historial',
        },

        {
            type: 'week',
            title: 'Agenda semanal',
            subtitle:
                'Planificar de lunes a domingo',
            badge: 'Navegación',
            url: '/semana.php',
            keywords:
                'semana semanal lunes martes miercoles jueves viernes sabado domingo planificar',
        },

        {
            type: 'calendar',
            title: 'Calendario académico',
            subtitle:
                'Abrir la vista mensual',
            badge: 'Navegación',
            url: '/calendario.php',
            keywords:
                'calendario mes mensual fechas',
        },

        {
            type: 'life',
            title: 'Calendario de Vida',
            subtitle:
                'Abrir tus 1.080 meses',
            badge: 'Navegación',
            url: '/vida.php',
            keywords:
                'vida meses 1080 calendario personal',
        },

        {
            type: 'add',
            title: 'Nueva materia',
            subtitle:
                'Agregar una materia al catálogo',
            badge: 'Acción rápida',
            url: '/materias.php#nueva-materia',
            keywords:
                'nueva crear materia asignatura',
        },



        {
            type: 'tasks',
            title: 'Tareas',
            subtitle: 'Pendientes, prioridades y ejecución',
            badge: 'Navegación',
            url: '/tareas.php',
            keywords: 'tareas pendientes todo trabajo prioridad completar ejecucion',
        },
        {
            type: 'system',
            title: 'Sistema',
            subtitle: 'Estado, backups y configuración',
            badge: 'Navegación',
            url: '/sistema.php',
            keywords: 'sistema configuracion estado backups salud pwa https',
        },
        {
            type: 'notifications',
            title: 'Notificaciones',
            subtitle:
                'Avisos, recordatorios e historial',
            badge: 'Navegación',
            url: '/notificaciones.php',
            keywords:
                'notificaciones avisos recordatorios alertas campana pendientes',
        },

        {
            type: 'performance',
            title: 'Rendimiento académico',
            subtitle:
                'Notas, estados y desempeño por materia',
            badge: 'Navegación',
            url: '/rendimiento.php',
            keywords:
                'rendimiento notas promedio estadisticas metricas aprobado entregado pendiente',
        },

        {
            type: 'materias',
            title: 'Materias',
            subtitle:
                'Abrir el catálogo académico',
            badge: 'Navegación',
            url: '/materias.php',
            keywords:
                'materias asignaturas docentes comision carrera',
        },
    ];


    let activeIndex = 0;
    let searchTimer = null;
    let controller = null;
    let lastFocus = null;


    const normalize = (value) => {
        return String(value || '')
            .normalize('NFD')
            .replace(
                /[\u0300-\u036f]/g,
                ''
            )
            .toLocaleLowerCase('es')
            .trim();
    };


    const iconSvg = (type) => {

        const icons = {

            home: `
                <svg viewBox="0 0 24 24">
                    <path d="m4 11 8-7 8 7"/>
                    <path d="M6.5 9.5V20h11V9.5"/>
                    <path d="M10 20v-6h4v6"/>
                </svg>
            `,

            agenda: `
                <svg viewBox="0 0 24 24">
                    <rect x="4" y="5" width="16" height="15" rx="2"/>
                    <path d="M8 2.5v5M16 2.5v5M4 9h16"/>
                    <path d="M8 13h3M8 16h6"/>
                </svg>
            `,

            calendar: `
                <svg viewBox="0 0 24 24">
                    <rect x="3.5" y="5" width="17" height="15" rx="2"/>
                    <path d="M7.5 2.5v5M16.5 2.5v5M3.5 9h17"/>
                    <path d="M7 13h2M11 13h2M15 13h2M7 16.5h2M11 16.5h2"/>
                </svg>
            `,

            life: `
                <svg viewBox="0 0 24 24">
                    <path d="M5 5h3v3H5zM10.5 5h3v3h-3zM16 5h3v3h-3z"/>
                    <path d="M5 10.5h3v3H5zM10.5 10.5h3v3h-3zM16 10.5h3v3h-3z"/>
                    <path d="M5 16h3v3H5zM10.5 16h3v3h-3zM16 16h3v3h-3z"/>
                </svg>
            `,

            materias: `
                <svg viewBox="0 0 24 24">
                    <path d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/>
                    <path d="M9 8h6M9 12h6M9 16h4"/>
                </svg>
            `,

            materia: `
                <svg viewBox="0 0 24 24">
                    <path d="M5 4.5h11a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2V4.5Z"/>
                    <path d="M8 8h7M8 12h7"/>
                    <path d="M7 20a2 2 0 0 1 0-4h11"/>
                </svg>
            `,

            evento: `
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="8"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
            `,

            add: `
                <svg viewBox="0 0 24 24">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            `,
        };


        return icons[type]
            || icons.evento;
    };


    const overlay =
        document.createElement(
            'div'
        );


    overlay.className =
        'command-overlay-v3';

    overlay.hidden = true;


    overlay.innerHTML = `
        <div
            class="command-dialog-v3"
            role="dialog"
            aria-modal="true"
            aria-label="Búsqueda global"
        >

            <div class="command-search-v3">

                <span class="command-search-icon-v3">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="6"/>
                        <path d="m16 16 4 4"/>
                    </svg>
                </span>

                <input
                    class="command-input-v3"
                    type="search"
                    autocomplete="off"
                    spellcheck="false"
                    placeholder="Buscar materias, eventos o acciones..."
                    aria-label="Buscar en Agenda Personal"
                >

                <kbd class="command-escape-v3">
                    ESC
                </kbd>

            </div>


            <div class="command-context-v3">

                <span class="command-context-label-v3">
                    Buscar en Agenda Personal
                </span>

                <span class="command-context-status-v3">
                    Escribí al menos 2 caracteres
                </span>

            </div>


            <div
                class="command-results-v3"
                role="listbox"
            ></div>


            <div class="command-footer-v3">

                <div>
                    <span>
                        <kbd>↑</kbd>
                        <kbd>↓</kbd>
                        navegar
                    </span>

                    <span>
                        <kbd>↵</kbd>
                        abrir
                    </span>

                    <span>
                        <kbd>ESC</kbd>
                        cerrar
                    </span>
                </div>

                <strong>
                    Agenda Personal
                </strong>

            </div>

        </div>
    `;


    document.body.appendChild(
        overlay
    );


    const dialog =
        overlay.querySelector(
            '.command-dialog-v3'
        );

    const input =
        overlay.querySelector(
            '.command-input-v3'
        );

    const results =
        overlay.querySelector(
            '.command-results-v3'
        );

    const status =
        overlay.querySelector(
            '.command-context-status-v3'
        );


    const setStatus = (text) => {
        if (status) {
            status.textContent = text;
        }
    };


    const createResult = (
        item,
        section
    ) => {

        const anchor =
            document.createElement(
                'a'
            );


        anchor.className =
            'command-item-v3';

        anchor.href =
            item.url;

        anchor.dataset.commandItem =
            '1';


        const icon =
            document.createElement(
                'span'
            );

        icon.className =
            'command-item-icon-v3 '
            + 'command-icon-'
            + item.type;

        icon.innerHTML =
            iconSvg(
                item.type
            );


        if (item.color) {

            icon.style.setProperty(
                '--command-color',
                item.color
            );

            icon.classList.add(
                'command-icon-colored-v3'
            );
        }


        const content =
            document.createElement(
                'span'
            );

        content.className =
            'command-item-content-v3';


        const title =
            document.createElement(
                'strong'
            );

        title.textContent =
            item.title;


        const subtitle =
            document.createElement(
                'small'
            );

        subtitle.textContent =
            item.subtitle || '';


        content.append(
            title,
            subtitle
        );


        const meta =
            document.createElement(
                'span'
            );

        meta.className =
            'command-item-meta-v3';


        const badge =
            document.createElement(
                'small'
            );

        badge.textContent =
            item.badge
            || section;


        const arrow =
            document.createElement(
                'span'
            );

        arrow.className =
            'command-item-arrow-v3';

        arrow.textContent = '→';


        meta.append(
            badge,
            arrow
        );


        anchor.append(
            icon,
            content,
            meta
        );


        anchor.addEventListener(
            'mouseenter',
            () => {

                const items =
                    getItems();

                activeIndex =
                    Math.max(
                        0,
                        items.indexOf(
                            anchor
                        )
                    );

                updateActive();
            }
        );


        return anchor;
    };


    const getItems = () => {
        return Array.from(
            results.querySelectorAll(
                '[data-command-item]'
            )
        );
    };


    const updateActive = () => {

        const items =
            getItems();


        if (items.length === 0) {
            activeIndex = 0;
            return;
        }


        if (
            activeIndex
            >= items.length
        ) {
            activeIndex =
                items.length - 1;
        }


        if (activeIndex < 0) {
            activeIndex = 0;
        }


        items.forEach(
            (item, index) => {

                const active =
                    index === activeIndex;

                item.classList.toggle(
                    'command-item-active-v3',
                    active
                );

                item.setAttribute(
                    'aria-selected',
                    active
                        ? 'true'
                        : 'false'
                );

            }
        );


        items[
            activeIndex
        ]?.scrollIntoView(
            {
                block: 'nearest',
            }
        );
    };


    const addSection = (
        title,
        items
    ) => {

        if (
            !Array.isArray(items)
            || items.length === 0
        ) {
            return;
        }


        const section =
            document.createElement(
                'section'
            );

        section.className =
            'command-section-v3';


        const heading =
            document.createElement(
                'div'
            );

        heading.className =
            'command-section-heading-v3';

        heading.textContent =
            title;


        section.appendChild(
            heading
        );


        items.forEach(
            (item) => {

                section.appendChild(
                    createResult(
                        item,
                        title
                    )
                );

            }
        );


        results.appendChild(
            section
        );
    };


    const filterStatic = (
        query
    ) => {

        const normalized =
            normalize(query);


        if (!normalized) {
            return staticCommands;
        }


        return staticCommands.filter(
            (item) => {

                const haystack =
                    normalize(
                        [
                            item.title,
                            item.subtitle,
                            item.keywords,
                        ].join(' ')
                    );


                return haystack.includes(
                    normalized
                );
            }
        );
    };


    const renderDefault = (
        query = ''
    ) => {

        results.innerHTML = '';

        const commands =
            filterStatic(query);


        addSection(
            query
                ? 'Acciones'
                : 'Acceso rápido',
            commands
        );


        activeIndex = 0;
        updateActive();


        if (query.length < 2) {

            setStatus(
                query.length === 0
                    ? 'Navegación y acciones rápidas'
                    : 'Escribí al menos 2 caracteres'
            );
        }
    };


    const renderLoading = () => {

        const loading =
            document.createElement(
                'div'
            );

        loading.className =
            'command-loading-v3';

        loading.innerHTML = `
            <span></span>
            Buscando...
        `;

        results.appendChild(
            loading
        );
    };


    const renderEmpty = () => {

        const empty =
            document.createElement(
                'div'
            );

        empty.className =
            'command-empty-v3';

        empty.innerHTML = `
            <div class="command-empty-icon-v3">
                ${iconSvg('evento')}
            </div>

            <strong>
                Sin resultados
            </strong>

            <span>
                Probá con una materia, código,
                docente, fecha o estado.
            </span>
        `;

        results.appendChild(
            empty
        );
    };


    const performSearch =
        async (query) => {

            const clean =
                query.trim();


            renderDefault(
                clean
            );


            if (clean.length < 2) {
                return;
            }


            if (controller) {
                controller.abort();
            }


            controller =
                new AbortController();


            renderLoading();

            setStatus(
                'Buscando...'
            );


            try {

                const response =
                    await fetch(
                        '/api/search.php?q='
                        + encodeURIComponent(
                            clean
                        ),
                        {
                            method: 'GET',
                            headers: {
                                'Accept':
                                    'application/json',
                            },
                            signal:
                                controller.signal,
                        }
                    );


                const data =
                    await response.json();


                if (
                    !response.ok
                    || !data.ok
                ) {
                    throw new Error(
                        data.error
                        || 'No se pudo buscar.'
                    );
                }


                results
                    .querySelector(
                        '.command-loading-v3'
                    )
                    ?.remove();


                const serverResults =
                    Array.isArray(
                        data.results
                    )
                        ? data.results
                        : [];


                const subjects =
                    serverResults.filter(
                        (item) =>
                            item.type
                            === 'materia'
                    );


                const events =
                    serverResults.filter(
                        (item) =>
                            item.type
                            === 'evento'
                    );


                addSection(
                    'Materias',
                    subjects
                );


                addSection(
                    'Eventos',
                    events
                );


                if (
                    getItems().length
                    === 0
                ) {
                    renderEmpty();
                }


                activeIndex = 0;
                updateActive();


                setStatus(
                    serverResults.length
                    + (
                        serverResults.length === 1
                            ? ' resultado'
                            : ' resultados'
                    )
                );


            } catch (error) {

                if (
                    error.name
                    === 'AbortError'
                ) {
                    return;
                }


                results
                    .querySelector(
                        '.command-loading-v3'
                    )
                    ?.remove();


                setStatus(
                    'Error de búsqueda'
                );


                const errorNode =
                    document.createElement(
                        'div'
                    );

                errorNode.className =
                    'command-error-v3';

                errorNode.textContent =
                    'No se pudo consultar la búsqueda global.';

                results.appendChild(
                    errorNode
                );

            }
        };


    const openPalette = () => {

        if (!overlay.hidden) {
            return;
        }


        lastFocus =
            document.activeElement;


        overlay.hidden = false;

        document.body.classList.add(
            'command-open-v3'
        );


        input.value = '';

        renderDefault();


        window.setTimeout(
            () => {
                input.focus();
            },
            20
        );
    };


    const closePalette = () => {

        if (overlay.hidden) {
            return;
        }


        if (controller) {
            controller.abort();
            controller = null;
        }


        overlay.hidden = true;

        document.body.classList.remove(
            'command-open-v3'
        );


        if (
            lastFocus
            && typeof lastFocus.focus
                === 'function'
        ) {
            lastFocus.focus();
        }
    };



    // ARK3360_R35H_CLOSE_BUTTON
    // Reutiliza el cierre nativo del buscador.
    const r35hDialog = overlay.querySelector(
        '.command-dialog-v3'
    );

    if (r35hDialog) {
        const r35hClose = document.createElement('button');

        r35hClose.type = 'button';
        r35hClose.className = 'r35h-command-close';
        r35hClose.title = 'Cerrar búsqueda';

        r35hClose.setAttribute(
            'aria-label',
            'Cerrar búsqueda'
        );

        r35hClose.innerHTML =
            '<span aria-hidden="true">×</span><kbd>ESC</kbd>';

        r35hClose.addEventListener(
            'click',
            closePalette
        );

        // ARK3360_R35H_D2_HIDE_DUPLICATE_ESC
        // El boton nuevo ya contiene X + ESC.
        // Ocultamos solamente el ESC visual anterior.
        const r35hOriginalEsc = overlay.querySelector(
            '.command-search-v3 .command-escape-v3'
        );

        if (r35hOriginalEsc) {
            r35hOriginalEsc.hidden = true;

            r35hOriginalEsc.style.setProperty(
                'display',
                'none',
                'important'
            );

            r35hOriginalEsc.setAttribute(
                'aria-hidden',
                'true'
            );
        }

        r35hDialog.appendChild(r35hClose);

        if (!document.querySelector(
            'link[data-r35h-command-close]'
        )) {
            const r35hCSS = document.createElement('link');

            r35hCSS.rel = 'stylesheet';
            r35hCSS.href =
                '/assets/css/r35h-command-close.css?v=r35ie1-c277aa722eb1176e';

            r35hCSS.dataset.r35hCommandClose = '1';

            document.head.appendChild(r35hCSS);
        }
    }

    input.addEventListener(
        'input',
        () => {

            if (searchTimer) {
                window.clearTimeout(
                    searchTimer
                );
            }


            const value =
                input.value;


            searchTimer =
                window.setTimeout(
                    () => {
                        performSearch(
                            value
                        );
                    },
                    110
                );
        }
    );


    input.addEventListener(
        'keydown',
        (event) => {

            const items =
                getItems();


            if (
                event.key
                === 'ArrowDown'
            ) {

                event.preventDefault();

                if (items.length) {

                    activeIndex =
                        (
                            activeIndex + 1
                        )
                        % items.length;

                    updateActive();
                }

                return;
            }


            if (
                event.key
                === 'ArrowUp'
            ) {

                event.preventDefault();

                if (items.length) {

                    activeIndex =
                        (
                            activeIndex
                            - 1
                            + items.length
                        )
                        % items.length;

                    updateActive();
                }

                return;
            }


            if (
                event.key
                === 'Enter'
            ) {

                if (
                    items[
                        activeIndex
                    ]
                ) {

                    event.preventDefault();

                    items[
                        activeIndex
                    ].click();
                }
            }

        }
    );


    overlay.addEventListener(
        'mousedown',
        (event) => {

            if (
                event.target
                === overlay
            ) {
                closePalette();
            }
        }
    );


    document.addEventListener(
        'keydown',
        (event) => {

            const modifier =
                event.ctrlKey
                || event.metaKey;


            if (
                modifier
                && event.key
                    .toLowerCase()
                    === 'k'
            ) {

                event.preventDefault();

                if (overlay.hidden) {
                    openPalette();
                } else {
                    closePalette();
                }

                return;
            }


            if (
                event.key
                === 'Escape'
                && !overlay.hidden
            ) {

                event.preventDefault();

                closePalette();

                return;
            }


            if (
                event.key === '/'
                && overlay.hidden
            ) {

                const target =
                    event.target;


                const editing =
                    target instanceof HTMLInputElement
                    || target instanceof HTMLTextAreaElement
                    || target instanceof HTMLSelectElement
                    || target?.isContentEditable;


                if (!editing) {

                    event.preventDefault();

                    openPalette();
                }
            }

        }
    );


    /*
     * ======================================================
     * Visible desktop trigger
     * ======================================================
     */

    const headerStatus =
        document.querySelector(
            '.app-header-status'
        );


    if (headerStatus) {

        const trigger =
            document.createElement(
                'button'
            );


        trigger.type =
            'button';

        trigger.className =
            'command-trigger-v3';

        trigger.setAttribute(
            'aria-label',
            'Abrir búsqueda global'
        );


        const shortcut =
            /Mac|iPhone|iPad/.test(
                navigator.platform
            )
                ? '⌘ K'
                : 'Ctrl K';


        trigger.innerHTML = `
            <svg viewBox="0 0 24 24">
                <circle
                    cx="11"
                    cy="11"
                    r="6"
                />
                <path
                    d="m16 16 4 4"
                />
            </svg>

            <span>
                Buscar
            </span>

            <kbd>
                ${shortcut}
            </kbd>
        `;


        trigger.addEventListener(
            'click',
            openPalette
        );


        headerStatus.prepend(
            trigger
        );
    }


    /*
     * ======================================================
     * Compact/mobile trigger
     * ======================================================
     */

    const mobileTrigger =
        document.createElement(
            'button'
        );


    mobileTrigger.type =
        'button';

    mobileTrigger.className =
        'command-mobile-trigger-v3';

    mobileTrigger.setAttribute(
        'aria-label',
        'Buscar'
    );


    mobileTrigger.innerHTML = `
        <svg viewBox="0 0 24 24">
            <circle
                cx="11"
                cy="11"
                r="6"
            />
            <path
                d="m16 16 4 4"
            />
        </svg>
    `;


    mobileTrigger.addEventListener(
        'click',
        openPalette
    );


    document.body.appendChild(
        mobileTrigger
    );

})();
