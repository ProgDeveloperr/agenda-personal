<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mfa_totp.php';
require_once __DIR__ . '/mfa_storage.php';

function agendaMfaEnabled(int $userId): bool
{
    $stmt = db()->prepare(
        'SELECT enabled_at FROM usuario_mfa
         WHERE user_id = :id LIMIT 1'
    );

    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row)
        && $row['enabled_at'] !== null;
}

function agendaMfaBeginPending(array $user): void
{
    if (
        session_status() !== PHP_SESSION_ACTIVE
        || !session_regenerate_id(true)
    ) {
        throw new RuntimeException(
            'No se pudo iniciar el segundo factor.'
        );
    }

    $_SESSION = [
        'mfa_pending_user_id' =>
            (int) $user['id'],

        'mfa_pending_password_fingerprint' =>
            hash(
                'sha256',
                (string) $user['password_hash']
            ),

        'mfa_pending_since' => time(),
        'mfa_pending_failures' => 0,
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

function agendaMfaPending(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $id = $_SESSION['mfa_pending_user_id'] ?? null;
    $since = $_SESSION['mfa_pending_since'] ?? null;
    $failures = $_SESSION['mfa_pending_failures'] ?? null;

    $fp = $_SESSION[
        'mfa_pending_password_fingerprint'
    ] ?? null;

    if (
        !is_int($id)
        || $id < 1
        || !is_int($since)
        || !is_int($failures)
        || !is_string($fp)
        || strlen($fp) !== 64
        || $since > time()
        || time() - $since > 300
        || $failures >= 5
    ) {
        unset(
            $_SESSION['mfa_pending_user_id'],
            $_SESSION['mfa_pending_since'],
            $_SESSION['mfa_pending_failures'],
            $_SESSION[
                'mfa_pending_password_fingerprint'
            ]
        );

        return false;
    }

    return true;
}

function agendaMfaVerifyPending(
    string $provided
): bool {
    if (!agendaMfaPending()) {
        return false;
    }

    $uid = $_SESSION['mfa_pending_user_id'];

    $fingerprint = $_SESSION[
        'mfa_pending_password_fingerprint'
    ];

    $provided = trim($provided);

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT
                m.secret_nonce,
                m.secret_cipher,
                m.last_used_step,
                m.failed_count,
                m.blocked_until,
                m.enabled_at,
                (m.blocked_until > NOW()) AS is_blocked,
                u.username,
                u.password_hash,
                u.activo,
                u.rol
             FROM usuario_mfa m
             JOIN usuarios u ON u.id = m.user_id
             WHERE m.user_id = :id
             FOR UPDATE'
        );

        $stmt->execute([':id' => $uid]);

        $mfa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($mfa)
            || $mfa['enabled_at'] === null
            || (int) $mfa['activo'] !== 1
            || $mfa['rol'] !== 'ADMIN'
            || !hash_equals(
                $fingerprint,
                hash(
                    'sha256',
                    (string) $mfa['password_hash']
                )
            )
        ) {
            $pdo->rollBack();

            unset($_SESSION['mfa_pending_user_id']);

            return false;
        }

        if ((int) $mfa['is_blocked'] === 1) {
            $pdo->rollBack();
            return false;
        }

        $step = null;
        $recoveryId = null;

        if (
            preg_match(
                '/\A[0-9]{6}\z/D',
                $provided
            ) === 1
        ) {
            $secret = agendaMfaDecryptSecret(
                (string) $mfa['secret_nonce'],
                (string) $mfa['secret_cipher']
            );

            $step = agendaTotpVerify(
                $secret,
                $provided,
                time(),
                (int) $mfa['last_used_step']
            );

            sodium_memzero($secret);

        } elseif (
            preg_match(
                '/\A(?:[0-9A-Fa-f]{4}-?){7}[0-9A-Fa-f]{4}\z/D',
                $provided
            ) === 1
        ) {
            $hash = agendaMfaRecoveryHash(
                $provided
            );

            $q = $pdo->prepare(
                'SELECT id
                 FROM usuario_mfa_recuperacion
                 WHERE user_id = :id
                   AND code_hash = :hash
                   AND used_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );

            $q->execute([
                ':id' => $uid,
                ':hash' => $hash,
            ]);

            $found = $q->fetchColumn();

            if ($found !== false) {
                $recoveryId = (int) $found;
            }
        }

        if (
            $step === null
            && $recoveryId === null
        ) {
            $count = $mfa['blocked_until'] !== null
                ? 1
                : ((int) $mfa['failed_count'] + 1);

            $q = $pdo->prepare(
                'UPDATE usuario_mfa
                 SET failed_count = :count,
                     blocked_until =
                       CASE
                         WHEN :threshold >= 5
                         THEN DATE_ADD(
                             NOW(), INTERVAL 10 MINUTE
                         )
                         ELSE NULL
                       END
                 WHERE user_id = :id'
            );

            $q->execute([
                ':count' => $count,
                ':threshold' => $count,
                ':id' => $uid,
            ]);

            $pdo->commit();

            $_SESSION['mfa_pending_failures']++;

            return false;
        }

        if ($recoveryId !== null) {
            $q = $pdo->prepare(
                'UPDATE usuario_mfa_recuperacion
                 SET used_at = NOW()
                 WHERE id = :id
                   AND used_at IS NULL'
            );

            $q->execute([
                ':id' => $recoveryId,
            ]);

            if ($q->rowCount() !== 1) {
                throw new RuntimeException(
                    'Recovery code already used'
                );
            }
        }

        if ($step !== null) {
            $q = $pdo->prepare(
                'UPDATE usuario_mfa
                 SET last_used_step = :step,
                     failed_count = 0,
                     blocked_until = NULL
                 WHERE user_id = :id'
            );

            $q->execute([
                ':step' => $step,
                ':id' => $uid,
            ]);

        } else {
            $q = $pdo->prepare(
                'UPDATE usuario_mfa
                 SET failed_count = 0,
                     blocked_until = NULL
                 WHERE user_id = :id'
            );

            $q->execute([
                ':id' => $uid,
            ]);
        }

        $q = $pdo->prepare(
            'UPDATE usuarios
             SET ultimo_login_at = NOW()
             WHERE id = :id'
        );

        $q->execute([':id' => $uid]);

        $pdo->commit();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Session renewal failed'
            );
        }

        $now = time();

        $_SESSION = [
            'auth_user_id' => $uid,
            'auth_issued_at' => $now,
            'auth_last_seen' => $now,

            'auth_password_fingerprint' =>
                hash(
                    'sha256',
                    (string) $mfa['password_hash']
                ),

            'auth_mfa_verified_user_id' => $uid,
            'auth_mfa_verified_at' => $now,

            'csrf_token' =>
                bin2hex(random_bytes(32)),

            'r8_welcome_pending' => true,
        ];

        if (function_exists('agendaLoginPermitido')) {
            agendaLoginPermitido(
                (string) $mfa['username'],
                true
            );
        }

        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}
