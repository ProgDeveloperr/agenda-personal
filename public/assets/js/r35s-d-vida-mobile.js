(() => {
    'use strict';

    const mq =
        window.matchMedia(
            '(width < 1450px), (height < 897px)'
        );

    let initialized = false;


    function init() {

        if (
            initialized
            || !mq.matches
        ) {
            return;
        }


        const body = document.body;

        if (
            !body
            || !body.classList.contains(
                'page-life-v3'
            )
        ) {
            return;
        }


        const matrix =
            document.querySelector(
                '.life-matrix-scroll-v3'
            );


        if (!matrix) {
            return;
        }


        const cells =
            Array.from(
                matrix.querySelectorAll(
                    '.life-cell-v3'
                )
            );


        /*
         * Fail-open.
         * Esperamos 1080, pero solo exigimos
         * que sea divisible por 12.
         */

        if (
            cells.length < 12
            || cells.length % 12 !== 0
        ) {
            return;
        }


        const totalYears =
            cells.length / 12;


        const currentIndex =
            cells.findIndex(
                (cell) =>
                    cell.classList.contains(
                        'vida-celda-actual'
                    )
            );


        let selectedIndex =
            currentIndex >= 0
                ? currentIndex
                : 0;


        let activeYear =
            Math.floor(
                selectedIndex / 12
            );


        /* ----------------------------------
           ESTRUCTURA
        ---------------------------------- */

        const section =
            document.createElement(
                'section'
            );

        section.className =
            'life-mobile-year-v1';

        section.setAttribute(
            'aria-label',
            'Calendario de vida por año'
        );


        const head =
            document.createElement(
                'header'
            );

        head.className =
            'life-mobile-head-v1';


        const kicker =
            document.createElement(
                'span'
            );

        kicker.textContent =
            'Tu vida';


        const title =
            document.createElement(
                'h3'
            );

        title.textContent =
            'Explorá año por año';


        const description =
            document.createElement(
                'p'
            );

        description.textContent =
            'Doce meses por vista. Cambiá de año y tocá cualquier mes para trabajar con él.';


        head.append(
            kicker,
            title,
            description
        );


        const nav =
            document.createElement(
                'div'
            );

        nav.className =
            'life-mobile-nav-v1';


        const previous =
            document.createElement(
                'button'
            );

        previous.type = 'button';

        previous.className =
            'life-mobile-arrow-v1';

        previous.setAttribute(
            'aria-label',
            'Año anterior'
        );

        previous.textContent = '‹';


        const selectWrap =
            document.createElement(
                'div'
            );

        selectWrap.className =
            'life-mobile-select-wrap-v1';


        const select =
            document.createElement(
                'select'
            );

        select.className =
            'life-mobile-select-v1';

        select.setAttribute(
            'aria-label',
            'Seleccionar año'
        );


        for (
            let year = 0;
            year < totalYears;
            year += 1
        ) {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(year);

            option.textContent =
                `Año ${year + 1} de ${totalYears}`;

            select.append(option);
        }


        selectWrap.append(select);


        const next =
            document.createElement(
                'button'
            );

        next.type = 'button';

        next.className =
            'life-mobile-arrow-v1';

        next.setAttribute(
            'aria-label',
            'Año siguiente'
        );

        next.textContent = '›';


        nav.append(
            previous,
            selectWrap,
            next
        );


        const summary =
            document.createElement(
                'div'
            );

        summary.className =
            'life-mobile-summary-v1';


        const summaryTitle =
            document.createElement(
                'strong'
            );


        const summaryMeta =
            document.createElement(
                'span'
            );


        summary.append(
            summaryTitle,
            summaryMeta
        );


        const grid =
            document.createElement(
                'div'
            );

        grid.className =
            'life-mobile-grid-v1';


        const legend =
            document.createElement(
                'div'
            );

        legend.className =
            'life-mobile-legend-v1';

        legend.innerHTML = `
            <span>
                <i class="life-mobile-legend-empty-v1"></i>
                Vacío
            </span>

            <span>
                <i class="life-mobile-legend-marked-v1"></i>
                Marcado
            </span>

            <span>
                <i class="life-mobile-legend-current-v1"></i>
                Actual
            </span>
        `;


        section.append(
            head,
            nav,
            summary,
            grid,
            legend
        );


        matrix.insertAdjacentElement(
            'beforebegin',
            section
        );


        /* ----------------------------------
           HELPERS
        ---------------------------------- */

        function statusOf(cell) {

            const isCurrent =
                cell.classList.contains(
                    'vida-celda-actual'
                );


            const isMarked =
                cell.classList.contains(
                    'vida-celda-marcada'
                );


            if (
                isCurrent
                && isMarked
            ) {
                return 'Marcado · Actual';
            }


            if (isCurrent) {
                return 'Actual';
            }


            if (isMarked) {
                return 'Marcado';
            }


            return 'Vacío';
        }


        function render() {

            activeYear =
                Math.max(
                    0,
                    Math.min(
                        totalYears - 1,
                        activeYear
                    )
                );


            select.value =
                String(activeYear);


            previous.disabled =
                activeYear === 0;


            next.disabled =
                activeYear
                === totalYears - 1;


            summaryTitle.textContent =
                `Año ${activeYear + 1}`;


            const start =
                activeYear * 12;


            const end =
                Math.min(
                    start + 12,
                    cells.length
                );


            const markedCount =
                cells
                    .slice(start, end)
                    .filter(
                        (cell) =>
                            cell.classList.contains(
                                'vida-celda-marcada'
                            )
                    )
                    .length;


            summaryMeta.textContent =
                `${markedCount} de 12 marcados`;


            grid.replaceChildren();


            for (
                let index = start;
                index < end;
                index += 1
            ) {

                const source =
                    cells[index];


                const month =
                    index - start + 1;


                const button =
                    document.createElement(
                        'button'
                    );


                button.type = 'button';

                button.className =
                    'life-mobile-month-v1';


                if (
                    source.classList.contains(
                        'vida-celda-marcada'
                    )
                ) {
                    button.classList.add(
                        'is-marked'
                    );
                }


                if (
                    source.classList.contains(
                        'vida-celda-actual'
                    )
                ) {
                    button.classList.add(
                        'is-current'
                    );
                }


                if (index === selectedIndex) {
                    button.classList.add(
                        'is-selected'
                    );
                }


                const sourceLabel =
                    source.getAttribute(
                        'aria-label'
                    )
                    || source.title
                    || `Mes ${month}`;


                button.setAttribute(
                    'aria-label',
                    sourceLabel
                );


                const monthLabel =
                    document.createElement(
                        'span'
                    );

                monthLabel.textContent =
                    `Mes ${String(month).padStart(2, '0')}`;


                const status =
                    document.createElement(
                        'small'
                    );

                status.textContent =
                    statusOf(source);


                button.append(
                    monthLabel,
                    status
                );


                button.addEventListener(
                    'click',
                    () => {

                        selectedIndex =
                            index;


                        /*
                         * No duplicamos lógica.
                         * El click real sigue siendo
                         * procesado por vida.js.
                         */

                        source.click();


                        window.requestAnimationFrame(
                            render
                        );
                    }
                );


                grid.append(button);
            }
        }


        /* ----------------------------------
           NAVEGACION
        ---------------------------------- */

        previous.addEventListener(
            'click',
            () => {

                activeYear -= 1;

                render();
            }
        );


        next.addEventListener(
            'click',
            () => {

                activeYear += 1;

                render();
            }
        );


        select.addEventListener(
            'change',
            () => {

                activeYear =
                    Number(select.value);

                render();
            }
        );


        /*
         * Mantiene la representación mobile
         * sincronizada cuando vida.js modifica
         * clase o aria-pressed.
         */

        let frame = null;


        const observer =
            new MutationObserver(
                () => {

                    if (frame !== null) {
                        return;
                    }


                    frame =
                        window.requestAnimationFrame(
                            () => {

                                frame = null;

                                render();
                            }
                        );
                }
            );


        observer.observe(
            matrix,
            {
                subtree: true,
                attributes: true,
                attributeFilter: [
                    'class',
                    'aria-pressed'
                ]
            }
        );


        const detail =
            document.querySelector(
                '#vidaCellDetail'
            );


        if (
            detail
            && detail.textContent
                .trim()
                .startsWith(
                    'Pasá el cursor'
                )
        ) {
            detail.textContent =
                'Tocá un mes para ver su período.';
        }


        render();


        /*
         * Solo después de construir todo
         * correctamente escondemos la matriz.
         */

        body.classList.add(
            'vida-mobile-ready-v1'
        );


        initialized = true;
    }


    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            init,
            {
                once: true
            }
        );

    } else {

        init();
    }


    if (
        typeof mq.addEventListener
        === 'function'
    ) {

        mq.addEventListener(
            'change',
            (event) => {

                if (event.matches) {
                    init();
                }
            }
        );
    }

})();
