---
name: new-module-scaffold
description: Usa este skill cuando necesites crear un módulo de negocio nuevo dentro de Modules/, o entender la estructura del módulo de referencia para replicarla.
---

# Crear un módulo de negocio nuevo

## Comando
```bash
php artisan module:make <Nombre>
```
Genera el andamiaje completo dentro de `Modules/<Nombre>/` (app, config, database, resources, routes, tests).

## Patrón de referencia
`Modules/Ejemplo/` es el módulo guía — cópialo/adáptalo en vez de empezar de cero o de improvisar una estructura distinta.

## Checklist
1. Migraciones y modelos propios del módulo dentro de `Modules/<Nombre>/database/` y `.../app/Models/`.
2. Pantallas como componentes Livewire dentro del módulo, mismo patrón que el core (`#[Layout('layouts.app')]`).
3. Si el módulo agrega pantallas nuevas al menú, sigue el skill `add-screen-permission` — el `Screen` correspondiente va en el **seeder del módulo**, nunca en `CoreSeeder`.
4. Sembrar datos del módulo: `php artisan module:seed <Nombre>`.
5. El módulo debe poder activarse/desactivarse desde `/modules` (`app/Livewire/Modules/Manage.php`) sin romper el resto del sitio — pruébalo en ambos estados.
6. Escribe tests dentro de `Modules/<Nombre>/tests/` siguiendo la convención de PHPUnit del proyecto.

## Errores comunes a evitar
- Meter lógica que en realidad es del core (auth, branding, permisos genéricos) dentro de un módulo de negocio — eso rompe la premisa de que el core ya está resuelto y los módulos son solo contenido.
- Registrar el `Screen` del módulo en `CoreSeeder` en vez de en el seeder propio del módulo.
- Usar un kit de UI de terceros en vez de los componentes de `resources/views/components/ui/`.
