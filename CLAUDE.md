# MDS — contexto para Claude Code

Plantilla base (Laravel 12 + Livewire 3 + Tailwind v4) para sitios internos con login, roles/permisos por pantalla, invitaciones, 2FA, branding dinámico, mensajería y bitácora de seguridad. Diseñada explícitamente para **clonarse y reutilizarse** en próximos desarrollos — la parte de plataforma ya está resuelta; lo que se agrega por proyecto es contenido de negocio vía módulos.

Lee esto antes de tocar código en una sesión nueva — evita que tengas que re-explicar el proyecto desde cero.

## Stack

- Laravel 12, PHP 8.3, MySQL (InnoDB forzado en `config/database.php`).
- Blade + Livewire 3 (todas las pantallas son componentes Livewire full-page con `#[Layout('layouts.app')]`, no controladores clásicos).
- Tailwind CSS v4 — sin config JS, todo vía `@theme`/`@custom-variant` en `resources/css/app.css`. Ver "Sistema de diseño" abajo antes de tocar estilos.
- Spatie Laravel Permission — roles = "perfiles" en la UI, permisos = "pantallas" (una pantalla = un permiso).
- nwidart/laravel-modules — cada módulo de contenido vive aislado en `Modules/`, con su propia migración/modelo/Livewire/rutas/seeder.
- Laravel Reverb (WebSockets propios) + Laravel Echo para notificaciones en tiempo real.
- PragmaRX Google2FA (TOTP, compatible Microsoft/Google Authenticator).
- Alpine.js para interactividad ligera (sidebar colapsable, dropdowns, `@entangle` en color pickers).

## Autenticación (sin contraseña)

Flujo: correo → código de 6 dígitos por email (`LoginCodeNotification`) → 2FA TOTP opcional si el usuario lo activó → sesión. No hay registro público, solo invitación (`Invitation` model + `AcceptInvitation` Livewire).

Reglas de seguridad (todas en `App\Models\User` + `EnsureSingleSession` middleware + `SecurityEvent::log(...)`):
- 5 intentos fallidos → bloqueo de 5 min. 3 ciclos de bloqueo corto → bloqueo de 24h + notifica a admins. Lógica centralizada en `App\Services\LoginSecurityManager` (por cuenta).
- **Rate limiting por IP** (`App\Concerns\GuardsAgainstFlooding`, usado por `RequestLoginCode`, `VerifyLoginCode`, `VerifyTwoFactor` y `AcceptInvitation`): máx. `security.max_requests_per_minute` (50 por defecto, `SECURITY_MAX_REQUESTS_PER_MINUTE` en `.env`) solicitudes por IP por acción en la ventana de `security.request_throttle_decay_seconds` (60s). Es independiente del bloqueo por cuenta de arriba — cubre a un atacante que reparte intentos entre muchas cuentas o sin cuenta válida. Se implementa **dentro de cada método Livewire**, no como middleware de ruta — las acciones de un componente Livewire full-page viajan por el endpoint compartido `/livewire/update`, no por la ruta GET nombrada, así que un `throttle` a nivel de ruta no alcanzaría a limitar los envíos de formulario reales (sí hay uno adicional, `throttle:login-pages` en `routes/web.php` + `RateLimiter::for('login-pages', ...)` en `AppServiceProvider`, pero solo cubre la carga de la página, no la acción). Cada bloqueo se registra en la bitácora (`SecurityEvent::RATE_LIMITED`).
- 1 sola sesión activa por usuario (`current_session_id` en `users`, forzado por `App\Http\Middleware\EnsureSingleSession`).
- Sesión inactiva expira a las `SESSION_LIFETIME` horas (`.env`).
- Cada evento relevante (login, fallo, bloqueo, 2FA, logout, revocación, rate limit...) se registra en `security_events` vía `SecurityEvent::log()` — visible en la pantalla "Bitácora de seguridad".

## Modelo de permisos: pantallas = permisos

`App\Models\Screen` es la tabla que impulsa **tanto** el menú lateral **como** la autorización. No hay nada hardcodeado en el sidebar — es 100% data-driven desde `screens` (`name`, `route_name`, `permission_name`, `icon`, `group_label`, `order`, `module`).

Para agregar una pantalla nueva y que aparezca en el menú + se pueda asignar a un perfil: **ver [`docs/agregar-pantallas.md`](docs/agregar-pantallas.md)**. Resumen: crea el registro en `screens` (en el seeder del módulo, no en `CoreSeeder`, salvo que sea pantalla del core), protege la ruta con `middleware('permission:<permission_name>')`, y asígnala a un perfil desde la pantalla "Perfiles" (`/roles`) o vía `$role->givePermissionTo(...)`.

El rol **Administrador** siempre tiene todos los permisos porque `CoreSeeder::run()` corre `$adminRole->syncPermissions(Screen::pluck('permission_name'))` cada vez que se siembra.

**Pantalla de inicio por usuario**: cada usuario puede elegir desde "Mi perfil" a qué pantalla llegar después de iniciar sesión (`users.home_screen_id`, FK nullable a `screens`, `nullOnDelete`). `User::homeRouteName()` resuelve el nombre de ruta a usar — vuelve a `'dashboard'` si no hay preferencia, si la pantalla elegida se desactivó, o si el usuario ya no tiene el permiso correspondiente. Los 4 puntos donde se completa un login (`VerifyLoginCode`, `VerifyTwoFactor::verify`/`verifyWithRecoveryCode`, `AcceptInvitation`) redirigen con `redirect()->route($user->homeRouteName())` en vez de un `route('dashboard')` fijo. El logo/nombre del sitio en el encabezado del sidebar (`partials/sidebar.blade.php`) también enlaza ahí, así que hace de botón "ir a inicio" además de identidad visual.

## Estructura de carpetas relevante

```
app/Livewire/           Un subdirectorio por área funcional; cada clase es una pantalla completa
  Auth/                 RequestLoginCode, VerifyLoginCode, VerifyTwoFactor, AcceptInvitation
  Branding/Manage.php   Pantalla /branding
  Connections/Manage.php Pool de conexiones a BD (/connections)
  Invitations/Manage.php "Configuración de acceso" (/invitations) — invitar, desactivar/reactivar, revocar 2FA
  Messages/Send.php     Enviar avisos a usuarios (/messages)
  Modules/Manage.php    Activar/desactivar módulos instalados (/modules)
  Notifications/Bell.php Campanita del topbar (componente embebido, no pantalla propia)
  Profile/Show.php      "Mi perfil" (/profile)
  Roles/Manage.php      "Perfiles" (/roles) — crear rol, marcar pantallas
  SecurityLog/Index.php Bitácora (/security-log)
  UserRoles/Manage.php  "Perfiles por usuario" (/user-roles)

app/Models/             Screen, User, Invitation, LoginCode, SecurityEvent, DatabaseConnection, SiteSetting, BrandingPreset
app/Notifications/      LoginCodeNotification, UserInvitationNotification, AccountLockedNotification, AdminMessageNotification
app/Services/           LoginSecurityManager.php — bloqueo por cuenta (intentos fallidos, ciclos, lockout)
app/Concerns/           GuardsAgainstFlooding.php — rate limiting por IP, usado por los componentes de Auth/
app/Console/Commands/   CleanTestData.php (mds:clean-test-data)
app/Http/Middleware/    EnsureSingleSession.php

Modules/Ejemplo/        Módulo de referencia — copia su patrón para módulos nuevos, no es parte del core

resources/views/
  components/ui/        Button, Card, Badge, Alert, Avatar, theme-toggle — set de componentes propio (NO Tailwind Plus ni kit de terceros)
  partials/             sidebar, topbar, branding-head (inyecta CSS vars de branding en <head>)
  livewire/             Vistas de cada componente, mismo árbol que app/Livewire/
  vendor/mail/          Plantilla de correo publicada y personalizada (logo dinámico en el header)

resources/css/app.css   @theme con tokens de color, reglas SIN @layer para inputs/focus/cursor (ver nota abajo)
lang/es.json            Traducciones literales del boilerplate de Laravel Mail ("Regards,", etc.) — necesario para que los correos salgan 100% en español

config/mds.php          admin_email / admin_name (vía .env MDS_ADMIN_EMAIL / MDS_ADMIN_NAME) — usado por CoreSeeder y por mds:clean-test-data

database/seeders/
  CoreSeeder.php            Pantallas base + rol Administrador + usuario admin (idempotente, updateOrCreate)
  BrandingPresetSeeder.php  3 presets is_system=true: Predeterminado, LandIT, Corporativo Kosmos

docs/
  deploy-lemp.md                    Despliegue completo a producción (Nginx+MySQL+PHP-FPM+Supervisor+Reverb)
  nuevo-proyecto-desde-plantilla.md Cómo clonar esta plantilla para un sitio nuevo sin tocar este repo
  limpiar-datos-de-prueba.md        Uso de mds:clean-test-data
  agregar-pantallas.md              Cómo agregar pantallas/módulos y asignarlas a perfiles
```

## Branding dinámico (sin recompilar assets)

`SiteSetting` (singleton, `firstOrCreate(['id' => 1], ...)`) guarda 8 colores (5 semánticos: primary/success/danger/warning/info + 3 de "chrome": topbar/sidebar_header/sidebar_body), logo y favicon. Se inyectan como variables CSS por request en `resources/views/partials/branding-head.blade.php`, sobrescribiendo los tokens `@theme` de Tailwind en `:root` — cualquier combinación de colores es posible en runtime sin tocar CSS compilado.

`BrandingPreset` guarda configuraciones completas (los mismos 8 colores) reutilizables y aplicables desde la pantalla `/branding`. Los presets `is_system=true` (sembrados) no se pueden borrar desde la UI.

El nombre del sitio y la URL **no** son parte de este branding dinámico — siguen viviendo en `.env` (`APP_NAME`, `APP_URL`), se ajustan por entorno al desplegar.

El color de la barra superior solo aplica en modo claro (`.dark` lo sobrescribe a gris neutro en `branding-head.blade.php`); los 2 colores del sidebar aplican siempre porque el sidebar siempre es oscuro, sin importar el tema.

## Sistema de diseño (importante antes de tocar CSS)

- Componentes propios en `resources/views/components/ui/` — decisión explícita de no usar un kit de terceros (Tailwind Plus es solo markup+Tailwind, no un componente reusable de verdad).
- Colores primitivos (`bg-primary`, `text-danger/10`, etc.) vienen de los tokens `@theme` en `app.css`, que a su vez pueden sobrescribirse en runtime por `SiteSetting` (ver arriba) — nunca hardcodees un color de marca (`indigo-600`, `bg-red-500`...) en un componente nuevo, usa los tokens semánticos.
- **Regla de cascade layers de Tailwind v4** (ya mordió una vez, dejar documentado): Tailwind emite `@theme` dentro de `@layer theme` y utilidades dentro de `@layer utilities`. **CSS sin capa (`@layer`) siempre gana sobre cualquier capa nombrada**, sin importar orden de aparición ni especificidad del selector. Por eso el padding/focus-ring de inputs y el `cursor:pointer` de botones en `app.css` están **deliberadamente fuera de cualquier `@layer`** — si los metes dentro de `@layer base` u otro, Tailwind los va a pisar silenciosamente y no vas a ver el efecto.
- Dark mode: `@custom-variant dark` + clase `.dark` en `<html>` (toggle guardado en `localStorage`, ver `theme-toggle.blade.php`). Todas las pantallas ya migraron sus `<x-ui.card>` y fondos a variantes `dark:` — si agregas una pantalla nueva, verifica en dark mode antes de darla por terminada (varias pantallas tuvieron tarjetas blancas olvidadas en un primer pase).

## Notificaciones en tiempo real

`notifications` table nativa de Laravel (uuid, morphs, `data` json, `read_at`) cubre todo — no hay tabla propia. `App\Livewire\Notifications\Bell` expone `unreadCount`/`totalCount`/`readCount`, `markAsRead`/`markAllAsRead`/`deleteNotification` (solo se puede borrar una notificación ya leída).

Push en vivo: Reverb (puerto 8080 en dev) + Echo en `layouts/app.blade.php` escuchando el canal privado `App.Models.User.{id}` → dispara el evento Livewire `notification-received` → `Bell::refresh()`. **Esto requiere que Reverb esté corriendo** (`composer run dev` lo levanta junto con la cola y Vite); si no está corriendo, el dato en BD es correcto pero el badge no se actualiza hasta el siguiente `mount()` (recarga de página). No es un bug si el badge no aparece en una pestaña ya abierta sin Reverb activo — es el comportamiento esperado sin el push.

`AdminMessageNotification` (vía `App\Livewire\Messages\Send`, pantalla `/messages`) es el mecanismo para que un admin envíe avisos a usuarios seleccionados o a todos los activos.

## Localización de correos

Laravel usa las vistas Markdown por defecto de `Illuminate\Notifications` (`@lang('Regards,')` etc.) como si fueran claves de traducción literales en inglés. `lang/es.json` (raíz del proyecto) las traduce — si agregas una notificación nueva con `->markdown(...)` o texto libre en inglés dentro de un `MailMessage`, revisa que no vuelva a aparecer texto sin traducir.

El logo en el header de los correos (`resources/views/vendor/mail/html/message.blade.php`, publicada y editada) usa `SiteSetting::current()->logoUrl()`. En dev, con dominio `.test` no resoluble públicamente, el logo se ve roto en clientes de correo externos — es esperado, funciona en cuanto el dominio es público.

## Comandos útiles de este proyecto

```bash
composer run dev              # Reverb + queue:listen + pail (logs) + vite, todo junto
php artisan test              # suite completa
php artisan mds:clean-test-data [--keep-email=] [--with-connections] [--force]
                               # ver docs/limpiar-datos-de-prueba.md
php artisan module:make X     # nuevo módulo de contenido
php artisan module:seed X     # sembrar el seeder de un módulo
```

## Flujos documentados aparte (no repetir aquí, leer el doc)

- **Nuevo proyecto desde esta plantilla** → [`docs/nuevo-proyecto-desde-plantilla.md`](docs/nuevo-proyecto-desde-plantilla.md)
- **Agregar pantallas y asignarlas a perfiles** → [`docs/agregar-pantallas.md`](docs/agregar-pantallas.md)
- **Limpiar datos de prueba antes de producción** → [`docs/limpiar-datos-de-prueba.md`](docs/limpiar-datos-de-prueba.md)
- **Desplegar a LEMP en producción** → [`docs/deploy-lemp.md`](docs/deploy-lemp.md)

## Convenciones al trabajar en este repo

- Componentes Livewire full-page, no controladores — sigue el patrón existente por área funcional.
- Nada de kits de terceros para UI — extiende `resources/views/components/ui/`.
- Todo string visible al usuario final va en español (la app es 100% en español, `APP_LOCALE=es`).
- Cambios de color van a través de los tokens `@theme`/`SiteSetting`, nunca hardcodeados.
- Corre `php artisan test` antes de dar por terminada cualquier tarea — la suite cubre auth, 2FA, bloqueo, sesión única, invitaciones, perfiles/pantallas, branding, mensajería, bitácora y el comando de limpieza.
- Este repo (`mds`) es la plantilla maestra — para un sitio nuevo, clónalo aparte (ver doc de arriba) en vez de reutilizar esta misma base de datos/instalación.
- Herramientas de navegador (`mcp__Claude_Browser__*` / Chrome) pueden no estar disponibles en todas las sesiones — si no lo están, verifica UI con `php artisan test` + Livewire component tests + `tinker`, y dilo explícitamente en vez de asumir verificación visual.

## Pendiente (no urgente, retomar solo si se pide)

- Componentes de formulario reutilizables (`Input`/`Toggle`) y patrones (`Table`, `Modal`, `Pagination`, `Stat tile`, `Empty state`) — quedaron pendientes tras construir los primitivos (Button/Card/Badge/Alert/Avatar); cada pantalla nueva por ahora resuelve sus propios inputs/tablas inline.
