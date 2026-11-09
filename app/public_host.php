<?php
declare(strict_types=1);

/** Fail-closed: hostname aprobado por configuracion privada. */
function agendaPublicHost(): string
{
    $file = '/run/agenda/agenda.env';
    if (!is_readable($file)) {
        throw new RuntimeException('Configuracion privada no disponible.');
    }
    $env = parse_ini_file($file, false, INI_SCANNER_RAW);
    if (!is_array($env)) {
        throw new RuntimeException('Configuracion privada invalida.');
    }
    $host = strtolower(trim((string) ($env['AGENDA_PUBLIC_HOST'] ?? '')));
    if (
        $host === '' || strlen($host) > 253 ||
        !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
        !str_contains($host, '.')
    ) {
        throw new RuntimeException('AGENDA_PUBLIC_HOST invalido.');
    }
    return $host;
}
