<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';


const AGENDA_TENANT_CONTEXT_MODE =
    'SESSION_AUTHENTICATED';


function tenantContextMode(): string
{
    return AGENDA_TENANT_CONTEXT_MODE;
}


function contextoTenantWebActual(): array
{
    return agendaExigirAutenticacion();
}

function contextoTenantWebActualId(): int
{
    $contexto =
        contextoTenantWebActual();


    return (int) $contexto['id'];
}
