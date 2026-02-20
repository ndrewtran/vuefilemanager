# VueFileManager — Easypanel Deployment Guide

## Prerequisites

- An Easypanel instance (v1.x+)
- A domain name pointed at your server
- The built frontend assets committed to your repository (run `npm run prod` locally first, or set up a CI build step)

---

## Step 1: Create a New Project

1. Open your Easypanel dashboard and click **Create Project**
2. Give it a name, e.g. `vuefilemanager`

---

## Step 2: Add a MySQL Database Service

1. Inside your project, click **Add Service → MySQL**
2. Configure:
   - **Service name:** `db`
   - **MySQL version:** 8.0 (or 5.7)
   - **Database name:** `vuefilemanager`
   - **Username:** `vfm_user`
   - **Password:** (generate a strong password and save it)
3. Click **Create**

---

## Step 3: Add the Application Service

1. Click **Add Service → App**
2. Set:
   - **Service name:** `app`
   - **Source:** Git repository (point it to your fork/clone)
   - **Branch:** `master`

### Dockerfile

Since VueFileManager has no Dockerfile, create one in the project root:

```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    exiftool

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    xml

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install and build frontend assets
RUN npm ci && npm run prod && rm -rf node_modules

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
```

### Supporting Docker Files

Create a `docker/` folder in the project root with these three files:

**docker/nginx.conf**
```nginx
worker_processes auto;
events { worker_connections 1024; }

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;
    sendfile on;
    client_max_body_size 1024M;

    server {
        listen 80;
        root /var/www/html/public;
        index index.php;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }

        location ~ /\.ht { deny all; }
    }
}
```

**docker/supervisord.conf**
```ini
[supervisord]
nodaemon=true
logfile=/var/log/supervisor/supervisord.log

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true

[program:queue-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=300
autostart=true
autorestart=true
numprocs=2
```

**docker/start.sh**
```bash
#!/bin/sh
set -e

cd /var/www/html

# Cache config & run migrations on start
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

---

## Step 4: Configure Environment Variables

In Easypanel, go to your **app** service → **Environment** tab and add the following variables:

### Required — Core

| Variable | Value |
|---|---|
| `APP_NAME` | `VueFileManager` |
| `APP_ENV` | `production` |
| `APP_KEY` | Generate with `php artisan key:generate --show` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain.com` |
| `APP_DEMO` | `false` |

### Required — Database

| Variable | Value |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `$(PROJECT_NAME)_db` (Easypanel internal hostname) |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `vuefilemanager` |
| `DB_USERNAME` | `vfm_user` |
| `DB_PASSWORD` | *(the password you set in Step 2)* |
| `DB_MYSQLDUMP_PATH` | `/usr/bin` |

> **Tip:** In Easypanel, the internal hostname for a service named `db` in project `vuefilemanager` is typically `vuefilemanager_db` or just `db` — check the **Network** tab of your DB service to confirm.

### Required — App Behaviour

| Variable | Value |
|---|---|
| `CACHE_DRIVER` | `file` |
| `QUEUE_CONNECTION` | `database` |
| `SESSION_DRIVER` | `file` |
| `SESSION_LIFETIME` | `120` |
| `BROADCAST_DRIVER` | `null` |
| `FILESYSTEM_DISK` | `local` |
| `SCOUT_DRIVER` | `tntsearch` |
| `SCOUT_QUEUE` | `true` |
| `SANCTUM_STATEFUL_DOMAINS` | `your-domain.com` |
| `LOG_CHANNEL` | `stderr` |

### Required — Email

| Variable | Value |
|---|---|
| `MAIL_DRIVER` | `smtp` |
| `MAIL_HOST` | *(your SMTP host)* |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | *(your email)* |
| `MAIL_PASSWORD` | *(your email password)* |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_FROM_ADDRESS` | `noreply@your-domain.com` |
| `MAIL_FROM_NAME` | `VueFileManager` |

### Optional — File Storage (if using S3)

| Variable | Value |
|---|---|
| `FILESYSTEM_DISK` | `s3` |
| `S3_ACCESS_KEY_ID` | *(your key)* |
| `S3_SECRET_ACCESS_KEY` | *(your secret)* |
| `S3_DEFAULT_REGION` | e.g. `us-east-1` |
| `S3_BUCKET` | *(your bucket name)* |
| `S3_URL` | *(your bucket URL)* |

### Optional — Payments, Social Auth, etc.

Add any of the Stripe, PayPal, Facebook, Google, or GitHub variables from `.env.example` as needed.

---

## Step 5: Add a Persistent Volume

Local file uploads need persistent storage. In Easypanel:

1. Go to your **app** service → **Volumes** tab
2. Add a volume:
   - **Mount path:** `/var/www/html/storage`
   - **Name:** `vfm-storage`

---

## Step 6: Configure the Domain

1. Go to your **app** service → **Domains** tab
2. Add your domain, e.g. `files.your-domain.com`
3. Enable **HTTPS** (Easypanel handles Let's Encrypt automatically)
4. Set the **port** to `80`

---

## Step 7: Deploy

1. Click **Deploy** on the app service
2. Watch the build logs — the first build will take a few minutes (Composer + npm install)
3. On success, the startup script will:
   - Cache config, routes, and views
   - Run database migrations automatically
4. Visit your domain — you should see the VueFileManager setup wizard

---

## Step 8: Complete the Setup Wizard

1. Navigate to `https://your-domain.com`
2. The setup wizard will guide you through:
   - Creating the admin account
   - Configuring storage settings
   - Setting up billing (optional)
3. After completing the wizard, log in to the admin dashboard

---

## Step 9: Configure the Cron Job

VueFileManager requires a cron job for background tasks (subscription renewals, cleanup, etc.).

In Easypanel, go to your **app** service → **Cron** tab and add:

- **Schedule:** `* * * * *`
- **Command:** `php /var/www/html/artisan schedule:run`

---

## Troubleshooting

**500 errors after deploy**
- Check that `APP_KEY` is set correctly
- Verify the DB hostname is reachable (use the Easypanel internal network name)
- Check logs: Easypanel **Logs** tab, or `storage/logs/laravel.log` inside the container

**Storage/permissions errors**
- Make sure the `/var/www/html/storage` volume is mounted
- The Dockerfile `chown` step should handle this; if not, exec into the container and run `chown -R www-data:www-data storage`

**File uploads failing**
- Ensure `client_max_body_size 1024M` is in nginx.conf (it is included above)
- Check `FILESYSTEM_DISK` is set to either `local` or a configured S3/Azure bucket

**Queue jobs not running**
- Supervisord runs 2 queue workers automatically
- Check supervisor logs inside the container: `/var/log/supervisor/`

**Migrations fail on redeploy**
- The `--force` flag is set, which is correct for production
- If a migration errors, exec into the container and run `php artisan migrate --force` manually to see the full error
