<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

const AGENDA_AUTH_IDLE_SECONDS = 1800;
const AGENDA_AUTH_ABSOLUTE_SECONDS = 43200;

function agendaIniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        throw new RuntimeException('No se puede iniciar una sesión segura.');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    session_name('agenda_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    if (!session_start()) {
        throw new RuntimeException('No se pudo iniciar la sesión.');
    }
}

function agendaCerrarSesionAutenticada(): void
{
    agendaIniciarSesionSegura();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);
    }

    session_destroy();
}

function agendaPrincipalAutenticado(): ?array
{
    agendaIniciarSesionSegura();

    $id = $_SESSION['auth_user_id'] ?? null;
    $issued = $_SESSION['auth_issued_at'] ?? null;
    $seen = $_SESSION['auth_last_seen'] ?? null;
    $fingerprint = $_SESSION['auth_password_fingerprint'] ?? null;

    if (!is_int($id) || $id <= 0 || !is_int($issued)
        || !is_int($seen) || !is_string($fingerprint)) {
        return null;
    }

    $now = time();

    if ($issued > $now || $seen > $now
        || $now - $issued > AGENDA_AUTH_ABSOLUTE_SECONDS
        || $now - $seen > AGENDA_AUTH_IDLE_SECONDS) {
        agendaCerrarSesionAutenticada();
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, nombre, rol, activo, password_hash '
        . 'FROM usuarios WHERE id = :id LIMIT 1'
    );

    $stmt->execute([':id' => $id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($u) || (int) ($u['activo'] ?? 0) !== 1
        || !hash_equals(
            $fingerprint,
            hash('sha256', (string) ($u['password_hash'] ?? ''))
        )) {
        agendaCerrarSesionAutenticada();
        return null;
    }

    $_SESSION['auth_last_seen'] = $now;

    return [
        'id' => (int) $u['id'],
        'username' => (string) $u['username'],
        'nombre' => (string) $u['nombre'],
        'rol' => (string) $u['rol'],
        'activo' => 1,
    ];
}

function agendaExigirAutenticacion(): array
{
    $user = agendaPrincipalAutenticado();

    if ($user !== null) {
        return $user;
    }

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('Identidad web autenticada requerida.');
    }

    http_response_code(401);
    header('Cache-Control: no-store');

    $uri = parse_url(
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );

    if (is_string($uri) && str_starts_with($uri, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['error' => 'Autenticación requerida'],
            JSON_UNESCAPED_UNICODE
        );
    } else {
        header('Location: /login.php', true, 303);
    }

    exit;
}

function agendaLoginPermitido(string $username, ?bool $resultado = null): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $dir = sys_get_temp_dir() . '/agenda-login-limiter';

    if ((!is_dir($dir) && !@mkdir($dir, 0700, true)) || is_link($dir)) {
        return false;
    }

    $key = hash('sha256', $ip . '|' . strtolower($username));
    $f = @fopen($dir . '/' . $key, 'c+');

    if ($f === false) {
        return false;
    }

    try {
        if (!flock($f, LOCK_EX)) {
            return false;
        }

        $raw = stream_get_contents($f);
        $v = json_decode(is_string($raw) ? $raw : '', true);
        $now = time();

        if (!is_array($v) || !isset($v['start'], $v['count'])
            || $now - (int) $v['start'] >= 900) {
            $v = ['start' => $now, 'count' => 0];
        }

        $allowed = (int) $v['count'] < 8;

        // Consultar no consume intentos.
        // Solo un resultado fallido incrementa el contador.
        if ($resultado !== null) {
            if ($resultado === true) {
                $v = ['start' => $now, 'count' => 0];
            } else {
                $v['count'] = (int) $v['count'] + 1;
            }

            rewind($f);
            ftruncate($f, 0);
            fwrite($f, json_encode($v, JSON_THROW_ON_ERROR));
            fflush($f);
        }
        flock($f, LOCK_UN);

        return $allowed;
    } finally {
        fclose($f);
    }
}

function agendaIntentarLogin(string $username, string $password): bool
{
    agendaIniciarSesionSegura();
    $username = trim($username);

    if (strlen($username) < 1 || strlen($username) > 100
        || strlen($password) < 1 || strlen($password) > 4096) {
        return false;
    }

    if (!agendaLoginPermitido($username)) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, username, nombre, rol, activo, password_hash '
        . 'FROM usuarios WHERE username = :username LIMIT 1'
    );

    $stmt->execute([':username' => $username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

    $ok = password_verify(
        $password,
        is_array($u) ? (string) $u['password_hash'] : $dummy
    );

    if (!$ok || !is_array($u) || (int) $u['activo'] !== 1) {
        agendaLoginPermitido($username, false);
        return false;
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('No se pudo renovar la sesión.');
    }

    $_SESSION = [
        'auth_user_id' => (int) $u['id'],
        'auth_issued_at' => time(),
        'auth_last_seen' => time(),
        'auth_password_fingerprint' => hash(
            'sha256',
            (string) $u['password_hash']
        ),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];

    $update = db()->prepare(
        'UPDATE usuarios SET ultimo_login_at=NOW() WHERE id=:id'
    );

    $update->execute([':id' => (int) $u['id']]);

    agendaLoginPermitido($username, true);
    return true;
}
