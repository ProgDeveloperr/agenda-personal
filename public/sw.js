'use strict';


const CACHE_VERSION =
    'agenda-static-v1';


const CORE_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/icons/agenda-personal.svg',
    '/assets/icons/icon-180.png',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
];


self.addEventListener(
    'install',
    (event) => {

        event.waitUntil(
            caches
                .open(
                    CACHE_VERSION
                )
                .then(
                    (cache) =>
                        cache.addAll(
                            CORE_ASSETS
                        )
                )
                .then(
                    () =>
                        self.skipWaiting()
                )
        );
    }
);


self.addEventListener(
    'activate',
    (event) => {

        event.waitUntil(
            caches
                .keys()
                .then(
                    (keys) =>
                        Promise.all(
                            keys
                                .filter(
                                    (key) =>
                                        key.startsWith(
                                            'agenda-static-'
                                        )
                                        && key
                                            !== CACHE_VERSION
                                )
                                .map(
                                    (key) =>
                                        caches.delete(
                                            key
                                        )
                                )
                        )
                )
                .then(
                    () =>
                        self.clients.claim()
                )
        );
    }
);


async function staticCacheFirst(
    request
) {

    const cached =
        await caches.match(
            request
        );


    if (cached) {
        return cached;
    }


    const response =
        await fetch(
            request
        );


    if (
        response
        && response.ok
        && response.type
            === 'basic'
    ) {

        const cache =
            await caches.open(
                CACHE_VERSION
            );


        await cache.put(
            request,
            response.clone()
        );
    }


    return response;
}


self.addEventListener(
    'fetch',
    (event) => {

        const request =
            event.request;


        /*
         * Nunca interceptar mutaciones.
         */
        if (
            request.method
            !== 'GET'
        ) {
            return;
        }


        const url =
            new URL(
                request.url
            );


        /*
         * Nunca interceptar otros orígenes.
         */
        if (
            url.origin
            !== self.location.origin
        ) {
            return;
        }


        /*
         * APIs siempre network-only.
         */
        if (
            url.pathname.startsWith(
                '/api/'
            )
        ) {
            return;
        }


        /*
         * Navegaciones PHP/HTML:
         *
         * - nunca quedan persistidas en Cache Storage;
         * - se consulta siempre al servidor;
         * - offline sólo devuelve una página estática
         *   sin datos del usuario.
         */
        if (
            request.mode
            === 'navigate'
        ) {

            event.respondWith(
                fetch(
                    request
                )
                    .catch(
                        () =>
                            caches.match(
                                '/offline.html'
                            )
                    )
            );

            return;
        }


        /*
         * Sólo recursos estáticos públicos.
         */
        if (
            url.pathname.startsWith(
                '/assets/'
            )
            || url.pathname
                === '/manifest.webmanifest'
            || url.pathname
                === '/offline.html'
        ) {

            event.respondWith(
                staticCacheFirst(
                    request
                )
            );
        }

    }
);
