---
name: design-system-tailwind-v4
description: Usa este skill antes de tocar CSS, componentes de resources/views/components/ui/, o cualquier cosa relacionada con branding dinámico o dark mode. Documenta una trampa real de Tailwind v4 que ya causó un bug en este proyecto.
---

# Sistema de diseño del proyecto

## Reglas base
- Componentes propios en `resources/views/components/ui/` (Button, Card, Badge, Alert, Avatar, theme-toggle) — decisión explícita de no usar un kit de terceros.
- Colores siempre vía tokens semánticos (`bg-primary`, `text-danger/10`) definidos en `@theme` de `resources/css/app.css` — nunca un color de marca hardcodeado (`indigo-600`, `bg-red-500`).
- `SiteSetting` puede sobrescribir esos tokens en runtime (branding dinámico por sitio) — un color hardcodeado rompe esa capacidad.

## ⚠️ Trampa de cascade layers de Tailwind v4 (ya causó un bug real)
Tailwind v4 emite `@theme` dentro de `@layer theme` y utilidades dentro de `@layer utilities`. **CSS que no está dentro de ningún `@layer` gana siempre sobre CSS que sí lo está**, sin importar el orden de aparición ni la especificidad del selector.

Por eso, en `resources/css/app.css`, el padding/focus-ring de inputs y el `cursor: pointer` de botones están **deliberadamente fuera de cualquier `@layer`**. Si mueves esas reglas dentro de `@layer base` (o cualquier otra capa nombrada), Tailwind las va a pisar en silencio — sin error, sin warning, simplemente no se van a ver.

**Regla práctica:** si agregas una regla CSS que debe ganarle a las utilidades de Tailwind, ponla fuera de `@layer`. Si es una utilidad más que debe convivir con las demás utilidades, sí puede ir en `@layer utilities`.

## Dark mode
- `@custom-variant dark` + clase `.dark` en `<html>`, toggle guardado en `localStorage` (`theme-toggle.blade.php`).
- **Toda pantalla nueva debe verificarse en dark mode antes de darla por terminada** — ya hubo tarjetas blancas olvidadas en un primer pase por todo el sitio.

## Branding dinámico (contexto, no lo dupliques por tenant — aquí es por sitio clonado)
- `SiteSetting` guarda 8 colores + logo + favicon, inyectados como variables CSS por request en `partials/branding-head.blade.php`.
- `BrandingPreset` guarda combinaciones reutilizables; los presets `is_system=true` no se pueden borrar desde la UI.
- El nombre del sitio y la URL (`APP_NAME`, `APP_URL`) NO son parte de este branding dinámico — viven en `.env`, se ajustan por entorno al desplegar (no confundir con branding en runtime).
