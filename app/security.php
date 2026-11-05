<?php

declare(strict_types=1);

require_once __DIR__ . '/tenant_context.php';

function iniciarSesion(): void
{
    agendaIniciarSesionSegura();
}

function csrfToken(): string
{
    iniciarSesion();

    if (
        !isset($_SESSION['csrf_token'])
        || !is_string($_SESSION['csrf_token'])
        || strlen($_SESSION['csrf_token']) < 32
    ) {
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['csrf_token'];
}

function validarCsrf(): bool
{
    iniciarSesion();

    $enviado = $_POST['csrf_token'] ?? '';

    if (!is_string($enviado)) {
        return false;
    }

    return hash_equals(
        csrfToken(),
        $enviado
    );
}

function guardarMensaje(
    string $tipo,
    string $mensaje
): void {
    iniciarSesion();

    $_SESSION['mensaje'] = [
        'tipo' => $tipo,
        'texto' => $mensaje,
    ];
}

function obtenerMensaje(): ?array
{
    iniciarSesion();

    if (!isset($_SESSION['mensaje'])) {
        return null;
    }

    $mensaje = $_SESSION['mensaje'];

    unset($_SESSION['mensaje']);

    return is_array($mensaje)
        ? $mensaje
        : null;
}
