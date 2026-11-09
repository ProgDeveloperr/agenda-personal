# Despliegue de Agenda Personal

## Alcance y advertencias

Este repositorio contiene el código de la aplicación y las migraciones, **no** los datos de
producción, secretos, sesiones ni respaldos. No es todavía un instalador Docker listo
para ejecutar con un único comando: la configuración de Apache/PHP, MariaDB, proxy HTTPS,
volúmenes y servicios programados debe prepararse y validarse para cada instancia.

La instalación original utiliza rutas del contenedor como `/var/www/app` (código de negocio),
`/var/www/html` (document root PHP) y `/run/agenda/agenda.env` (configuración privada).
Las rutas de host, volúmenes, redes, servicios systemd y proxies NO son portables sin adaptación.

## Requisitos

- PHP 8.2 o posterior, con `pdo_mysql` y `sodium`, más extensiones requeridas por el entorno.
- Servidor web con document root apuntando exclusivamente a `public/`; no exponer `app/`.
- MariaDB compatible, esquema y migraciones revisados y aplicados en orden.
- Proxy HTTPS confiable que establezca `X-Forwarded-Proto: https` y evite que clientes
  externos inyecten cabeceras de proxy; probar también cookies y redirecciones.
- Archivo privado `/run/agenda/agenda.env` legible por PHP y no accesible por HTTP.
- Trabajos programados del generador de notificaciones, recurrencias y otros servicios
  según los scripts del entorno de despliegue; no se incluyen los timers productivos.

## Configuración privada

`.env.example` es **solo una plantilla**; copiar sus claves a un archivo privado que se
monte **dentro del contenedor** como `/run/agenda/agenda.env`. No colocar el archivo real
en `public/`, ni añadirlo a Git. Variables leídas por `app/config.php`:
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. `app/public_host.php`
requiere `AGENDA_PUBLIC_HOST` para las rutas de autenticación. Debe ser un dominio sin
esquema ni puerto, por ejemplo `agenda.example.com`, válido para el proxy HTTPS.

`app/mfa_storage.php` necesita `AGENDA_MFA_KEY_HEX` para descifrar secretos TOTP:
64 caracteres hexadecimales aleatorios (32 bytes), generados de forma segura.
**Guardar copias protegidas de esta clave**: cambiarla o perderla puede inutilizar
secretos MFA existentes. No utilizar literalmente los valores de `.env.example`.

La aplicación actual usa una ruta **fija** `/run/agenda/agenda.env`, aunque el nombre
del archivo exterior pueda ser diferente. No asumir que una variable `AGENDA_ENV_FILE`
modifica automáticamente esa ruta interna.

## Base de datos y migraciones

La base utilizada por el código es `agenda_personal`. Las migraciones se conservan
bajo `database/migrations/`, pero no ejecutar indiscriminadamente todos los archivos
sobre una base existente. Aplicarlas en orden en una **instancia nueva**, después de
verificar el esquema inicial y los procedimientos de bootstrap; primero realizar un
respaldo restaurable. Algunas migraciones no actualizan `configuracion.schema_version`.
La migración `014_r36j2_notificaciones_exclusiones.sql` crea huellas para evitar la
regeneración de notificaciones eliminadas y deja la versión de esquema en 11.

## Recursos web

Flatpickr 4.6.13 se distribuye localmente en
`public/assets/vendor/flatpickr-4.6.13/` con su licencia MIT. También se versiona
`public/manifest.webmanifest` y los íconos utilizados por la interfaz.

## Seguridad antes de exponer a Internet

- No publicar claves, tokens de invitación, bases de datos, volcados, respaldos ni sesiones.
- Mantener la autenticación, MFA, reglas de proxy, límites de intentos y CSRF habilitados.
- Comprobar el aislamiento de datos por `user_id` y el flujo de invitaciones en
  una instalación de prueba; una auditoría estática no sustituye estas pruebas.
- Configurar TLS, backups cifrados y un procedimiento de restauración probado.
- No usar la instancia productiva para ensayar migraciones destructivas.

## Copias de seguridad y restauración

El respaldo de código en GitHub **no** contiene la base de datos, archivos privados ni
la clave MFA. Resguardar separadamente el volcado SQL, código desplegado, volúmenes
necesarios, configuración del host y secretos. Verificar SHA-256, almacenar una copia
fuera del servidor y ejecutar un ensayo de restauración aislado antes del lanzamiento.

## Estado del despliegue

La versión publicada corresponde al código revisado del proyecto existente. Para
crear una nueva instancia se necesita documentar y probar una plantilla de Docker
Compose ajustada a la infraestructura concreta. El `compose.yml` **productivo** no
se publica sin revisión de secretos y rutas privadas.
