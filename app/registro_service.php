<?php
declare(strict_types=1);

function registroValidarDatos(
    string $nombre,
    string $usuario,
    string $clave,
    string $confirmacion,
    string $invitacion
): array {
    $nombre = trim($nombre);
    $usuario = strtolower(trim($usuario));
    $invitacion = strtolower(trim($invitacion));

    if ($nombre === '' || strlen($nombre) > 120
        || preg_match('/[\x00-\x1f\x7f]/', $nombre)) {
        throw new DomainException('Nombre no valido.');
    }

    if (!preg_match('/\A[a-z][a-z0-9._-]{2,31}\z/D', $usuario)) {
        throw new DomainException('Usuario no valido.');
    }

    if (strlen($clave) < 12 || strlen($clave) > 128) {
        throw new DomainException('La contraseña debe tener entre 12 y 128 bytes.');
    }

    if (!hash_equals($clave, $confirmacion)) {
        throw new DomainException('Las contraseñas no coinciden.');
    }

    if (!preg_match('/\A[a-f0-9]{64}\z/D', $invitacion)) {
        throw new DomainException('Invitacion no valida.');
    }

    return [
        'nombre' => $nombre,
        'username' => $usuario,
        'password' => $clave,
        'token_hash' => hash('sha256', $invitacion),
    ];
}

function registroCrearCuentaInvitada(
    PDO $pdo,
    string $nombre,
    string $usuario,
    string $clave,
    string $confirmacion,
    string $invitacion
): int {
    $datos = registroValidarDatos(
        $nombre, $usuario, $clave, $confirmacion, $invitacion
    );

    // No calcular bcrypt antes de validar la invitacion.
    $pdo->beginTransaction();

    try {
        $invite = $pdo->prepare(
            'SELECT id FROM invitaciones_registro '
            . 'WHERE token_hash=:token AND utilizado_at IS NULL '
            . 'AND revocada_at IS NULL AND vence_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE'
        );

        $invite->execute([
            ':token' => $datos['token_hash']
        ]);

        $invitationId = $invite->fetchColumn();

        if ($invitationId === false) {
            throw new DomainException('Invitacion no valida o vencida.');
        }

        $hash = password_hash($datos['password'], PASSWORD_DEFAULT);

        if (!is_string($hash)) {
            throw new RuntimeException('Error al procesar la contraseña.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO usuarios '
            . '(username, nombre, password_hash, rol, activo, '
            . 'ultimo_login_at, created_at, updated_at) '
            . "VALUES (:username, :nombre, :hash, 'USER', 1, "
            . 'NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        $insert->execute([
            ':username' => $datos['username'],
            ':nombre' => $datos['nombre'],
            ':hash' => $hash,
        ]);

        $userId = (int) $pdo->lastInsertId();

        if ($userId <= 0) {
            throw new RuntimeException('No se pudo crear la cuenta.');
        }

        $prefs = $pdo->prepare(
            'INSERT INTO preferencias_usuario '
            . '(user_id, fecha_nacimiento, horizonte_vida_anios, '
            . 'created_at, updated_at) '
            . 'VALUES (:user_id, NULL, NULL, '
            . 'UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        $prefs->execute([':user_id' => $userId]);

        $consume = $pdo->prepare(
            'UPDATE invitaciones_registro '
            . 'SET utilizado_at=UTC_TIMESTAMP(), '
            . 'utilizado_por_user_id=:uid '
            . 'WHERE id=:id AND utilizado_at IS NULL'
        );

        $consume->execute([
            ':uid' => $userId,
            ':id' => $invitationId,
        ]);

        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('Invitacion ya utilizada.');
        }

        $pdo->commit();
        return $userId;

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
