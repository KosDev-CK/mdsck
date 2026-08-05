# Agregar pantallas nuevas y asignarlas a perfiles

Todo lo que aparece en el menú lateral y puede permisarse por perfil viene de la tabla `screens` (modelo `App\Models\Screen`). No hay nada "hardcodeado" en el sidebar — es 100% data-driven.

## Columnas de `screens`

| Columna | Para qué |
|---|---|
| `name` | Texto que se ve en el menú. |
| `slug` | Identificador único, libre (no se usa en rutas, solo como clave). |
| `route_name` | Nombre de la ruta Laravel (`route($screen->route_name)`) — el link del menú se genera con esto. |
| `permission_name` | El permiso de Spatie que controla quién ve la pantalla (convención: `screens.<slug>.<verbo>`, ej. `screens.messages.manage`). |
| `icon` | Nombre de un ícono **outline** de [Heroicons](https://heroicons.com), sin el prefijo `o-` (ej. `cube`, `megaphone`). Se renderiza como `<x-heroicon-o-{icon}>`. |
| `group_label` | Sección del menú donde agrupa (ej. "General", "Accesos", "Sistema", "Módulos"). El sidebar agrupa automáticamente por este campo. |
| `order` | Orden dentro del grupo. |
| `module` | Solo para pantallas de un módulo de `Modules/` — nulo para pantallas del core. |
| `parent_id` | Nulo salvo que quieras una pantalla anidada (el sidebar solo lista las de `parent_id = null` como entradas de primer nivel). |

El sidebar (`resources/views/partials/sidebar.blade.php`) filtra las pantallas con `auth()->user()->can($screen->permission_name)` — si el usuario no tiene el permiso, ni siquiera ve la entrada en el menú, aunque la ruta exista.

## A) Agregar una pantalla dentro de un módulo (lo normal para contenido nuevo)

1. Crea el módulo si no existe: `php artisan module:make NombreModulo` (usa `Modules/Ejemplo` como referencia de estructura: modelo, migración, componente Livewire, vista).
2. En el seeder **del propio módulo** (no en `CoreSeeder` — así el módulo es instalable/removible sin tocar el core):

    ```php
    Screen::updateOrCreate(
        ['slug' => 'mi-pantalla'],
        [
            'name' => 'Mi Pantalla',
            'route_name' => 'mimodulo.index',
            'permission_name' => 'screens.mimodulo.manage',
            'icon' => 'cube',
            'group_label' => 'Módulos',
            'module' => 'NombreModulo',
            'order' => 1,
        ]
    );
    ```

3. Da de alta la ruta en `Modules/NombreModulo/routes/web.php`, protegida con el mismo `permission_name`:

    ```php
    Route::middleware(['auth', 'permission:screens.mimodulo.manage'])
        ->get('/mi-pantalla', \Modules\NombreModulo\Livewire\MiPantalla::class)
        ->name('mimodulo.index');
    ```

4. Corre `php artisan module:seed NombreModulo`.
5. Asigna el permiso a los perfiles que deban verla (ver sección C).

## B) Agregar una pantalla al core (poco común — la mayoría del contenido nuevo va en un módulo)

Mismo patrón que arriba, pero:

- El registro `Screen::updateOrCreate(...)` va en `database/seeders/CoreSeeder.php`, dentro del arreglo `$screens`.
- La ruta va en `routes/web.php`, dentro del grupo `Route::middleware('auth')->group(...)`, con `->middleware('permission:screens.mi-pantalla.manage')`.
- Corre `php artisan db:seed --class="Database\Seeders\CoreSeeder"` (es idempotente — usa `updateOrCreate`, seguro correrlo de nuevo).

## C) Asignar la pantalla nueva a un perfil

Dos formas, ambas sin código:

1. **Pantalla "Perfiles"** (`/roles`) — selecciona el perfil en la lista, marca el checkbox de la pantalla nueva (aparece automáticamente porque `screens` ya tiene el registro) y guarda. Internamente hace `$role->syncPermissions([...])`.
2. **Tinker / seeder**, si quieres que un perfil la tenga de entrada al sembrar:

    ```php
    $role->givePermissionTo('screens.mi-pantalla.manage');
    ```

El rol **Administrador** siempre tiene *todos* los permisos porque `CoreSeeder` corre `$adminRole->syncPermissions(Screen::pluck('permission_name'))` cada vez que se siembra — no necesitas asignarle nada a mano.

## Notas

- El menú agrupa y colapsa solo — no hay que tocar el sidebar al agregar pantallas, siempre que uses `group_label`/`order`/`icon` correctamente.
- Si una ruta no existe todavía pero el registro en `screens` sí, el sidebar la muestra pero el link apunta a `#` (`Route::has(...)` lo protege) — útil si quieres dar de alta el menú antes de terminar la pantalla, pero normalmente conviene hacer ambos pasos juntos.
- `permission_name` es lo único que Spatie usa para autorizar — el middleware de la ruta y el `@can`/filtro del sidebar deben usar exactamente el mismo string.
