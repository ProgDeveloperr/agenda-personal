(() => {
    'use strict';

    const body =
        document.body;

    if (!body) {
        return;
    }


    const isStandalone =
        window.matchMedia(
            '(display-mode: standalone)'
        ).matches
        || window.navigator.standalone === true;


    document.documentElement.classList.toggle(
        'pwa-standalone-v3',
        isStandalone
    );


    const path =
        window.location.pathname;


    const routes = [
        {
            key: 'home',
            label: 'Inicio',
            href: '/',
            paths: [
                '/',
                '/index.php',
            ],
            icon: 'home',
        },
        {
            key: 'agenda',
            label: 'Agenda',
            href: '/agenda.php',
            paths: [
                '/agenda.php',
            ],
            icon: 'agenda',
        },
        {
            key: 'week',
            label: 'Semana',
            href: '/semana.php',
            paths: [
                '/semana.php',
            ],
            icon: 'week',
        },
        {
            key: 'life',
            label: 'Vida',
            href: '/vida.php',
            paths: [
                '/vida.php',
            ],
            icon: 'life',
        },
    ];


    const moreRoutes = [
        {
            label: 'Hoy',
            href: '/hoy.php',
            path: '/hoy.php',
            copy: 'Focus diario y siguiente acción',
        },
        {
            label: 'Calendario',
            href: '/calendario.php',
            path: '/calendario.php',
            copy: 'Vista mensual académica',
        },
        {
            label: 'Materias',
            href: '/materias.php',
            path: '/materias.php',
            copy: 'Materias y configuración',
        },
        {
            label: 'Rendimiento',
            href: '/rendimiento.php',
            path: '/rendimiento.php',
            copy: 'Notas y desempeño',
        },
        {
            label: 'Notificaciones',
            href: '/notificaciones.php',
            path: '/notificaciones.php',
            copy: 'Avisos e historial',
        },

        {
            label: 'Tareas',
            href: '/tareas.php',
            path: '/tareas.php',
            copy: 'Pendientes, prioridades y ejecución',
        },
        {
            label: 'Sistema',
            href: '/sistema.php',
            path: '/sistema.php',
            copy: 'Estado, backups y configuración',
        },

    ];


    const svgFor = (
        icon
    ) => {

        const paths = {
            home:
                '<path d="M4 11.5 12 5l8 6.5"/><path d="M6.5 10v9h11v-9"/><path d="M10 19v-5h4v5"/>',

            agenda:
                '<rect x="4" y="5" width="16" height="15" rx="3"/><path d="M8 3v4M16 3v4M4 10h16"/><path d="M8 14h3M13 14h3M8 17h3"/>',

            week:
                '<rect x="3.5" y="5" width="17" height="15" rx="3"/><path d="M7 3v4M17 3v4M3.5 10h17"/><path d="M8 13h2M14 13h2M8 16h2M14 16h2"/>',

            life:
                '<path d="M12 21s-7-4.4-7-10.2A4.8 4.8 0 0 1 13 7.2a4.8 4.8 0 0 1 8 3.6C21 16.6 12 21 12 21Z"/>',

            more:
                '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
        };


        return `
            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                ${paths[icon] || paths.more}
            </svg>
        `;
    };


    const nav =
        document.createElement(
            'nav'
        );

    nav.className =
        'mobile-bottom-nav-v3';

    nav.setAttribute(
        'aria-label',
        'Navegación principal móvil'
    );


    const inner =
        document.createElement(
            'div'
        );

    inner.className =
        'mobile-bottom-nav-inner-v3';


    for (
        const route
        of routes
    ) {

        const link =
            document.createElement(
                'a'
            );

        link.href =
            route.href;

        link.className =
            'mobile-bottom-nav-item-v3';

        link.dataset.mobileRoute =
            route.key;


        if (
            route.paths.includes(
                path
            )
        ) {

            link.classList.add(
                'is-active'
            );

            link.setAttribute(
                'aria-current',
                'page'
            );
        }


        link.innerHTML =
            svgFor(
                route.icon
            )
            + `<span>${route.label}</span>`;


        inner.appendChild(
            link
        );
    }


    const moreButton =
        document.createElement(
            'button'
        );

    moreButton.type =
        'button';

    moreButton.className =
        'mobile-bottom-nav-item-v3 mobile-more-button-v3';

    moreButton.setAttribute(
        'aria-expanded',
        'false'
    );

    moreButton.setAttribute(
        'aria-controls',
        'mobileMoreSheet'
    );


    const moreIsActive =
        moreRoutes.some(
            (route) =>
                path === route.path
                || (
                    route.path
                    === '/materias.php'
                    && path === '/materia.php'
                )
        );


    if (moreIsActive) {

        moreButton.classList.add(
            'is-active'
        );
    }


    moreButton.innerHTML =
        svgFor(
            'more'
        )
        + '<span>Más</span>';


    inner.appendChild(
        moreButton
    );

    nav.appendChild(
        inner
    );


    const backdrop =
        document.createElement(
            'button'
        );

    backdrop.type =
        'button';

    backdrop.className =
        'mobile-more-backdrop-v3';

    backdrop.setAttribute(
        'aria-label',
        'Cerrar menú'
    );

    backdrop.hidden =
        true;


    const sheet =
        document.createElement(
            'section'
        );

    sheet.id =
        'mobileMoreSheet';

    sheet.className =
        'mobile-more-sheet-v3';

    sheet.hidden =
        true;

    sheet.setAttribute(
        'aria-label',
        'Más secciones'
    );


    const sheetHeader =
        document.createElement(
            'div'
        );

    sheetHeader.className =
        'mobile-more-sheet-head-v3';


    const titleWrap =
        document.createElement(
            'div'
        );

    const eyebrow =
        document.createElement(
            'span'
        );

    eyebrow.textContent =
        'Agenda Personal';


    const title =
        document.createElement(
            'strong'
        );

    title.textContent =
        'Más secciones';


    titleWrap.append(
        eyebrow,
        title
    );


    const closeButton =
        document.createElement(
            'button'
        );

    closeButton.type =
        'button';

    closeButton.className =
        'mobile-more-close-v3';

    closeButton.setAttribute(
        'aria-label',
        'Cerrar'
    );

    closeButton.textContent =
        '×';


    sheetHeader.append(
        titleWrap,
        closeButton
    );


    const sheetLinks =
        document.createElement(
            'div'
        );

    sheetLinks.className =
        'mobile-more-links-v3';


    for (
        const route
        of moreRoutes
    ) {

        const link =
            document.createElement(
                'a'
            );

        link.href =
            route.href;


        if (
            path === route.path
            || (
                route.path
                === '/materias.php'
                && path === '/materia.php'
            )
        ) {

            link.classList.add(
                'is-active'
            );

            link.setAttribute(
                'aria-current',
                'page'
            );
        }


        const copy =
            document.createElement(
                'span'
            );

        const strong =
            document.createElement(
                'strong'
            );

        strong.textContent =
            route.label;


        const small =
            document.createElement(
                'small'
            );

        small.textContent =
            route.copy;


        copy.append(
            strong,
            small
        );


        const arrow =
            document.createElement(
                'i'
            );

        arrow.textContent =
            '→';


        link.append(
            copy,
            arrow
        );


        sheetLinks.appendChild(
            link
        );
    }


    sheet.append(
        sheetHeader,
        sheetLinks
    );


    const closeSheet = () => {

        sheet.classList.remove(
            'is-open'
        );

        backdrop.classList.remove(
            'is-open'
        );

        moreButton.setAttribute(
            'aria-expanded',
            'false'
        );

        body.classList.remove(
            'mobile-more-open-v3'
        );


        window.setTimeout(
            () => {

                if (
                    !sheet.classList.contains(
                        'is-open'
                    )
                ) {

                    sheet.hidden =
                        true;

                    backdrop.hidden =
                        true;
                }

            },
            180
        );
    };


    const openSheet = () => {

        sheet.hidden =
            false;

        backdrop.hidden =
            false;


        window.requestAnimationFrame(
            () => {

                sheet.classList.add(
                    'is-open'
                );

                backdrop.classList.add(
                    'is-open'
                );
            }
        );


        moreButton.setAttribute(
            'aria-expanded',
            'true'
        );

        body.classList.add(
            'mobile-more-open-v3'
        );


        closeButton.focus();
    };


    moreButton.addEventListener(
        'click',
        () => {

            if (
                sheet.classList.contains(
                    'is-open'
                )
            ) {

                closeSheet();

            } else {

                openSheet();
            }
        }
    );


    closeButton.addEventListener(
        'click',
        closeSheet
    );


    backdrop.addEventListener(
        'click',
        closeSheet
    );


    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape'
                && sheet.classList.contains(
                    'is-open'
                )
            ) {

                closeSheet();

                moreButton.focus();
            }
        }
    );



    document.body.append(
        backdrop,
        sheet,
        nav
    );

})();
