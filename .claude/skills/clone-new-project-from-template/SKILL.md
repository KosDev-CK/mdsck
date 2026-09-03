---
name: clone-new-project-from-template
description: Usa este skill cuando se vaya a clonar este repo (mdsck) como base para un cliente/proyecto nuevo. Resume el checklist de docs/nuevo-proyecto-desde-plantilla.md y docs/correo-oauth2-azure.md en un solo lugar — consulta esos docs para el detalle exacto.
---

# Checklist para clonar la plantilla en un proyecto nuevo

Este repo es la **plantilla maestra** — nunca se reutiliza su misma base de datos/instalación para un cliente nuevo. Se clona aparte.

## Pasos de alto nivel
1. Clonar el repo en una instalación nueva e independiente (ver `docs/nuevo-proyecto-desde-plantilla.md` para el detalle exacto de comandos).
2. Configurar `.env` propio del proyecto nuevo: `APP_NAME`, `APP_URL`, base de datos propia, credenciales propias.
3. **Correo (obligatorio, no se puede compartir entre proyectos):** crear un **App Registration nuevo en Azure AD** para este cliente — tenant/client id/secret propios. Ver `docs/correo-oauth2-azure.md` para:
   - Por qué se usa Microsoft Graph y no SMTP con OAuth2 directo (Microsoft retiró SMTP AUTH básico).
   - El permiso de aplicación `Mail.Send` + consentimiento de administrador.
   - Cómo acotar la app a un solo buzón con una Application Access Policy de Exchange Online.
4. Branding inicial: cargar logo/colores del cliente vía `/branding` (o seeders si se automatiza), no dejar los valores de ejemplo de la plantilla.
5. Antes de considerar el clon listo para producción: correr `php artisan mds:clean-test-data` para no llevar datos de prueba del desarrollo al cliente real (ver `docs/limpiar-datos-de-prueba.md`).
6. Desplegar siguiendo `docs/deploy-lemp.md` (Reverb + worker de colas mantenidos vivos con Supervisor).

## Errores comunes
- Reutilizar credenciales de Azure entre dos proyectos clonados — cada cliente necesita su propio App Registration.
- Olvidar limpiar datos de prueba antes de entregar a producción.
- Dejar `APP_NAME`/`APP_URL` con los valores de la plantilla en vez de los del cliente real.
