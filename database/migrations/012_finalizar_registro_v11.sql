-- Ejecutar SOLO tras validar migracion estructural 011 y datos v10.
UPDATE configuracion SET valor = '11' WHERE clave = 'schema_version' AND valor = '10';
