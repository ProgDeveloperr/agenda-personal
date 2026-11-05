(() => {
    'use strict';

    const mq = window.matchMedia(
        '(max-width: 760px)'
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
                'page-calendar-v3'
            )
        ) {
            return;
        }


        const boardScroll =
            document.querySelector(
                '.calendar-board-scroll-v3'
            );


        const sourceDays =
            Array.from(
                document.querySelectorAll(
                    '.calendar-days-v3 > .calendar-day-v3'
                )
            );


        if (
            !boardScroll
            || sourceDays.length < 28
        ) {
            return;
        }


        const monthTitle =
            document.querySelector(
                '.calendar-month-title-v3 h2'
            );


        const yearLabel =
            document.querySelector(
                '.calendar-month-title-v3 > span'
            );


        const monthName =
            monthTitle
                ? monthTitle.textContent.trim()
                : 'Mes';


        const yearText =
            yearLabel
                ? yearLabel.textContent.trim()
                : '';


        const realDays =
            sourceDays.filter(
                (day) =>
                    !day.classList.contains(
                        'calendar-day-empty-v3'
                    )
            );


        if (realDays.length === 0) {
            return;
        }


        /* ==================================
           SELECCION INICIAL
        ================================== */

        let selectedDay =
            realDays.find(
                (day) =>
                    day.classList.contains(
                        'calendar-day-today-v3'
                    )
            )
            || realDays.find(
                (day) =>
                    day.querySelector(
                        '.calendar-event-v3'
                    )
            )
            || realDays[0];


        /* ==================================
           ESTRUCTURA
        ================================== */

        const section =
            document.createElement(
                'section'
            );

        section.className =
            'calendar-mobile-v1';

        section.setAttribute(
            'aria-label',
            'Calendario mensual mobile'
        );


        const head =
            document.createElement(
                'header'
            );

        head.className =
            'calendar-mobile-head-v1';


        const headCopy =
            document.createElement(
                'div'
            );

        headCopy.className =
            'calendar-mobile-head-copy-v1';


        const kicker =
            document.createElement(
                'span'
            );

        kicker.textContent =
            'Vista mobile';


        const headTitle =
            document.createElement(
                'strong'
            );

        headTitle.textContent =
            'Calendario mensual';


        const headSubtitle =
            document.createElement(
                'small'
            );

        headSubtitle.textContent =
            'Tocá un día para ver su agenda.';


        headCopy.append(
            kicker,
            headTitle,
            headSubtitle
        );


        const headState =
            document.createElement(
                'span'
            );

        headState.className =
            'calendar-mobile-head-state-v1';

        headState.textContent =
            `${monthName} ${yearText}`.trim();


        head.append(
            headCopy,
            headState
        );


        /* ==================================
           SEMANA
        ================================== */

        const weekdays =
            document.createElement(
                'div'
            );

        weekdays.className =
            'calendar-mobile-weekdays-v1';


        const weekdayLabels = [
            'LU',
            'MA',
            'MI',
            'JU',
            'VI',
            'SÁ',
            'DO'
        ];


        const weekdayLong = [
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
            'Sábado',
            'Domingo'
        ];


        for (const label of weekdayLabels) {

            const span =
                document.createElement(
                    'span'
                );

            span.textContent = label;

            weekdays.append(span);
        }


        /* ==================================
           GRID
        ================================== */

        const grid =
            document.createElement(
                'div'
            );

        grid.className =
            'calendar-mobile-grid-v1';


        /* ==================================
           AGENDA
        ================================== */

        const agenda =
            document.createElement(
                'div'
            );

        agenda.className =
            'calendar-mobile-agenda-v1';


        const agendaHead =
            document.createElement(
                'div'
            );

        agendaHead.className =
            'calendar-mobile-agenda-head-v1';


        const agendaTitle =
            document.createElement(
                'div'
            );

        agendaTitle.className =
            'calendar-mobile-agenda-title-v1';


        const agendaKicker =
            document.createElement(
                'span'
            );

        agendaKicker.textContent =
            'Agenda del día';


        const agendaDate =
            document.createElement(
                'strong'
            );


        agendaTitle.append(
            agendaKicker,
            agendaDate
        );


        const agendaCount =
            document.createElement(
                'span'
            );

        agendaCount.className =
            'calendar-mobile-agenda-count-v1';


        agendaHead.append(
            agendaTitle,
            agendaCount
        );


        const eventsContainer =
            document.createElement(
                'div'
            );

        eventsContainer.className =
            'calendar-mobile-events-v1';


        agenda.append(
            agendaHead,
            eventsContainer
        );


        section.append(
            head,
            weekdays,
            grid,
            agenda
        );


        boardScroll.insertAdjacentElement(
            'beforebegin',
            section
        );


        /* ==================================
           HELPERS
        ================================== */

        function dayNumber(day) {

            const element =
                day.querySelector(
                    '.calendar-day-number-v3'
                );


            if (!element) {
                return '';
            }


            const match =
                element.textContent.match(
                    /\d+/
                );


            return match
                ? match[0]
                : element.textContent.trim();
        }


        function eventsOf(day) {

            return Array.from(
                day.querySelectorAll(
                    '.calendar-event-v3'
                )
            );
        }


        function sourceIndex(day) {

            return sourceDays.indexOf(day);
        }


        function weekdayFor(day) {

            const index =
                sourceIndex(day);


            if (index < 0) {
                return '';
            }


            return weekdayLong[
                index % 7
            ];
        }


        function eventColor(event) {

            const stripe =
                event.querySelector(
                    '.calendar-event-stripe-v3'
                );


            if (!stripe) {
                return '#68829a';
            }


            const color =
                window
                    .getComputedStyle(stripe)
                    .backgroundColor;


            return color
                && color !== 'rgba(0, 0, 0, 0)'
                ? color
                : '#68829a';
        }


        /* ==================================
           AGENDA DEL DIA
        ================================== */

        function renderAgenda() {

            const number =
                dayNumber(selectedDay);


            const weekday =
                weekdayFor(selectedDay);


            const events =
                eventsOf(selectedDay);


            agendaDate.textContent =
                `${weekday} ${number} · ${monthName}`;


            agendaCount.textContent =
                events.length === 1
                    ? '1 evento'
                    : `${events.length} eventos`;


            eventsContainer.replaceChildren();


            if (events.length === 0) {

                const empty =
                    document.createElement(
                        'div'
                    );

                empty.className =
                    'calendar-mobile-empty-v1';


                const icon =
                    document.createElement(
                        'span'
                    );

                icon.textContent = '✓';


                const title =
                    document.createElement(
                        'strong'
                    );

                title.textContent =
                    'Sin eventos para este día';


                const copy =
                    document.createElement(
                        'p'
                    );

                copy.textContent =
                    'Podés elegir otra fecha o crear un evento con el botón +.';


                empty.append(
                    icon,
                    title,
                    copy
                );


                eventsContainer.append(
                    empty
                );

                return;
            }


            for (const sourceEvent of events) {

                const clone =
                    sourceEvent.cloneNode(
                        true
                    );


                clone.classList.add(
                    'calendar-mobile-event-v1'
                );


                eventsContainer.append(
                    clone
                );
            }
        }


        /* ==================================
           GRID
        ================================== */

        function renderGrid() {

            grid.replaceChildren();


            for (
                const sourceDay
                of sourceDays
            ) {

                if (
                    sourceDay.classList.contains(
                        'calendar-day-empty-v3'
                    )
                ) {

                    const placeholder =
                        document.createElement(
                            'div'
                        );

                    placeholder.className =
                        'calendar-mobile-day-empty-v1';

                    placeholder.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    grid.append(
                        placeholder
                    );

                    continue;
                }


                const number =
                    dayNumber(sourceDay);


                const events =
                    eventsOf(sourceDay);


                const button =
                    document.createElement(
                        'button'
                    );


                button.type = 'button';

                button.className =
                    'calendar-mobile-day-v1';


                if (events.length > 0) {

                    button.classList.add(
                        'has-events'
                    );
                }


                if (
                    sourceDay.classList.contains(
                        'calendar-day-today-v3'
                    )
                ) {

                    button.classList.add(
                        'is-today'
                    );
                }


                if (
                    sourceDay === selectedDay
                ) {

                    button.classList.add(
                        'is-selected'
                    );
                }


                const numberElement =
                    document.createElement(
                        'strong'
                    );

                numberElement.textContent =
                    number;


                const dots =
                    document.createElement(
                        'span'
                    );

                dots.className =
                    'calendar-mobile-dots-v1';


                for (
                    const event
                    of events.slice(0, 3)
                ) {

                    const dot =
                        document.createElement(
                            'i'
                        );

                    dot.className =
                        'calendar-mobile-dot-v1';

                    dot.style.backgroundColor =
                        eventColor(event);

                    dots.append(dot);
                }


                if (events.length > 3) {

                    const more =
                        document.createElement(
                            'span'
                        );

                    more.className =
                        'calendar-mobile-more-v1';

                    more.textContent =
                        `+${events.length - 3}`;

                    dots.append(more);
                }


                const weekday =
                    weekdayFor(sourceDay);


                button.setAttribute(
                    'aria-label',
                    `${weekday} ${number} de ${monthName}, ${
                        events.length
                    } ${
                        events.length === 1
                            ? 'evento'
                            : 'eventos'
                    }`
                );


                button.append(
                    numberElement,
                    dots
                );


                button.addEventListener(
                    'click',
                    () => {

                        selectedDay =
                            sourceDay;

                        renderGrid();
                        renderAgenda();
                    }
                );


                grid.append(button);
            }
        }


        renderGrid();
        renderAgenda();


        /*
         * Solo ahora ocultamos la grilla
         * horizontal original.
         */

        body.classList.add(
            'calendar-mobile-ready-v1'
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
