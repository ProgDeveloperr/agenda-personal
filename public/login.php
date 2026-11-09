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


// Las credenciales se ingresan por Tailscale HTTPS.
$hostActual = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hostActual = preg_replace('/:443$/', '', $hostActual);
$proxyProtocol = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
if (!in_array($hostActual, [$agendaPublicHost, ($agendaPublicHost . ':10000')], true) || $proxyProtocol !== 'https') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(403);
        header('Cache-Control: no-store');
        exit;
    }
    header('Location: https://' . $agendaPublicHost . '/login.php', true, 308);
    header('Cache-Control: no-store');
    exit;
}
header('Strict-Transport-Security: max-age=31536000');

require_once '/var/www/app/security.php';
iniciarSesion();
header('Cache-Control: no-store');

if (agendaPrincipalAutenticado() !== null) {
    header('Location: /', true, 303);
    exit;
}

$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (validarCsrf() && is_string($name) && is_string($pass)
        && agendaIntentarLogin($name, $pass)) {
        // Bienvenida tras autenticar correctamente.
        if (
            isset($_SESSION['mfa_pending_user_id'])
        ) {
            header(
                'Location: /mfa-verificar.php',
                true,
                303
            );
            exit;
        }

        $_SESSION['r8_welcome_pending'] = true;
        header('Location: /', true, 303);
        exit;
    }

    $error = true;
}

$token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b121b">
<title>Ingresar · Agenda Personal</title>
<link rel="stylesheet" href="/assets/css/r8-login-premium.css?v=2">
<!-- ARK3360_R35N_B_AGIL_ICON -->

<!-- /ARK3360_R35N_B_AGIL_ICON -->

    <!-- ARK3360_R35N_C_AGIL_TASKBAR_BOOST -->
    <link rel="icon" href="/assets/icons/agil-hola-taskbar-32.png?v=3e4f3c6e8665eba2" sizes="32x32" type="image/png">
    <link rel="shortcut icon" href="/assets/icons/agil-hola-taskbar.ico?v=3e4f3c6e8665eba2">
    <link rel="apple-touch-icon" href="/assets/icons/agil-hola-taskbar-180.png?v=3e4f3c6e8665eba2">
    <link rel="manifest" href="/manifest.webmanifest?v=cba488cbbc0c086a">
    <!-- /ARK3360_R35N_C_AGIL_TASKBAR_BOOST -->
</head>
<body>
<div class="login-scene">
  <div class="login-shell">
    <header class="login-brand" aria-label="Agenda Personal">
      <div class="login-brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="5" width="16" height="16" rx="3"/><path d="M8 3v4M16 3v4M4 10h16m-11 5 2 2 4-4"/>
        </svg>
      </div>
      <div class="login-brand-type"><strong>Agenda Personal</strong><span>WORKSPACE</span></div>
    </header>

    <main class="login-card" id="contenido">
      <div class="login-card-top"><span class="login-overline"><span class="login-status-dot" aria-hidden="true"></span> ACCESO PRIVADO</span><span class="login-edition">01 / AGENDA</span></div>
      <h1>Tu espacio,<br><span>en orden.</span></h1>
      <p class="login-intro">Ingresá para continuar donde lo dejaste.</p>
      <?php if ($error): ?>
      <div class="login-error" role="alert"><span class="login-error-symbol" aria-hidden="true">!</span><span>No se pudo iniciar sesión. Verificá tus datos e intentá nuevamente.</span></div>
      <?php endif; ?>
      <form method="post" action="/login.php" autocomplete="on" class="login-form">
        <input type="hidden" name="csrf_token" value="<?= $token ?>">
        <div class="login-field"><label for="username">Usuario</label>
          <div class="login-control">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.25"/><path d="M5 20c.5-4 3-6 7-6s6.5 2 7 6"/></svg>
            <input id="username" name="username" autocomplete="username" maxlength="100" placeholder="Tu usuario" required>
          </div>
        </div>
        <div class="login-field"><label for="password">Contraseña</label>
          <div class="login-control">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresá tu contraseña" required>
          </div>
        </div>
        <button class="login-submit" type="submit"><span>Ingresar a mi espacio</span>
          <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>
        </button>
      </form>
      <p class="r9-login-invite-entry">
        ¿Tenés un código de invitación?
        <a href="/registro.php">Crear cuenta</a>
      </p>
      <div class="login-trust"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"><path d="m12 3 8 4v5c0 5-3 8-8 10-5-2-8-5-8-10V7l8-4Z"/><path d="m9 12 2 2 4-4"/></svg><span>Sesión protegida · Acceso personal</span></div>
    </main>
    <footer class="login-footer"><span>Tu día, en un solo lugar.</span><span>AGENDA PERSONAL <span aria-hidden="true">/</span> WORKSPACE</span></footer>
  </div>
</div>
</body>
</html>
