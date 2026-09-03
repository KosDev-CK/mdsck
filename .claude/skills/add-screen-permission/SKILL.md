---
name: add-screen-permission
description: Usa este skill antes de crear cualquier pantalla nueva (Livewire) que deba aparecer en el menú y ser asignable a un perfil. Resume el flujo de docs/agregar-pantallas.md — consulta ese doc para el detalle completo, este skill es el recordatorio rápido.
---

# Agregar una pantalla nueva y su permiso

En este proyecto, **una pantalla = un permiso**, gobernado por el modelo `Screen` (`name`, `route_name`, `permission_name`, `icon`, `group_label`, `order`, `module`). No hay nada hardcodeado en el sidebar — es 100% data-driven desde la tabla `screens`.

## Pasos
1. Crea el componente Livewire full-page (`#[Layout('layouts.app')]`) siguiendo el patrón de `app/Livewire/<Area>/`.
2. Protege la ruta con `middleware('permission:<permission_name>')`.
3. Crea el registro `Screen` correspondiente **en el seeder del módulo al que pertenece**, no en `CoreSeeder`, salvo que sea una pantalla del core.
4. Asigna el permiso a un perfil desde la pantalla "Perfiles" (`/roles`), o vía `$role->givePermissionTo(...)` si es parte de un seeder.
5. Recuerda: el rol **Administrador** obtiene todos los permisos automáticamente (`CoreSeeder::run()` hace `$adminRole->syncPermissions(Screen::pluck('permission_name'))` cada vez que se siembra) — no hace falta asignárselo a mano, pero sí tiene que existir el `Screen` para que ese sync la recoja.

## Detalle completo
Para el detalle exacto (nombres de campos, ejemplos de código), lee `docs/agregar-pantallas.md` en el repo — este skill es solo el recordatorio del flujo, no lo reemplaza.

## Nota sobre "pantalla de inicio"
Si tu pantalla nueva es candidata a ser "pantalla de inicio" configurable por usuario (`users.home_screen_id`), no necesitas hacer nada especial — `User::homeRouteName()` ya la va a considerar automáticamente en cuanto exista como `Screen` activo con permiso.
