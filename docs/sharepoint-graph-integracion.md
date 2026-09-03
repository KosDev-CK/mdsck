# Acceso a SharePoint (documentos digitalizados) vía Microsoft Graph

`Modules/GestionTI` necesita subir y leer documentos digitalizados (responsivas de asignación de activos, remisiones de proveedor) en una biblioteca de SharePoint. Igual que el correo (ver [`docs/correo-oauth2-azure.md`](correo-oauth2-azure.md)), esto se hace con **Microsoft Graph** autenticado por *client credentials* (OAuth2 app-only, sin usuario/contraseña) — no hay login interactivo ni delegación de un usuario.

Se **reutiliza el mismo App Registration de Azure AD** que ya usa `App\Mail\Transport\MicrosoftGraphTransport` para correo (mismo tenant, mismo `AZURE_MAIL_CLIENT_ID`/`AZURE_MAIL_CLIENT_SECRET`) — no se crea una aplicación nueva. Ese app hoy solo tiene el permiso `Mail.Send`; aquí se le agrega un segundo permiso, `Sites.Selected`, acotado a un sitio concreto.

## Por qué `Sites.Selected` y no `Sites.ReadWrite.All`/`Files.ReadWrite.All`

Mismo criterio de mínimo privilegio ya aplicado al correo: allá, el permiso de aplicación `Mail.Send` por sí solo deja enviar como **cualquier buzón del tenant**, y se acota con una Application Access Policy de Exchange a un solo buzón (paso 5 de `docs/correo-oauth2-azure.md`). Aquí el problema es análogo pero peor si no se acota: `Sites.ReadWrite.All`/`Files.ReadWrite.All` son permisos de aplicación que dan acceso de escritura a **todos los sitios de SharePoint del tenant**, no solo a `Landit` — si el secreto del App Registration se filtra, el radio de daño sería todo el SharePoint de la organización, no solo una biblioteca.

`Sites.Selected` es distinto: otorgado por sí solo, **no da acceso a ningún sitio**. Requiere un segundo paso (Paso 2 más abajo) donde un administrador concede explícitamente acceso a un sitio puntual con un nivel de permiso puntual (`read` o `write`). Es el equivalente de SharePoint a la Application Access Policy de Exchange — la diferencia es que allá se restringe con PowerShell de Exchange Online, y aquí con una llamada a Graph API (`/sites/{site-id}/permissions`).

## Paso 1 — Agregar el permiso `Sites.Selected` en el App Registration

Sobre el **mismo** App Registration que ya usa el correo (el que tiene `AZURE_MAIL_CLIENT_ID`/`AZURE_MAIL_CLIENT_SECRET`) — no crear uno nuevo:

1. [portal.azure.com](https://portal.azure.com) → **Microsoft Entra ID** → **App registrations** → busca el app ya existente (ej. `MDS - Correo` / el nombre que tenga en este tenant).
2. Dentro del App Registration → **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions** (no *Delegated*) → busca y marca `Sites.Selected`.
3. **Grant admin consent for [tu organización]** — requiere un rol de administrador (Global Admin o Privileged Role Administrator), igual que se hizo para `Mail.Send`. Sin este consentimiento, el permiso queda "concedido" en la lista pero Graph lo rechaza con `Forbidden` al usarlo.
4. Al terminar, el App Registration debe tener **dos** permisos de aplicación en la lista: `Mail.Send` y `Sites.Selected`, ambos con estado "Granted for [tu organización]".

Nota: `Sites.Selected` no reemplaza a `Mail.Send` — conviven en el mismo app, cada uno cubre una API distinta (correo vs. SharePoint).

## Paso 2 — Conceder acceso al sitio específico (`Landit`)

Este paso es el que `Sites.Selected` **no puede resolver por sí solo**: alguien con permisos de administrador (Global Admin o SharePoint Admin) tiene que llamar a Graph autenticado **con sus propias credenciales de administrador** (no con el client id/secret de la app) para decirle a Graph "este App Registration puede acceder a este sitio, con este nivel". Se puede hacer desde **Graph Explorer** (https://developer.microsoft.com/graph/graph-explorer, inicia sesión con la cuenta de administrador) o desde PowerShell con el módulo `Microsoft.Graph`.

### 2.1 Resolver el `site-id` del sitio

```http
GET https://graph.microsoft.com/v1.0/sites/grupokosmosmexico.sharepoint.com:/sites/Landit
```

La respuesta trae un campo `id` con el formato `grupokosmosmexico.sharepoint.com,{guid-1},{guid-2}` — ese valor completo es el `{site-id}` que se usa en los pasos siguientes.

### 2.2 Otorgar el permiso `write` al App Registration sobre ese sitio

```http
POST https://graph.microsoft.com/v1.0/sites/{site-id}/permissions
Content-Type: application/json

{
  "roles": ["write"],
  "grantedToIdentities": [
    {
      "application": {
        "id": "{{AZURE_MAIL_CLIENT_ID}}",
        "displayName": "MDS - Correo"
      }
    }
  ]
}
```

- `roles: ["write"]` porque la app necesita **subir** archivos nuevos (responsivas, remisiones de proveedor), no solo leerlos — si en algún momento un caso de uso solo necesitara lectura, se usaría `["read"]` en una entrada de permiso separada, pero para este módulo se otorga `write` directamente.
- `{{AZURE_MAIL_CLIENT_ID}}` es el mismo *Application (client) ID* usado en `.env` para correo — mismo app, permiso adicional.
- Equivalente en PowerShell (`Connect-MgGraph -Scopes "Sites.FullControl.All"` con una cuenta de administrador, no con la app):

```powershell
Connect-MgGraph -Scopes "Sites.FullControl.All"

New-MgSitePermission -SiteId "{site-id}" -BodyParameter @{
    roles = @("write")
    grantedToIdentities = @(
        @{
            application = @{
                id          = "{{AZURE_MAIL_CLIENT_ID}}"
                displayName = "MDS - Correo"
            }
        }
    )
}
```

La respuesta trae un `id` de permiso (distinto del `site-id`) — anótalo, se usa para revocar en el futuro (ver más abajo).

### 2.3 Verificar que el permiso quedó bien

```http
GET https://graph.microsoft.com/v1.0/sites/{site-id}/permissions
```

Debe aparecer una entrada con `roles: ["write"]` y `grantedToIdentities` (o `grantedToIdentitiesV2`) apuntando al `client_id` del App Registration. Si no aparece, el Paso 2.2 no se aplicó — repetirlo autenticado con una cuenta de administrador (el error más común es intentarlo con una cuenta sin rol de administrador, o pasar el `client_id` equivocado).

## Nuevas variables de entorno

```env
SHAREPOINT_SITE_HOSTNAME=grupokosmosmexico.sharepoint.com
SHAREPOINT_SITE_PATH=/sites/Landit

# Nombres de carpeta dentro de "Documentos compartidos" (biblioteca default del sitio)
SHAREPOINT_FOLDER_RESPONSIVA="Responsivas Asignación de Activos"
SHAREPOINT_FOLDER_REMISION_PROVEEDOR="Remisiones de Proveedor"
```

No se guarda un `site_id` fijo en `.env` a propósito: Graph permite resolver el sitio por hostname + ruta en una sola llamada (`GET /sites/{hostname}:{ruta}`, igual que el Paso 2.1), lo que hace la configuración más portable entre entornos — dev y producción podrían apuntar a sitios de SharePoint completamente distintos (o incluso tenants distintos, en un clon de esta plantilla) sin tener que ir a buscar y pegar un `site-id` cada vez. Estas variables también deben agregarse a `.env.example` (ver nota de alcance abajo) siguiendo el mismo patrón que `AZURE_MAIL_*`.

`AZURE_MAIL_TENANT_ID`/`AZURE_MAIL_CLIENT_ID`/`AZURE_MAIL_CLIENT_SECRET` (ya existentes, usados hoy solo para correo) se reutilizan tal cual para autenticar contra SharePoint — no se agregan variables de tenant/client/secret nuevas, es el mismo App Registration.

> Nota de alcance: este documento no modifica `.env`/`.env.example` ni código — eso lo cubre el trabajo en paralelo que construye la subida/lectura de documentos en `Modules/GestionTI`.

## Nota de caché de token

El token de aplicación para llamar a Graph se pide igual que para correo: `POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`, `grant_type=client_credentials`, `scope=https://graph.microsoft.com/.default` — el mismo patrón que ya implementa `App\Mail\Transport\MicrosoftGraphTransport`, cacheado (`Cache::remember`) por ~50 minutos para no pedir uno nuevo en cada llamada.

El parámetro `scope` sigue siendo `.default` en ambos casos — no existe un "scope de SharePoint" distinto que se pida en la petición de token. Lo que determina qué puede hacer ese token es el conjunto de permisos que el App Registration tiene otorgados (`Mail.Send` + `Sites.Selected`, ambos con admin consent), no el parámetro de la petición. Esto significa que **un mismo token de aplicación cachea acceso a ambas APIs a la vez** — no hace falta (ni tiene sentido) pedir un token separado "para correo" y otro "para SharePoint"; si el código que llama a SharePoint reutiliza el mismo mecanismo de caché de token que `MicrosoftGraphTransport`, es correcto y evitable duplicar la lógica de obtención de token.

## Revocar o rotar el acceso al sitio

Si algún día hay que quitarle a la app el acceso a este sitio (por ejemplo, se descontinúa la integración, o se detecta el secreto comprometido y hay que cortar el radio de daño rápido):

1. Ubicar el `id` del permiso otorgado en el Paso 2.2 (si no se guardó, se recupera con el `GET` del Paso 2.3 — cada entrada trae su propio `id`).
2. Revocar:

```http
DELETE https://graph.microsoft.com/v1.0/sites/{site-id}/permissions/{permission-id}
```

3. Verificar que ya no aparece en `GET /sites/{site-id}/permissions` (Paso 2.3).

Esto solo corta el acceso a **este sitio** — el permiso `Sites.Selected` del App Registration (Paso 1) sigue existiendo y "granted", pero sin ningún sitio otorgado vuelve a ser inofensivo, igual que estaba antes del Paso 2. Si en cambio lo que se necesita es rotar el secreto del App Registration (comprometido o por vencer), sigue el mismo procedimiento que ya usa correo — **Certificates & secrets** → nuevo secreto → actualizar `AZURE_MAIL_CLIENT_SECRET` en ambos consumidores (correo y SharePoint, es el mismo secreto) → eliminar el secreto viejo.
