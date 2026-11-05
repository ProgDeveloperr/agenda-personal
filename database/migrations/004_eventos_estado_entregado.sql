ALTER TABLE eventos_academicos
    MODIFY COLUMN estado
    ENUM(
        'PENDIENTE',
        'ENTREGADO',
        'APROBADO',
        'DESAPROBADO',
        'AUSENTE',
        'CANCELADO'
    )
    NOT NULL
    DEFAULT 'PENDIENTE';

UPDATE configuracion
SET valor = '4'
WHERE clave = 'schema_version';
