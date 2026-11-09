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

$proto = strtolower(trim(
    (string) (
        $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
    )
));

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

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Strict-Transport-Security: max-age=31536000');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

header(
    "Content-Security-Policy: default-src 'none'; "
    . "style-src 'self'; form-action 'self'; "
    . "base-uri 'none'; frame-ancestors 'none'"
);

require_once '/var/www/app/security.php';
require_once '/var/www/app/mfa_totp.php';
require_once '/var/www/app/mfa_storage.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET' && $method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$user = contextoTenantWebActual();

if (($user['rol'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit('Acceso restringido');
}

$uid = (int) $user['id'];
$pdo = db();

function mfaSetupUserRow(
    PDO $db,
    int $id,
    bool $lock = false
): array {
    $sql =
        'SELECT id, password_hash, rol, activo '
        . 'FROM usuarios WHERE id=:id'
        . ($lock ? ' FOR UPDATE' : '');

    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !is_array($row)
        || (int) $row['activo'] !== 1
        || $row['rol'] !== 'ADMIN'
    ) {
        throw new RuntimeException(
            'Cuenta administrativa no disponible.'
        );
    }

    return $row;
}

function mfaSetupExists(PDO $db, int $id): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM usuario_mfa '
        . 'WHERE user_id=:id'
    );

    $stmt->execute([':id' => $id]);

    return (int) $stmt->fetchColumn() > 0;
}

function mfaSetupEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$existing = mfaSetupExists($pdo, $uid);
$success = false;
$error = '';

$flow = $_SESSION['agenda_mfa_setup'] ?? null;

if (
    !is_array($flow)
    || ($flow['uid'] ?? null) !== $uid
    || !is_int($flow['started'] ?? null)
    || time() - $flow['started'] > 600
    || $flow['started'] > time()
) {
    unset($_SESSION['agenda_mfa_setup']);
    $flow = null;
}

if ($existing) {
    unset($_SESSION['agenda_mfa_setup']);
    $flow = null;
}

if ($method === 'POST') {
    if (
        (int) ($_SERVER['CONTENT_LENGTH'] ?? 0)
        > 4096
    ) {
        http_response_code(413);
        exit;
    }

    if (!validarCsrf()) {
        http_response_code(403);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {
        $action = '';
    }

    if ($action === 'cancel') {
        unset($_SESSION['agenda_mfa_setup']);
        header(
            'Location: /mfa-configurar.php',
            true,
            303
        );
        exit;
    }

    if ($existing) {
        $error =
            'Ya existe una configuración MFA '
            . 'para esta cuenta.';

    } elseif (
        $action === 'start'
        && $flow === null
    ) {
        $limit = $_SESSION[
            'agenda_mfa_reauth_limit'
        ] ?? [
            'at' => time(),
            'failed' => 0,
        ];

        if (
            !is_array($limit)
            || time() -
                (int) ($limit['at'] ?? 0) >= 900
        ) {
            $limit = [
                'at' => time(),
                'failed' => 0,
            ];
        }

        $password = $_POST['password'] ?? null;

        if ((int) $limit['failed'] >= 5) {
            $error =
                'Demasiados intentos. '
                . 'Reintentá en 15 minutos.';

        } elseif (
            !is_string($password)
            || strlen($password) > 4096
            || !password_verify(
                $password,
                (string) mfaSetupUserRow(
                    $pdo,
                    $uid
                )['password_hash']
            )
        ) {
            $limit['failed']++;

            $_SESSION[
                'agenda_mfa_reauth_limit'
            ] = $limit;

            $error = 'Contraseña incorrecta.';

        } else {
            unset(
                $_SESSION['agenda_mfa_reauth_limit']
            );

            $flow = [
                'uid' => $uid,
                'started' => time(),
                'phase' => 'verify',
                'secret' => agendaTotpBase32Encode(
                    random_bytes(20)
                ),
                'tries' => 0,
            ];

            $_SESSION[
                'agenda_mfa_setup'
            ] = $flow;
        }

    } elseif (
        $action === 'verify'
        && is_array($flow)
        && ($flow['phase'] ?? '') === 'verify'
    ) {
        $code = $_POST['code'] ?? null;

        $step = (
            is_string($code)
            && strlen($code) === 6
        )
            ? agendaTotpVerify(
                (string) $flow['secret'],
                $code,
                time()
            )
            : null;

        if ($step === null) {
            $flow['tries'] =
                (int) ($flow['tries'] ?? 0) + 1;

            if ($flow['tries'] >= 5) {
                unset(
                    $_SESSION['agenda_mfa_setup']
                );

                $flow = null;
                $error =
                    'Se agotaron los intentos. '
                    . 'Volvé a empezar.';
            } else {
                $_SESSION[
                    'agenda_mfa_setup'
                ] = $flow;

                $error =
                    'Código incorrecto. '
                    . 'Verificá la hora del autenticador.';
            }

        } else {
            $flow['phase'] = 'backup';
            $flow['verified_step'] = $step;
            $flow['codes'] =
                agendaMfaGenerateRecoveryCodes();
            $flow['finish_tries'] = 0;

            $_SESSION[
                'agenda_mfa_setup'
            ] = $flow;
        }

    } elseif (
        $action === 'activate'
        && is_array($flow)
        && ($flow['phase'] ?? '') === 'backup'
    ) {
        $password = $_POST['password'] ?? null;
        $ack = $_POST['saved'] ?? null;

        if ($ack !== '1') {
            $error =
                'Confirmá que guardaste '
                . 'los códigos de recuperación.';

        } elseif (
            (int) ($flow['finish_tries'] ?? 0)
            >= 5
        ) {
            $error =
                'Demasiados intentos. '
                . 'Cancelá y volvé a comenzar.';

        } elseif (
            !is_string($password)
            || strlen($password) > 4096
        ) {
            $error = 'Confirmá tu contraseña.';

        } else {
            $pdo->beginTransaction();

            try {
                $admin = mfaSetupUserRow(
                    $pdo,
                    $uid,
                    true
                );

                if (!password_verify(
                    $password,
                    (string) $admin['password_hash']
                )) {
                    $pdo->rollBack();

                    $flow['finish_tries'] =
                        (int) (
                            $flow['finish_tries'] ?? 0
                        ) + 1;

                    $_SESSION[
                        'agenda_mfa_setup'
                    ] = $flow;

                    $error =
                        'Contraseña incorrecta.';

                } else {
                    if (
                        mfaSetupExists($pdo, $uid)
                    ) {
                        throw new RuntimeException(
                            'MFA ya configurado.'
                        );
                    }

                    $sealed =
                        agendaMfaEncryptSecret(
                            (string) $flow['secret']
                        );

                    $stmt = $pdo->prepare(
                        'INSERT INTO usuario_mfa '
                        . '(user_id, secret_nonce, '
                        . 'secret_cipher, enabled_at, '
                        . 'last_used_step) '
                        . 'VALUES '
                        . '(:uid,:nonce,:cipher,'
                        . 'NOW(),:step)'
                    );

                    $stmt->bindValue(
                        ':uid',
                        $uid,
                        PDO::PARAM_INT
                    );

                    $stmt->bindValue(
                        ':nonce',
                        $sealed['nonce'],
                        PDO::PARAM_LOB
                    );

                    $stmt->bindValue(
                        ':cipher',
                        $sealed['cipher'],
                        PDO::PARAM_LOB
                    );

                    $stmt->bindValue(
                        ':step',
                        (int) $flow['verified_step'],
                        PDO::PARAM_INT
                    );

                    $stmt->execute();

                    $stmt = $pdo->prepare(
                        'INSERT INTO '
                        . 'usuario_mfa_recuperacion '
                        . '(user_id,code_hash) '
                        . 'VALUES (:uid,:hash)'
                    );

                    foreach (
                        $flow['codes'] as $recovery
                    ) {
                        $stmt->execute([
                            ':uid' => $uid,
                            ':hash' =>
                                agendaMfaRecoveryHash(
                                    $recovery
                                ),
                        ]);
                    }

                    $pdo->commit();

                    if (
                        !session_regenerate_id(true)
                    ) {
                        throw new RuntimeException(
                            'No se pudo renovar la sesión.'
                        );
                    }

                    $now = time();

                    $_SESSION[
                        'auth_mfa_verified_user_id'
                    ] = $uid;

                    $_SESSION[
                        'auth_mfa_verified_at'
                    ] = $now;

                    $_SESSION[
                        'auth_last_seen'
                    ] = $now;

                    $_SESSION[
                        'auth_issued_at'
                    ] = $now;

                    $_SESSION['csrf_token'] =
                        bin2hex(random_bytes(32));

                    unset(
                        $_SESSION['agenda_mfa_setup']
                    );

                    $flow = null;
                    $existing = true;
                    $success = true;
                }

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Agenda MFA activation: '
                    . get_class($e)
                );

                $error =
                    'No se pudo activar MFA. '
                    . 'Revisá el estado con '
                    . 'el administrador del servidor.';
            }
        }

    } else {
        $error =
            'La operación no es válida '
            . 'o la preparación expiró.';
    }
}

$csrf = mfaSetupEscape(csrfToken());

$phase = is_array($flow)
    ? ($flow['phase'] ?? '')
    : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0b121b">
<title>Configurar segundo factor · Agenda Personal</title>
<link rel="stylesheet"
      href="/assets/css/r8-login-premium.css?v=2">
<link rel="stylesheet"
      href="/assets/css/r12-mfa-configurar.css?v=1">
</head>
<body>
<div class="login-scene">
<div class="login-shell">
<main class="login-card mfa-setup">

<p class="mfa-kicker">
AGENDA PERSONAL / SEGURIDAD
</p>

<h1>Segundo factor <span>ADMIN.</span></h1>

<?php if ($error !== ''): ?>
<p class="mfa-error" role="alert">
<?= mfaSetupEscape($error) ?>
</p>
<?php endif; ?>

<?php if ($success): ?>

<p class="mfa-ok">
MFA activado para tu cuenta ADMIN.
</p>

<p>
Ya podés cerrar sesión e ingresar nuevamente
para probar el desafío. Usá un código nuevo del
autenticador: el utilizado durante el enrolamiento
no se puede reutilizar.
</p>

<p><a href="/">Volver a Agenda</a></p>

<?php elseif ($existing): ?>

<p class="mfa-ok">
Esta cuenta ya tiene una configuración MFA registrada.
</p>

<p>
No se muestra ni se regenera el secreto
desde esta página.
</p>

<p><a href="/">Volver a Agenda</a></p>

<?php elseif ($phase === 'verify'): ?>

<p>
Agregá una cuenta a tu aplicación autenticadora
con la opción
<strong>Introducir clave de configuración</strong>.
</p>

<p>
Nombre sugerido:
<strong>Agenda Personal (ADMIN)</strong>.
Tipo: <strong>Basado en tiempo (TOTP)</strong>.
</p>

<p class="mfa-kicker">
Clave de configuración privada
</p>

<pre class="mfa-secret"><?= mfaSetupEscape((string) $flow['secret']) ?></pre>

<p>
No compartas esta clave ni la incluyas en capturas.
Tenés diez minutos para continuar.
</p>

<form method="post"
      action="/mfa-configurar.php"
      class="login-form">

<input type="hidden"
       name="csrf_token"
       value="<?= $csrf ?>">

<input type="hidden"
       name="action"
       value="verify">

<label for="code">
Código de seis dígitos del autenticador
</label>

<input id="code"
       name="code"
       inputmode="numeric"
       pattern="[0-9]{6}"
       maxlength="6"
       autocomplete="one-time-code"
       required>

<button type="submit" class="login-submit">
Verificar código
</button>
</form>

<?php elseif ($phase === 'backup'): ?>

<p class="mfa-ok">
Código TOTP verificado. MFA todavía no está activo.
</p>

<p>
<strong>Guardá estos diez códigos de recuperación</strong>
en un gestor de contraseñas o en papel.
Cada código puede utilizarse una sola vez.
</p>

<pre class="mfa-secret mfa-recovery"><?= mfaSetupEscape(implode("\n", $flow['codes'])) ?></pre>

<p>
No los pegues en ChatGPT ni los subas a GitHub.
</p>

<form method="post"
      action="/mfa-configurar.php"
      class="login-form">

<input type="hidden"
       name="csrf_token"
       value="<?= $csrf ?>">

<input type="hidden"
       name="action"
       value="activate">

<label class="mfa-check">
<input type="checkbox"
       name="saved"
       value="1"
       required>
Confirmo que guardé los diez códigos fuera del servidor.
</label>

<label for="password-final">
Volvé a ingresar tu contraseña ADMIN
para activar MFA
</label>

<input id="password-final"
       type="password"
       name="password"
       autocomplete="current-password"
       maxlength="4096"
       required>

<button type="submit" class="login-submit">
Activar MFA para ADMIN
</button>
</form>

<?php else: ?>

<p>
Vamos a vincular tu aplicación autenticadora.
El segundo factor se activará únicamente después
de verificar un código y guardar los códigos
de recuperación.
</p>

<form method="post"
      action="/mfa-configurar.php"
      class="login-form">

<input type="hidden"
       name="csrf_token"
       value="<?= $csrf ?>">

<input type="hidden"
       name="action"
       value="start">

<label for="password">
Confirmá tu contraseña de Agenda Personal
</label>

<input id="password"
       type="password"
       name="password"
       autocomplete="current-password"
       maxlength="4096"
       required>

<button type="submit" class="login-submit">
Comenzar configuración segura
</button>
</form>

<?php endif; ?>

<?php if ($phase !== '' && !$existing): ?>
<form method="post"
      action="/mfa-configurar.php"
      class="mfa-cancel">

<input type="hidden"
       name="csrf_token"
       value="<?= $csrf ?>">

<input type="hidden"
       name="action"
       value="cancel">

<button type="submit">
Cancelar configuración
</button>
</form>
<?php endif; ?>

</main>
</div>
</div>
</body>
</html>
