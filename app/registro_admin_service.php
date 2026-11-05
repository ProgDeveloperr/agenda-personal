<?php
declare(strict_types=1);

function registroAdminExigir(
    PDO $pdo,
    int $adminId,
    bool $bloquear = false
): void {
    if ($adminId <= 0) {
        throw new DomainException('Acceso no autorizado.');
    }

    $sql = "SELECT id FROM usuarios "
         . "WHERE id=:id AND rol='ADMIN' AND activo=1";

    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $q = $pdo->prepare($sql);
    $q->execute([':id' => $adminId]);

    if ($q->fetchColumn() === false) {
        throw new DomainException('Acceso no autorizado.');
    }
}

function registroAdminCrearInvitacion(
    PDO $pdo,
    int $adminId,
    int $horas
): array {
    if (!in_array($horas, [24, 168], true)) {
        throw new DomainException('Vigencia no permitida.');
    }

    $pdo->beginTransaction();

    try {
        registroAdminExigir($pdo, $adminId, true);

        // Las cuotas incluyen invitaciones luego utilizadas
        // o revocadas. La revocacion no reinicia los limites.
        foreach ([
            ['1 HOUR', 3],
            ['24 HOUR', 12]
        ] as [$periodo, $maximo]) {

            $q = $pdo->prepare(
                'SELECT COUNT(*) FROM invitaciones_registro '
                . 'WHERE creado_por_user_id=:id '
                . 'AND created_at >= NOW() - INTERVAL '
                . $periodo
            );

            $q->execute([':id' => $adminId]);

            if ((int) $q->fetchColumn() >= $maximo) {
                throw new DomainException(
                    'Limite de invitaciones alcanzado.'
                );
            }
        }

        $q = $pdo->prepare(
            'SELECT COUNT(*) FROM invitaciones_registro '
            . 'WHERE creado_por_user_id=:id '
            . 'AND utilizado_at IS NULL '
            . 'AND revocada_at IS NULL '
            . 'AND vence_at > UTC_TIMESTAMP()'
        );

        $q->execute([':id' => $adminId]);

        if ((int) $q->fetchColumn() >= 20) {
            throw new DomainException(
                'Demasiadas invitaciones pendientes.'
            );
        }

        $codigo = bin2hex(random_bytes(32));

        // Solo se almacena SHA-256 del codigo.
        $q = $pdo->prepare(
            'INSERT INTO invitaciones_registro '
            . '(token_hash,creado_por_user_id,vence_at) '
            . 'VALUES (:hash,:admin,'
            . 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL '
            . $horas . ' HOUR))'
        );

        $q->execute([
            ':hash' => hash('sha256', $codigo),
            ':admin' => $adminId
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($id <= 0) {
            throw new RuntimeException(
                'No se pudo emitir la invitacion.'
            );
        }

        $pdo->commit();

        // El codigo solamente vuelve en esta respuesta.
        return [
            'id' => $id,
            'codigo' => $codigo,
            'vigencia_horas' => $horas
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function registroAdminRevocarInvitacion(
    PDO $pdo,
    int $adminId,
    int $invitacionId
): void {
    if ($invitacionId <= 0) {
        throw new DomainException('Invitacion no valida.');
    }

    $pdo->beginTransaction();

    try {
        registroAdminExigir($pdo, $adminId, true);

        $q = $pdo->prepare(
            'UPDATE invitaciones_registro '
            . 'SET revocada_at=UTC_TIMESTAMP(), '
            . 'revocada_por_user_id=:admin '
            . 'WHERE id=:id '
            . 'AND utilizado_at IS NULL '
            . 'AND revocada_at IS NULL '
            . 'AND vence_at > UTC_TIMESTAMP()'
        );

        $q->execute([
            ':admin' => $adminId,
            ':id' => $invitacionId
        ]);

        if ($q->rowCount() !== 1) {
            throw new DomainException(
                'Invitacion no disponible para revocar.'
            );
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function registroAdminListarInvitaciones(
    PDO $pdo,
    int $adminId
): array {
    registroAdminExigir($pdo, $adminId);

    // No seleccionar token_hash.
    $q = $pdo->query(
        'SELECT id,creado_por_user_id,vence_at,'
        . 'utilizado_at,revocada_at,created_at,'
        . "CASE "
        . "WHEN utilizado_at IS NOT NULL THEN 'UTILIZADA' "
        . "WHEN revocada_at IS NOT NULL THEN 'REVOCADA' "
        . "WHEN vence_at <= UTC_TIMESTAMP() THEN 'VENCIDA' "
        . "ELSE 'PENDIENTE' END AS estado "
        . 'FROM invitaciones_registro '
        . 'ORDER BY id DESC LIMIT 50'
    );

    return $q->fetchAll(PDO::FETCH_ASSOC);
}
