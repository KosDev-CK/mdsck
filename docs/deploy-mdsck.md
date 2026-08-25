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
CREATE USER 'mdsck_app'@'127.0.0.1' IDENTIFIED BY '{{contraseña segura}}';
GRANT ALL PRIVILEGES ON mdsck.* TO 'mdsck_app'@'127.0.0.1';
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

**4. Nginx** — copiar los dos bloques de `mdsck-deploy/nginx-mdsck.conf` (ya generado):
```bash
# En el app server:
# pega el bloque 1 en /etc/nginx/sites-available/mdsck
ln -s /etc/nginx/sites-available/mdsck /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# En el proxy externo (150.136.232.112):
# pega el bloque 2 en /etc/nginx/sites-available/mdsck (mismo nombre, otro server)
ln -s /etc/nginx/sites-available/mdsck /etc/nginx/sites-enabled/
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
AZURE_MAIL_SENDER=notificaciones@ck.com.mx
AZURE_MAIL_HTTP_PROXY=http://172.16.11.69:3128
MAIL_FROM_ADDRESS=notificaciones@ck.com.mx
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

**Confirmar/crear también:**
- DNS: `mds.ck.com.mx` debe resolver a `150.136.232.112` (si `*.ck.com.mx` ya es wildcard DNS hacia el proxy, no hace falta nada nuevo — verificar con `nslookup mds.ck.com.mx`).
- IP `from=` del paso 2 (autorización de la llave) — confirmar cuál es la IP real que ve `deployer` al conectar a través del proxy interno.

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
3. El primer deploy no genera `APP_KEY` — hazlo a mano (paso 6 de arriba) o cualquier request tira 500.
4. Los seeders de módulo no corren solos — `module:seed` a mano tras cada módulo nuevo.
5. El puerto local de Reverb es un bind real por proceso — nunca reutilices uno ya ocupado por otro sitio en este mismo appserver.
