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


// Registro disponible solamente por la ruta HTTPS privada.
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:443$/', '', $host);
$proto = strtolower(trim(
    (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
));

if (!in_array($host, [$agendaPublicHost, ($agendaPublicHost . ':10000')], true) || $proto !== 'https') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        http_response_code(403);
        header('Cache-Control: no-store');
        exit;
    }

    header(
        'Location: https://' . $agendaPublicHost . '/registro.php',
        true, 308
    );
    exit;
}

header('Strict-Transport-Security: max-age=31536000');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

require_once '/var/www/app/security.php';
require_once '/var/www/app/database.php';
require_once '/var/www/app/registro_service.php';
require_once '/var/www/app/registro_rate_limit.php';

iniciarSesion();

if (agendaPrincipalAutenticado() !== null) {
    header('Location: /', true, 303);
    exit;
}

// Bloqueo preventivo si la migracion v11 no esta aplicada.
try {
    $version = db()->query(
        "SELECT valor FROM configuracion WHERE clave='schema_version' LIMIT 1"
    )->fetchColumn();

    if ((string) $version !== '11') {
        http_response_code(503);
        exit('Registro temporalmente no disponible.');
    }
} catch (Throwable $e) {
    error_log('R9 schema gate: ' . get_class($e));
    http_response_code(503);
    exit('Registro temporalmente no disponible.');
}

$error = '';
$completado = ($_SESSION['r9_registro_completado'] ?? false) === true;
unset($_SESSION['r9_registro_completado']);

$nombre = '';
$usuario = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Limite adicional de tamano del formulario.
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        http_response_code(413);
        exit;
    }

    if (!validarCsrf()) {
        http_response_code(403);
        exit;
    }

    $n = $_POST['nombre'] ?? null;
    $u = $_POST['username'] ?? null;
    $p = $_POST['password'] ?? null;
    $c = $_POST['password_confirm'] ?? null;
    $i = $_POST['invitacion'] ?? null;

    // El contador persiste antes de validar datos o usar bcrypt.
    try {
        $permitido = r9RegistroConsumirIntento(
            db(),
            session_id(),
            is_string($u) ? $u : '#invalid-user',
            is_string($i) ? $i : '#invalid-invitation'
        );
    } catch (Throwable $e) {
        error_log('R9 rate limit unavailable: ' . get_class($e));
        http_response_code(503);
        exit('Registro temporalmente no disponible.');
    }

    if (!$permitido) {
        header('Retry-After: 900');
        http_response_code(429);
        $error = 'Demasiados intentos. Intentá más tarde.';

    } elseif (
        !is_string($n) || !is_string($u)
        || !is_string($p) || !is_string($c)
        || !is_string($i)
        || strlen($n) > 240
        || strlen($u) > 100
        || strlen($p) > 128
        || strlen($c) > 128
        || strlen($i) > 128
    ) {
        $error = 'Revisá los datos del formulario.';
        http_response_code(422);
    } else {
        $nombre = $n;
        $usuario = $u;

        try {
            registroCrearCuentaInvitada(
                db(), $n, $u, $p, $c, $i
            );

            $_SESSION['r9_registro_completado'] = true;

            header('Location: /registro.php', true, 303);
            exit;

        } catch (DomainException $e) {
            $error = 'No se pudo completar el registro. Revisá tus datos y la invitación.';
            http_response_code(422);

        } catch (PDOException $e) {
            error_log('R9 database SQLSTATE: ' . (string) $e->getCode());
            $error = 'No se pudo completar el registro. Revisá los datos o intentá más tarde.';
            http_response_code(422);

        } catch (Throwable $e) {
            error_log('R9 registration failure: ' . get_class($e));
            $error = 'No se pudo completar el registro. Intentá más tarde.';
            http_response_code(503);
        }
    }
}

$csrf = htmlspecialchars(
    csrfToken(),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$nombreSeguro = htmlspecialchars(
    $nombre,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$usuarioSeguro = htmlspecialchars(
    $usuario,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b121b">
<title>Crear cuenta · Agenda Personal</title>
<link rel="stylesheet" href="/assets/css/r9-registro.css?v=1">
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
<div class="register-scene">
  <div class="register-shell">
    <header class="register-brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="5" width="16" height="16" rx="3"/>
          <path d="M8 3v4M16 3v4M4 10h16m-11 5 2 2 4-4"/>
        </svg>
      </span>
      <span>
        <strong>Agenda Personal</strong>
        <small>WORKSPACE</small>
      </span>
    </header>

    <main class="register-card">
      <?php if ($completado): ?>
        <div class="register-overline">
          <span class="status-dot"></span>
          CUENTA CREADA
        </div>

        <h1>Tu espacio<br><em>comienza acá.</em></h1>

        <p class="register-description">
          La cuenta se creó correctamente.
          Ya podés ingresar con tu usuario y contraseña.
        </p>

        <a class="register-submit register-link" href="/login.php">
          Ir al inicio de sesión
          <span aria-hidden="true">→</span>
        </a>
      <?php else: ?>
        <div class="register-overline">
          <span class="status-dot"></span>
          REGISTRO POR INVITACIÓN
        </div>

        <h1>Un espacio<br><em>para vos.</em></h1>

        <p class="register-description">
          Organizá tus materias, tareas y objetivos
          en un entorno privado.
        </p>

        <?php if ($error !== ''): ?>
          <p class="register-error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </p>
        <?php endif; ?>

        <form method="post" action="/registro.php"
              autocomplete="on" class="register-form">

          <input type="hidden" name="csrf_token"
                 value="<?= $csrf ?>">

          <div class="register-field">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="120"
                   autocomplete="name" required
                   value="<?= $nombreSeguro ?>"
                   placeholder="Tu nombre">
          </div>

          <div class="register-field">
            <label for="username">Usuario</label>
            <input id="username" name="username"
                   maxlength="32" autocomplete="username"
                   autocapitalize="none" spellcheck="false"
                   required value="<?= $usuarioSeguro ?>"
                   placeholder="Elegí un usuario">
          </div>

          <div class="register-grid">
            <div class="register-field">
              <label for="password">Contraseña</label>
              <input id="password" name="password"
                     type="password" autocomplete="new-password"
                     minlength="12" maxlength="128" required
                     placeholder="Mínimo 12 caracteres">
            </div>

            <div class="register-field">
              <label for="password_confirm">
                Confirmar contraseña
              </label>
              <input id="password_confirm"
                     name="password_confirm"
                     type="password" autocomplete="new-password"
                     minlength="12" maxlength="128" required
                     placeholder="Repetí tu contraseña">
            </div>
          </div>

          <div class="register-field">
            <label for="invitacion">
              Código de invitación
            </label>
            <input id="invitacion" name="invitacion"
                   minlength="64" maxlength="64"
                   pattern="[A-Fa-f0-9]{64}"
                   autocapitalize="none" spellcheck="false"
                   autocomplete="off" required
                   placeholder="Código de 64 caracteres">
            <small>
              Solicitá el código al administrador.
              Se utiliza una sola vez.
            </small>
          </div>

          <button class="register-submit" type="submit">
            Crear mi cuenta
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <p class="register-secondary">
          ¿Ya tenés una cuenta?
          <a href="/login.php">Ingresá acá</a>
        </p>
      <?php endif; ?>

      <div class="register-trust">
        <svg viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.7"
             aria-hidden="true">
          <path d="m12 3 8 4v5c0 5-3 8-8 10-5-2-8-5-8-10V7l8-4Z"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
        Cuenta privada · Acceso protegido
      </div>
    </main>

    <footer class="register-footer">
      AGENDA PERSONAL <span aria-hidden="true">/</span> WORKSPACE
    </footer>
  </div>
</div>
</body>
</html>
