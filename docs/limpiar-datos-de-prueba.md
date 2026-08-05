# Limpiar datos de prueba antes de usar un proyecto en producción

Si un proyecto basado en esta plantilla acumuló usuarios, invitaciones, mensajes o bitácora de prueba durante el desarrollo y quieres dejarlo 100% listo para el primer uso real —sin perder la configuración del administrador base—, usa el comando:

```bash
php artisan mds:clean-test-data
```

## Qué hace

Pide confirmación y luego, dentro de una transacción:

- **Conserva** al usuario cuyo correo coincide con `MDS_ADMIN_EMAIL` (o el que pases con `--keep-email=`), con su rol Administrador, contraseña/2FA y demás datos de identidad intactos. Solo le reinicia el estado transitorio de seguridad: intentos fallidos, bloqueo, sesión activa, último login.
- **Borra**: todos los demás usuarios; todos los perfiles/roles distintos de "Administrador" (y sus permisos asociados); invitaciones; bitácora de seguridad completa; notificaciones (mensajes de la campanita); códigos de acceso (OTP); sesiones activas de todos los usuarios (fuerza a volver a iniciar sesión); conexiones a BD registradas en "Conexiones a BD"; y presets de branding personalizados (no de sistema).
- **Restablece** los 8 colores de `site_settings` al preset "Predeterminado" (no toca logo/favicon subidos).
- También limpia las tablas técnicas `cache`, `jobs` y `failed_jobs` si existen.
- **No toca** (a propósito, porque no son "datos de prueba" sino configuración real de la plantilla): el catálogo de pantallas (`screens`), el rol Administrador y sus permisos, ni los 3 presets de branding de sistema (Predeterminado, LandIT, Corporativo Kosmos).

## Opciones

| Opción | Para qué |
|---|---|
| `--keep-email=otro@correo.com` | Conservar un usuario distinto al de `MDS_ADMIN_EMAIL`. |
| `--force` | Salta la confirmación interactiva (para correrlo en un script/CI). |

## Cuándo usarlo

- **No es necesario** al clonar la plantilla para un proyecto nuevo — una base de datos nueva ya nace vacía con solo el administrador (ver [`docs/nuevo-proyecto-desde-plantilla.md`](nuevo-proyecto-desde-plantilla.md)).
- **Sí es útil** cuando el propio proyecto donde estuviste desarrollando (por ejemplo, si `mds` termina siendo el sitio real de LandIT) va a pasar a uso real y quieres borrar todo lo que fue solo prueba durante la construcción, conservando el administrador y la configuración de branding ya definida.

## Advertencia

Esta acción **no se puede deshacer**. Corre un respaldo de la base de datos antes si tienes cualquier duda:

```bash
mysqldump -u root nombre_de_la_bd > respaldo-antes-de-limpiar.sql
```
