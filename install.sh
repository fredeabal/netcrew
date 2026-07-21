#!/usr/bin/env bash

# ==============================================================================
# 🚀 NETCREW - SCRIPT DE INSTALACIÓN AUTOMÁTICA
# ==============================================================================
# Este script automatiza la instalación de dependencias, configuración de Nginx,
# base de datos SQLite, migraciones y permisos para desplegar NetCrew en Linux.
# ==============================================================================

set -e

# Si se ejecuta mediante curl/pipe, guardar en un archivo temporal y reconectar la terminal
if [ ! -t 0 ] && [ -z "$NETCREW_SELF_RUN" ]; then
    TMP_SCRIPT=$(mktemp /tmp/netcrew_install.XXXXXX.sh)
    cat > "$TMP_SCRIPT"
    chmod +x "$TMP_SCRIPT"
    export NETCREW_SELF_RUN=1
    exec "$TMP_SCRIPT" "$@" < /dev/tty
fi

# Colores para la consola
RED='\030[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 1. Verificar si el usuario es root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}❌ Error: Este script debe ejecutarse como root (usando sudo).${NC}"
  exit 1
fi

clear
echo -e "${BLUE}"
echo "======================================================================"
echo "         🚀 BIENVENIDO AL INSTALADOR AUTOMÁTICO DE NETCREW           "
echo "======================================================================"
echo -e "${NC}"

# 2. Detectar IP pública o interfaz de red del servidor
SERVER_IP=$(hostname -I | awk '{print $1}')
read -p "👉 Ingresa la IP o Dominio del servidor [Predeterminado: ${SERVER_IP}]: " INPUT_DOMAIN
DOMAIN=${INPUT_DOMAIN:-$SERVER_IP}

echo -e "\n${YELLOW}⏳ [1/6] Actualizando paquetes e instalando dependencias del sistema...${NC}"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx git unzip curl \
    php-fpm php-cli php-sqlite3 php-curl php-intl php-mbstring php-xml php-zip \
    wireguard wireguard-tools iptables openssh-server

# Instalación de Composer si no está presente
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}⏳ Instalando Composer...${NC}"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# 3. Directorio de instalación
INSTALL_DIR="/var/www/netcrew"

if [ ! -f "./spark" ]; then
    echo -e "${YELLOW}⏳ [2/6] Descargando el código de NetCrew desde GitHub...${NC}"
    if [ -d "$INSTALL_DIR" ]; then
        # Limpiar si ya existe para evitar conflictos al clonar. 
        # Cambiamos a /tmp por si el usuario ejecutó el script desde dentro de la carpeta a borrar.
        cd /tmp
        rm -rf "$INSTALL_DIR"
    fi
    git clone https://github.com/fredeabal/netcrew.git "$INSTALL_DIR"
else
    echo -e "${YELLOW}⏳ [2/6] Preparando el directorio del proyecto en ${INSTALL_DIR}...${NC}"
    mkdir -p "$INSTALL_DIR"
    cp -r ./* "$INSTALL_DIR/"
    cp -r ./.env* "$INSTALL_DIR/" 2>/dev/null || true
fi

cd "$INSTALL_DIR"

if [ ! -f "composer.json" ]; then
    echo -e "\n${RED}❌ Error: No se encontraron los archivos del proyecto (composer.json no existe en ${INSTALL_DIR}).${NC}"
    echo -e "${YELLOW}Si el repositorio es PRIVADO en GitHub, ejecuta estos pasos en tu servidor:${NC}"
    echo -e "  1. git clone https://github.com/fredeabal/netcrew.git"
    echo -e "  2. cd netcrew"
    echo -e "  3. bash install.sh\n"
    exit 1
fi

# 4. Instalación de librerías PHP mediante Composer
echo -e "\n${YELLOW}⏳ [3/6] Instalando dependencias PHP de Composer...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Configurar archivo .env
echo -e "\n${YELLOW}⏳ [4/6] Configurando archivo de entorno (.env)...${NC}"
if [ ! -f ".env" ]; then
    if [ -f "env" ]; then
        cp env .env
    else
        echo -e "${RED}❌ Error: No se encontró la plantilla del archivo 'env'.${NC}"
        exit 1
    fi
fi

# Crear directorio para SQLite si no existe
DB_DIR="${INSTALL_DIR}/writable/database"
mkdir -p "$DB_DIR"
DB_PATH="${DB_DIR}/database.sqlite"

# Ajustar valores en .env (mantener en development para la siembra inicial)
sed -i "s|# CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|# app.baseURL = .*|app.baseURL = 'http://${DOMAIN}/'|g" .env
sed -i "s|app.baseURL = .*|app.baseURL = 'http://${DOMAIN}/'|g" .env
sed -i "s|# database.default.hostname = .*|database.default.hostname = localhost|g" .env
sed -i "s|# database.default.database = .*|database.default.database = ${DB_PATH}|g" .env
sed -i "s|# database.default.DBDriver = .*|database.default.DBDriver = SQLite3|g" .env

# Generar llave de encriptación si no existe
php spark key:generate --force > /dev/null 2>&1 || true

# 6. Ejecutar Migraciones y Semilla de Base de Datos
echo -e "\n${YELLOW}⏳ [5/6] Configurando la base de datos y creando usuario inicial...${NC}"
# Permisos temporales para migrar
chmod -R 777 writable/

# Configuración automática de WireGuard y SSH local
sysctl -w net.ipv4.ip_forward=1 > /dev/null 2>&1 || true
sed -i 's/#net.ipv4.ip_forward=1/net.ipv4.ip_forward=1/g' /etc/sysctl.conf 2>/dev/null || true

systemctl enable ssh > /dev/null 2>&1 || true
systemctl start ssh > /dev/null 2>&1 || true

mkdir -p /etc/wireguard
if [ ! -f "/etc/wireguard/privatekey" ] || [ ! -f "/etc/wireguard/publickey" ]; then
    wg genkey | tee /etc/wireguard/privatekey | wg pubkey > /etc/wireguard/publickey 2>/dev/null || true
    chmod 600 /etc/wireguard/privatekey /etc/wireguard/publickey 2>/dev/null || true
fi

SSH_KEY_FILE="${INSTALL_DIR}/writable/netcrew_ssh_key"
if [ ! -f "$SSH_KEY_FILE" ]; then
    ssh-keygen -t rsa -b 4096 -N "" -f "$SSH_KEY_FILE" -q 2>/dev/null || true
fi
mkdir -p /root/.ssh
chmod 700 /root/.ssh
if [ -f "${SSH_KEY_FILE}.pub" ] && ! grep -q -f "${SSH_KEY_FILE}.pub" /root/.ssh/authorized_keys 2>/dev/null; then
    cat "${SSH_KEY_FILE}.pub" >> /root/.ssh/authorized_keys
    chmod 600 /root/.ssh/authorized_keys
fi
chown www-data:www-data "$SSH_KEY_FILE" "${SSH_KEY_FILE}.pub" 2>/dev/null || true
chmod 600 "$SSH_KEY_FILE" 2>/dev/null || true

php spark migrate --all
php spark db:seed UserSeeder

# Cambiar a entorno de producción una vez completada la instalación
sed -i "s|CI_ENVIRONMENT = development|CI_ENVIRONMENT = production|g" .env

# 7. Configurar Nginx
echo -e "\n${YELLOW}⏳ [6/6] Configurando el servidor web Nginx...${NC}"

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"

cat <<EOF > /etc/nginx/sites-available/netcrew
server {
    listen 80;
    server_name ${DOMAIN};

    root ${INSTALL_DIR}/public;
    index index.php index.html index.htm;

    location / {
        try_files \$uri \$uri/ /index.php\$is_args\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    error_log  /var/log/nginx/netcrew_error.log;
    access_log /var/log/nginx/netcrew_access.log;
}
EOF

# Activar sitio en Nginx y desactivar default
ln -sf /etc/nginx/sites-available/netcrew /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test de configuración y recarga de Nginx
nginx -t
systemctl restart nginx
systemctl restart php${PHP_VER}-fpm

# 8. Ajustar permisos finales del sistema de archivos
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "${INSTALL_DIR}/writable"
chmod -R 775 "${INSTALL_DIR}/public/uploads" 2>/dev/null || true

clear
echo -e "${GREEN}"
echo "======================================================================"
echo "         🎉 ¡INSTALACIÓN DE NETCREW COMPLETADA CON ÉXITO!            "
echo "======================================================================"
echo -e "${NC}"
echo -e "👉 **Acceso Web:** http://${DOMAIN}"
echo -e "👉 **Usuario Admin:** admin@demo.com"
echo -e "👉 **Contraseña:** admin1234"
echo -e "----------------------------------------------------------------------"
echo -e "${YELLOW}⚠️ Recuerda cambiar la contraseña desde el panel tras tu primer inicio.${NC}\n"
