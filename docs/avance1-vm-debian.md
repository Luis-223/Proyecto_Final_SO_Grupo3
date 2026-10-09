# Avance 1 — VM Debian 12 con Apache y PHP

Esta guía deja la máquina virtual de desarrollo lista con Debian 12, Apache 2, PHP 8.2 y SQLite, y publica el proyecto como VirtualHost. Cada integrante debe tener su propia VM funcionando; en la presentación se muestra al menos una.

## 1. Crear la VM en VirtualBox

| Parámetro | Valor recomendado |
|---|---|
| ISO | `debian-12.x.x-amd64-netinst.iso` (debian.org → Download) |
| RAM | 2048 MB (mínimo 1024) |
| CPU | 2 núcleos (M2 muestra el uso por núcleo, conviene más de uno) |
| Disco | 20 GB VDI dinámico |
| Red | **Adaptador puente** (la VM recibe IP de la red local) o **NAT** con reenvío de puertos: anfitrión 8080 → invitado 80 y anfitrión 2222 → invitado 22 |

Durante la instalación:

- Idioma español, zona horaria Guatemala.
- Contraseña de root **vacía**, para que el usuario creado quede en el grupo `sudo`.
- En “Selección de programas” marcar solo **servidor SSH** y **utilidades estándar del sistema** (sin entorno gráfico; se trabaja igual que en el VPS).

## 2. Instalar todo con el script

Dentro de la VM, con el usuario creado en la instalación:

```bash
sudo apt update && sudo apt install -y git wget
wget -O setup-vm.sh https://raw.githubusercontent.com/USUARIO/REPO/main/scripts/setup-vm.sh
sudo bash setup-vm.sh https://github.com/USUARIO/REPO.git
```

Si el repositorio es privado, primero se agrega una llave SSH de la VM a GitHub (`ssh-keygen -t ed25519` y copiar `~/.ssh/id_ed25519.pub` en GitHub → Settings → SSH keys) y se usa la URL `git@github.com:USUARIO/REPO.git`.

El script hace, en orden: instala los paquetes, clona el repo en `/var/www/sysmonitor-web`, ejecuta `composer install`, crea `.env` y la base SQLite con las migraciones, ajusta permisos y activa el VirtualHost `apache/sysmonitor.conf`.

## 3. Instalación manual (lo que hace el script, paso a paso)

Útil para entender cada comando en la defensa.

```bash
# Paquetes
sudo apt install -y apache2 libapache2-mod-php php php-cli php-sqlite3 \
  php-mbstring php-xml php-curl php-zip php-bcmath php-intl sqlite3 composer git unzip nano

# Proyecto
sudo mkdir -p /var/www/sysmonitor-web && sudo chown $USER:www-data /var/www/sysmonitor-web
git clone https://github.com/USUARIO/REPO.git /var/www/sysmonitor-web
cd /var/www/sysmonitor-web
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate

# Permisos: www-data solo escribe donde hace falta
sudo chown -R $USER:www-data .
sudo chmod -R 775 storage bootstrap/cache database

# Apache
sudo cp apache/sysmonitor.conf /etc/apache2/sites-available/
sudo a2enmod rewrite
sudo a2dissite 000-default
sudo a2ensite sysmonitor
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Cuentas de demostración: editar con `nano .env` los campos `SEED_ADMIN_PASSWORD` y `SEED_OBSERVADOR_PASSWORD` y ejecutar `php artisan db:seed`. Las contraseñas nunca se escriben en el README ni se suben a GitHub.

## 4. Evidencias para entregar

Tomar captura de cada una:

| # | Comando / acción | Qué demuestra |
|---|---|---|
| 1 | `cat /etc/os-release` | Debian 12 (Bookworm) |
| 2 | `apache2 -v` y `systemctl status apache2` | Apache instalado y activo |
| 3 | `php -v` | PHP 8.2 |
| 4 | `php -m \| grep -iE 'sqlite\|pdo\|mbstring\|xml'` | Extensiones que necesita Laravel |
| 5 | `php artisan --version` | Laravel instalado |
| 6 | `sudo apache2ctl -S` | VirtualHost `sysmonitor` habilitado |
| 7 | Navegador del anfitrión en `http://IP-DE-LA-VM/` | Pantalla de login de SysMonitor Web |
| 8 | `cat /proc/loadavg` y `ls /proc \| head` | El /proc real que usarán M1, M2 y M5 |

Guardar las capturas en `docs/evidencias/avance1/`.

## 5. Problemas comunes

| Síntoma | Solución |
|---|---|
| Página en blanco o error 500 | `sudo tail -n 30 /var/log/apache2/sysmonitor_error.log` y `tail storage/logs/laravel.log`; casi siempre son permisos de `storage/` |
| “attempt to write a readonly database” | La carpeta `database/` y el archivo `.sqlite` deben pertenecer al grupo `www-data` con permiso de escritura (`chmod 775 database && chmod 664 database/database.sqlite`) |
| Se ve la página por defecto de Apache | Falta `sudo a2dissite 000-default && sudo systemctl reload apache2` |
| Las rutas distintas de `/` dan 404 | Falta `sudo a2enmod rewrite` o `AllowOverride All` en el VirtualHost |
| No abre desde el anfitrión con NAT | Revisar el reenvío de puertos en VirtualBox → Configuración → Red → Avanzado |
