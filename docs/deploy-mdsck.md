# Deploy de mdsck — bitácora y guía específica de este sitio

Este documento es **exclusivo de `mdsck`**, a diferencia de [`deploy-lemp.md`](deploy-lemp.md) (guía genérica heredada de la plantilla MDS, válida para cualquier clon). Aquí quedan los datos reales de infraestructura y el checklist para los deploys. `mdsck` comparte infraestructura (app server, proxy, mecanismo de deploy) con `portalck`/`operacionesck`, pero es un proyecto independiente — no comparte código, base de datos ni ciclo de desarrollo con ellos.

## Infraestructura real

| | |
|---|---|
| Dominio | `mds.ck.com.mx` |
| App server | `172.16.11.89` (hostname `portalwebkosmos01`) — mismo que `portalck`/`operacionesck` |
| Carpeta en el app server | `/var/www/mdsck` |
| Proxy externo (SSL) | `150.136.232.112` (hostname `kosmos-proxy-2023`) — certificado wildcard `*.ck.com.mx` |
| BD | `mdsck`, usuario dedicado `mdsck_app`, host `127.0.0.1` |
| Puerto HTTP en el app server | `80` (compartido vía `server_name`, igual que `operacionesck`) |
| Puerto local de Reverb | `127.0.0.1:8084` (`REVERB_SERVER_PORT` en `.env`) |
| Proxy saliente para Azure/Graph | `http://172.16.11.69:3128` (`AZURE_MAIL_HTTP_PROXY`) — el app server no tiene salida directa a internet |
| IP de confianza (reverse proxy) | `172.16.11.69` (`TRUSTED_PROXIES`) |
| Admin inicial (`CoreSeeder`) | `victor.gonzalez@landit.com.mx` |
| App Registration Azure AD (correo) | Ya existe — reutiliza tenant/client id/secret ya provistos (confirmar valores exactos antes del primer deploy) |

Mapa de puertos HTTP/Reverb en `172.16.11.89` (confirmado el 2026-08-24 vía `nginx -T` / `grep REVERB_SERVER_PORT /var/www/*/.env`):

| Puerto | Uso |
|---|---|
| `80` | Compartido por `capacity`, `ciberseguridad`, `cksite`, `compras`, `mdslandit`, `monitor_menu`, `operacionesck` y ahora **`mdsck`** — cada uno con su propio `server_name`. |
| `8080` | `firmas-local` (Nginx, servicio local) |
| `8081` | `portalck` — HTTP dedicado (excepción histórica) |
| `8082` | `portalck` — Reverb |
| `8083` | `operacionesck` — Reverb |
| `8084` | **`mdsck`** — Reverb (nuevo) |

`mdslandit` (mismo servidor, `php8.1-fpm.sock`, sin dominio `.ck.com.mx` propio) es un sitio **distinto y no relacionado** con este proyecto — no tocar.

Antes de un futuro sitio nuevo en este mismo appserver, vuelve a verificar el mapa con:
```bash
nginx -T 2>/dev/null | grep -E "server_name|listen"
grep -rH "REVERB_SERVER_PORT" /var/www/*/.env
ss -tlnp | grep -E ":80[0-9][0-9]\b"
```

## Dónde viven los scripts y llaves de deploy (fuera de este repo)

- `C:\wamp64\www\mdsck-deploy\` (máquina local, Windows, **no se commitea**):
  - `deploy-mdsck.ps1` — el comando de deploy del día a día.
  - `production-vite.env` — variables `VITE_*` que se hornean en el build de producción (ya incluye `VITE_REVERB_APP_KEY` generado).
  - `nginx-mdsck.conf` — los dos bloques de Nginx (app server + proxy), listos para copiar.
- `~/.ssh/mdsck-deploy/` — llave SSH dedicada (`deploy-mdsck` / `.pub`, ya generada) + `config` (reutiliza el `jumper` de portalck para el salto al appserver, mismo patrón que `operacionesck`).
- En el appserver (pendiente de provisionar, ver checklist abajo): usuario `deployer` (compartido, ya existe), script fijo `/usr/local/bin/mdsck-deploy.sh`, regla `/etc/sudoers.d/mdsck-deploy`, log `/var/log/mdsck-deploy.log`.
- Servicios systemd: `mdsck-reverb`, `mdsck-queue`.

## Checklist de aprovisionamiento inicial (pendiente — requiere acceso root al app server y al proxy)

**1. Base de datos** (en el app server o donde viva MySQL):
```sql
CREATE DATABASE mdsck CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crea el usuario para AMBOS hosts, no solo 127.0.0.1: MySQL resuelve la
-- conexión entrante de PHP (DB_HOST=127.0.0.1) como 'localhost' vía DNS
-- inverso, así que 'mdsck_app'@'127.0.0.1' solo no basta — pasó en el primer
-- deploy real (2026-08-24), migrate fallaba con "Access denied for user
-- 'mdsck_app'@'localhost'" hasta agregar esta segunda cuenta.
CREATE USER 'mdsck_app'@'127.0.0.1' IDENTIFIED BY '{{contraseña segura}}';
CREATE USER 'mdsck_app'@'localhost' IDENTIFIED BY '{{la misma contraseña}}';
GRANT ALL PRIVILEGES ON mdsck.* TO 'mdsck_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON mdsck.* TO 'mdsck_app'@'localhost';
FLUSH PRIVILEGES;
```

**2. Autorizar la llave de deploy** (usuario `deployer`, ya existe en el server):
```bash
echo 'from="172.16.11.69",command="sudo -n /usr/local/bin/mdsck-deploy.sh",restrict ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAJ2eOMdwFKgPou/SjUmvs9HUxUz2yI7s7PQ+c0ha8g2 deploy-only@mdsck' >> /home/deployer/.ssh/authorized_keys
```
(La IP `from=` es la del proxy interno que reenvía al app server — ajusta si `172.16.11.69` no es la IP correcta vista por `deployer`; confírmalo con `who`/`last` en un salto de prueba u otro sitio ya andando.)

**3. Script de deploy** — crear `/usr/local/bin/mdsck-deploy.sh`:
```bash
cat > /usr/local/bin/mdsck-deploy.sh << 'SCRIPTEOF'
#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/var/www/mdsck"
LOG="/var/log/mdsck-deploy.log"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "[$(date -Is)] Deploy iniciado" >> "$LOG"

cat > "$TMP/release.tar.gz"
mkdir -p "$TMP/extracted"
tar -xzf "$TMP/release.tar.gz" -C "$TMP/extracted" --no-same-owner

rsync -a --delete \
  --exclude ".env" \
  --exclude "storage/" \
  --exclude "bootstrap/cache/" \
  "$TMP/extracted/" "$SITE_DIR/"

chown -R www-data:www-data "$SITE_DIR"
find "$SITE_DIR" -type d -exec chmod 755 {} \;
find "$SITE_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$SITE_DIR/storage" "$SITE_DIR/bootstrap/cache"

cd "$SITE_DIR"
sudo -u www-data php8.3 artisan migrate --force
sudo -u www-data php8.3 artisan config:cache
sudo -u www-data php8.3 artisan route:cache
sudo -u www-data php8.3 artisan view:cache
sudo -u www-data php8.3 artisan livewire:publish --assets
sudo -u www-data php8.3 artisan db:seed --class=Database\\Seeders\\CoreSeeder --force

systemctl reload php8.3-fpm
systemctl restart mdsck-reverb mdsck-queue

echo "[$(date -Is)] Deploy completado OK" >> "$LOG"
SCRIPTEOF
chown root:root /usr/local/bin/mdsck-deploy.sh
chmod 700 /usr/local/bin/mdsck-deploy.sh
touch /var/log/mdsck-deploy.log && chmod 600 /var/log/mdsck-deploy.log

echo 'deployer ALL=(root) NOPASSWD: /usr/local/bin/mdsck-deploy.sh' > /etc/sudoers.d/mdsck-deploy
chmod 440 /etc/sudoers.d/mdsck-deploy
visudo -c
```

La línea `db:seed --class=Database\Seeders\CoreSeeder` se agregó (2026-08-24) porque el pipeline automático solo corre `migrate`, no seeders — así que una pantalla nueva agregada al array `$screens` de `CoreSeeder` (ver [`agregar-pantallas.md`](agregar-pantallas.md)) nunca llegaba a producción aunque el código ya estuviera desplegado. `CoreSeeder` es idempotente (`updateOrCreate`), así que correrlo en cada deploy es seguro y no duplica ni resetea nada.

**Si el servidor ya está provisionado** (como es el caso de `mdsck` desde el primer deploy real), esta línea no aparece sola — hay que parchear el archivo ya existente a mano, una sola vez, con acceso root directo (no por el canal restringido `deployer`, que solo puede invocar el script tal cual está, no editarlo):
```bash
# En el app server, como root:
sed -i '/livewire:publish --assets/a sudo -u www-data php8.3 artisan db:seed --class=Database\\Seeders\\CoreSeeder --force' /usr/local/bin/mdsck-deploy.sh
grep -A1 'livewire:publish' /usr/local/bin/mdsck-deploy.sh   # confirmar que quedó la línea nueva justo debajo
```
Después de este parche único, todos los deploys futuros vía `deploy-mdsck.ps1` quedan 100% automáticos: código + pantallas nuevas del `CoreSeeder` en un solo paso, sin intervención manual.

**Cuidado con las comillas al parchear** (mordió en el primer deploy real que ejecutó esta línea, 2026-08-25): el valor de `--class=` **debe ir entre comillas dobles** — `--class="Database\Seeders\CoreSeeder"`, tal como aparece en el paso manual de arriba (línea "de vuelta en el app server"). Si queda sin comillas, bash se come las diagonales invertidas **al ejecutar** esa línea del script (no al escribirla) — el valor que realmente le llega a PHP queda como `DatabaseSeedersCoreSeeder` (sin diagonales), Laravel le antepone su namespace por defecto, y el error real es `Class "Database\Seeders\DatabaseSeedersCoreSeeder" does not exist`. Como el script tiene `set -euo pipefail`, se detiene ahí mismo — **`systemctl reload php8.3-fpm` y `restart mdsck-reverb mdsck-queue` nunca corren**, aunque el código ya se haya sincronizado y las cachés ya se hayan reconstruido. Si esto pasa: corregir la línea a mano en el servidor (agregar las comillas dobles), confirmar con `grep -A1 'livewire:publish' /usr/local/bin/mdsck-deploy.sh`, y volver a correr `deploy-mdsck.ps1` — es seguro reintentarlo completo, `migrate` no repite lo ya aplicado.

**4. Nginx** — copiar los dos bloques de `mdsck-deploy/nginx-mdsck.conf` (ya generado):
```bash
# En el app server (172.16.11.89):
# pega el bloque 1 en /etc/nginx/sites-available/mdsck
ln -s /etc/nginx/sites-available/mdsck /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

**En el proxy externo (150.136.232.112, `kosmos-proxy-2023`) el archivo NO se llama `mdsck`** — la convención real de este proxy nombra el archivo igual que el dominio: **`mds.ck.com.mx`** (confírmalo con `ls /etc/nginx/sites-available/ | grep ck.com.mx` antes de asumir el nombre — en el primer deploy real el symlink apuntó a `mdsck` y `nginx -t` falló con "No such file or directory" hasta corregir el nombre). El bloque real usado en producción (más completo que el genérico de `nginx-lemp.md` — incluye headers de seguridad, timeouts largos y logs propios, igual que los demás sitios de este proxy):

```bash
cat > /etc/nginx/sites-available/mds.ck.com.mx << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name mds.ck.com.mx;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name mds.ck.com.mx;

    ssl_certificate     /etc/nginx/ssl/fullchain_ck_com_mx.crt;
    ssl_certificate_key /etc/nginx/ssl/ck.com.mx.key;

    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    access_log /var/log/nginx/nginx.mds.ck.com.mx.access.log;
    error_log  /var/log/nginx/nginx.mds.ck.com.mx.error.log;

    add_header X-Xss-Protection "1; mode=block" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubdomains" always;
    add_header Referrer-Policy "same-origin";
    add_header Content-Security-Policy "frame-ancestors self https://mds.ck.com.mx" always;
    add_header Permissions-Policy "geolocation=(), midi=(), sync-xhr=(), accelerometer=(), gyroscope=(), magnetometer=(), payment=(), camera=(), microphone=(), usb=(), fullscreen=(self)" always;
    add_header X-Powered-By "CKSites" always;
    add_header X-Permitted-Cross-Domain-Policies "none";
    add_header Cross-Origin-Embedder-Policy "unsafe-none; report-to='default'";
    add_header Cross-Origin-Opener-Policy "unsafe-none";
    add_header Cross-Origin-Resource-Policy "cross-origin";

    location / {
        proxy_read_timeout 300s;
        proxy_connect_timeout 300s;
        proxy_send_timeout 300s;

        proxy_headers_hash_max_size 512;
        proxy_headers_hash_bucket_size 64;

        proxy_buffering on;
        proxy_pass http://172.16.11.89:80;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Host $host;

        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_cache_bypass $http_upgrade;
        proxy_redirect off;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
ln -s /etc/nginx/sites-available/mds.ck.com.mx /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

**5. Systemd** (o Supervisor si ya está instalado en este server — ver `deploy-lemp.md` sección 7):
```bash
cat > /etc/systemd/system/mdsck-reverb.service << 'EOF'
[Unit]
Description=Laravel Reverb (mdsck)
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/mdsck
ExecStart=/usr/bin/php8.3 artisan reverb:start --host=127.0.0.1 --port=8084
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/mdsck-queue.service << 'EOF'
[Unit]
Description=Laravel Queue Worker (mdsck)
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/mdsck
ExecStart=/usr/bin/php8.3 artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable mdsck-reverb mdsck-queue
```
**No uses `--now` todavía** — el directorio no tiene código aún, entrarían en crash-loop. El primer `systemctl restart` del script de deploy (paso 3) los arranca ya con el código presente.

**6. Primer deploy — este app server NO tiene salida a internet, así que `git clone` en el servidor no funciona.** El código se construye en la máquina local (`composer install --no-dev`, `npm run build`) y se manda ya compilado por el canal SSH restringido — el servidor nunca necesita internet. Solo hay que crear el `.env` a mano ANTES del primer envío, porque el pipeline nunca lo toca ni lo sube (queda excluido del `rsync`).

**6a. En el app server (root, SSH directo)** — crea la carpeta, el `.env` real y la estructura de `storage`/`bootstrap/cache`:
```bash
mkdir -p /var/www/mdsck
nano /var/www/mdsck/.env   # pega la plantilla de abajo con los valores reales

# El script de deploy excluye storage/ y bootstrap/cache/ del rsync a propósito
# (para no pisar logs/sesiones/archivos subidos en deploys futuros) — eso significa
# que en un sitio NUEVO estas carpetas nunca las crea el pipeline, hay que hacerlo
# a mano una sola vez o el primer deploy falla con "chmod: cannot access
# '/var/www/mdsck/bootstrap/cache': No such file or directory".
mkdir -p /var/www/mdsck/storage/framework/cache/data
mkdir -p /var/www/mdsck/storage/framework/sessions
mkdir -p /var/www/mdsck/storage/framework/views
mkdir -p /var/www/mdsck/storage/logs
mkdir -p /var/www/mdsck/storage/app/public
mkdir -p /var/www/mdsck/bootstrap/cache
chown -R www-data:www-data /var/www/mdsck
chmod -R 775 /var/www/mdsck/storage /var/www/mdsck/bootstrap/cache
```

**6b. En tu máquina local** — con los pasos 2–5 ya hechos, corre el pipeline normal:
```powershell
powershell -ExecutionPolicy Bypass -File "C:\wamp64\www\mdsck-deploy\deploy-mdsck.ps1"
```
Esto compila localmente, empaqueta el `.tar.gz` y lo manda por el canal restringido. En el servidor, `mdsck-deploy.sh` hace `rsync` (respetando el `.env` ya puesto porque está excluido), corre `migrate`/`config:cache`/`route:cache`/`view:cache`/`livewire:publish`, y arranca `mdsck-reverb`/`mdsck-queue` por primera vez vía `systemctl restart`.

**6c. De vuelta en el app server** — pasos que el pipeline automático nunca hace (solo la primera vez):
```bash
sudo -u www-data php8.3 artisan key:generate --force
sudo -u www-data php8.3 artisan config:cache
sudo -u www-data php8.3 artisan db:seed --class="Database\Seeders\CoreSeeder" --force
sudo -u www-data php8.3 artisan storage:link
```

### Plantilla `.env` de producción para `mdsck`

```env
APP_NAME="MDS CK"
APP_KEY=
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mds.ck.com.mx

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mdsck
DB_USERNAME=mdsck_app
DB_PASSWORD={{contraseña de la BD, paso 1}}

SESSION_DRIVER=database
SESSION_LIFETIME=180
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.ck.com.mx

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

MDS_ADMIN_EMAIL=victor.gonzalez@landit.com.mx
MDS_ADMIN_NAME="Victor Gonzalez"

MAIL_MAILER=graph
AZURE_MAIL_TENANT_ID={{ya existe — confirmar valor}}
AZURE_MAIL_CLIENT_ID={{ya existe — confirmar valor}}
AZURE_MAIL_CLIENT_SECRET={{ya existe — confirmar valor}}
AZURE_MAIL_SENDER={{buzón real ya autorizado en el App Registration — ver nota abajo}}
AZURE_MAIL_HTTP_PROXY=http://172.16.11.69:3128
MAIL_FROM_ADDRESS="${AZURE_MAIL_SENDER}"
MAIL_FROM_NAME="${APP_NAME}"

TRUSTED_PROXIES=172.16.11.69

REVERB_APP_ID={{ver production-vite.env / .env real del servidor — no se repite aquí}}
REVERB_APP_KEY={{idem}}
REVERB_APP_SECRET={{idem}}
REVERB_HOST=mds.ck.com.mx
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8084

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`REVERB_APP_ID/KEY/SECRET` ya fueron generados aleatoriamente para este sitio (2026-08-24) — no reutilizar los de `mds`/`portalck`/`operacionesck`. Los valores reales viven solo en `C:\wamp64\www\mdsck-deploy\production-vite.env` (local, no se commitea) y en el `.env` del servidor — nunca en este doc versionado, para no dejar secretos de producción en el historial de git. `AZURE_MAIL_TENANT_ID/CLIENT_ID/CLIENT_SECRET` quedan como placeholder por la misma razón — el App Registration ya existe, solo falta copiar los valores reales al `.env` del servidor.

**`AZURE_MAIL_SENDER` — ojo con esto:** en el primer deploy real se dejó accidentalmente el placeholder de ejemplo (`notificaciones@ck.com.mx`, un buzón que no existe) y Microsoft Graph rechazó el envío con `404: The requested user 'notificaciones@ck.com.mx' is invalid.` al pedir el código de login (pantalla 500). Tiene que ser un buzón real que ya esté cubierto por la Application Access Policy del App Registration — revisa qué usan `portalck`/`operacionesck` (`grep AZURE_MAIL_SENDER /var/www/operacionesck/.env /var/www/portalck/.env`) si no tienes uno dedicado para `mdsck`. **Cada vez que edites `.env` a mano en el servidor, corre `php artisan config:cache` de nuevo** — si no, Laravel sigue sirviendo la config vieja cacheada y el cambio no se nota hasta la próxima vez que se recachee.

**Confirmar/crear también:**
- DNS: `mds.ck.com.mx` debe resolver a `150.136.232.112` — confirmado, ya resolvía (wildcard `*.ck.com.mx`), no hizo falta nada nuevo.
- IP `from=` del paso 2 (autorización de la llave) — confirmado, `172.16.11.69` fue correcto.

## Checklist para el día a día (deploys posteriores al inicial)

1. Desarrolla y commitea el cambio/módulo, corre `php artisan test` local.
2. Desde la máquina local (PowerShell nativo, **no** Git Bash — ver gotcha #1):
   ```powershell
   powershell -ExecutionPolicy Bypass -File "C:\wamp64\www\mdsck-deploy\deploy-mdsck.ps1"
   ```
3. **Si el módulo agrega pantallas nuevas** (trae su propio seeder que crea `Screen`s):
   ```bash
   sudo -u www-data php8.3 /var/www/mdsck/artisan module:seed NombreDelModulo
   ```
4. Verifica: `curl -I https://mds.ck.com.mx/` y revisa `/var/log/mdsck-deploy.log` (última línea debe ser `Deploy completado OK`).

## Gotchas heredados de `operacionesck` (aplican igual aquí)

1. Corre el `.ps1` desde PowerShell nativo, no desde Git Bash (el `tar` de MSYS no entiende rutas `C:\...`).
2. En el proxy externo, symlinkea `sites-available` → `sites-enabled` — si se olvida, `nginx -t` pasa sin quejarse y el tráfico cae silenciosamente en el catch-all.
3. El primer deploy no genera `APP_KEY` — hazlo a mano (paso 6c de arriba) o cualquier request tira 500.
4. Los seeders de módulo no corren solos — `module:seed` a mano tras cada módulo nuevo.
5. El puerto local de Reverb es un bind real por proceso — nunca reutilices uno ya ocupado por otro sitio en este mismo appserver.

## Gotchas nuevos descubiertos en el primer deploy real de `mdsck` (2026-08-24)

6. **`APP_KEY=` debe existir como línea (aunque vacía) en el `.env` antes de correr `key:generate`.** Si la línea no está, `artisan key:generate` falla con "No APP_KEY variable was found in the .env file." — no basta con que la variable esté ausente, Laravel busca la línea para reemplazarla.
7. **`storage/` y `bootstrap/cache/` no las crea el pipeline** — el script las excluye del `rsync` a propósito (para no pisar logs/sesiones/archivos subidos en deploys futuros). En un sitio nuevo hay que crearlas a mano (ver paso 6a) o el primer deploy falla con `chmod: cannot access '.../bootstrap/cache': No such file or directory`.
8. **El usuario de MySQL necesita cuenta para `127.0.0.1` Y para `localhost`.** Aunque `DB_HOST=127.0.0.1` en el `.env`, MySQL puede resolver la conexión entrante como `'usuario'@'localhost'` — si solo existe la cuenta `@127.0.0.1`, `migrate` falla con `Access denied for user 'x'@'localhost'` aunque la contraseña sea correcta.
9. **El nombre del archivo Nginx en el proxy externo no sigue el mismo patrón que en el app server.** En `kosmos-proxy-2023` los sitios `*.ck.com.mx` se nombran igual que el dominio (`mds.ck.com.mx`), no como el proyecto (`mdsck`) — verifica la convención real de ese proxy antes de crear el symlink, o `nginx -t` fallará con "No such file or directory" apuntando a un archivo que nunca existió.
10. **`AZURE_MAIL_SENDER` con un buzón que no existe da 500 silencioso al pedir el código de login**, con el mensaje real solo visible en `storage/logs/laravel.log` (`Microsoft Graph rechazó el envío (404): The requested user '...' is invalid.`). Usa siempre un buzón real ya autorizado en el App Registration.
11. **Cualquier edición manual de `.env` en el servidor necesita `php artisan config:cache` después**, o Laravel sigue usando la config vieja cacheada del deploy anterior.
