# Crear un nuevo proyecto a partir de esta plantilla

Esta plantilla (MDS) está diseñada para clonarse tal cual y arrancar un sitio nuevo sin tocar código de la "plataforma" (login, 2FA, perfiles/pantallas, invitaciones, notificaciones, branding, bitácora). Solo desarrollas el contenido específico del nuevo sitio.

Este documento asume que quieres dejar **este** repositorio (`mds`) intacto — sigue siendo el proyecto base para agregar funciones nuevas a futuro — y crear una copia independiente para el otro sitio.

## 1. Copiar el repositorio

```bash
cd C:\wamp64\www
git clone C:\wamp64\www\mds nombre-del-sitio-nuevo
cd nombre-del-sitio-nuevo
git remote remove origin
# si el nuevo proyecto va a vivir en su propio repo remoto:
git remote add origin <url-del-nuevo-repo>
```

Clonar en vez de copiar carpeta a carpeta conserva el historial de commits de la plantilla como punto de partida — útil si luego quieres comparar qué le agregaste encima.

## 2. Base de datos propia

Crea una base de datos MySQL **nueva y distinta** a la de `mds` (nunca reutilices la misma BD entre proyectos):

```sql
CREATE DATABASE nombre_del_sitio_nuevo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Configurar `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Ajusta como mínimo:

- `APP_NAME`, `APP_URL` — nombre y dominio del sitio nuevo.
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — la BD que creaste en el paso 2.
- `MDS_ADMIN_EMAIL`, `MDS_ADMIN_NAME` — quién será el primer (y único, hasta que invite a otros) administrador de este sitio. `CoreSeeder` lee estas dos variables — **no** edites el seeder.
- `MAIL_*` — SMTP del sitio nuevo (o déjalo en `MAIL_MAILER=log` mientras desarrollas, como aquí).
- En producción: genera `REVERB_APP_ID/KEY/SECRET` nuevos y distintos a los de `mds` (ver [`docs/deploy-lemp.md`](deploy-lemp.md), sección 3).

## 4. Instalar, migrar y sembrar

```bash
composer install
npm install
php artisan migrate --seed
npm run build
```

El seeder (`CoreSeeder` + `BrandingPresetSeeder`) crea únicamente: las pantallas base, el rol Administrador con todos los permisos, el usuario administrador que configuraste en el paso 3, y los 3 presets de branding de referencia (`Predeterminado`, `LandIT`, `Corporativo Kosmos`). **No hay usuarios ni datos de prueba** — una BD nueva nace limpia porque nunca corriste nada de este sesión de desarrollo sobre ella.

> Si en algún momento este mismo proyecto (`mds`) termina usándose como sitio real y quieres limpiarlo de las pruebas hechas durante el desarrollo (usuarios de prueba, bitácora, notificaciones), usa `php artisan mds:clean-test-data` — ver [`docs/limpiar-datos-de-prueba.md`](limpiar-datos-de-prueba.md). No es necesario para un proyecto clonado nuevo, cuya BD ya nace vacía.

## 5. Verificar que arranca

```bash
composer run dev
```

Levanta Reverb, el worker de colas, los logs (`pail`) y Vite en paralelo. Entra a `APP_URL`, pide el código de acceso con el correo del administrador (llega a `storage/logs/laravel.log` si `MAIL_MAILER=log`), confirma que ves el dashboard.

## 6. Personalizar branding sin tocar código

Desde `/branding` (rol Administrador): logo, favicon, colores institucionales y de "chrome" (barra superior, menú lateral). Puedes aplicar directamente el preset `LandIT` o `Corporativo Kosmos` si el sitio nuevo es para esa marca, o definir colores propios y guardarlos como preset nuevo.

## 7. Construir el contenido propio del sitio

Todo lo de plataforma ya está resuelto. Para agregar las pantallas/funciones específicas de este sitio nuevo:

- Si es contenido aislado y con su propia tabla(s): créalo como módulo — `php artisan module:make NombreModulo` (usa `Modules/Ejemplo` como referencia) y sigue [`docs/agregar-pantallas.md`](agregar-pantallas.md).
- Si es una pantalla simple del core (poco probable en un sitio nuevo, más común seguir agregando módulos): sigue el mismo documento, sección "Pantallas del core".

Si el módulo `Ejemplo` no aplica a este sitio, bórralo (`Modules/Ejemplo` + su registro en `screens` vía `php artisan module:seed` no se vuelve a correr) — es solo una referencia de patrón, no es parte del core.

## 8. Desplegar a producción

Sigue [`docs/deploy-lemp.md`](deploy-lemp.md) completo — está escrito para cualquier proyecto basado en esta plantilla, no solo para `mds`. Presta atención especial a generar credenciales de Reverb y `APP_KEY` **nuevos**, distintos de cualquier otro proyecto basado en esta misma plantilla.

## Qué NO copiar entre proyectos

- El archivo `.env` real (contiene credenciales) — cada proyecto tiene el suyo, generado desde `.env.example`.
- La base de datos de `mds` — cada proyecto nace con su propia BD vacía vía `migrate --seed`.
- `storage/app/public/*` (logos/favicons subidos) — cada sitio sube los suyos desde `/branding`.
