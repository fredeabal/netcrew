#!/bin/bash
# =================================================================================
# NETCREW - AUTOMATIC UPDATER SCRIPT
# =================================================================================
# Este script descarga e instala la última versión de NetCrew sin perder datos.
# =================================================================================

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
if [ ! -d "/var/www/netcrew" ]; then
  echo -e "${RED}¡ERROR! No se encontró una instalación de NetCrew en /var/www/netcrew.${NC}"
  echo -e "Si aún no lo has instalado, utiliza el instalador primero."
  exit 1
fi

# 3. Entrar a la carpeta e iniciar actualización
echo -e "${YELLOW}⏳ [1/4] Descargando última versión desde GitHub...${NC}"
cd /var/www/netcrew

# Configurar Git para confiar en el directorio aunque pertenezca a www-data
git config --global --add safe.directory /var/www/netcrew

# Hacer stash de cualquier cambio local por si acaso para no romper el pull
git stash > /dev/null 2>&1
git pull origin main

echo -e "${YELLOW}⏳ [2/4] Actualizando dependencias de PHP (Composer)...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}⏳ [3/4] Aplicando migraciones de base de datos...${NC}"
php spark migrate

echo -e "${YELLOW}⏳ [4/4] Restaurando permisos seguros...${NC}"
# Asegurar que la carpeta uploads exista antes de darle permisos
mkdir -p /var/www/netcrew/public/uploads

chown -R www-data:www-data /var/www/netcrew
chmod -R 775 /var/www/netcrew/writable
chmod -R 775 /var/www/netcrew/public/uploads

echo ""
echo -e "${GREEN}======================================================================${NC}"
echo -e "${GREEN}  ✅ ACTUALIZACIÓN COMPLETADA CON ÉXITO                             ${NC}"
echo -e "${GREEN}======================================================================${NC}"
echo -e "Tu servidor NetCrew ya está en la última versión."
echo -e "Tus configuraciones, redes y dispositivos están a salvo."
echo -e "${BLUE}======================================================================${NC}"
