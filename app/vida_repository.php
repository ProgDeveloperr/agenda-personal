<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';


function obtenerConfiguracionVida(
    int $userId,
    string $clave
): ?string {
    $columna = match ($clave) {
        'fecha_nacimiento' =>
            'fecha_nacimiento',

        'horizonte_vida_anios' =>
            'horizonte_vida_anios',

        default =>
            throw new InvalidArgumentException(
                'Clave de configuración de Vida no soportada.'
            ),
    };

    $stmt = db()->prepare("
        SELECT {$columna}
        FROM preferencias_usuario
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    $valor = $stmt->fetchColumn();

    if (
        $valor === false
        || $valor === null
    ) {
        return null;
    }

    return (string) $valor;
}


function guardarConfiguracionVida(
    int $userId,
    string $clave,
    string $valor
): void {
    $columna = match ($clave) {
        'fecha_nacimiento' =>
            'fecha_nacimiento',

        'horizonte_vida_anios' =>
            'horizonte_vida_anios',

        default =>
            throw new InvalidArgumentException(
                'Clave de configuración de Vida no soportada.'
            ),
    };

    $stmt = db()->prepare("
        INSERT INTO preferencias_usuario (
            user_id,
            {$columna}
        )
        VALUES (
            :user_id,
            :valor
        )
        ON DUPLICATE KEY UPDATE
            {$columna} =
                VALUES({$columna})
    ");

    $stmt->execute([
        'user_id' => $userId,
        'valor' => $valor,
    ]);
}


function obtenerFechaNacimientoVida(
    int $userId
): ?string
{
    return obtenerConfiguracionVida(
        $userId,
        'fecha_nacimiento'
    );
}


function guardarFechaNacimientoVida(
    int $userId,
    string $fecha
): void {
    guardarConfiguracionVida(
        $userId,
        'fecha_nacimiento',
        $fecha
    );
}


function obtenerHorizonteVidaAnios(
    int $userId
): int
{
    $valor = obtenerConfiguracionVida(
        $userId,
        'horizonte_vida_anios'
    );

    if (
        $valor === null
        || !ctype_digit($valor)
    ) {
        return 90;
    }

    $anios = (int) $valor;

    /*
     * El schema V1 reserva índices 0..1079,
     * por lo que el calendario actual es
     * deliberadamente de 90 años.
     */
    return $anios === 90
        ? 90
        : 90;
}


function obtenerCalendarioVida(
    int $userId
): array
{
    $stmt = db()->prepare("
        SELECT
            indice_mes,
            nivel,
            nota,
            updated_at
        FROM calendario_vida
        WHERE user_id = :user_id
          AND indice_mes BETWEEN 0 AND 1079
        ORDER BY indice_mes
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    $rows = $stmt->fetchAll();

    $resultado = [];

    foreach ($rows as $row) {
        $resultado[
            (int) $row['indice_mes']
        ] = $row;
    }

    return $resultado;
}


function contarMesesVidaMarcados(
    int $userId
): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM calendario_vida
        WHERE user_id = :user_id
          AND nivel > 0
          AND indice_mes
              BETWEEN 0 AND 1079
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return (int) $stmt->fetchColumn();
}


function alternarMesVida(
    int $userId,
    int $indice
): int {
    if (
        $indice < 0
        || $indice > 1079
    ) {
        throw new InvalidArgumentException(
            'Índice de mes fuera de rango.'
        );
    }

    $pdo = db();

    $pdo->beginTransaction();

    try {

        $stmt = $pdo->prepare("
            SELECT nivel
            FROM calendario_vida
            WHERE user_id = :user_id
              AND indice_mes = :indice
            FOR UPDATE
        ");

        $stmt->execute([
            'user_id' => $userId,
            'indice' => $indice,
        ]);

        $actual = $stmt->fetchColumn();


        if ($actual === false) {

            $nuevoNivel = 1;

            $insert = $pdo->prepare("
                INSERT INTO calendario_vida (
                    user_id,
                    indice_mes,
                    nivel
                )
                VALUES (
                    :user_id,
                    :indice,
                    :nivel
                )
            ");

            $insert->execute([
                'user_id' => $userId,
                'indice' => $indice,
                'nivel' => $nuevoNivel,
            ]);

        } else {

            $nuevoNivel =
                (int) $actual > 0
                    ? 0
                    : 1;

            $update = $pdo->prepare("
                UPDATE calendario_vida
                SET nivel = :nivel
                WHERE user_id = :user_id
                  AND indice_mes = :indice
            ");

            $update->execute([
                'user_id' => $userId,
                'indice' => $indice,
                'nivel' => $nuevoNivel,
            ]);

        }


        $pdo->commit();

        return $nuevoNivel;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*
 * ==========================================================
 * R3-H1 VIDA NOTES
 * ==========================================================
 */


function buscarMesVidaDetalle(
    int $userId,
    int $indice
): array {

    $stmt =
        db()->prepare(
            "
            SELECT
                indice_mes,
                nivel,
                nota,
                updated_at

            FROM calendario_vida

            WHERE
                user_id=:user_id
                AND indice_mes=:indice

            LIMIT 1
            "
        );


    $stmt->execute(
        [
            'user_id' =>
                $userId,

            'indice' =>
                $indice,
        ]
    );


    $fila =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($fila === false) {

        return [
            'indice_mes' =>
                $indice,

            'nivel' =>
                0,

            'nota' =>
                null,

            'updated_at' =>
                null,
        ];
    }


    return $fila;
}


function alternarMesVidaPreservandoNota(
    int $userId,
    int $indice
): int {
    /*
     * alternarMesVida() ya:
     * - valida el rango;
     * - bloquea sólo la fila del owner;
     * - inserta user_id explícito;
     * - actualiza únicamente nivel;
     * - preserva nota.
     *
     * Reutilizarlo evita un ON DUPLICATE KEY
     * que bajo el schema transitorio
     * UNIQUE(indice_mes) podría resolver
     * contra una fila de otro tenant.
     */
    return alternarMesVida(
        $userId,
        $indice
    );
}


function guardarNotaMesVida(
    int $userId,
    int $indice,
    ?string $nota
): array {

    $nota =
        $nota !== null
            ? trim($nota)
            : null;


    if ($nota === '') {
        $nota = null;
    }


    $pdo =
        db();


    $pdo->beginTransaction();


    try {

        if ($nota === null) {

            $stmt =
                $pdo->prepare(
                    "
                    UPDATE calendario_vida

                    SET nota=NULL

                    WHERE
                        user_id=:user_id
                        AND indice_mes=:indice
                    "
                );


            $stmt->execute(
                [
                    'user_id' =>
                        $userId,

                    'indice' =>
                        $indice,
                ]
            );


            /*
             * Si el registro sólo existía por una nota
             * y además está sin marcar, vuelve al estado
             * sparse original.
             */
            $cleanup =
                $pdo->prepare(
                    "
                    DELETE FROM calendario_vida

                    WHERE
                        user_id=:user_id
                        AND indice_mes=:indice
                        AND nivel=0
                        AND nota IS NULL
                    "
                );


            $cleanup->execute(
                [
                    'user_id' =>
                        $userId,

                    'indice' =>
                        $indice,
                ]
            );

        } else {

            /*
             * No usar ON DUPLICATE KEY UPDATE aquí.
             *
             * Mientras producción conserve temporalmente
             * UNIQUE(indice_mes), un duplicate global
             * nunca debe poder actualizar otra fila.
             *
             * Primero bloqueamos exclusivamente la fila
             * del owner. Si no existe, hacemos INSERT
             * explícito; una colisión de otro tenant
             * bajo el schema transitorio falla cerrada.
             */
            $lock =
                $pdo->prepare(
                    "
                    SELECT id

                    FROM calendario_vida

                    WHERE
                        user_id=:user_id
                        AND indice_mes=:indice

                    FOR UPDATE
                    "
                );


            $lock->execute(
                [
                    'user_id' =>
                        $userId,

                    'indice' =>
                        $indice,
                ]
            );


            $id =
                $lock->fetchColumn();


            if ($id === false) {

                $stmt =
                    $pdo->prepare(
                        "
                        INSERT INTO calendario_vida
                        (
                            user_id,
                            indice_mes,
                            nivel,
                            nota
                        )

                        VALUES
                        (
                            :user_id,
                            :indice,
                            0,
                            :nota
                        )
                        "
                    );

            } else {

                $stmt =
                    $pdo->prepare(
                        "
                        UPDATE calendario_vida

                        SET nota=:nota

                        WHERE
                            user_id=:user_id
                            AND indice_mes=:indice
                        "
                    );
            }


            $stmt->execute(
                [
                    'user_id' =>
                        $userId,

                    'indice' =>
                        $indice,

                    'nota' =>
                        $nota,
                ]
            );
        }


        $pdo->commit();


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        throw $e;
    }


    return
        buscarMesVidaDetalle(
            $userId,
            $indice
        );
}


function listarNotasVida(
    int $userId
): array
{
    $stmt =
        db()->prepare(
            "
            SELECT
                indice_mes,
                nivel,
                nota,
                updated_at

            FROM calendario_vida

            WHERE
                user_id=:user_id
                AND nota IS NOT NULL
                AND CHAR_LENGTH(
                    TRIM(nota)
                ) > 0

            ORDER BY
                indice_mes DESC
            "
        );

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


function contarNotasVida(
    int $userId
): int
{
    $stmt =
        db()->prepare(
            "
            SELECT COUNT(*)

            FROM calendario_vida

            WHERE
                user_id=:user_id
                AND nota IS NOT NULL
                AND CHAR_LENGTH(
                    TRIM(nota)
                ) > 0
            "
        );

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return
        (int) $stmt->fetchColumn();
}

