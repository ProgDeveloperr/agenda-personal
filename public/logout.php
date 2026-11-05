<?php
declare(strict_types=1);

require_once '/var/www/app/security.php';
iniciarSesion();
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!validarCsrf()) {
    http_response_code(403);
    exit;
}

agendaCerrarSesionAutenticada();

header('Location: /login.php', true, 303);
exit;
