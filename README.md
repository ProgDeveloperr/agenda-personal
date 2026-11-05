# Agenda Personal

Aplicación web multiusuario para la organización académica y personal.

## Funcionalidades

- Registro de usuarios mediante invitaciones administradas.
- Autenticación y roles `ADMIN` y `USER`.
- Aislamiento de datos por usuario.
- Gestión de materias, tareas y eventos académicos.
- Calendario, vida personal y notificaciones.
- Tareas recurrentes y paneles de seguimiento.
- Interfaz responsive con modo claro y oscuro.

## Tecnologías

PHP 8.2, MariaDB, JavaScript, HTML, CSS, Docker y systemd.

## Arquitectura

- `app/`: lógica de negocio, seguridad y acceso a datos.
- `public/`: páginas y recursos de la interfaz.
- `database/`: esquema y migraciones versionadas.

## Seguridad

El repositorio no debe incluir bases de datos productivas,
credenciales, respaldos, registros de usuarios ni archivos `.env`.

La aplicación utiliza configuración de entorno y requiere una
instalación propia de PHP y MariaDB.

## Estado

Versión productiva v11. Registro mediante invitación y aislamiento
multiusuario validados funcionalmente.

Desarrollado por [ProgDeveloperr](https://github.com/ProgDeveloperr).

## Configuración

Definir las variables de entorno según `.env.example`. Las credenciales y bases de datos productivas no forman parte del repositorio. El código utiliza rutas de contenedor que deben adaptarse al entorno de despliegue.
