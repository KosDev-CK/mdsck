# Conexión OAuth2 con ServiceDesk Plus Cloud (Zoho Accounts)

`Modules/MesaServicio` habla con la API REST v3 de **ServiceDesk Plus Cloud** (SDPOD, de ManageEngine) para hacer seguimiento de tickets. La autenticación es OAuth2 vía **Zoho Accounts** — no hay usuario/contraseña, todo se resuelve con un "Self Client" que produce un **refresh token permanente**, canjeado una sola vez a partir de un **grant token** de vida muy corta (~10 min).

El mailer `graph` de esta misma plantilla (`docs/correo-oauth2-azure.md`) resuelve el problema equivalente para Microsoft Graph — el patrón de código es el mismo (`Modules\MesaServicio\Services\SdpClient`, token cacheado ~50 min).

## 1. Entrar a la Zoho API Console

1. Con la cuenta administradora de tu instancia SDP, entra a [api-console.zoho.com](https://api-console.zoho.com) (o el dominio regional equivalente, ver tabla del paso 5 — para EU es `api-console.zoho.eu`, etc.).
2. Si es la primera vez, tendrás que aceptar los términos de Zoho Developer.

## 2. Registrar un "Self Client"

1. **Add Client** → **Self Client**.
2. Acepta el aviso (los Self Client están pensados para integraciones servidor-a-servidor propias, exactamente este caso).
3. Al terminar, la consola te da un **Client ID** y **Client Secret** — cópialos, van a `SDP_CLIENT_ID`/`SDP_CLIENT_SECRET`.

## 3. Generar el grant token

1. Dentro del Self Client recién creado, pestaña **Generate Code**.
2. **Scope**: `SDPOnDemand.requests.ALL,SDPOnDemand.technicians.ALL,SDPOnDemand.setup.ALL,SDPOnDemand.general.ALL` (separados por coma, sin espacios — confirmado contra una instancia real: sin `technicians.ALL` el endpoint `/technicians` responde 401 aunque el resto del token sea válido, y sin `general.ALL` algunas consultas fallan también. Este módulo nunca escribe en SDP, pero los scopes granulares `.READ` no siempre están disponibles en la consola según la versión — si tu consola sí los ofrece, `.READ` es suficiente y más restrictivo).
3. **Time Duration**: el máximo permitido (normalmente 10 minutos) — el reloj empieza a correr en cuanto lo generas, así que ten listo el paso 4 antes de generarlo.
4. **Description**: algo identificable, ej. "MesaServicio - mdsck".
5. Clic en **Create** — te da un **grant token** (código largo tipo `1000.xxxxx.yyyyy`). Cópialo de inmediato.

## 4. Canjear el grant token por el refresh token (una sola vez)

Esto se hace con una sola llamada, de un solo uso — el grant token expira a los 10 min y no vuelve a servir después de canjeado (ni aunque no haya expirado). Usa `curl` o Postman:

```bash
curl -X POST "https://accounts.zoho.com/oauth/v2/token" \
  -d "grant_type=authorization_code" \
  -d "client_id={{Client ID del paso 2}}" \
  -d "client_secret={{Client Secret del paso 2}}" \
  -d "code={{grant token del paso 3}}"
```

Cambia `accounts.zoho.com` por el dominio de cuentas de tu región (tabla abajo) si tu instancia no es de EE. UU.

La respuesta trae `refresh_token` (permanente, no expira salvo que lo revoques o dejes de usarlo por mucho tiempo) y un `access_token`/`expires_in` de esa primera vez (no hace falta guardar ese access_token, `SdpClient` pide los suyos). Copia el `refresh_token` — va a `SDP_REFRESH_TOKEN`.

## 5. Variables en `.env`

```env
SDP_CLIENT_ID={{Client ID}}
SDP_CLIENT_SECRET={{Client Secret}}
SDP_REFRESH_TOKEN={{refresh_token del paso 4}}

# Segmento de portal en la URL de tu instancia SDP.
# Si entras a https://sdpondemand.manageengine.com/app/tuempresa/... el portal es "tuempresa".
SDP_PORTAL=tuempresa

SDP_API_DOMAIN=https://sdpondemand.manageengine.com
SDP_ACCOUNTS_DOMAIN=https://accounts.zoho.com
```

`config/services.php` (`servicedesk_plus`) lee estas variables; `Modules\MesaServicio\Services\SdpClient` es el cliente que las usa, registrado como singleton en `MesaServicioServiceProvider::register()`.

### Dominios por región

SDP Cloud y Zoho Accounts viven en dominios distintos según la región donde se contrató la instancia — **nunca asumas el dominio de EE. UU. por defecto**, confírmalo con la URL real a la que entra el equipo de mesa de servicio todos los días.

| Región | `SDP_API_DOMAIN` | `SDP_ACCOUNTS_DOMAIN` |
|---|---|---|
| Estados Unidos (US) | `https://sdpondemand.manageengine.com` | `https://accounts.zoho.com` |
| Europa (EU) | `https://sdpondemand.manageengine.eu` | `https://accounts.zoho.eu` |
| India (IN) | `https://sdpondemand.manageengine.in` | `https://accounts.zoho.in` |
| Australia (AU) | `https://servicedeskplus.net.au` | `https://accounts.zoho.com.au` |
| Japón (JP) | `https://servicedeskplus.jp` | `https://accounts.zoho.jp` |
| Canadá (CA) | `https://servicedeskplus.ca` | `https://accounts.zohocloud.ca` |
| Reino Unido (UK) | `https://servicedeskplus.uk` | `https://accounts.zoho.uk` |

Nota: en todos los casos el dominio va **sin** slash final ni `/api/v3` — `SdpClient` arma la ruta completa (`{api_domain}/app/{portal}/api/v3/...`).

## 6. Verificar que funciona

```bash
php artisan sdp:test-connection
```

Si todo está bien configurado, imprime cuántos técnicos hay en la instancia (o el nombre del primero, según lo que devuelva la API). Si algo falla, el mensaje de error trae el status HTTP y el cuerpo crudo de la respuesta de Zoho/SDP.

## Troubleshooting

| Error | Causa típica |
|---|---|
| `invalid_client` al pedir el token | `SDP_CLIENT_ID`/`SDP_CLIENT_SECRET` incorrectos, o pertenecen a un Self Client de otra región/cuenta |
| `invalid_code` al canjear el grant token (paso 4) | El grant token ya expiró (pasaron los ~10 min) o ya se canjeó antes — genera uno nuevo desde el paso 3 y repite el canje de inmediato |
| `invalid_grant` al pedir un access token con `SDP_REFRESH_TOKEN` | El refresh token es incorrecto, se revocó desde la Zoho API Console, o corresponde a un `client_id` distinto del configurado |
| `403` / `You are not authorized to perform this operation` al llamar `requests`/`technicians` | Al grant token original le faltó alguno de los 3 scopes del paso 3 — hay que generar un grant token nuevo con el scope completo y repetir el canje (el refresh token viejo no gana el scope nuevo) |
| Todo responde `404` | `SDP_PORTAL` no coincide con el segmento real de la URL de tu instancia, o `SDP_API_DOMAIN` es el de otra región |
| Tarda o falla intermitentemente | Confirma que `SDP_ACCOUNTS_DOMAIN` y `SDP_API_DOMAIN` son de la **misma región** — mezclarlos (ej. accounts de EU con api de US) no funciona, un Self Client solo es válido en la región donde se creó |
| `401` en un endpoint puntual (ej. `technicians`) pero otros sí funcionan (ej. `requests`) | Al token le falta el scope de ese recurso — el grant token se generó sin `SDPOnDemand.<recurso>.ALL`. Genera un grant token nuevo con el scope completo (paso 3) y repite el canje (paso 4); el refresh token viejo no gana el scope nuevo, hay que reemplazarlo |
| `{"error":"invalid_code"}` al pedir un access token con `grant_type=refresh_token` | El valor guardado en `SDP_REFRESH_TOKEN` es en realidad el **grant token** del paso 3 (código de un solo uso, ~10 min), no el `refresh_token` que devuelve el canje del paso 4 — falta hacer ese canje |
| `EXTRA_KEY_FOUND_IN_JSON` sobre el campo `list_info` | Se está mandando la petición por `POST` con el `input_data` en el cuerpo — los listados van por `GET` con `input_data` como parámetro de query string (JSON serializado). `SdpClient::getListInfo()` ya lo hace así; si ves este error es que algo está llamando directo a la API sin pasar por el cliente |

## Cada proyecto clonado necesita lo suyo

Igual que con Microsoft Graph: si esta plantilla se clona para un sitio nuevo con su propia instancia de ServiceDesk Plus, ese sitio necesita su propio Self Client (su propio `SDP_CLIENT_ID`/`SDP_CLIENT_SECRET`/`SDP_REFRESH_TOKEN`) — no reutilices las credenciales de otro proyecto.
