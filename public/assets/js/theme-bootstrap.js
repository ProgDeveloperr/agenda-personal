'use strict';

(() => {

    const STORAGE_KEY =
        'agenda-personal-theme';

    let theme =
        'light';


    try {

        const saved =
            window.localStorage
                .getItem(
                    STORAGE_KEY
                );


        if (
            saved === 'dark'
            || saved === 'light'
        ) {
            theme =
                saved;
        }

    } catch (_) {
        /*
         * Fail-safe:
         * Light sigue siendo el baseline.
         */
    }


    document
        .documentElement
        .setAttribute(
            'data-theme',
            theme
        );


    document
        .documentElement
        .style
        .colorScheme =
            theme;

})();
