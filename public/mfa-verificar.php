<?php
declare(strict_types=1);
require_once '/var/www/app/public_host.php';
try {
    $agendaPublicHost = agendaPublicHost();
} catch (Throwable $e) {
    http_response_code(503);
    header('Cache-Control: no-store');
    exit('Configuracion de host no disponible.');
}


$host = strtolower(
    (string) ($_SERVER['HTTP_HOST'] ?? '')
);

$host = preg_replace('/:443$/', '', $host);

$proto = strtolower(
    trim(
        (string) (
            $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
        )
    )
);

if (
    !in_array(
        $host,
        [
            $agendaPublicHost,
            ($agendaPublicHost . ':10000'),
        ],
        true
    )
    || $proto !== 'https'
) {
    http_response_code(403);
    exit;
}

header('Strict-Transport-Security: max-age=31536000');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

header(
    "Content-Security-Policy: default-src 'none'; "
    . "style-src 'self'; "
    . "form-action 'self'; "
    . "base-uri 'none'; "
    . "frame-ancestors 'none'"
);

require_once '/var/www/app/security.php';
require_once '/var/www/app/mfa_auth.php';

if (
    !in_array(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ['GET', 'POST'],
        true
    )
) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

iniciarSesion();

if (!agendaMfaPending()) {
    header('Location: /login.php', true, 303);
    exit;
}

$error = false;

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'POST'
) {
    $code = $_POST['code'] ?? null;

    if (
        validarCsrf()
        && is_string($code)
        && strlen($code) <= 50
        && agendaMfaVerifyPending($code)
    ) {
        header('Location: /', true, 303);
        exit;
    }

    $error = true;

    if (!agendaMfaPending()) {
        header('Location: /login.php', true, 303);
        exit;
    }
}

$csrf = htmlspecialchars(
    csrfToken(),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b121b">
<title>Verificación de seguridad · Agenda Personal</title>
<link rel="stylesheet" href="/assets/css/r8-login-premium.css?v=2">
</head>
<body>
<div class="login-scene">
<div class="login-shell">
<main class="login-card">

<h1>Verificación de <span>seguridad.</span></h1>

<p class="login-intro">
Ingresá el código de seis dígitos de tu autenticador
o un código de recuperación.
</p>

<?php if ($error): ?>
<p role="alert">
No se pudo validar el código.
Revisalo e intentá nuevamente.
</p>
<?php endif; ?>

<form method="post"
      action="/mfa-verificar.php"
      class="login-form"
      autocomplete="off">

<input type="hidden"
       name="csrf_token"
       value="<?= $csrf ?>">

<div class="login-field">
<label for="code">Código de verificación</label>
<input
    id="code"
    name="code"
    type="text"
    inputmode="text"
    autocomplete="one-time-code"
    maxlength="39"
    required
    autofocus>
</div>

<button class="login-submit" type="submit">
Verificar acceso
</button>

</form>

<p class="login-intro">
La verificación vence cinco minutos después
de ingresar la contraseña.
</p>

</main>
</div>
</div>
</body>
</html>
