<?php
declare(strict_types=1);

/**
 * Claves seudonimizadas para ventanas fijas de 15 minutos.
 * No se almacenan nombres, sesiones ni codigos en texto claro.
 */
function r9RegistroRateBuckets(
    string $sessionId,
    string $username,
    string $invitation
): array {
    if ($sessionId === '' || strlen($sessionId) > 128) {
        throw new RuntimeException('R9 session identifier unavailable.');
    }

    $username = strtolower(trim($username));
    $invitation = strtolower(trim($invitation));

    $labels = [
        ['global', 'all', 100],
        ['session', $sessionId, 10],
        ['username', $username, 8],
        ['invitation', $invitation, 8],
    ];

    $buckets = [];

    foreach ($labels as [$type, $value, $limit]) {
        $buckets[] = [
            'hash' => hash(
                'sha256',
                'R9|' . $type . '|' . $value
            ),
            'limit' => $limit,
        ];
    }

    return $buckets;
}

/**
 * Registra el intento antes de validar el formulario.
 * El contador se confirma aunque luego falle el alta.
 * Devuelve false al superar cualquiera de las cuotas.
 */
function r9RegistroConsumirIntento(
    PDO $pdo,
    string $sessionId,
    string $username,
    string $invitation
): bool {
    if ($pdo->inTransaction()) {
        throw new LogicException(
            'R9 rate limiter requires its own transaction.'
        );
    }

    $slot = intdiv(time(), 900);
    $buckets = r9RegistroRateBuckets(
        $sessionId,
        $username,
        $invitation
    );

    $allowed = true;
    $globalCount = 0;

    $pdo->beginTransaction();

    try {
        $upsert = $pdo->prepare(
            'INSERT INTO registro_rate_limits '
            . '(bucket_hash, ventana_slot, intentos) '
            . 'VALUES (:hash, :slot, 1) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'intentos = IF('
            . 'ventana_slot = VALUES(ventana_slot), '
            . 'LEAST(intentos + 1, 1000000), 1'
            . '), '
            . 'ventana_slot = VALUES(ventana_slot)'
        );

        $read = $pdo->prepare(
            'SELECT intentos FROM registro_rate_limits '
            . 'WHERE bucket_hash = :hash '
            . 'AND ventana_slot = :slot FOR UPDATE'
        );

        foreach ($buckets as $i => $bucket) {
            $params = [
                ':hash' => $bucket['hash'],
                ':slot' => $slot
            ];

            $upsert->execute($params);
            $read->execute($params);

            $count = $read->fetchColumn();
            $read->closeCursor();

            if ($count === false) {
                throw new RuntimeException(
                    'R9 rate counter unavailable.'
                );
            }

            if ($i === 0) {
                $globalCount = (int) $count;
            }

            if ((int) $count > $bucket['limit']) {
                $allowed = false;
                break;
            }
        }

        // Limpieza acotada de ventanas anteriores a 24 horas.
        if ($globalCount === 40 || $globalCount === 80) {
            $purge = $pdo->prepare(
                'DELETE FROM registro_rate_limits '
                . 'WHERE ventana_slot < :older LIMIT 200'
            );

            $purge->execute([
                ':older' => $slot - 96
            ]);
        }

        $pdo->commit();

        return $allowed;

    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}
