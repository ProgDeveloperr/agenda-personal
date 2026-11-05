<?php
declare(strict_types=1);

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:443$/', '', $host);
$proto = strtolower(trim(
    (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')
));

if ($host !== ((getenv('AGENDA_PUBLIC_HOST') ?: 'agenda.example.com')) || $proto !== 'https') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(403);
        exit;
    }

    header(
        ('Location: https://' . (getenv('AGENDA_PUBLIC_HOST') ?: 'agenda.example.com') . '/admin-invitaciones.php'),
        true,
        308
    );
    exit;
}

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header(
    "Content-Security-Policy: default-src 'none'; "
    . "style-src 'self'; form-action 'self'; "
    . "base-uri 'none'; frame-ancestors 'none'"
);

require_once '/var/www/app/security.php';
require_once '/var/www/app/database.php';
require_once '/var/www/app/auth.php';
require_once '/var/www/app/registro_admin_service.php';

iniciarSesion();

$principal = agendaPrincipalAutenticado();

if (!is_array($principal)) {
    header('Location: /login.php', true, 303);
    exit;
}

$adminId = (int) ($principal['id'] ?? 0);

try {
    registroAdminExigir(db(), $adminId);
} catch (DomainException $e) {
    http_response_code(403);
    exit('Acceso no autorizado.');
} catch (Throwable $e) {
    error_log('R9 admin access: ' . get_class($e));
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

try {
    $version = db()->query(
        "SELECT valor FROM configuracion "
        . "WHERE clave='schema_version' LIMIT 1"
    )->fetchColumn();

    if ((string) $version !== '11') {
        http_response_code(503);
        exit('Administración de invitaciones todavía no disponible.');
    }
} catch (Throwable $e) {
    error_log('R9 admin schema: ' . get_class($e));
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

function r9e(string $text): string {
    return htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($method === 'POST') {
    if (!validarCsrf()) {
        http_response_code(403);
        exit;
    }

    $action = $_POST['accion'] ?? null;

    try {
        if ($action === 'emitir') {
            $hours = $_POST['horas'] ?? null;

            if (
                !is_string($hours)
                || !in_array($hours, ['24', '168'], true)
            ) {
                throw new DomainException('Vigencia no permitida.');
            }

            $issued = registroAdminCrearInvitacion(
                db(),
                $adminId,
                (int) $hours
            );

            $_SESSION['r9_invitation_once'] = [
                'codigo' => $issued['codigo'],
                'id' => $issued['id'],
                'horas' => $hours
            ];

            $_SESSION['r9_admin_flash'] =
                'Invitación generada. Guardá el código: '
                . 'se mostrará una sola vez.';

        } elseif ($action === 'revocar') {
            $raw = $_POST['invitacion_id'] ?? null;

            if (
                !is_string($raw)
                || !preg_match('/\A[1-9][0-9]{0,18}\z/D', $raw)
            ) {
                throw new DomainException('Identificador no válido.');
            }

            registroAdminRevocarInvitacion(
                db(),
                $adminId,
                (int) $raw
            );

            $_SESSION['r9_admin_flash'] = 'Invitación revocada.';

        } else {
            throw new DomainException('Acción no válida.');
        }

    } catch (DomainException $e) {
        $_SESSION['r9_admin_flash'] = $e->getMessage();

    } catch (Throwable $e) {
        error_log('R9 admin mutation: ' . get_class($e));
        $_SESSION['r9_admin_flash'] =
            'La operación no pudo completarse.';
    }

    header('Location: /admin-invitaciones.php', true, 303);
    exit;
}

$once = $_SESSION['r9_invitation_once'] ?? null;
$flash = (string) ($_SESSION['r9_admin_flash'] ?? '');

unset(
    $_SESSION['r9_invitation_once'],
    $_SESSION['r9_admin_flash']
);

try {
    $items = registroAdminListarInvitaciones(db(), $adminId);
} catch (Throwable $e) {
    error_log('R9 admin list: ' . get_class($e));
    http_response_code(503);
    exit('No se pudieron consultar las invitaciones.');
}

$counts = [
    'PENDIENTE' => 0,
    'UTILIZADA' => 0,
    'REVOCADA' => 0,
    'VENCIDA' => 0
];

foreach ($items as $item) {
    $state = (string) ($item['estado'] ?? '');

    if (isset($counts[$state])) {
        ++$counts[$state];
    }
}

$csrf = r9e(csrfToken());
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b121b">
<title>Invitaciones · Agenda Personal</title>
<link rel="stylesheet" href="/assets/css/r9-admin-invitaciones.css?v=1">
</head>
<body>
<div class="admin-scene">
<main class="admin-shell">

<header class="admin-top">
  <div>
    <p class="eyebrow">AGENDA PERSONAL / ADMINISTRACIÓN</p>
    <h1>Invitaciones</h1>
    <p class="subtext">Autorizá nuevas cuentas sin exponer tus datos.</p>
  </div>
  <a class="back" href="/">Volver al dashboard</a>
</header>

<section class="stats" aria-label="Resumen de últimas 50 invitaciones">
<?php foreach (
    [
        'PENDIENTE' => 'Pendientes',
        'UTILIZADA' => 'Utilizadas',
        'REVOCADA' => 'Revocadas',
        'VENCIDA' => 'Vencidas'
    ] as $key => $label
): ?>
  <div class="stat">
    <span><?= r9e($label) ?></span>
    <strong><?= (int) $counts[$key] ?></strong>
  </div>
<?php endforeach; ?>
</section>

<?php if ($flash !== ''): ?>
<p class="notice" role="status"><?= r9e($flash) ?></p>
<?php endif; ?>

<?php if (
    is_array($once)
    && isset($once['codigo'])
    && is_string($once['codigo'])
): ?>
<section class="reveal">
  <h2>Código generado</h2>
  <p>Copialo ahora. Por seguridad no volverá a mostrarse.</p>
  <code><?= r9e($once['codigo']) ?></code>
  <p class="fine">
    Compartilo únicamente con la persona invitada.
  </p>
</section>
<?php endif; ?>

<section class="panel">
  <h2>Crear una invitación</h2>
  <p>
    Una cuenta por invitación.
    El usuario invitado tendrá siempre rol USER.
  </p>

  <form method="post"
        action="/admin-invitaciones.php"
        class="emit-form">

    <input type="hidden" name="csrf_token"
           value="<?= $csrf ?>">

    <input type="hidden" name="accion" value="emitir">

    <label for="horas">Vencimiento</label>

    <select id="horas" name="horas">
      <option value="24">24 horas</option>
      <option value="168">7 días</option>
    </select>

    <button type="submit">Generar invitación</button>
  </form>

  <p class="fine">
    Máximo 3 por hora, 12 por día y 20 pendientes.
  </p>
</section>

<section class="panel">
  <h2>Invitaciones recientes</h2>
  <p>
    Se muestran las últimas 50.
    Los códigos nunca aparecen en el historial.
  </p>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Estado</th>
          <th>Emisión</th>
          <th>Vencimiento</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td>#<?= (int) $item['id'] ?></td>
          <td>
            <span class="state">
              <?= r9e((string) $item['estado']) ?>
            </span>
          </td>
          <td><?= r9e((string) $item['created_at']) ?></td>
          <td><?= r9e((string) $item['vence_at']) ?></td>
          <td>
          <?php if ($item['estado'] === 'PENDIENTE'): ?>
            <form method="post"
                  action="/admin-invitaciones.php">

              <input type="hidden" name="csrf_token"
                     value="<?= $csrf ?>">

              <input type="hidden" name="accion"
                     value="revocar">

              <input type="hidden" name="invitacion_id"
                     value="<?= (int) $item['id'] ?>">

              <button class="revoke" type="submit">
                Revocar
              </button>
            </form>
          <?php else: ?>
            —
          <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <?php if (!$items): ?>
        <tr>
          <td colspan="5">
            Todavía no hay invitaciones.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<footer class="footer">
  AGENDA PERSONAL / ACCESO PRIVADO
</footer>

</main>
</div>
</body>
</html>
