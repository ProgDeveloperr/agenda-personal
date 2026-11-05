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
