# 🕸️ NetCrew - VPN & WireGuard Manager

NetCrew es una plataforma avanzada y autogestionada (**Control Plane**) para la administración centralizada de redes VPN basadas en **WireGuard**. Permite coordinar topologías de red, generar configuraciones automáticas para dispositivos de clientes, monitorear el estado de las conexiones en tiempo real a través de SSH y gestionar accesos mediante un sistema robusto de roles y permisos.

El servidor de NetCrew actúa como panel de control y como servidor de salto (Hub-and-Spoke), lo que permite interconectar dispositivos detrás de cualquier NAT de forma centralizada sin necesidad de configuraciones complejas o abrir puertos en los clientes.

---

## 🚀 Características Principales

### 🌐 Gestión de Redes y Nodos
* **Topología Hub-and-Spoke:** Los nodos se conectan de forma centralizada al servidor VPN WireGuard facilitando la comunicación entre ellos sin importar su ubicación.
* **Cálculo de Direccionamiento Automático:** Generación inteligente de direcciones IP libres dentro del rango CIDR asignado a la red para evitar colisiones.
* **Configuración al Instante:** Descarga directa de archivos `.conf` y códigos QR autogenerados compatibles con clientes oficiales (iOS, Android, macOS, Windows, Linux).

### ⚡ Sincronización y Monitoreo en Tiempo Real
* **Integración SSH Segura:** Sincronización automatizada de peers con el servidor VPN WireGuard (LXC/Baremetal) mediante comandos SSH no interactivos (utilizando la librería segura `phpseclib3`).
* **Mantenimiento Simplificado:** Limpieza integrada desde el panel para sesiones inactivas, archivos debugbar, archivos logs y **peers fantasmas** (usuarios eliminados en el panel pero que siguen activos en la memoria de WireGuard).

### 🛡️ Seguridad Avanzada y Roles
* **Control de Acceso basado en Roles (RBAC):** Integración con CodeIgniter Shield para gestionar grupos y permisos (`superadmin`, `supervisor`, `user`).
* **Protección CSRF & Filtros Globales:** Filtros activos en todos los formularios para sanear caracteres inválidos y proteger contra falsificación de peticiones.
* **Encriptación de Credenciales:** Almacenamiento seguro y encriptado en base de datos para contraseñas de conexión SSH y claves privadas.

### 🎨 Experiencia de Usuario Premium
* **Diseño Moderno & Responsivo:** Interfaz oscura y limpia (Dark Theme) con una estética premium basada en Bootstrap 5 y Tabler Icons.
* **Alertas SweetAlert2 Integradas:** Diálogos flotantes modernos y centrados para confirmaciones de acciones y notificaciones de estado auto-descartables en 5 segundos, integrados perfectamente en la estética oscura de la app (sin molestos botones de confirmación o cierre).
* **Guía de Uso Integrada:** Documentación interactiva paso a paso accesible directamente desde el panel, diseñada para guiar a los usuarios en la creación de redes y la configuración rápida de dispositivos cliente.

---

## 🛠️ Stack Tecnológico

* **Core Backend:** CodeIgniter 4.x
* **Autenticación:** CodeIgniter Shield
* **Base de Datos:** SQLite3 (requiere rutas absolutas en el archivo `.env` para garantizar el correcto funcionamiento de migraciones CLI)
* **Librerías Clave:** `phpseclib3` (para ejecución segura de comandos de red vía SSH)
* **Frontend:** Bootstrap 5, SweetAlert2, Tabler Icons, Javascript nativo

---

## 📂 Estructura del Proyecto

```
app/
 ├── Config/          # Configuración de base de datos, filtros de seguridad, rutas y Shield
 ├── Controllers/     # Controladores de negocio (Device, Network, Settings, Maintenance, Profile, Users)
 ├── Database/        # Migraciones y configuraciones de datos (SQLite)
 ├── Models/          # Modelos de base de datos (DeviceModel, NetworkModel)
 └── Views/           # Vistas compuestas secuencialmente (Header, Body, Footer)
public/
 └── assets/          # Hojas de estilo personalizadas (custom.css) y librerías CSS/JS
```

---

## ⚙️ Guía de Instalación Paso a Paso

NetCrew está diseñado bajo una arquitectura desacoplada en dos partes:
1. **El Panel Web (Control Plane):** La interfaz visual que manejas desde tu navegador. Se ejecuta sobre un servidor web estándar con PHP y una base de datos SQLite ligera.
2. **El Servidor VPN (Data Plane):** El servidor WireGuard real (generalmente un contenedor LXC en Proxmox). Es la máquina encargada de enrutar el tráfico de los usuarios.

> [!NOTE]
> **No necesitan estar en el mismo servidor.** Puedes alojar el panel web de NetCrew en un hosting web normal o VPS secundario, y apuntar remotamente vía SSH a tu servidor WireGuard físico o máquina en tu red local.

---

### 🌐 Paso A: Instalar el Panel Web de NetCrew

Sigue estos pasos para poner en marcha la interfaz web del administrador:

#### 1. Requisitos del Servidor Web
* **PHP:** Versión 8.2 o superior.
* **Extensiones PHP obligatorias:** `php-sqlite3`, `php-curl`, `php-intl`, `php-mbstring`, `php-xml`.
* **Base de Datos:** SQLite3 (suele venir preinstalado en la mayoría de los servidores con PHP).

#### 2. Subir Archivos y Estructura
Sube todos los archivos del proyecto a tu servidor web (a la carpeta raíz de tu sitio, como `/var/www/html` o `public_html`). Asegúrate de que el servidor web apunte a la carpeta `public/` como el directorio raíz expuesto al público.

#### 3. Configurar el Archivo de Entorno (`.env`)
En la raíz del proyecto encontrarás un archivo llamado `env` (o `.env`). Si no tiene el punto al inicio, cámbiale el nombre a `.env`. 

Ábrelo con un editor de texto y edita estas variables:
```ini
CI_ENVIRONMENT = production

# Pon aquí la dirección exacta de tu página web (debe terminar en /)
app.baseURL = 'http://mi-dominio-vpn.com/'

# Configuración de SQLite (se recomienda usar la ruta absoluta del archivo db)
database.default.database = '/var/www/html/writable/database.db'
database.default.DBDriver = 'SQLite3'
```

#### 4. Inicializar la Base de Datos SQLite
NetCrew utiliza una base de datos SQLite integrada en un único archivo. Para iniciar con un sistema limpio:
1. Dirígete a la carpeta `writable/` en los archivos de tu proyecto.
2. Copia el archivo `database.empty.db` y renómbralo exactamente como `database.db`.
3. **Paso crítico (Permisos):** Asegúrate de que tanto la carpeta `writable/` como el nuevo archivo `database.db` tengan permisos de escritura y lectura para tu servidor web. En servidores Linux, esto suele hacerse ejecutando en la terminal:
   ```bash
   chmod -R 775 writable/
   chown -R www-data:www-data writable/
   ```
4. Corre las migraciones del sistema para crear y poblar las tablas necesarias ejecutando el CLI:
   ```bash
   php spark migrate
   ```

#### 5. Credenciales de Acceso por Defecto
Una vez configurado, abre tu navegador, dirígete a tu dominio y accede con los siguientes datos iniciales:
* **Usuario/Email:** `admin@demo.com`
* **Contraseña:** `admin1234`
*(Se recomienda cambiar estos datos inmediatamente desde la sección "Mi Perfil" en la esquina superior derecha).*

---

### 📦 Paso B: Preparar el Servidor VPN WireGuard (Proxmox LXC)

Ahora prepararemos el servidor donde se conectarán los clientes:

#### 1. Crear el Contenedor LXC
Desde tu interfaz de Proxmox, crea un contenedor (CT) Debian 12 o Ubuntu 22.04/24.04:
* **Privileged (Contenedor Privilegiado):** **Desmarcado** (por seguridad).
* **Recursos mínimos recomendados:** 1 vCPU, 512 MB de RAM, 8 GB de disco.
* **Red:** Asigna una IP estática local (ej. `192.168.0.235/24`) y tu puerta de enlace.

#### 2. Habilitar Soporte TUN/TAP (Dispositivo Virtual de Red)
WireGuard necesita crear tarjetas de red virtuales dentro del contenedor. Debes darle permisos especiales.
1. Conéctate por SSH a tu **servidor físico de Proxmox** (no al contenedor).
2. Edita el archivo de configuración del contenedor (reemplaza `ID` por el número de tu contenedor en Proxmox, ej. `114`):
   ```bash
   nano /etc/pve/lxc/ID.conf
   ```
3. Pega estas dos líneas al final del archivo y guarda los cambios:
   ```ini
   lxc.cgroup2.devices.allow: c 10:200 rwm
   lxc.mount.entry: /dev/net/tun dev/net/tun none bind,create=file
   ```

#### 3. Instalar y Permitir SSH en el Contenedor
Inicia el contenedor, entra a su consola y haz lo siguiente:
1. Instala el servidor de SSH:
   ```bash
   apt update && apt install -y openssh-server
   ```
2. Permite que el panel de NetCrew se conecte. **Recomendación de Producción**: Utiliza un usuario estándar con permisos `sudo` (NetCrew es compatible y pedirá la contraseña para escalar privilegios) o configura llaves SSH autorizadas.
   
   *Si por simplicidad prefieres usar `root` directamente en entornos de desarrollo local:*
   Edita el archivo de configuración SSH:
   ```bash
   nano /etc/ssh/sshd_config
   ```
3. Busca la línea `#PermitRootLogin` (o similar), quítale el símbolo `#` y cámbiala a:
   ```ini
   PermitRootLogin yes
   ```
4. Guarda y reinicia el servicio SSH:
   ```bash
   systemctl restart ssh
   ```

---

### ⚡ Paso C: Vinculación final (Configuración en NetCrew)

Con el panel web instalado y el contenedor Proxmox con SSH activo, ya estás listo para el paso final sin tocar una sola línea de comandos más:

#### 1. Configurar la Conexión SSH
1. Entra a tu panel web de NetCrew con tus credenciales de administrador.
2. Ve a la sección **Configuración > Ajustes WireGuard**.
3. Rellena los datos de tu contenedor Proxmox:
   * **Host/IP:** `192.168.0.235` (la IP de tu contenedor).
   * **Puerto SSH:** `22`
   * **Usuario SSH:** `root`
   * **Contraseña SSH:** La contraseña de root que elegiste al crear el contenedor.
4. Haz clic en **Probar**.
5. **¿Qué hará NetCrew aquí?** Se conectará de forma totalmente automatizada a tu contenedor Proxmox, instalará `wireguard` e `iptables`, habilitará el ruteo interno de Internet en el Kernel (IP Forwarding) y generará los certificados de seguridad necesarios en el servidor de forma transparente.

#### 2. Configurar el Servicio de Correo (SMTP)
Para que puedas enviar los accesos de VPN automáticamente a los correos de tus usuarios y clientes:
1. Ve a **Configuración > Ajustes SMTP** en NetCrew.
2. Introduce los datos de conexión de tu proveedor de correo electrónico (Host SMTP, Puerto, Usuario, Contraseña y tipo de cifrado).
3. Guarda la configuración. ¡Ahora la app podrá enviar códigos QR e invitaciones de forma automática!
