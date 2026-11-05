<?php

declare(strict_types=1);

function appConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = (getenv('AGENDA_ENV_FILE') ?: dirname(__DIR__) . '/.env');

    if (!is_readable($path)) {
        throw new RuntimeException(
            'No se puede leer la configuración de la aplicación.'
        );
    }

    $values = parse_ini_file(
        $path,
        false,
        INI_SCANNER_RAW
    );

    if ($values === false) {
        throw new RuntimeException(
            'No se pudo interpretar la configuración.'
        );
    }

    $required = [
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ];

    foreach ($required as $key) {
        if (
            !array_key_exists($key, $values)
            || trim((string) $values[$key]) === ''
        ) {
            throw new RuntimeException(
                'Falta una variable requerida: ' . $key
            );
        }
    }

    $port = filter_var(
        $values['DB_PORT'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ],
        ]
    );

    if ($port === false) {
        throw new RuntimeException(
            'DB_PORT no contiene un puerto válido.'
        );
    }

    $config = [
        'db' => [
            'host' => (string) $values['DB_HOST'],
            'port' => (int) $port,
            'name' => (string) $values['DB_NAME'],
            'user' => (string) $values['DB_USER'],
            'password' => (string) $values['DB_PASSWORD'],
        ],
    ];

    return $config;
}
