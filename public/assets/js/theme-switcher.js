'use strict';

(() => {

    const STORAGE_KEY =
        'agenda-personal-theme';


    const DARK =
        'dark';

    const LIGHT =
        'light';


    const moonIcon = `
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="
                    M20.2 15.2
                    A8.3 8.3 0 0 1
                    8.8 3.8
                    8.5 8.5 0 1 0
                    20.2 15.2Z
                "
            />
        </svg>
    `;


    const sunIcon = `
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                cx="12"
                cy="12"
                r="3.6"
            />
            <path
                d="
                    M12 2.3v2
                    M12 19.7v2
                    M4.3 12h-2
                    M21.7 12h-2
                    M5.15 5.15l1.4 1.4
                    M17.45 17.45l1.4 1.4
                    M18.85 5.15l-1.4 1.4
                    M6.55 17.45l-1.4 1.4
                "
            />
        </svg>
    `;


    function getTheme() {

        return (
            document
                .documentElement
                .getAttribute(
                    'data-theme'
                )
            === DARK
        )
            ? DARK
            : LIGHT;

    }


    function updateButton(
        button,
        theme
    ) {

        const isDark =
            theme === DARK;


        button.innerHTML =
            isDark
                ? sunIcon
                : moonIcon;


        const actionLabel =
            isDark
                ? 'Activar modo claro'
                : 'Activar modo oscuro';


        button.setAttribute(
            'aria-label',
            actionLabel
        );


        button.setAttribute(
            'title',
            actionLabel
        );


        button.setAttribute(
            'aria-pressed',
            isDark
                ? 'true'
                : 'false'
        );

    }


    function applyTheme(
        theme,
        persist = true
    ) {

        const next =
            theme === DARK
                ? DARK
                : LIGHT;


        document
            .documentElement
            .setAttribute(
                'data-theme',
                next
            );


        document
            .documentElement
            .style
            .colorScheme =
                next;


        if (persist) {

            try {

                window.localStorage
                    .setItem(
                        STORAGE_KEY,
                        next
                    );

            } catch (_) {
                /*
                 * El theme sigue funcionando
                 * aunque storage no esté disponible.
                 */
            }

        }


        const button =
            document.querySelector(
                '[data-theme-toggle]'
            );


        if (button) {
            updateButton(
                button,
                next
            );
        }


        document.dispatchEvent(
            new CustomEvent(
                'agenda:themechange',
                {
                    detail: {
                        theme: next,
                    },
                }
            )
        );

    }


    function createToggle() {

        if (
            document.querySelector(
                '[data-theme-toggle]'
            )
        ) {
            return;
        }


        const status =
            document.querySelector(
                '.app-header-status'
            );


        if (!status) {
            return;
        }


        const button =
            document.createElement(
                'button'
            );


        button.type =
            'button';


        button.className =
            'theme-toggle-v3';


        button.setAttribute(
            'data-theme-toggle',
            ''
        );


        updateButton(
            button,
            getTheme()
        );


        button.addEventListener(
            'click',
            () => {

                const next =
                    getTheme()
                    === DARK
                        ? LIGHT
                        : DARK;


                applyTheme(
                    next,
                    true
                );

            }
        );


        const statusDot =
            status.querySelector(
                '.status-dot'
            );


        status.insertBefore(
            button,
            statusDot || null
        );

    }


    window.addEventListener(
        'storage',
        (event) => {

            if (
                event.key
                !== STORAGE_KEY
            ) {
                return;
            }


            if (
                event.newValue
                !== DARK
                && event.newValue
                !== LIGHT
            ) {
                return;
            }


            applyTheme(
                event.newValue,
                false
            );

        }
    );


    if (
        document.readyState
        === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            createToggle,
            {
                once: true,
            }
        );

    } else {

        createToggle();

    }

})();
