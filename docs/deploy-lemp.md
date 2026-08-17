# Despliegue en producción (LEMP)

Esta guía asume un servidor Linux con Nginx + MySQL + PHP-FPM ("LEMP") independiente del WAMP de desarrollo.

## 1. Requisitos del servidor

- PHP 8.3+ con extensiones: `pdo_mysql`, `mbstring`, `xml`, `curl`, `bcmath`, `gd` o `imagick`, `zip`, `intl`, `redis` (opcional pero recomendado).
- MySQL 8+ (o MariaDB 10.6+) con el motor **InnoDB** como default (el proyecto ya fuerza `engine=InnoDB` en `config/database.php`, pero confirma que el servidor no esté forzado a MyISAM).
- Composer 2.x, Node 20+ (solo para compilar assets — puede hacerse en CI/local y subir `public/build/` ya compilado si el servidor no tiene Node).
- Nginx.
- Supervisor (para mantener corriendo la cola y Reverb).
- Certificado SSL (Let's Encrypt/Certbot).

## 2. Clonar y preparar el proyecto

```bash
cd /var/www
git clone <tu-repo> mds
cd mds
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## 3. Variables de entorno clave (`.env` de producción)

```env
APP_NAME=MDS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mds
DB_USERNAME=mds_app
DB_PASSWORD={{contraseña segura}}

SESSION_DRIVER=database
SESSION_LIFETIME=180
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.tu-dominio.com

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

MAIL_MAILER=graph
AZURE_MAIL_TENANT_ID={{tenant ID del App Registration de este sitio}}
AZURE_MAIL_CLIENT_ID={{client ID del App Registration de este sitio}}
AZURE_MAIL_CLIENT_SECRET={{secreto del App Registration de este sitio}}
AZURE_MAIL_SENDER=notificaciones@tu-dominio.com
MAIL_FROM_ADDRESS=notificaciones@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"
```

Microsoft retiró la autenticación básica de SMTP AUTH — el envío real usa Microsoft Graph con OAuth2 (app-only), no usuario/contraseña. Cada sitio necesita su propio App Registration en Azure AD; pasos exactos en [`docs/correo-oauth2-azure.md`](correo-oauth2-azure.md). Si por algún motivo necesitas SMTP en vez de Graph, `MAIL_MAILER=smtp` sigue disponible con las variables clásicas (`MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD`/`MAIL_ENCRYPTION`).

**Si el app server no tiene salida directa a internet** (ver §6.1) y necesita salir por un proxy saliente para hablar con `login.microsoftonline.com`/`graph.microsoft.com`, usa:

```env
AZURE_MAIL_HTTP_PROXY=http://<IP_DEL_PROXY_SALIENTE>:<PUERTO>
```

`config/services.php` y `App\Mail\Transport\MicrosoftGraphTransport` ya soportan esta variable — si queda vacía, el transporte se conecta directo, sin cambio de comportamiento.

```env
REVERB_APP_ID={{genera uno nuevo, distinto al de dev}}
REVERB_APP_KEY={{genera uno nuevo}}
REVERB_APP_SECRET={{genera uno nuevo}}
REVERB_HOST=tu-dominio.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

**Importante:** genera `REVERB_APP_ID/KEY/SECRET` nuevos para producción (no reutilices los de desarrollo). Cualquier valor aleatorio de 16+ bytes sirve:

```bash
php -r "echo bin2hex(random_bytes(16));"
```

**`VITE_REVERB_*` se hornea en el JS en tiempo de build, no de arranque.** `npm run build` incrusta estos valores dentro de `public/build/assets/*.js` — si el `.env` en el momento de correr `npm run build` no los tiene (por ejemplo, compilaste en tu máquina local con un `.env` distinto al del servidor), el navegador falla con `You must pass your app key when you instantiate Pusher` aunque el `.env` del servidor esté perfecto. Si compilas en un lugar distinto de donde corre la app (ver `git archive` más abajo), asegúrate de que ese `.env` de build tenga los mismos `VITE_REVERB_APP_KEY/HOST/PORT/SCHEME` que usará producción, y vuelve a compilar/subir `public/build/` si cambian.

## 4. Base de datos

```bash
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\CoreSeeder" --force
```

`CoreSeeder` crea las pantallas base, el rol Administrador y un primer usuario administrador — el correo/nombre se toman de `MDS_ADMIN_EMAIL`/`MDS_ADMIN_NAME` en `.env` (no hace falta editar el seeder). Como el login es sin contraseña, este es el único usuario que existe hasta que él mismo invite a los demás desde la pantalla de "Configuración de acceso".

Si el entorno acumuló datos de prueba (usuarios, bitácora, mensajes) antes de este primer deploy real, límpialos primero con `php artisan mds:clean-test-data` — ver [`docs/limpiar-datos-de-prueba.md`](limpiar-datos-de-prueba.md).

Si vas a activar el módulo de ejemplo o cualquier módulo nuevo con su propio seeder:

```bash
php artisan module:seed Ejemplo
```

**Repite esto por cada módulo que traiga su propio seeder de pantallas**, cada vez que lo agregues — `migrate` crea las tablas del módulo pero nunca corre su seeder, así que las pantallas no aparecen en el sidebar hasta correr `module:seed` a mano.

## 5. Optimización

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Cada vez que cambies `.env` en producción, corre `php artisan config:cache` de nuevo (o `config:clear` si prefieres no cachear config).

**`route:cache` rompe los assets de Livewire si no publicas los estáticos.** Livewire registra la ruta de `livewire.min.js` dinámicamente en tiempo de render (no es una ruta cacheable), así que con `route:cache` activo esa ruta da 404. Corre esto una vez (y de nuevo cada vez que actualices la versión de Livewire):

```bash
php artisan livewire:publish --assets
```

Esto publica el JS/CSS en `public/vendor/livewire/` — Livewire detecta el archivo publicado automáticamente y sirve ese en vez de depender de la ruta dinámica.

## 6. Nginx (server block)

```nginx
server {
    listen 443 ssl http2;
    server_name tu-dominio.com;

    root /var/www/mds/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}

server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;
}
```

**No olvides habilitar el sitio** — dejarlo solo en `sites-available` no hace nada:

```bash
ln -s /etc/nginx/sites-available/tu-dominio.com /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

Este paso se olvida fácil y el fallo es silencioso: `nginx -t` pasa sin quejarse (el archivo huérfano en `sites-available` ni se evalúa) y las peticiones al dominio nuevo caen en el sitio `default`/catch-all del servidor, sin ningún error que apunte a la causa real. Si un dominio nuevo no responde como se espera, lo primero es `ls /etc/nginx/sites-enabled/ | grep tu-dominio`.

Reverb necesita su propio proxy WebSocket. Agrega dentro del bloque `server` (puerto 443):

```nginx
    # /app/  = conexión WebSocket del cliente (Echo)
    # /apps/ = API HTTP que usa el propio servidor al publicar un evento (queue:work)
    location ~ ^/app(s)?/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
```

Y en `.env`, ya que Reverb queda detrás del proxy en el mismo dominio/puerto 443:

```env
REVERB_HOST=tu-dominio.com
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_HOST=tu-dominio.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

## 6.1 Cuando el SSL termina en un proxy externo (servidor aparte)

Si el SSL (por ejemplo un wildcard) se instala en **otro servidor** que hace de reverse proxy, y este servidor de aplicación solo recibe HTTP plano (puertos 80/22 abiertos, sin 443), cambia el bloque del paso 6 por este — sin bloque SSL, solo puerto 80, y con el location del WebSocket de Reverb:

```nginx
server {
    listen 80;
    server_name tu-dominio.com;

    root /var/www/tu-sitio/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # WebSocket de Reverb (/app/) + API HTTP de broadcasting server-side (/apps/) —
    # el proxy externo reenvía aquí mismo (puerto 80), esta location lo enruta
    # internamente al puerto donde escucha Reverb. Usa el REVERB_SERVER_PORT de
    # ESTE sitio — nunca asumas 8080 por default si el app server ya hospeda
    # otros sitios con Reverb, ver "Día cero" antes de la sección 9.1.
    location ~ ^/app(s)?/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

**En el proxy externo** (el que tiene el certificado wildcard) necesitas un server block equivalente que termine el SSL y reenvíe todo hacia este servidor por HTTP, incluyendo los headers `X-Forwarded-*` y el soporte de upgrade para el WebSocket:

```nginx
server {
    listen 443 ssl http2;
    server_name tu-dominio.com;

    ssl_certificate     /ruta/al/wildcard/fullchain.pem;
    ssl_certificate_key /ruta/al/wildcard/privkey.pem;

    location / {
        proxy_pass http://<IP_DEL_APP_SERVER>:80;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;
}
```

**Habilita el sitio en AMBOS lados** — appserver y proxy tienen `sites-available`/`sites-enabled` independientes, olvidar el symlink de cualquiera de los dos deja el sitio sirviendo el catch-all sin ningún error visible (ver nota en la sección 6):

```bash
# en el app server
ln -s /etc/nginx/sites-available/tu-dominio.com /etc/nginx/sites-enabled/ && nginx -t && systemctl reload nginx
# en el proxy externo
ln -s /etc/nginx/sites-available/tu-dominio.com /etc/nginx/sites-enabled/ && nginx -t && systemctl reload nginx
```

**`.env` del app server** — usa `TRUSTED_PROXIES` (ver `bootstrap/app.php`) para que `request()->ip()` vea al cliente real y no al proxy (crítico: el rate limiting por IP y el bloqueo de cuentas de `LoginSecurityManager`/`GuardsAgainstFlooding` dependen de esto), y fuerza el esquema/host correctos ya que este servidor nunca ve tráfico HTTPS directamente:

```env
APP_URL=https://tu-dominio.com
TRUSTED_PROXIES=<IP_DEL_PROXY>

SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.tu-dominio.com

REVERB_HOST=tu-dominio.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_HOST=tu-dominio.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`REVERB_PORT`/`REVERB_SCHEME` en `.env` describen cómo el **navegador** llega a Reverb (a través del proxy, por 443/https) — el proceso `reverb:start` en este servidor sigue escuchando en `127.0.0.1:<REVERB_SERVER_PORT>` sin cambios.

`TRUSTED_PROXIES` se define en `config/security.php` y se aplica en `App\Providers\AppServiceProvider::boot()`, **no** en `bootstrap/app.php` — la clausura de `withMiddleware()` en `bootstrap/app.php` corre antes de que `config`/`env` estén disponibles en el contenedor, así que fijar ahí la IP de confianza no resuelve de forma fiable. Si alguna vez ves `isSecure()` en `false`/URLs en `http://` pese a que `TRUSTED_PROXIES` está bien en `.env`, corre `php artisan config:cache` de nuevo tras el cambio.

**Firewall del app server**: ya que el proxy es quien debe hablarle por el puerto 80 (no el público en general), restringe ese puerto al IP del proxy. Ejemplo con `ufw`:

```bash
sudo ufw allow from <IP_DEL_PROXY> to any port 80 proto tcp
sudo ufw allow 22/tcp
sudo ufw enable
sudo ufw status verbose
```

O con `firewalld`:

```bash
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="<IP_DEL_PROXY>" port port="80" protocol="tcp" accept'
sudo firewall-cmd --permanent --remove-service=http   # quita el 80 abierto a todo el mundo si estaba
sudo firewall-cmd --reload
```

Sin esta restricción, cualquiera que le pegue directo al puerto 80 del app server (saltándose el proxy) podría falsificar `X-Forwarded-Proto`/`X-Forwarded-For` y engañar a `TrustProxies` — por eso `TRUSTED_PROXIES` debe ser la IP real del proxy, nunca `*`, y el puerto 80 debe estar cerrado a todo lo que no sea ese proxy.

## 7. Procesos persistentes (Supervisor)

Crea `/etc/supervisor/conf.d/mds-reverb.conf`:

```ini
[program:mds-reverb]
command=php /var/www/mds/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/mds/storage/logs/reverb.log
```

Y `/etc/supervisor/conf.d/mds-queue.conf`:

```ini
[program:mds-queue]
command=php /var/www/mds/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mds/storage/logs/queue.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start mds-reverb:*
supervisorctl start mds-queue:*
```

**Si el servidor no tiene salida a internet** (por ejemplo una instancia en subred privada sin NAT/gateway) y `apt install supervisor` falla con timeouts de conexión, usa `systemd` directamente en su lugar — ya viene instalado y no depende de descargar nada:

```bash
cat > /etc/systemd/system/mds-reverb.service << 'EOF'
[Unit]
Description=Laravel Reverb
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/mds
ExecStart=/usr/bin/php8.3 artisan reverb:start
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/mds-queue.service << 'EOF'
[Unit]
Description=Laravel Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/mds
ExecStart=/usr/bin/php8.3 artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now mds-reverb mds-queue
systemctl status mds-reverb mds-queue --no-pager
```

Ajusta la ruta del binario de PHP (`/usr/bin/php8.3`) si el servidor solo tiene una versión instalada bajo `/usr/bin/php`. Para reiniciar tras un deploy, usa `systemctl restart mds-reverb mds-queue` en vez de `supervisorctl restart`.

## 8. Permisos

```bash
chown -R www-data:www-data /var/www/mds
chmod -R 775 storage bootstrap/cache
```

## 9. Cada nuevo deploy

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart mds-queue:*
supervisorctl restart mds-reverb:*
```

## 9.1 Deploy automático desde tu máquina (recomendado cuando el manual ya cansa)

Si el app server no tiene salida a internet, el deploy manual son ~20 pasos por SSH cada vez que agregas un módulo. Este mecanismo lo reduce a un solo comando local, **sin** exponer nada nuevo a internet ni entregar llaves de root: un usuario dedicado en el app server que solo puede disparar un script fijo, y un usuario en el proxy que solo puede reenviar la conexión — ninguno de los dos tiene shell.

Diseñado para reutilizarse: **un usuario `deployer` por app server, una llave + script + regla de sudoers por sitio.** Agregar un sitio nuevo (o migrar este patrón a otro app server) no implica tocar lo ya armado para los demás.

### 9.1.0 Día cero: qué preguntar antes de escribir el primer comando

Esta sección tiene placeholders (`<sitio>`, `tu-dominio.com`, `<IP_...>`) a propósito — son datos reales que cambian por sitio y por servidor, y que **no debes asumir ni inventar**. Si estás automatizando este deploy (incluida una sesión de Claude Code), confirma esto con quien lo pidió antes de tocar nada:

- **Dominio** del sitio nuevo.
- **IP del app server** destino, y si es uno **nuevo** o uno que **ya hospeda otros sitios** (cambia el Paso 2/3 de abajo).
- **IP del proxy externo** que termina SSL, si el sitio va detrás de uno (§6.1) — y si ya existe un `jumper` apuntando a ese app server o hay que crear uno.
- **Puerto local de Reverb** (`REVERB_SERVER_PORT`). **Nunca asumas el default (`8080`) si el app server ya hospeda otros sitios** — es un bind real de proceso, no algo que Nginx pueda compartir por `server_name` como sí ocurre con el puerto HTTP principal. Antes de asignarlo, corre en el app server y pregunta/confirma cuál es el siguiente libre en vez de decidirlo en silencio:
  ```bash
  nginx -T 2>/dev/null | grep -E "server_name|listen"
  grep -rH "REVERB_SERVER_PORT" /var/www/*/.env
  ss -tlnp | grep -E ":80[0-9][0-9]\b"
  ```

Cada sitio real que use este mecanismo debería llevar su propia bitácora (`docs/deploy-<sitio>.md` en su propio repo) con las IPs, puertos y credenciales concretas que terminó usando — esta guía se queda genérica a propósito, para que sirva igual de bien al segundo, tercer o décimo sitio clonado de esta plantilla.

### 9.1.1 Por qué es seguro (léelo antes de replicarlo)

- La llave de deploy de cada sitio **solo puede disparar su propio script fijo** (`command=` en `authorized_keys`) — no hay shell, no hay otros comandos, `restrict` desactiva port-forwarding/X11/agente.
- **Restringida por IP de origen** (`from="<ip-interna-del-proxy>"`) — aunque el archivo de la llave se filtre, no sirve desde otro lugar.
- El script de deploy en sí es dueño `root`, no editable por el usuario `deployer`; su único privilegio es un `sudo` **NOPASSWD acotado a ese path exacto, sin argumentos** (`/etc/sudoers.d/<sitio>-deploy`).
- **El pipeline nunca toca `.env` ni `storage/`** — la automatización de deploy no tiene ni necesita conocimiento de las credenciales de BD/Graph/Reverb; esas viven solo en el `.env` del servidor, fuera de este mecanismo por completo.
- El usuario `deployer` (y el `jumper` del proxy) tienen shell `/bin/bash` pero **password bloqueado** (`passwd -l`) — solo entran por la llave restringida, nunca de forma interactiva.
- Cada corrida queda en una bitácora propia (`/var/log/<sitio>-deploy.log`).

### 9.1.2 Configuración inicial de un sitio nuevo

**Paso 1 — genera un par de llaves dedicado** (en tu máquina, PowerShell o Git Bash):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/<sitio>-deploy/deploy-<sitio> -N "" -C "deploy-only@<sitio>"
```

Si el app server destino es uno **nuevo**, genera también su propia llave de salto; si es un app server que **ya hospeda otro sitio con este mismo mecanismo**, reutiliza el usuario `jumper` y su llave existente — no hace falta una nueva, ProxyJump no distingue "para qué sitio" reenvía, solo hacia qué IP:puerto.

**Paso 2 — en el proxy** (solo si el app server destino es nuevo; si ya existe `jumper` apuntando a ese mismo app server, sáltate este paso):

```bash
useradd -m -s /usr/sbin/nologin jumper
passwd -l jumper
mkdir -p /home/jumper/.ssh && chmod 700 /home/jumper/.ssh
echo 'TU_LLAVE_PUBLICA_DE_SALTO' > /home/jumper/.ssh/authorized_keys
chmod 600 /home/jumper/.ssh/authorized_keys
chown -R jumper:jumper /home/jumper

cat > /etc/ssh/sshd_config.d/jumper-<sitio>.conf << 'EOF'
Match User jumper
    ForceCommand /bin/false
    PermitOpen <IP_APP_SERVER>:22
    AllowTcpForwarding yes
    X11Forwarding no
    AllowAgentForwarding no
    PermitTTY no
EOF
sshd -t && systemctl reload ssh
```

Si `jumper` ya existe para otro sitio en el mismo app server, solo agrega otra línea `PermitOpen <IP_NUEVO_APP_SERVER>:22` dentro de su mismo bloque `Match User jumper` — un usuario, varios destinos permitidos.

**Paso 3 — en el app server**, crea `deployer` la primera vez (una sola vez por servidor):

```bash
useradd -m -s /bin/bash deployer   # /bin/bash, NO /usr/sbin/nologin — el forced command de authorized_keys se invoca vía "shell -c comando", con nologin nunca corre
passwd -l deployer
mkdir -p /home/deployer/.ssh && chmod 700 /home/deployer/.ssh
touch /home/deployer/.ssh/authorized_keys && chmod 600 /home/deployer/.ssh/authorized_keys
chown -R deployer:deployer /home/deployer
```

Para **cada sitio** (incluido el primero), agrega su línea a `authorized_keys` y crea su script y su regla de sudoers:

```bash
echo 'from="<IP_INTERNA_DEL_PROXY>",command="sudo -n /usr/local/bin/<sitio>-deploy.sh",restrict TU_LLAVE_PUBLICA_DE_DEPLOY' >> /home/deployer/.ssh/authorized_keys

cat > /usr/local/bin/<sitio>-deploy.sh << 'SCRIPTEOF'
#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/var/www/<sitio>"
LOG="/var/log/<sitio>-deploy.log"
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

systemctl reload php8.3-fpm            # reload, no restart — si el server comparte FPM con otros sitios, no los tumbes
systemctl restart <sitio>-reverb <sitio>-queue

echo "[$(date -Is)] Deploy completado OK" >> "$LOG"
SCRIPTEOF
chown root:root /usr/local/bin/<sitio>-deploy.sh
chmod 700 /usr/local/bin/<sitio>-deploy.sh
touch /var/log/<sitio>-deploy.log && chmod 600 /var/log/<sitio>-deploy.log

echo 'deployer ALL=(root) NOPASSWD: /usr/local/bin/<sitio>-deploy.sh' > /etc/sudoers.d/<sitio>-deploy
chmod 440 /etc/sudoers.d/<sitio>-deploy
visudo -c
```

**Antes de crear nada, verifica que no exista ya** (naming collision entre sitios/servicios existentes en el mismo servidor):

```bash
id deployer 2>&1; ls -la /usr/local/bin/<sitio>-deploy.sh 2>&1; ls /etc/sudoers.d/
grep -rn "^Match" /etc/ssh/sshd_config /etc/ssh/sshd_config.d/ 2>/dev/null   # en el proxy
```

**Paso 4 — SSH config local** (`~/.ssh/<sitio>-deploy/config`), para no manejar dos llaves a mano en cada comando:

```
Host <sitio>-jump
    HostName <IP_PUBLICA_DEL_PROXY>
    User jumper
    IdentityFile ~/.ssh/<sitio>-deploy/jump-<algo>
    IdentitiesOnly yes

Host <sitio>-app
    HostName <IP_APP_SERVER>
    User deployer
    IdentityFile ~/.ssh/<sitio>-deploy/deploy-<sitio>
    IdentitiesOnly yes
    ProxyJump <sitio>-jump
```

**Paso 5 — prueba la cadena** antes de confiar en ella (manda basura a propósito; debe fallar en el `tar`, no antes):

```bash
echo "prueba" | ssh -F ~/.ssh/<sitio>-deploy/config <sitio>-app
```

Si sale `gzip: stdin: not in gzip format` / `tar: ...`, la cadena completa (salto → llave de deploy → `from=` → `sudo` → script) funciona. Si en cambio sale `This account is currently not available`, revisa que el shell de `deployer` sea `/bin/bash`, no `nologin`.

### 9.1.3 El script local que arma y despacha el release

Vive fuera del repo (nunca commitear llaves ni este archivo con secretos), en una carpeta dedicada, p. ej. `C:\wamp64\www\<sitio>-deploy\`:
- `deploy-<sitio>.ps1` — build + empaquetado + envío.
- `production-<sitio>-vite.env` — los `VITE_REVERB_*`/`VITE_APP_NAME` de producción de ESE sitio (Vite los hornea en el JS en tiempo de build, no de arranque — ver nota en §3).

El script (PowerShell; adapta rutas/nombres):

```powershell
param(
    [string]$RepoDir = "C:\wamp64\www\<sitio>",
    [string]$SshConfig = "$env:USERPROFILE\.ssh\<sitio>-deploy\config",
    [string]$ViteEnv = "C:\wamp64\www\<sitio>-deploy\production-<sitio>-vite.env",
    [switch]$Force
)
$ErrorActionPreference = "Stop"
function Fail($msg) { Write-Host "ERROR: $msg" -ForegroundColor Red; exit 1 }

Push-Location $RepoDir
$dirty = git status --porcelain
if ($dirty -and -not $Force) { Fail "Hay cambios sin commitear. Commitea o usa -Force." }
$commit = git rev-parse --short HEAD
Pop-Location

$stamp = [guid]::NewGuid().ToString("N").Substring(0,8)
$buildDir = Join-Path $env:TEMP "<sitio>-release-$stamp"
$archiveTar = Join-Path $env:TEMP "<sitio>-release-$stamp.tar"
$releaseTarGz = Join-Path $env:TEMP "<sitio>-release-$stamp.tar.gz"
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

try {
    git -C $RepoDir archive HEAD -o $archiveTar
    tar -xf $archiveTar -C $buildDir
    Copy-Item $ViteEnv (Join-Path $buildDir ".env") -Force

    Push-Location $buildDir
    composer install --no-dev --optimize-autoloader --no-interaction
    npm ci --no-audit --no-fund
    npm run build
    Pop-Location

    tar -czf $releaseTarGz --exclude=node_modules --exclude=tests -C $buildDir .

    # ssh.exe necesita un file handle real en stdin, no el pipeline de
    # PowerShell (puede corromper binarios) -> se redirige vía cmd.
    cmd /c "ssh.exe -F `"$SshConfig`" <sitio>-app < `"$releaseTarGz`""
    if ($LASTEXITCODE -ne 0) { Fail "Deploy remoto falló (exit $LASTEXITCODE). Revisa /var/log/<sitio>-deploy.log." }

    Write-Host "Deploy completado: $commit" -ForegroundColor Green
}
finally {
    Remove-Item -Recurse -Force $buildDir -ErrorAction SilentlyContinue
    Remove-Item -Force $archiveTar, $releaseTarGz -ErrorAction SilentlyContinue
}
```

Uso del día a día, cada vez que agregues un módulo o cambies código:

```bash
powershell -ExecutionPolicy Bypass -File "C:\wamp64\www\<sitio>-deploy\deploy-<sitio>.ps1"
```

**Corre este comando desde PowerShell nativo, no desde dentro de Git Bash.** Si se invoca `powershell -File ...` desde una sesión de Git Bash, el proceso hijo hereda el `PATH` de Git Bash y el script termina usando el `tar` de MSYS en vez del `tar.exe` de Windows — MSYS `tar` no entiende rutas con letra de unidad (`C:\...`) y falla con `Cannot connect to C: resolve failed`, un error que no tiene nada que ver con el contenido del release.

Verifica siempre después: `curl -I https://<dominio>/` y revisa `/var/log/<sitio>-deploy.log` en el servidor (`Deploy completado OK` debe ser la última línea).

### 9.1.4 Primer deploy de un sitio nuevo: pasos manuales que el pipeline no hace

El pipeline automático (9.1.3) está diseñado para deploys *repetidos* de un sitio que ya funciona — nunca toca `.env` ni corre nada que solo deba pasar una vez. Después del primer envío de código a un sitio recién configurado (9.1.2), completa esto a mano en el app server antes de darlo por listo:

```bash
# El .env se crea a mano fuera del pipeline (9.1.1) y nunca trae APP_KEY —
# sin esto, cualquier request tira 500 (MissingAppKeyException).
sudo -u www-data php8.3 /var/www/<sitio>/artisan key:generate --force
sudo -u www-data php8.3 /var/www/<sitio>/artisan config:cache

# CoreSeeder crea el rol Administrador + el primer usuario admin.
sudo -u www-data php8.3 /var/www/<sitio>/artisan db:seed --class="Database\Seeders\CoreSeeder" --force

# Symlink público de storage (logos/favicons subidos desde /branding).
sudo -u www-data php8.3 /var/www/<sitio>/artisan storage:link
```

**Y cada vez que un módulo nuevo trae su propio seeder de pantallas** (crea registros en `screens`, como hace `Ejemplo`): el pipeline solo corre `migrate`, nunca seeders de módulo. Las tablas del módulo se crean bien pero las pantallas no aparecen en el sidebar hasta correr, a mano, una sola vez por módulo:

```bash
sudo -u www-data php8.3 /var/www/<sitio>/artisan module:seed NombreDelModulo
```

Para un módulo nuevo dentro de un sitio ya montado no hay que tocar nada de este mecanismo — solo correr el mismo comando después de hacer commit (y el `module:seed` si aplica); para un **sitio nuevo** clonado de esta plantilla (ver sección 10), repite 9.1.2 con su propio `<sitio>`, empezando siempre por el "Día cero" (9.1.0).

## 10. Usar este proyecto como plantilla para otro desarrollo

Ver guía completa: [`docs/nuevo-proyecto-desde-plantilla.md`](nuevo-proyecto-desde-plantilla.md). Resumen: clona el repo aparte, crea una BD nueva, ajusta `MDS_ADMIN_EMAIL`/`MDS_ADMIN_NAME` en el `.env` del proyecto nuevo (no hace falta tocar `CoreSeeder`), `migrate --seed`, y desarrolla el contenido específico como módulo(s) nuevo(s) — deja este repo (`mds`) intacto como base para agregar más funciones a futuro.
