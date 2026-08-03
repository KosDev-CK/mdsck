# MDS

Plantilla base para sitios internos con login, roles/permisos por pantalla, invitaciones, 2FA y notificaciones en tiempo real. Pensada para clonarse y reutilizarse en nuevos desarrollos — la parte de plataforma ya está resuelta, solo hace falta construir el contenido de cada módulo nuevo.

## Stack

- Laravel 12 + MySQL (InnoDB forzado, ver `config/database.php`)
- Blade + Livewire 3 + Tailwind v4
- Spatie Laravel Permission (roles = "perfiles", permisos = "pantallas")
- nwidart/laravel-modules (cada proceso/módulo vive aislado bajo `Modules/`)
- Laravel Reverb (WebSockets propios, sin dependencias externas) para notificaciones en tiempo real
- Google2FA (TOTP, compatible con Microsoft/Google Authenticator)

## Autenticación

Sin contraseña: el usuario ingresa su correo, recibe un código de 6 dígitos por email, y opcionalmente confirma con 2FA (TOTP) si lo tiene activado en su perfil. El acceso es solo por invitación — no hay registro público.

Reglas de seguridad: 5 intentos fallidos bloquean la cuenta 5 minutos; 3 ciclos de bloqueo corto escalan a un bloqueo de 24 horas y notifican a los administradores. Solo se permite 1 sesión activa por usuario, y la sesión inactiva expira a las 3 horas (`SESSION_LIFETIME` en `.env`).

## Arranque en desarrollo (WAMP)

```bash
composer install
npm install
cp .env.example .env   # ajusta credenciales de BD
php artisan key:generate
php artisan migrate --seed
npm run build           # o "npm run dev" mientras desarrollas
```

Corre `composer run dev` para levantar en paralelo Reverb, el worker de colas, los logs (`pail`) y Vite.

El seeder crea las pantallas base, el rol **Administrador** y un primer usuario administrador (ver `database/seeders/CoreSeeder.php` — ajusta el correo antes de sembrar en un proyecto nuevo). Como el login es sin contraseña, ese es el único acceso hasta que ese usuario invite a los demás desde "Configuración de acceso".

En dev, los correos se escriben en `storage/logs/laravel.log` (`MAIL_MAILER=log`) — ahí verás el código de acceso y los enlaces de invitación hasta que configures un SMTP real.

## Pruebas

```bash
php artisan test
```

Cubre autenticación passwordless, 2FA, bloqueo de cuenta, sesión única, invitaciones, perfiles/pantallas, pool de conexiones a BD, módulos, notificaciones y bitácora de seguridad.

## Agregar un módulo de contenido nuevo

```bash
php artisan module:make MiModulo
```

Sigue el patrón del módulo `Modules/Ejemplo` (incluido como referencia): su propia migración, modelo, componente Livewire y vista, resuelto por el mismo sistema de perfiles/pantallas del core. Para que aparezca en el menú y sea asignable a un perfil:

1. Crea un registro en `screens` con `module`, `route_name` y `permission_name` (hazlo en el seeder del propio módulo, no en `CoreSeeder`, para que el módulo sea instalable/removible de forma independiente).
2. Da de alta la ruta con el middleware `permission:{permission_name}` en `Modules/MiModulo/routes/web.php`.
3. Corre `php artisan module:seed MiModulo`.

Activar/desactivar módulos instalados: pantalla "Módulos" (`/modules`).

## Pool de conexiones a BD

Pantalla "Conexiones a BD" (`/connections`) permite registrar conexiones adicionales (MySQL, PostgreSQL, SQL Server o APIs externas) que un módulo puede usar en tiempo de ejecución vía `DatabaseConnection::toConnectionConfig()`. El soporte para Oracle (yajra/laravel-oci8) se agrega cuando el primer módulo lo necesite — requiere instalar el Oracle Instant Client en el servidor.

## Despliegue a producción

Ver [`docs/deploy-lemp.md`](docs/deploy-lemp.md).
