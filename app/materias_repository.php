<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';


function listarMaterias(
    int $userId
): array {
    $stmt = db()->prepare("
        SELECT
            id,
            nombre,
            codigo,
            cuatrimestre,
            turno,
            comision,
            docente,
            color,
            activa,
            created_at,
            updated_at
        FROM materias
        WHERE user_id = :user_id
        ORDER BY
            activa DESC,
            cuatrimestre IS NULL,
            cuatrimestre,
            nombre
    ");

    $stmt->execute([
        'user_id' => $userId,
    ]);

    return $stmt->fetchAll();
}


function buscarMateria(
    int $userId,
    int $id
): ?array {
    $stmt = db()->prepare("
        SELECT
            id,
            nombre,
            codigo,
            cuatrimestre,
            turno,
            comision,
            docente,
            color,
            activa
        FROM materias
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
    ]);

    $materia = $stmt->fetch();

    return $materia !== false
        ? $materia
        : null;
}


function crearMateria(
    int $userId,
    string $nombre,
    ?string $codigo,
    ?int $cuatrimestre,
    ?string $turno,
    ?string $comision,
    ?string $docente,
    string $color
): int {
    $stmt = db()->prepare("
        INSERT INTO materias (
            user_id,
            nombre,
            codigo,
            cuatrimestre,
            turno,
            comision,
            docente,
            color,
            activa
        )
        VALUES (
            :user_id,
            :nombre,
            :codigo,
            :cuatrimestre,
            :turno,
            :comision,
            :docente,
            :color,
            1
        )
    ");

    $stmt->execute([
        'user_id' => $userId,
        'nombre' => $nombre,
        'codigo' => $codigo,
        'cuatrimestre' => $cuatrimestre,
        'turno' => $turno,
        'comision' => $comision,
        'docente' => $docente,
        'color' => $color,
    ]);

    return (int) db()->lastInsertId();
}


function actualizarMateria(
    int $userId,
    int $id,
    string $nombre,
    ?string $codigo,
    ?int $cuatrimestre,
    ?string $turno,
    ?string $comision,
    ?string $docente,
    string $color
): bool {
    $stmt = db()->prepare("
        UPDATE materias
        SET
            nombre = :nombre,
            codigo = :codigo,
            cuatrimestre = :cuatrimestre,
            turno = :turno,
            comision = :comision,
            docente = :docente,
            color = :color
        WHERE id = :id
          AND user_id = :user_id
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
        'nombre' => $nombre,
        'codigo' => $codigo,
        'cuatrimestre' => $cuatrimestre,
        'turno' => $turno,
        'comision' => $comision,
        'docente' => $docente,
        'color' => $color,
    ]);

    return $stmt->rowCount() === 1;
}


function cambiarEstadoMateria(
    int $userId,
    int $id,
    bool $activa
): bool {
    $stmt = db()->prepare("
        UPDATE materias
        SET activa = :activa
        WHERE id = :id
          AND user_id = :user_id
    ");

    $stmt->execute([
        'id' => $id,
        'user_id' => $userId,
        'activa' => $activa ? 1 : 0,
    ]);

    return $stmt->rowCount() === 1;
}


// ARK3360_R36H_MATERIA_DELETE_REPOSITORY
function eliminarMateriaSeguraR36H(int $userId, int $id): array
{
    if ($userId <= 0 || $id <= 0) {
        throw new InvalidArgumentException('Identificador inválido.');
    }

    $pdo = db();
    if ($pdo->inTransaction()) {
        throw new RuntimeException('No se admite una eliminación anidada.');
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT id FROM materias '
            . 'WHERE id=:id AND user_id=:user_id FOR UPDATE'
        );
        $lock->execute(['id' => $id, 'user_id' => $userId]);

        if ($lock->fetchColumn() === false) {
            throw new InvalidArgumentException(
                'La materia no existe o no te pertenece.'
            );
        }

        $contar = static function (string $tabla) use ($pdo, $id, $userId): int {
            if (!in_array(
                $tabla,
                ['eventos_academicos', 'tareas', 'series_tareas'],
                true
            )) {
                throw new LogicException('Tabla no autorizada.');
            }

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . $tabla
                . ' WHERE materia_id=:id AND user_id=:user_id'
            );
            $stmt->execute(['id' => $id, 'user_id' => $userId]);
            return (int) $stmt->fetchColumn();
        };

        // Fail closed ante vinculaciones incorrectas entre usuarios.
        foreach (['eventos_academicos', 'tareas', 'series_tareas'] as $tabla) {
            $cruce = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . $tabla
                . ' WHERE materia_id=:id AND user_id<>:user_id'
            );
            $cruce->execute(['id' => $id, 'user_id' => $userId]);

            if ((int) $cruce->fetchColumn() !== 0) {
                throw new RuntimeException(
                    'Se detectaron referencias de otros usuarios. '
                    . 'El borrado fue bloqueado por seguridad.'
                );
            }
        }

        $eventos = $contar('eventos_academicos');

        if ($eventos > 0) {
            throw new InvalidArgumentException(
                'No se puede eliminar esta materia: tiene ' . $eventos
                . ' evento(s) académico(s) asociado(s). '
                . 'Eliminá primero esos eventos desde Agenda, '
                . 'o conservá la materia desactivada.'
            );
        }

        $tareas = $contar('tareas');
        $series = $contar('series_tareas');

        $borrar = $pdo->prepare(
            'DELETE FROM materias WHERE id=:id AND user_id=:user_id'
        );
        $borrar->execute(['id' => $id, 'user_id' => $userId]);

        if ($borrar->rowCount() !== 1) {
            throw new RuntimeException('No se pudo verificar la eliminación.');
        }

        // MariaDB aplica SET NULL a tareas y series relacionadas.
        $pdo->commit();
        return ['tareas' => $tareas, 'series' => $series];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
