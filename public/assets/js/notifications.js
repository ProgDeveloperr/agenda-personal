(() => {
    'use strict';

    const API =
        '/api/notifications.php';

    const state = {
        csrfToken: '',
        open: false,
        loading: false,
    };


    const header =
        document.querySelector(
            '.app-header-inner'
        );


    if (!header) {
        return;
    }


    if (
        document.querySelector(
            '.notification-center-v3'
        )
    ) {
        return;
    }


    const root =
        document.createElement(
            'div'
        );

    root.className =
        'notification-center-v3';


    const button =
        document.createElement(
            'button'
        );

    button.type =
        'button';

    button.className =
        'notification-bell-v3';

    button.setAttribute(
        'aria-label',
        'Notificaciones'
    );

    button.setAttribute(
        'aria-haspopup',
        'dialog'
    );

    button.setAttribute(
        'aria-expanded',
        'false'
    );

    button.innerHTML = `
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
            ></path>
            <path
                d="M10 21h4"
            ></path>
        </svg>
        <span
            class="notification-badge-v3"
            hidden
        ></span>
    `;


    const badge =
        button.querySelector(
            '.notification-badge-v3'
        );


    const popover =
        document.createElement(
            'div'
        );

    popover.className =
        'notification-popover-v3';

    popover.hidden =
        true;

    popover.setAttribute(
        'role',
        'dialog'
    );

    popover.setAttribute(
        'aria-label',
        'Notificaciones'
    );


    const status =
        header.querySelector(
            '.app-header-status'
        );


    root.appendChild(
        button
    );

    root.appendChild(
        popover
    );


    if (status) {

        header.insertBefore(
            root,
            status
        );

    } else {

        header.appendChild(
            root
        );
    }


    function formatDate(
        value
    ) {

        const parts =
            String(value)
                .split('-');


        if (
            parts.length !== 3
        ) {
            return value;
        }


        return (
            parts[2]
            + '/'
            + parts[1]
            + '/'
            + parts[0]
        );
    }


    async function getState() {

        const response =
            await fetch(
                API,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept:
                            'application/json',
                    },
                }
            );


        if (!response.ok) {

            throw new Error(
                `HTTP ${response.status}`
            );
        }


        return response.json();
    }


    async function postAction(
        action,
        id = null
    ) {

        const body =
            new URLSearchParams();

        body.set(
            'action',
            action
        );

        body.set(
            'csrf_token',
            state.csrfToken
        );


        if (id !== null) {

            body.set(
                'id',
                String(id)
            );
        }


        const response =
            await fetch(
                API,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept:
                            'application/json',

                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body:
                        body.toString(),
                }
            );


        if (!response.ok) {

            throw new Error(
                `HTTP ${response.status}`
            );
        }


        return response.json();
    }


    function renderBadge(
        unread
    ) {

        const count =
            Number(unread) || 0;


        if (count <= 0) {

            badge.hidden =
                true;

            badge.textContent =
                '';

            button.classList.remove(
                'has-unread'
            );

            return;
        }


        badge.hidden =
            false;

        badge.textContent =
            count > 99
                ? '99+'
                : String(count);

        button.classList.add(
            'has-unread'
        );
    }


    function createEmpty() {

        const empty =
            document.createElement(
                'div'
            );

        empty.className =
            'notification-empty-v3';


        const icon =
            document.createElement(
                'div'
            );

        icon.textContent =
            '✓';


        const title =
            document.createElement(
                'strong'
            );

        title.textContent =
            'Todo al día';


        const copy =
            document.createElement(
                'span'
            );

        copy.textContent =
            'No tenés recordatorios activos.';


        empty.append(
            icon,
            title,
            copy
        );


        return empty;
    }


    function createItem(
        item
    ) {

        const wrapper =
            document.createElement(
                'article'
            );

        wrapper.className =
            'notification-item-v3';


        if (!item.leida) {

            wrapper.classList.add(
                'is-unread'
            );
        }


        wrapper.style.setProperty(
            '--notification-color',
            item.materia_color
            || '#5794ff'
        );


        const main =
            document.createElement(
                'button'
            );

        main.type =
            'button';

        main.className =
            'notification-item-main-v3';


        const dot =
            document.createElement(
                'span'
            );

        dot.className =
            'notification-item-dot-v3';


        const copy =
            document.createElement(
                'span'
            );

        copy.className =
            'notification-item-copy-v3';


        const eyebrow =
            document.createElement(
                'small'
            );

        eyebrow.textContent =
            (
                item.materia_codigo
                || item.materia_nombre
            )
            + ' · '
            + formatDate(
                item.fecha_objetivo
            );


        const title =
            document.createElement(
                'strong'
            );

        title.textContent =
            item.titulo;


        const message =
            document.createElement(
                'span'
            );

        message.textContent =
            item.mensaje;


        copy.append(
            eyebrow,
            title,
            message
        );


        main.append(
            dot,
            copy
        );


        main.addEventListener(
            'click',
            async () => {

                const target =
                    item.url;


                if (!item.leida) {

                    try {

                        const data =
                            await postAction(
                                'mark_read',
                                item.id
                            );


                        applyState(
                            data
                        );

                    } catch (error) {

                        console.error(
                            'No se pudo marcar la notificación.',
                            error
                        );
                    }
                }


                window.location.href =
                    target;
            }
        );


        const dismiss =
            document.createElement(
                'button'
            );

        dismiss.type =
            'button';

        dismiss.className =
            'notification-dismiss-v3';

        dismiss.setAttribute(
            'aria-label',
            'Descartar notificación'
        );

        dismiss.title =
            'Descartar';

        dismiss.textContent =
            '×';


        dismiss.addEventListener(
            'click',
            async (event) => {

                event.preventDefault();
                event.stopPropagation();


                dismiss.disabled =
                    true;


                try {

                    const data =
                        await postAction(
                            'dismiss',
                            item.id
                        );


                    applyState(
                        data
                    );

                } catch (error) {

                    dismiss.disabled =
                        false;

                    console.error(
                        'No se pudo descartar la notificación.',
                        error
                    );
                }
            }
        );


        wrapper.append(
            main,
            dismiss
        );


        return wrapper;
    }


    function renderPopover(
        data
    ) {

        popover.replaceChildren();


        const head =
            document.createElement(
                'div'
            );

        head.className =
            'notification-popover-head-v3';


        const heading =
            document.createElement(
                'div'
            );


        const kicker =
            document.createElement(
                'small'
            );

        kicker.textContent =
            'CENTRO DE AVISOS';


        const title =
            document.createElement(
                'strong'
            );

        title.textContent =
            'Notificaciones';


        heading.append(
            kicker,
            title
        );


        const readAll =
            document.createElement(
                'button'
            );

        readAll.type =
            'button';

        readAll.className =
            'notification-read-all-v3';

        readAll.textContent =
            'Marcar leídas';

        readAll.hidden =
            Number(
                data.unread_count
            ) <= 0;


        readAll.addEventListener(
            'click',
            async () => {

                readAll.disabled =
                    true;


                try {

                    const next =
                        await postAction(
                            'mark_all_read'
                        );


                    applyState(
                        next
                    );

                } catch (error) {

                    readAll.disabled =
                        false;

                    console.error(
                        'No se pudieron marcar las notificaciones.',
                        error
                    );
                }
            }
        );


        head.append(
            heading,
            readAll
        );


        const list =
            document.createElement(
                'div'
            );

        list.className =
            'notification-list-v3';


        const items =
            Array.isArray(
                data.notifications
            )
                ? data.notifications
                : [];


        if (items.length === 0) {

            list.appendChild(
                createEmpty()
            );

        } else {

            for (
                const item
                of items
            ) {

                list.appendChild(
                    createItem(
                        item
                    )
                );
            }
        }


        const footer =
            document.createElement(
                'div'
            );

        footer.className =
            'notification-popover-footer-v3';


        const active =
            Number(
                data.active_count
            ) || 0;


        const footerLabel =
            document.createElement(
                'span'
            );


        footerLabel.textContent =
            active === 1
                ? '1 notificación activa'
                : `${active} notificaciones activas`;


        if (
            active
            > items.length
        ) {

            footerLabel.textContent +=
                ` · mostrando ${items.length}`;
        }


        const centerLink =
            document.createElement(
                'a'
            );

        centerLink.className =
            'notification-center-link-v3';

        centerLink.href =
            '/notificaciones.php';

        centerLink.textContent =
            'Ver centro →';


        footer.append(
            footerLabel,
            centerLink
        );


        popover.append(
            head,
            list,
            footer
        );
    }


    function applyState(
        data
    ) {

        if (
            !data
            || data.ok !== true
        ) {
            return;
        }


        state.csrfToken =
            data.csrf_token
            || state.csrfToken;


        renderBadge(
            data.unread_count
        );


        renderPopover(
            data
        );
    }


    async function refresh() {

        if (state.loading) {
            return;
        }


        state.loading =
            true;


        try {

            const data =
                await getState();


            applyState(
                data
            );

        } catch (error) {

            console.error(
                'No se pudieron cargar las notificaciones.',
                error
            );

        } finally {

            state.loading =
                false;
        }
    }


    function setOpen(
        open
    ) {

        state.open =
            open;

        popover.hidden =
            !open;

        root.classList.toggle(
            'is-open',
            open
        );

        button.setAttribute(
            'aria-expanded',
            open
                ? 'true'
                : 'false'
        );


        if (open) {

            refresh();
        }
    }


    button.addEventListener(
        'click',
        (event) => {

            event.stopPropagation();

            setOpen(
                !state.open
            );
        }
    );


    popover.addEventListener(
        'click',
        (event) => {

            event.stopPropagation();
        }
    );


    document.addEventListener(
        'click',
        () => {

            if (state.open) {

                setOpen(
                    false
                );
            }
        }
    );


    document.addEventListener(
        'keydown',
        (event) => {

            if (
                event.key === 'Escape'
                && state.open
            ) {

                setOpen(
                    false
                );

                button.focus();
            }
        }
    );


    refresh();


    window.setInterval(
        refresh,
        60000
    );

})();
