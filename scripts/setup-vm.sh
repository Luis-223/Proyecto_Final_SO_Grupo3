#!/usr/bin/env bash
# =============================================================================
# SysMonitor Web — Preparación de la VM Debian 12 (Avance 1)
# Instala Apache 2 + PHP 8.2 + SQLite + Composer, clona el repositorio y lo
# publica como VirtualHost en http://<ip-de-la-vm>/
#
# Uso (dentro de la VM, con un usuario que tenga sudo):
#   wget -O setup-vm.sh https://raw.githubusercontent.com/USUARIO/REPO/main/scripts/setup-vm.sh
#   sudo bash setup-vm.sh https://github.com/USUARIO/REPO.git
# =============================================================================
set -euo pipefail

REPO_URL="${1:-}"
APP_DIR="/var/www/sysmonitor-web"
DEV_USER="${SUDO_USER:-$(whoami)}"

if [[ $EUID -ne 0 ]]; then
    echo "Ejecute con sudo: sudo bash $0 <url-del-repo>" >&2
    exit 1
fi
if [[ -z "$REPO_URL" ]]; then
    echo "Falta la URL del repositorio. Ej.: sudo bash $0 https://github.com/usuario/sysmonitor-web.git" >&2
    exit 1
fi
if ! grep -q 'VERSION_ID="12"' /etc/os-release; then
    echo "Aviso: este script está pensado para Debian 12 (Bookworm)." >&2
fi

echo "==> 1/6 Paquetes: Apache, PHP 8.2 y extensiones de Laravel"
apt-get update
apt-get install -y \
    apache2 libapache2-mod-php \
    php php-cli php-sqlite3 php-mbstring php-xml php-curl php-zip php-bcmath php-intl \
    sqlite3 composer git unzip curl nano

echo "==> 2/6 Clonando el repositorio en $APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
    sudo -u "$DEV_USER" git -C "$APP_DIR" pull
else
    mkdir -p "$APP_DIR"
    chown "$DEV_USER":www-data "$APP_DIR"
    sudo -u "$DEV_USER" git clone "$REPO_URL" "$APP_DIR"
fi

echo "==> 3/6 Dependencias PHP (composer install)"
cd "$APP_DIR"
sudo -u "$DEV_USER" composer install --no-interaction --prefer-dist

echo "==> 4/6 .env, clave y base de datos SQLite"
if [[ ! -f .env ]]; then
    sudo -u "$DEV_USER" cp .env.example .env
    sudo -u "$DEV_USER" php artisan key:generate
fi
sudo -u "$DEV_USER" touch database/database.sqlite
sudo -u "$DEV_USER" php artisan migrate --force

echo "==> 5/6 Permisos: www-data escribe solo en storage, cache y database"
chown -R "$DEV_USER":www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 storage bootstrap/cache database
chmod 664 database/database.sqlite
chmod 640 .env
chmod 755 artisan

echo "==> 6/6 VirtualHost de Apache"
cp "$APP_DIR/apache/sysmonitor.conf" /etc/apache2/sites-available/sysmonitor.conf
a2enmod rewrite
a2dissite 000-default || true
a2ensite sysmonitor
apache2ctl configtest
systemctl reload apache2
systemctl enable apache2

IP=$(hostname -I | awk '{print $1}')
cat <<EOF

============================================================
 Listo. Abra en el navegador del equipo anfitrión:
   http://$IP/
 (si usa NAT con reenvío de puertos: http://localhost:8080/)

 Crear las cuentas de demostración:
   1) nano $APP_DIR/.env   -> llenar SEED_ADMIN_PASSWORD y SEED_OBSERVADOR_PASSWORD
   2) cd $APP_DIR && php artisan db:seed

 Evidencias para el Avance 1: ver docs/avance1-vm-debian.md
============================================================
EOF
