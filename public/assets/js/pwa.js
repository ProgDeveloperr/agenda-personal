(() => {
    'use strict';


    /*
     * La PWA se activa exclusivamente bajo HTTPS.
     * El acceso LAN HTTP sigue funcionando sin SW.
     */
    if (
        window.location.protocol
        !== 'https:'
    ) {
        return;
    }


    if (
        !(
            'serviceWorker'
            in navigator
        )
    ) {
        return;
    }


    window.addEventListener(
        'load',
        async () => {

            try {

                const registration =
                    await navigator
                        .serviceWorker
                        .register(
                            '/sw.js',
                            {
                                scope:
                                    '/',

                                updateViaCache:
                                    'none',
                            }
                        );


                document
                    .documentElement
                    .classList
                    .add(
                        'pwa-service-worker-v3'
                    );


                window.dispatchEvent(
                    new CustomEvent(
                        'agenda:pwa-ready',
                        {
                            detail: {
                                scope:
                                    registration.scope,
                            },
                        }
                    )
                );


                registration.update()
                    .catch(
                        () => {}
                    );

            } catch (error) {

                console.warn(
                    'Agenda PWA: service worker no disponible.',
                    error
                );
            }
        }
    );

})();
