(() => {
    'use strict';


    const form =
        document.querySelector(
            '[data-task-form]'
        );


    if (form) {

        const materia =
            form.querySelector(
                '[data-task-materia]'
            );


        const evento =
            form.querySelector(
                '[data-task-evento]'
            );


        const syncEventoMateria =
            () => {

                if (
                    !materia
                    || !evento
                    || evento.value === ''
                ) {
                    return;
                }


                const option =
                    evento.options[
                        evento.selectedIndex
                    ];


                const materiaId =
                    option
                        ?.dataset
                        ?.materiaId
                    || '';


                if (materiaId !== '') {

                    materia.value =
                        materiaId;
                }
            };


        evento?.addEventListener(
            'change',
            syncEventoMateria
        );


        materia?.addEventListener(
            'change',
            () => {

                if (
                    !evento
                    || evento.value === ''
                ) {
                    return;
                }


                const option =
                    evento.options[
                        evento.selectedIndex
                    ];


                const expected =
                    option
                        ?.dataset
                        ?.materiaId
                    || '';


                if (
                    expected !== ''
                    && materia.value
                        !== expected
                ) {

                    evento.value = '';
                }
            }
        );
    }


    document
        .querySelectorAll(
            '[data-task-confirm]'
        )
        .forEach(
            (button) => {

                button.addEventListener(
                    'click',
                    (event) => {

                        const message =
                            (
                                button.dataset
                                    .taskConfirm
                                || ''
                            ).trim();


                        if (
                            message !== ''
                            && !window.confirm(
                                message
                            )
                        ) {

                            event.preventDefault();
                        }
                    }
                );
            }
        );

})();



/* R4-D3 — Recurring Tasks UX */

(() => {

    'use strict';


    const form =
        document.querySelector(
            '[data-task-form]'
        );


    const frequency =
        form?.querySelector(
            '[data-task-frequency]'
        );


    const options =
        form?.querySelector(
            '[data-recurrence-options]'
        );


    const interval =
        form?.querySelector(
            '[data-task-interval]'
        );


    const endDate =
        form?.querySelector(
            '[data-task-end-date]'
        );


    const state =
        form?.querySelector(
            '[data-task-state]'
        );


    const firstDate =
        form?.querySelector(
            'input[name="fecha_limite"]'
        );


    if (
        !form
        || !frequency
        || !options
    ) {
        return;
    }


    const syncRecurrence = () => {

        const recurring =
            frequency.value !== 'UNA_VEZ';


        options.hidden =
            !recurring;


        if (interval) {

            interval.disabled =
                !recurring;

            interval.required =
                recurring;
        }


        if (endDate) {

            endDate.disabled =
                !recurring;
        }


        if (firstDate) {

            firstDate.required =
                recurring;
        }


        if (state) {

            if (recurring) {

                state.value =
                    'PENDIENTE';

                state.disabled =
                    true;

            } else {

                state.disabled =
                    false;
            }
        }
    };


    frequency.addEventListener(
        'change',
        syncRecurrence
    );


    syncRecurrence();

})();
