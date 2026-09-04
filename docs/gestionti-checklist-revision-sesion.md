# Checklist de revisión — sesión EBS proxy + Roles + Sistema de Ayuda

Generado para validar todo lo hecho en esta sesión antes de dar por cerrado el trabajo. Marca cada punto conforme lo verifiques.

**Estado de commits (aclarado 2026-09-04):** el fix de proxy EBS y la asignación de roles **no tienen nada pendiente de commitear** — el proxy ya quedó en el commit `428e283` (código) más cambios de infraestructura fuera del repo (Squid, no versionado), y los roles son datos aplicados vía tinker en dev/prod, sin diff de código. Lo único realmente pendiente de commit en este repo es el **sistema de Ayuda** (sección 3).

---

## 1. Fix de proxy saliente para EBS (backfill de producción) — código ya commiteado (`428e283`)

- [x] Confirmado en producción que el backfill de EBS corrió sin cURL error 28 ni HTTP 403 tras el fix (confirmado por el usuario con capturas en la sesión anterior).
- [x] `Modules/GestionTI/app/Support/Ebs/EbsRequisitionsClient.php` — nuevo parámetro `?string $proxy` y método `httpClient()`. Parte del commit `428e283`.
- [x] `config/services.php` → `services.ebs.proxy` (lee `AZURE_MAIL_HTTP_PROXY`). Parte del commit `428e283`.
- [x] `Modules/GestionTI/app/Providers/GestionTIServiceProvider.php` pasa el proxy al construir el cliente. Parte del commit `428e283`.
- [x] `.env` de producción ya tenía `AZURE_MAIL_HTTP_PROXY` (reutilizado de Graph/SharePoint) — no requirió cambio nuevo.
- [x] Archivo Squid `/etc/squid/conf.d/05-mdsck-ebs-proxy.conf` en el servidor proxy — vive fuera de este repo (infraestructura), documentado en `docs/gestionti-progreso.md` líneas 812-818.
- [x] Confirmado que ningún otro `conf.d/*.conf` interfiere (el prefijo `05-` lo carga antes que `portalck-graph-proxy.conf`) — causa raíz ya documentada.
- [x] Confirmado por el usuario que el correo (Graph, mismo proxy) sigue saliendo sin problemas tras el cambio de ACL ("el correo sí sale sin problemas").

## 2. Roles y permisos de GestionTI

- [x] Confirmado en **dev**, en vivo (`/roles`, leyendo los checkboxes marcados vía JS), que los 5 roles tienen exactamente las pantallas esperadas — coincide 100% con la tabla de `docs/gestionti-progreso.md`:
  - [x] Mesa de Servicio → Tickets, Solicitud de SIC, SIC en EBS
  - [x] Compras → Catálogos de Compras, Solicitud a Proveedores, Recepción de Proveedor
  - [x] Almacén/TI → Catálogos de Inventario, Asignación de Activo, Stock, Registro Manual de Activo, Mantenimiento, Ficha de Activo, Configuración de Almacenamiento
  - [x] Contabilidad/Finanzas → Facturación
  - [x] Proyectos (rol nuevo) → Presupuesto por Proyecto
- [x] Intentado reconfirmar en **producción** a petición del usuario (2026-09-04) — **no fue posible entrar en vivo**: la llave SSH de deploy (`mdsck-app`) está restringida por `forced command` a `sudo -n /usr/local/bin/mdsck-deploy.sh` únicamente (sin shell/tinker), y el login sin contraseña de producción manda el código al correo real del usuario, no accesible desde aquí. Confirmado en su lugar **por análisis de diff**: el commit `c0a9a98` desplegado hoy no toca `screens`, `role_has_permissions` ni ningún seeder — es exclusivamente código de la Ayuda (vistas/rutas/PHP), así que la asignación de roles confirmada por el usuario en la sesión anterior no pudo haberse visto afectada.
- [x] Revisado `/user-roles` en dev: solo existe 1 usuario (Victor Gonzalez, Administrador) — **ningún usuario real de las áreas operativas tiene todavía un perfil de los 5 nuevos asignado**. Sigue pendiente como tarea de negocio (dar de alta a las personas reales y asignarles su perfil), no es un bug.
- [ ] Pendiente explícito del usuario ("después decido"): evaluar si Dashboard / Búsqueda Global / Catálogos Núcleo / Empleados se agregan a los 4 roles operativos, o quedan solo en Administrador.
- [x] `docs/gestionti-progreso.md` (líneas 46-56) documenta el mapeo de forma precisa y coincide con lo verificado en vivo — no requirió corrección.

**Nota:** al revisar `/roles` también aparece un rol **"Supervisor Mesa de Servicio" (0 usuarios)** que no forma parte del trabajo de esta sesión — pertenece al módulo `Modules/MesaServicio`, un desarrollo independiente y no relacionado (ver nota en la sección 5 sobre `git status`).

## 3. Sistema de Ayuda por pantalla (21 pantallas)

### Infraestructura base — releída y confirmada (2026-09-04)
- [x] `resources/views/components/ui/help-button.blade.php` — botón "?" con evento Alpine puro (`window.dispatchEvent`), sin acoplamiento a Livewire. Incluye comentario explicando por qué (límite del árbol DOM de Livewire).
- [x] `resources/views/components/ui/help-modal.blade.php` — modal Alpine puro (`x-on:{{ $event }}.window`), no reutiliza `x-ui.modal`. Mismo razonamiento documentado en comentario.
- [x] `Modules/GestionTI/app/Support/Ayuda/AyudaCatalog.php` — valida el slug con regex `^[a-z0-9-]+$` antes de resolver el archivo (protección contra path traversal), con comentario explicando el riesgo.
- [x] `Modules/GestionTI/app/Http/Controllers/Ayuda/AyudaPdfController.php` — genera el PDF con Dompdf, imágenes de branding embebidas como base64 (no URL pública).
- [x] Ruta `gestionti.ayuda.pdf` en `Modules/GestionTI/routes/web.php` con `->where('slug', '[a-z0-9-]+')` y middleware `auth`.
- [x] Imágenes reales de branding en `Modules/GestionTI/resources/assets/ayuda/{cabecera,pie}.png` — dimensiones confirmadas (2550×355 y 2550×95, @300dpi = 3cm y 8mm), son las versiones finales aprobadas por el usuario.

### Verificación visual — YA HECHA en esta sesión (2026-09-04)
- [x] Confirmado con navegador real (login vía código forzado en dev + build de Vite temporalmente activado) que el botón "?" existe, con el título y el slug de PDF correctos, en las **21/21 pantallas** (verificado con `find` por pantalla — botón, heading del modal, y link al PDF con el slug correcto, uno por uno).
- [x] Modal abre correctamente en **modo oscuro** (pantalla "Stock", contenido largo con scroll) y en **modo claro** (mismo caso) — buen contraste en ambos, sin tarjetas blancas olvidadas.
- [x] Descargados y revisados visualmente 3 PDFs completos (`dashboard`, `tickets`, `presupuestos-proyecto` — este último cruza a una 2ª página): encabezado/pie llegan hasta el borde en todas las páginas, sin franjas blancas, texto nunca tapado, el fix de `@page { margin: 3cm 0 8mm 0; }` se sostiene también en multipágina.
- [x] Posición del botón "?" consistente (junto al título, al lado de tema/campanita/avatar) en todas las pantallas revisadas.
- [x] Resuelto (2026-09-04): el usuario pidió agregar el ícono también en `Show`. Se agregó a `presupuesto-proyectos/show.blade.php` y `inventarios/ficha-activo/show.blade.php`, reutilizando el contenido existente del mismo slug. Verificado en navegador (modal abre, título/PDF correctos) y con los tests `PresupuestoProyectos/ShowTest.php` + `Inventarios/FichaActivo/ShowTest.php` (26/26, sin colisiones).

**Nota técnica para la próxima sesión:** el preview con `npm run dev` (HMR) no renderiza CSS en el navegador de esta herramienta porque el archivo `public/hot` apunta a `http://[::1]:5174`, un host/puerto que el sandbox del navegador no puede alcanzar (ERR_BLOCKED_BY_CLIENT). Workaround usado: renombrar temporalmente `public/hot` (ya restaurado al terminar) para forzar el uso de los assets ya compilados en `public/build/`. Si `composer run dev` no está corriendo o no hay build reciente, correr `npm run build` antes de una sesión de QA visual.

### Contenido — auditoría completa hecha (2026-09-04)
Los 21 archivos (`Modules/GestionTI/resources/ayuda/data/*.php`) ya fueron verificados contra el código real del componente Livewire que describen (4 agentes en paralelo, uno por grupo de pantallas, comparando campos/reglas/flujos contra el código fuente real, no solo releyendo el texto). Se encontraron y **ya se corrigieron** 8 imprecisiones en 6 archivos:

- [x] `catalogos-nucleo.php` — "Nombre comercial" (Empresas) estaba marcado como opcional siendo obligatorio; se separó del campo "Nombre conocido" (sí opcional, en las demás pestañas). Se agregó el paso "Exportar a Excel" al proceso.
- [x] `catalogos-compras.php` — se agregó el paso "Exportar a Excel" al proceso (faltaba).
- [x] `catalogos-inventario.php` — corregida la afirmación falsa de que "Nombre conocido" existe en Marca/Sistema Operativo/Licencia/Propiedad/Validador (solo existe en Tipo de Equipo). Se agregó el paso "Exportar a Excel".
- [x] `facturas.php` — se documentó el buscador y los filtros del listado (estatus, "Solo con diferencia a revisar"), que no estaban cubiertos.
- [x] `asignaciones.php` — se documentó la columna "Estado" (Activa/Devuelta) del listado, que no estaba cubierta.
- [x] `solicitudes-proveedor.php` — **corrección más relevante**: el texto afirmaba que el origen debía ser "una Solicitud de SIC autorizada", pero el código no filtra por estatus (acepta una SIC en cualquier estatus). También se corrigió la implicación de que un origen (SIC o proyecto) es obligatorio — en realidad ambos son opcionales y se puede guardar sin ninguno.
- [x] `ficha-activo.php` — se documentaron los campos "Ubicación actual" y "SIC reservada actual" del encabezado de la ficha de detalle, que faltaban.
- [x] `almacenamiento-documentos.php` — corregida la afirmación de que activar un tipo sin carpeta de SharePoint configurada "muestra un mensaje de error claro" — en realidad la excepción no se captura en ningún punto de subida y termina en un error genérico de la aplicación, no en un aviso amigable.

Confirmados **sin errores** (contenido correcto tal cual, sin cambios): `dashboard.php`, `tickets.php`, `presupuestos-proyecto.php` (spot-check de sesión anterior), `catalogos-empleados.php` (spot-check de sesión anterior), `busqueda-global.php`, `solicitudes-sic.php`, `ebs-requisiciones.php`, `recepciones.php`, `stock.php`, `registro-manual.php`, `mantenimientos.php`, `tipos-aviso.php`, `avisos-historial.php`.

- [x] Re-verificado tras las correcciones: `php -l` en los 8 archivos editados (sin errores de sintaxis) + suite `Modules/GestionTI/tests/Feature/Ayuda` en verde (4 tests, 67 assertions).

### Pruebas automatizadas
- [x] `Modules/GestionTI/tests/Feature/Ayuda/AyudaPdfControllerTest.php` — 4 tests, 67 assertions, cubre las 21 pantallas + slug desconocido (404) + path traversal (404). Confirmado en verde (re-corrido después de las correcciones de contenido).
- [x] Suite completa de GestionTI — corrida de nuevo el 2026-09-04 tras las correcciones de contenido de Ayuda: **OK, 402 tests, 1311 assertions** (7m37s). Sin regresiones.
- [x] Revisados los 4 ajustes de tests por colisión de texto con el contenido de ayuda (contra `docs/gestionti-progreso.md` líneas 763 y 775-778, cada uno con su razón documentada):
  - [x] `DashboardTest.php` — se quitó una cadena de `assertDontSee` que colisionaba con el texto genérico de ayuda.
  - [x] `BusquedaGlobalTest.php` — se quitó `assertDontSee('Activos')` por la misma razón.
  - [x] `Catalogos/InventarioMergeTest.php` — se afinó a verificar el atributo `wire:click="openMerge"` real en vez del texto del botón.
  - [x] `MesaServicio/EbsRequisicionesTest.php` — colisión con el SVG del ícono de descarga (`18.75V16.5` contiene "V1"), sin relación con la ayuda.
- [x] Confirmado que ninguno de los 4 ajustes debilitó una aserción de seguridad real — los `assertNull(...)` de permisos en `DashboardTest` no se tocaron; los otros 3 son colisiones de texto genuinamente accidentales, no relacionadas con control de acceso.

### Limpieza post-rollout
- [x] Confirmado que no queda ningún atributo `model="showHelp"` huérfano — verificado de nuevo con `grep -r 'model="showHelp"' Modules/GestionTI` (0 resultados).
- [x] Revisado `git status --porcelain` completo — ver hallazgo importante en la sección 5.

## 4. Documentación

- [x] Revisado `docs/gestionti-progreso.md` (líneas 46-56 para roles, 747-822 para EBS proxy y sistema de Ayuda) — el resumen es preciso, coincide con lo verificado en vivo en esta sesión (roles) y con el código (Ayuda/EBS). No requirió corrección.
- [ ] Revisar la memoria persistente del asistente (`project_mdsck_production_deploy.md`) si se vuelve a tocar el tema de proxy/EBS en el futuro.

## 5. Antes de hacer commit

- [ ] El único commit pendiente real es el **sistema de Ayuda completo** (proxy EBS ya está en `428e283`, roles no generan diff de código). Archivos a incluir: los `??` de Ayuda bajo `Modules/GestionTI/` + `resources/views/components/ui/help-{button,modal}.blade.php` + las 21 vistas `M` + `Modules/GestionTI/routes/web.php` + los 4 tests `M` + `docs/gestionti-progreso.md` + `docs/gestionti-checklist-revision-sesion.md` (opcional, es un doc de trabajo de esta sesión, se puede excluir si se prefiere no versionarlo).
- [x] **Investigada la carpeta `Modules/Gestion` del status inicial — resuelto, no existe tal carpeta.** Era un artefacto de truncamiento del status de 2000 caracteres mostrado al inicio de la conversación (cortó a media línea un path largo). `git status --porcelain` completo no muestra ningún `Modules/Gestion` suelto.
- [x] **Hallazgo real en su lugar: `Modules/MesaServicio/` aparece como `??` (sin trackear) en el working tree**, junto con `docs/mesaservicio-progreso.md` y `docs/servicedesk-plus-oauth.md`. Esto **no es parte del trabajo de esta sesión** — es un módulo independiente (integración con ServiceDesk Plus) en desarrollo, aparentemente por otra sesión/hilo de trabajo en este mismo repo. **Al armar el/los commit(s) de esta sesión, excluir explícitamente `Modules/MesaServicio/`, `docs/mesaservicio-progreso.md` y `docs/servicedesk-plus-oauth.md`** — usar `git add` con rutas específicas (nunca `git add -A`/`git add .`) para no mezclar ambos trabajos en un mismo commit.
- [x] Confirmado por `git status --porcelain` completo (33 líneas) — no aparece ningún `.env`, credencial ni archivo binario inesperado. Solo vistas/rutas/tests de GestionTI, los archivos nuevos de Ayuda, y los 2 componentes UI compartidos.

## 6. Pendientes explícitos ya identificados (fuera de esta sesión, no bloquean el commit)

- [ ] SharePoint go-live: permiso `Sites.Selected` en Azure, acceso al sitio, carpeta faltante "Remisiones de Proveedor", variables `.env` de producción.
- [ ] Migración de histórico de inventario a producción (`gestionti:importar-histórico`) — pendiente el Excel real.
- [ ] Plantilla Excel corporativa para exportar Presupuesto por Proyecto (hoy usa placeholder).
