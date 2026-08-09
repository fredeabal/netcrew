#!/usr/bin/env bash
# =================================================================================
# NETCREW - AUTOMATIC UPDATER SCRIPT
# =================================================================================
# Este script descarga e instala la última versión de NetCrew sin perder datos.
# =================================================================================

# Si se ejecuta mediante curl/pipe, guardar en un archivo temporal y reconectar la terminal
if [ ! -t 0 ] && [ -z "$NETCREW_SELF_RUN" ]; then
    TMP_SCRIPT=$(mktemp /tmp/netcrew_update.XXXXXX.sh)
    cat > "$TMP_SCRIPT"
    chmod +x "$TMP_SCRIPT"
    export NETCREW_SELF_RUN=1
    exec "$TMP_SCRIPT" "$@" < /dev/tty
fi

# Colores para salida de terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}======================================================================${NC}"
echo -e "${GREEN}             🚀 NETCREW - ACTUALIZADOR AUTOMÁTICO 🚀            ${NC}"
echo -e "${BLUE}======================================================================${NC}"
echo ""

# 1. Verificar si es root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}¡ERROR! Por favor, ejecuta este script como root (ej: sudo bash <(curl...))${NC}"
  exit 1
fi

# 2. Verificar si NetCrew está instalado
INSTALL_DIR="/var/www/netcrew"
if [ ! -d "$INSTALL_DIR" ]; then
  echo -e "${RED}¡ERROR! No se encontró una instalación de NetCrew en ${INSTALL_DIR}.${NC}"
  echo -e "Si aún no lo has instalado, utiliza el instalador primero."
  exit 1
fi

cd "$INSTALL_DIR"

echo -e "${BLUE}======================================================================${NC}"
echo -e "${YELLOW}¿El dominio, IP o protocolo (HTTP/HTTPS) de acceso ha cambiado?${NC}"
echo -e "Si has migrado a HTTPS o cambiado de IP, introduce la nueva URL completa."
echo -e "Ejemplo: ${GREEN}https://netcrew.miempresa.com${NC} o ${GREEN}http://192.168.1.100${NC}"
echo -e "(${YELLOW}Pulsa Enter si sigue siendo exactamente igual para no cambiar nada${NC})"
read -p "Nueva URL Base: " NEW_URL

if [ ! -z "$NEW_URL" ]; then
    # Añadir barra final si no la tiene
    if [[ "$NEW_URL" != */ ]]; then
        NEW_URL="${NEW_URL}/"
    fi
    
    # Actualizar en .env
    sed -i "s|^app.baseURL.*|app.baseURL = '${NEW_URL}'|g" "$INSTALL_DIR/.env"
    echo -e "${GREEN}✅ URL Base actualizada internamente a: ${NEW_URL}${NC}"
    
    # Actualizar server_name en Nginx si existe
    if [ -f /etc/nginx/sites-available/netcrew ]; then
        # Extraer dominio/ip limpio sin protocolo ni ruta
        CLEAN_DOMAIN=$(echo "$NEW_URL" | sed -e 's|^[^/]*//||' -e 's|/.*$||')
        sed -i "s|server_name .*|server_name $CLEAN_DOMAIN;|g" /etc/nginx/sites-available/netcrew
        echo -e "${GREEN}✅ Configuración de Nginx actualizada (server_name: $CLEAN_DOMAIN)${NC}"
    fi
    echo ""
fi

# 3. Entrar a la carpeta e iniciar actualización
echo -e "${YELLOW}⏳ [1/5] Descargando última versión desde GitHub...${NC}"

# Configurar Git para confiar en el directorio aunque pertenezca a www-data
git config --global --add safe.directory "$INSTALL_DIR"

# Guardar cambios locales sin commitear por seguridad
git stash > /dev/null 2>&1 || true

# Descargar la última versión y forzar sincronización exacta con GitHub
git fetch origin
git reset --hard origin/main

echo -e "${YELLOW}⏳ [2/5] Actualizando dependencias de PHP (Composer)...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

echo -e "${YELLOW}⏳ [3/5] Aplicando migraciones de base de datos...${NC}"
php spark migrate

echo -e "${YELLOW}⏳ [4/5] Restaurando permisos y limpiando caché de la aplicación...${NC}"
# Limpiar caché interno de CodeIgniter 4
php spark cache:clear 2>/dev/null || true

# Asegurar que la carpeta uploads exista antes de darle permisos
mkdir -p "$INSTALL_DIR/public/uploads"

chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/writable"
chmod -R 775 "$INSTALL_DIR/public/uploads"

echo -e "${YELLOW}⏳ [5/5] Reiniciando servicios y limpiando caché (OPCache / Nginx)...${NC}"
# Buscar dinámicamente cualquier versión de PHP-FPM instalada (ej: php8.1-fpm, php8.2-fpm, php9.0-fpm)
for service in $(systemctl list-unit-files --type=service 2>/dev/null | grep -oE 'php[0-9.]*-fpm\.service'); do
    systemctl restart "$service" 2>/dev/null || true
done
systemctl restart php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

echo ""
echo -e "${GREEN}======================================================================${NC}"
echo -e "${GREEN}  ✅ ACTUALIZACIÓN COMPLETADA CON ÉXITO                             ${NC}"
echo -e "${GREEN}======================================================================${NC}"
echo -e "Tu servidor NetCrew ya está en la última versión."
echo -e "Tus configuraciones, redes y dispositivos están a salvo."
echo -e "${BLUE}======================================================================${NC}"
