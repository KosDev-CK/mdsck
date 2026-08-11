# Envío de correo con OAuth2 vía Microsoft Graph (Azure AD)

Microsoft retiró la autenticación básica (usuario/contraseña) para SMTP AUTH en Exchange Online. Esta plantilla ya no depende de eso para enviar correo: en vez de SMTP, usa el endpoint `sendMail` de **Microsoft Graph**, autenticado con un token de aplicación (OAuth2 *client credentials*, sin usuario ni contraseña) obtenido de un **App Registration** en Azure AD (Entra ID).

El mailer sigue soportando `smtp`/`log` como alternativa (útil en dev o si algún día vuelven a necesitar SMTP), pero `graph` es el que reemplaza la autenticación básica.

## Por qué Graph y no SMTP con OAuth2

SMTP también se puede autenticar con OAuth2 (XOAUTH2), pero requiere que SMTP AUTH siga habilitado en el tenant/buzón — algo que Microsoft está restringiendo cada vez más, además de necesitar código a la medida igual que Graph. Graph API es la ruta que Microsoft recomienda activamente como reemplazo de SMTP, y permite acotar el permiso a un solo buzón con una **Application Access Policy** (ver paso 5) — más fácil de asegurar que un login SMTP que, sin políticas adicionales, puede enviar como cualquier buzón del tenant.

## 1. Registrar la aplicación en Azure AD (Entra ID)

Uno de estos **por cada sitio** basado en esta plantilla (cada proyecto clonado necesita su propio App Registration — su propio tenant/client id y secreto, nunca comparten credenciales entre sitios):

1. [portal.azure.com](https://portal.azure.com) → **Microsoft Entra ID** → **App registrations** → **New registration**.
2. Nombre descriptivo, por ejemplo `MDS - Correo` / `Operaciones CK - Correo` / `Portal CK - Correo`.
3. **Supported account types**: *Accounts in this organizational directory only* (single tenant) — no necesita ser multi-tenant.
4. No hace falta configurar Redirect URI (no hay login interactivo, es app-only).
5. Al terminar, copia de la pantalla "Overview": **Application (client) ID** y **Directory (tenant) ID**.

## 2. Dar el permiso de aplicación `Mail.Send`

1. Dentro del App Registration → **API permissions** → **Add a permission** → **Microsoft Graph** → **Application permissions** (no *Delegated*) → busca y marca `Mail.Send`.
2. **Grant admin consent for [tu organización]** — este botón requiere un rol de administrador (Global Admin o Privileged Role Administrator). Sin este consentimiento el token se genera pero el envío falla con `Forbidden`.

## 3. Generar el secreto de cliente

1. **Certificates & secrets** → **Client secrets** → **New client secret**.
2. Descripción + expiración (Azure ofrece 6, 12, 18, 24 meses — anótala en tu calendario, expirado el secreto los correos dejan de salir hasta renovarlo).
3. Copia el **Value** en cuanto se genera — no vuelve a mostrarse completo después.

## 4. Variables en `.env`

```env
MAIL_MAILER=graph

AZURE_MAIL_TENANT_ID={{Directory (tenant) ID}}
AZURE_MAIL_CLIENT_ID={{Application (client) ID}}
AZURE_MAIL_CLIENT_SECRET={{el Value del secreto}}

# Buzón desde el que se envía — debe existir y tener licencia de correo.
# Si no lo defines, cae en MAIL_FROM_ADDRESS.
AZURE_MAIL_SENDER=web.master@ck.com.mx

MAIL_FROM_ADDRESS="web.master@ck.com.mx"
MAIL_FROM_NAME="Web Master"
```

`config/services.php` (`microsoft_graph`) lee estas variables; `App\Mail\Transport\MicrosoftGraphTransport` es el transporte que Laravel usa cuando `MAIL_MAILER=graph` (registrado en `AppServiceProvider::boot()`).

## 5. (Muy recomendado) Restringir la app a un solo buzón

Sin este paso, el permiso de aplicación `Mail.Send` le permite a la app enviar correo **como cualquier buzón del tenant** — si el secreto se filtra, el radio de daño es todo el tenant. Una **Application Access Policy** en Exchange Online lo acota a un grupo/buzón específico.

Desde PowerShell, conectado a Exchange Online (`Connect-ExchangeOnline`):

```powershell
# Un grupo de distribución con el/los buzones permitidos (puede ser de 1 solo miembro)
New-DistributionGroup -Name "AppMailSenders-MDS" -Members web.master@ck.com.mx

New-ApplicationAccessPolicy `
  -AppId "{{Application (client) ID}}" `
  -PolicyScopeGroupId "AppMailSenders-MDS" `
  -AccessRight RestrictAccess `
  -Description "Solo puede enviar como web.master@ck.com.mx"

# Verificar que quedó aplicada correctamente
Test-ApplicationAccessPolicy -AppId "{{Application (client) ID}}" -Identity web.master@ck.com.mx
```

Repite esto por cada App Registration (una por sitio).

## 6. Verificar que funciona

```bash
php artisan tinker
>>> Mail::raw('Prueba OAuth2', fn ($m) => $m->to('tu-correo@ejemplo.com')->subject('Prueba'));
```

Si algo falla, el mensaje de la excepción trae la respuesta cruda de Azure/Graph (`TransportException`) — los errores más comunes:

| Error | Causa típica |
|---|---|
| `invalid_client` al pedir el token | Client ID o secreto incorrectos, o el secreto ya expiró |
| `Forbidden` / `Access is denied` al enviar | Falta el "Grant admin consent" del paso 2 |
| `ErrorSendAsDenied` | El buzón en `AZURE_MAIL_SENDER` no coincide con el permitido por la Application Access Policy del paso 5 (o esa policy target a otro buzón) |

## Cada proyecto clonado necesita lo suyo

Como se comentó al crear el proyecto: **cada sitio = su propio App Registration**, con su propio `AZURE_MAIL_TENANT_ID`/`AZURE_MAIL_CLIENT_ID`/`AZURE_MAIL_CLIENT_SECRET`. No reutilices las credenciales de `mds` en `operacionesck` o `portalck` — sigue los 5 pasos de arriba una vez por cada uno.
