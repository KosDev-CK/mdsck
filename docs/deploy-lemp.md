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

MAIL_MAILER=smtp
MAIL_HOST={{tu servidor SMTP, ej. smtp.office365.com}}
MAIL_PORT=587
MAIL_USERNAME={{usuario}}
MAIL_PASSWORD={{contraseña o app password}}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"

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

## 4. Base de datos

```bash
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\CoreSeeder" --force
```

`CoreSeeder` crea las pantallas base, el rol Administrador y un primer usuario administrador (ajusta el correo en `database/seeders/CoreSeeder.php` antes de este paso si no debe ser `victor.gonzalez@landit.com.mx`). Como el login es sin contraseña, este es el único usuario que existe hasta que él mismo invite a los demás desde la pantalla de "Configuración de acceso".

Si vas a activar el módulo de ejemplo o cualquier módulo nuevo con su propio seeder:

```bash
php artisan module:seed Ejemplo
```

## 5. Optimización

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Cada vez que cambies `.env` en producción, corre `php artisan config:cache` de nuevo (o `config:clear` si prefieres no cachear config).

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

Reverb necesita su propio proxy WebSocket. Agrega dentro del bloque `server` (puerto 443):

```nginx
    location /app/ {
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

## 10. Usar este proyecto como plantilla para otro desarrollo

1. Copia/clona el repo a la nueva carpeta de proyecto.
2. Cambia `APP_NAME` en `.env` y en `composer.json`/`package.json` si aplica.
3. Ajusta el correo del primer administrador en `database/seeders/CoreSeeder.php`.
4. Borra el módulo `Modules/Ejemplo` (o consérvalo como referencia) y crea tus módulos de contenido reales con `php artisan module:make NombreModulo`.
5. Corre `composer install`, `npm install`, configura `.env` para el nuevo entorno, `php artisan migrate --seed` con `CoreSeeder`.
6. Todo lo demás (login sin contraseña, 2FA, perfiles/pantallas, invitaciones, notificaciones en tiempo real, pool de conexiones, bitácora de seguridad) ya queda listo — solo desarrollas el contenido del nuevo módulo.
