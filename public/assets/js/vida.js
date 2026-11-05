(() => {
    'use strict';

    const root =
        document.querySelector(
            '[data-vida-root]'
        );

    if (!root) {
        return;
    }


    const csrf =
        root.dataset.csrf || '';

    const currentIndex =
        root.dataset.currentIndex || '';


    const cells =
        Array.from(
            root.querySelectorAll(
                '[data-vida-indice]'
            )
        );


    const marcadosNode =
        document.getElementById(
            'vidaMarcados'
        );

    const porcentajeNode =
        document.getElementById(
            'vidaPorcentaje'
        );

    const restantesNode =
        document.getElementById(
            'vidaRestantes'
        );

    const progressBar =
        document.getElementById(
            'vidaProgressBar'
        );

    const feedback =
        document.getElementById(
            'vidaFeedback'
        );

    const inspector =
        document.getElementById(
            'vidaCellDetail'
        );


    const selectedTitle =
        document.getElementById(
            'vidaSelectedTitle'
        );

    const selectedDetail =
        document.getElementById(
            'vidaSelectedDetail'
        );

    const selectedState =
        document.getElementById(
            'vidaSelectedState'
        );

    const selectedIndexNode =
        document.getElementById(
            'vidaSelectedIndex'
        );

    const selectedLevelNode =
        document.getElementById(
            'vidaSelectedLevel'
        );

    const selectedNoteState =
        document.getElementById(
            'vidaSelectedNoteState'
        );

    const noteInput =
        document.getElementById(
            'vidaNoteInput'
        );

    const noteCounter =
        document.getElementById(
            'vidaNoteCounter'
        );

    const toggleSelected =
        document.getElementById(
            'vidaToggleSelected'
        );

    const saveNote =
        document.getElementById(
            'vidaSaveNote'
        );

    const clearNote =
        document.getElementById(
            'vidaClearNote'
        );

    const notesCount =
        document.getElementById(
            'vidaNotesCount'
        );

    const notesSearch =
        document.getElementById(
            'vidaNotesSearch'
        );

    const notesList =
        document.getElementById(
            'vidaNotesList'
        );


    let selectedCell = null;
    let selectedData = null;
    let notesCache = [];
    let feedbackTimer = null;


    const showFeedback = (
        message,
        error = false
    ) => {

        if (!feedback) {
            return;
        }


        if (feedbackTimer) {
            window.clearTimeout(
                feedbackTimer
            );
        }


        feedback.textContent =
            message;


        feedback.classList.toggle(
            'vida-feedback-error',
            error
        );


        feedback.hidden =
            false;


        feedback.style.opacity =
            '1';


        feedbackTimer =
            window.setTimeout(
                () => {

                    feedback.style.opacity =
                        '';

                },
                1900
            );
    };


    const api = async (
        action,
        extra = {}
    ) => {

        const body =
            new URLSearchParams();


        body.set(
            'accion',
            action
        );

        body.set(
            'csrf_token',
            csrf
        );


        for (
            const [key, value]
            of Object.entries(extra)
        ) {

            body.set(
                key,
                String(value)
            );
        }


        const response =
            await fetch(
                '/api/vida.php',
                {
                    method: 'POST',

                    credentials:
                        'same-origin',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                    },

                    body:
                        body.toString(),
                }
            );


        let data;


        try {

            data =
                await response.json();

        } catch {

            throw new Error(
                'La respuesta del servidor no es válida.'
            );
        }


        if (
            !response.ok
            || data.ok !== true
        ) {

            throw new Error(
                data.error
                || 'No se pudo completar la operación.'
            );
        }


        return data;
    };


    const updateInspector = (
        button
    ) => {

        if (!inspector) {
            return;
        }


        const detail =
            button.dataset.vidaDetalle
            || button.title
            || '';


        if (detail !== '') {

            inspector.textContent =
                detail;
        }
    };


    const updateMetrics = (
        data
    ) => {

        if (
            marcadosNode
            && data.marcados !== undefined
        ) {

            marcadosNode.textContent =
                String(
                    data.marcados
                );
        }


        if (
            restantesNode
            && data.marcados !== undefined
        ) {

            restantesNode.textContent =
                String(
                    Math.max(
                        0,
                        1080
                        - Number(
                            data.marcados
                        )
                    )
                );
        }


        if (
            porcentajeNode
            && data.porcentaje !== undefined
        ) {

            porcentajeNode.textContent =
                String(
                    data.porcentaje
                )
                .replace(
                    '.',
                    ','
                )
                + ' %';
        }


        if (
            progressBar
            && data.porcentaje !== undefined
        ) {

            progressBar.style.width =
                String(
                    data.porcentaje
                )
                + '%';


            const track =
                progressBar.parentElement;


            if (track) {

                track.setAttribute(
                    'aria-valuenow',
                    String(
                        data.porcentaje
                    )
                );
            }
        }
    };


    const updateCounter = () => {

        if (
            !noteInput
            || !noteCounter
        ) {
            return;
        }


        noteCounter.textContent =
            `${noteInput.value.length} / 500`;
    };


    const updatePanel = (
        button,
        data
    ) => {

        selectedCell =
            button;

        selectedData =
            data;


        cells.forEach(
            (cell) => {

                cell.classList.toggle(
                    'vida-celda-seleccionada-v3',
                    cell === button
                );
            }
        );


        const index =
            Number(
                button.dataset.vidaIndice
            );


        const marked =
            Number(
                data.nivel
            ) > 0;


        const hasNote =
            Boolean(
                data.has_note
            );


        if (selectedTitle) {

            selectedTitle.textContent =
                `Mes #${index + 1}`;
        }


        if (selectedDetail) {

            selectedDetail.textContent =
                button.dataset.vidaDetalle
                || button.title
                || 'Período seleccionado';
        }


        if (selectedIndexNode) {

            selectedIndexNode.textContent =
                `#${index + 1}`;
        }


        if (selectedLevelNode) {

            selectedLevelNode.textContent =
                marked
                    ? 'Marcado'
                    : 'Sin marcar';
        }


        if (selectedNoteState) {

            selectedNoteState.textContent =
                hasNote
                    ? 'Con hito'
                    : 'Sin nota';
        }


        if (selectedState) {

            selectedState.textContent =
                marked
                    ? 'Marcado'
                    : 'Sin marcar';


            selectedState.classList.toggle(
                'is-marked',
                marked
            );
        }


        if (noteInput) {

            noteInput.disabled =
                false;

            noteInput.value =
                data.nota || '';

            updateCounter();
        }


        if (toggleSelected) {

            toggleSelected.disabled =
                false;

            toggleSelected.textContent =
                marked
                    ? 'Desmarcar mes'
                    : 'Marcar mes';
        }


        if (saveNote) {

            saveNote.disabled =
                false;
        }


        if (clearNote) {

            clearNote.disabled =
                !hasNote;
        }


        button.classList.toggle(
            'vida-celda-marcada',
            marked
        );


        button.classList.toggle(
            'vida-celda-con-nota-v3',
            hasNote
        );


        button.setAttribute(
            'aria-pressed',
            marked
                ? 'true'
                : 'false'
        );


        updateInspector(
            button
        );
    };


    const selectCell = async (
        button,
        scroll = false
    ) => {

        const index =
            button.dataset.vidaIndice;


        if (
            index === undefined
            || index === ''
        ) {
            return;
        }


        try {

            const data =
                await api(
                    'detail',
                    {
                        indice:
                            index,
                    }
                );


            updatePanel(
                button,
                data
            );


            if (scroll) {

                button.scrollIntoView(
                    {
                        behavior:
                            'smooth',

                        block:
                            'center',

                        inline:
                            'center',
                    }
                );
            }


        } catch (error) {

            showFeedback(
                error instanceof Error
                    ? error.message
                    : 'No se pudo cargar el mes.',
                true
            );
        }
    };


    const monthLabel = (
        index
    ) => {

        const cell =
            cells.find(
                (item) =>
                    Number(
                        item.dataset.vidaIndice
                    )
                    === Number(index)
            );


        if (!cell) {

            return (
                `Mes #${Number(index) + 1}`
            );
        }


        return (
            cell.dataset.vidaDetalle
            || cell.title
            || `Mes #${Number(index) + 1}`
        );
    };


    const renderNotes = () => {

        if (
            !notesList
            || !notesCount
        ) {
            return;
        }


        const query =
            (
                notesSearch
                    ? notesSearch.value
                    : ''
            )
            .trim()
            .toLocaleLowerCase(
                'es'
            );


        const filtered =
            notesCache.filter(
                (item) => {

                    const searchable =
                        (
                            `mes ${Number(item.indice_mes) + 1} `
                            + monthLabel(
                                item.indice_mes
                            )
                            + ' '
                            + (
                                item.nota
                                || ''
                            )
                        )
                        .toLocaleLowerCase(
                            'es'
                        );


                    return (
                        query === ''
                        || searchable.includes(
                            query
                        )
                    );
                }
            );


        notesCount.textContent =
            String(
                notesCache.length
            );


        notesList.replaceChildren();


        if (
            filtered.length
            === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'life-notes-empty-v3';


            const symbol =
                document.createElement(
                    'span'
                );

            symbol.textContent =
                notesCache.length === 0
                    ? '◇'
                    : '⌕';


            const title =
                document.createElement(
                    'strong'
                );

            title.textContent =
                notesCache.length === 0
                    ? 'Sin hitos todavía'
                    : 'Sin coincidencias';


            const copy =
                document.createElement(
                    'small'
                );

            copy.textContent =
                notesCache.length === 0
                    ? 'Las notas que guardes aparecerán acá.'
                    : 'Probá con otro texto o número de mes.';


            empty.append(
                symbol,
                title,
                copy
            );


            notesList.appendChild(
                empty
            );


            return;
        }


        for (
            const item
            of filtered
        ) {

            const button =
                document.createElement(
                    'button'
                );

            button.type =
                'button';

            button.className =
                'life-note-index-item-v3';


            const meta =
                document.createElement(
                    'span'
                );

            meta.className =
                'life-note-index-meta-v3';


            const month =
                document.createElement(
                    'strong'
                );

            month.textContent =
                `Mes #${Number(item.indice_mes) + 1}`;


            const level =
                document.createElement(
                    'small'
                );

            level.textContent =
                Number(item.nivel) > 0
                    ? 'Marcado'
                    : 'Sin marcar';


            meta.append(
                month,
                level
            );


            const note =
                document.createElement(
                    'span'
                );

            note.className =
                'life-note-index-copy-v3';

            note.textContent =
                item.nota || '';


            const arrow =
                document.createElement(
                    'i'
                );

            arrow.textContent =
                '→';


            button.append(
                meta,
                note,
                arrow
            );


            button.addEventListener(
                'click',
                () => {

                    const cell =
                        cells.find(
                            (candidate) =>
                                Number(
                                    candidate
                                        .dataset
                                        .vidaIndice
                                )
                                === Number(
                                    item.indice_mes
                                )
                        );


                    if (cell) {

                        selectCell(
                            cell,
                            true
                        );
                    }
                }
            );


            notesList.appendChild(
                button
            );
        }
    };


    const loadNotes = async () => {

        try {

            const data =
                await api(
                    'list_notes'
                );


            notesCache =
                Array.isArray(
                    data.notes
                )
                    ? data.notes
                    : [];


            const indices =
                new Set(
                    notesCache.map(
                        (item) =>
                            String(
                                item.indice_mes
                            )
                    )
                );


            cells.forEach(
                (cell) => {

                    cell.classList.toggle(
                        'vida-celda-con-nota-v3',
                        indices.has(
                            String(
                                cell.dataset.vidaIndice
                            )
                        )
                    );
                }
            );


            renderNotes();


        } catch (error) {

            showFeedback(
                error instanceof Error
                    ? error.message
                    : 'No se pudieron cargar los hitos.',
                true
            );
        }
    };


    let gridToggleBusy = false;


    cells.forEach(
        (button) => {

            button.addEventListener(
                'mouseenter',
                () => {

                    updateInspector(
                        button
                    );
                }
            );


            button.addEventListener(
                'focus',
                () => {

                    updateInspector(
                        button
                    );
                }
            );


            /*
             * Clic directo: alternar la marca manual.
             * La seleccion tambien actualiza el panel.
             * La marca del mes actual es independiente.
             */
            button.addEventListener(
                'click',
                async (event) => {

                    event.preventDefault();

                    if (button.disabled || gridToggleBusy) {
                        return;
                    }

                    const indice =
                        button.dataset.vidaIndice;

                    if (
                        indice === undefined
                        || indice === ''
                    ) {
                        return;
                    }

                    gridToggleBusy = true;
                    button.disabled = true;

                    try {

                        const data =
                            await api(
                                'toggle',
                                { indice }
                            );

                        updateMetrics(data);

                        updatePanel(
                            button,
                            data
                        );

                        showFeedback(
                            Number(data.nivel) > 0
                                ? 'Mes marcado'
                                : 'Mes desmarcado'
                        );

                        try {
                            await loadNotes();
                        } catch {
                            // La marca ya fue guardada.
                            // No repetir el toggle.
                        }

                    } catch (error) {

                        showFeedback(
                            error instanceof Error
                                ? error.message
                                : 'No se pudo guardar.',
                            true
                        );

                    } finally {

                        button.disabled = false;
                        gridToggleBusy = false;

                    }
                }
            );
        }
    );


    if (noteInput) {

        noteInput.addEventListener(
            'input',
            updateCounter
        );
    }


    if (notesSearch) {

        notesSearch.addEventListener(
            'input',
            renderNotes
        );
    }


    if (toggleSelected) {

        toggleSelected.addEventListener(
            'click',
            async () => {

                if (!selectedCell) {
                    return;
                }


                toggleSelected.disabled =
                    true;


                try {

                    const data =
                        await api(
                            'toggle',
                            {
                                indice:
                                    selectedCell
                                        .dataset
                                        .vidaIndice,
                            }
                        );


                    updateMetrics(
                        data
                    );


                    updatePanel(
                        selectedCell,
                        data
                    );


                    await loadNotes();


                    showFeedback(
                        Number(data.nivel) > 0
                            ? 'Mes marcado'
                            : 'Mes desmarcado'
                    );


                } catch (error) {

                    showFeedback(
                        error instanceof Error
                            ? error.message
                            : 'No se pudo guardar.',
                        true
                    );


                } finally {

                    toggleSelected.disabled =
                        false;
                }
            }
        );
    }


    if (saveNote) {

        saveNote.addEventListener(
            'click',
            async () => {

                if (
                    !selectedCell
                    || !noteInput
                ) {
                    return;
                }


                saveNote.disabled =
                    true;


                try {

                    const data =
                        await api(
                            'save_note',
                            {
                                indice:
                                    selectedCell
                                        .dataset
                                        .vidaIndice,

                                nota:
                                    noteInput.value,
                            }
                        );


                    updatePanel(
                        selectedCell,
                        data
                    );


                    await loadNotes();


                    showFeedback(
                        data.has_note
                            ? 'Hito guardado'
                            : 'Nota eliminada'
                    );


                } catch (error) {

                    showFeedback(
                        error instanceof Error
                            ? error.message
                            : 'No se pudo guardar la nota.',
                        true
                    );


                } finally {

                    saveNote.disabled =
                        false;
                }
            }
        );
    }


    if (clearNote) {

        clearNote.addEventListener(
            'click',
            async () => {

                if (
                    !selectedCell
                    || !noteInput
                ) {
                    return;
                }


                clearNote.disabled =
                    true;


                try {

                    const data =
                        await api(
                            'save_note',
                            {
                                indice:
                                    selectedCell
                                        .dataset
                                        .vidaIndice,

                                nota:
                                    '',
                            }
                        );


                    updatePanel(
                        selectedCell,
                        data
                    );


                    await loadNotes();


                    showFeedback(
                        'Nota eliminada'
                    );


                } catch (error) {

                    showFeedback(
                        error instanceof Error
                            ? error.message
                            : 'No se pudo borrar la nota.',
                        true
                    );


                } finally {

                    if (
                        selectedData
                        && selectedData.has_note
                    ) {

                        clearNote.disabled =
                            false;
                    }
                }
            }
        );
    }


    loadNotes();


    if (currentIndex !== '') {

        const currentCell =
            cells.find(
                (cell) =>
                    String(
                        cell.dataset.vidaIndice
                    )
                    === String(
                        currentIndex
                    )
            );


        if (currentCell) {

            selectCell(
                currentCell
            );
        }
    }

})();
