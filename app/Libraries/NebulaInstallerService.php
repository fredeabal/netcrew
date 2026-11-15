<?php

namespace App\Libraries;

/**
 * Genera instaladores/configuradores autoejecutables por plataforma
 * con el archivo config.yml de Nebula embebido en su interior.
 */
class NebulaInstallerService
{
    /**
     * Construye el payload descargable según la plataforma solicitada.
     *
     * @return array{filename: string, contentType: string, body: string}
     */
    public static function buildForPlatform(string $platform, string $slug, string $ymlContent): array
    {
        $platform = strtolower(trim($platform));

        return match ($platform) {
            'mac', 'macos', 'darwin' => self::buildMac($slug, $ymlContent),
            'mac-sh'                 => [
                'filename'    => "instalar-netcrew-{$slug}.sh",
                'contentType' => 'text/plain; charset=utf-8',
                'body'        => self::buildMacScript($slug, $ymlContent),
            ],
            'windows', 'win'         => self::buildWindows($slug, $ymlContent),
            'win-ps1'                => [
                'filename'    => "instalar-netcrew-{$slug}.ps1",
                'contentType' => 'text/plain; charset=utf-8',
                'body'        => self::buildWindowsPs1($ymlContent),
            ],
            'linux'                  => self::buildLinux($slug, $ymlContent),
            default                  => [
                'filename'    => $slug . '.yml',
                'contentType' => 'text/plain; charset=utf-8',
                'body'        => $ymlContent,
            ],
        };
    }

    private static function buildMacScript(string $slug, string $ymlContent): string
    {
        $marker = 'NETCREW_YML_EOF_' . substr(md5($slug . $ymlContent), 0, 8);
        $script = <<<BASH
#!/bin/bash
set -e

echo "=========================================="
echo "  NetCrew - Configurador Automático macOS "
echo "=========================================="
echo ""

if ! command -v brew >/dev/null 2>&1; then
  echo "[1/4] Instalando Homebrew..."
  NONINTERACTIVE=1 /bin/bash -c "\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
  if [ -x /opt/homebrew/bin/brew ]; then
    eval "\$(/opt/homebrew/bin/brew shellenv)"
  elif [ -x /usr/local/bin/brew ]; then
    eval "\$(/usr/local/bin/brew shellenv)"
  fi
fi

if ! command -v nebula >/dev/null 2>&1; then
  echo "[2/4] Instalando Nebula..."
  brew install nebula
else
  echo "[2/4] Nebula ya está instalado."
fi

BREW_PREFIX="\$(brew --prefix)"
NEBULA_ETC="\${BREW_PREFIX}/etc/nebula"

echo "[3/4] Guardando configuración (se solicitará tu contraseña de Mac)..."
sudo mkdir -p "\$NEBULA_ETC"
sudo tee "\$NEBULA_ETC/config.yml" >/dev/null <<'{$marker}'
{$ymlContent}
{$marker}
sudo chmod 600 "\$NEBULA_ETC/config.yml"

echo "[4/4] Iniciando servicio Nebula..."
sudo brew services restart nebula

echo ""
echo "¡Listo! Tu Mac ya está conectado a la red NetCrew."
BASH;

        return str_replace("\r\n", "\n", $script);
    }

    private static function buildMac(string $slug, string $ymlContent): array
    {
        $script = self::buildMacScript($slug, $ymlContent) . "\necho \"Puedes cerrar esta ventana.\"\nread -r -p \"Pulsa Enter para salir...\"\n";

        if (class_exists(\ZipArchive::class)) {
            $tmpZip = tempnam(sys_get_temp_dir(), 'netcrew_mac_');
            if ($tmpZip !== false) {
                $zip = new \ZipArchive();
                if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                    $entryName = "Instalar-NetCrew-{$slug}.command";
                    $zip->addFromString($entryName, $script);
                    // Permisos ejecutables 0755 en macOS/Unix dentro del ZIP
                    $zip->setExternalAttributesName($entryName, \ZipArchive::OPSYS_UNIX, (0100755 << 16));
                    $zip->close();

                    $body = (string) file_get_contents($tmpZip);
                    @unlink($tmpZip);

                    return [
                        'filename'    => "netcrew-{$slug}-macos.zip",
                        'contentType' => 'application/zip',
                        'body'        => $body,
                    ];
                }
                @unlink($tmpZip);
            }
        }

        return [
            'filename'    => "Instalar-NetCrew-{$slug}.command",
            'contentType' => 'text/plain; charset=utf-8',
            'body'        => $script,
        ];
    }

    private static function buildWindows(string $slug, string $ymlContent): array
    {
        $b64Yml = base64_encode($ymlContent);

        $bat = <<<BAT
@echo off
setlocal
title NetCrew - Configurador Automatico Windows

:: Solicitar permisos de Administrador si no los tiene
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Solicitando permisos de Administrador...
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

echo ==========================================
echo   NetCrew - Configurador Automatico Win   
echo ==========================================
echo.

set "NEBULA_DIR=C:\Program Files\Nebula"
if not exist "%NEBULA_DIR%" mkdir "%NEBULA_DIR%"

if not exist "%NEBULA_DIR%\nebula.exe" (
    echo [1/3] Descargando Nebula oficial para Windows...
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "\$ProgressPreference='SilentlyContinue'; " ^
      "\$rel = Invoke-RestMethod 'https://api.github.com/repos/slackhq/nebula/releases/latest'; " ^
      "\$asset = \$rel.assets | Where-Object { \$_.name -match 'nebula-windows-amd64\.zip$' } | Select-Object -First 1; " ^
      "\$zip = Join-Path \$env:TEMP 'nebula-win.zip'; " ^
      "Invoke-WebRequest -Uri \$asset.browser_download_url -OutFile \$zip; " ^
      "Expand-Archive -Path \$zip -DestinationPath '%NEBULA_DIR%' -Force; " ^
      "Remove-Item \$zip -Force"
) else (
    echo [1/3] Nebula ya esta instalado.
)

echo [2/3] Escribiendo configuracion de NetCrew...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "\$bytes = [Convert]::FromBase64String('{$b64Yml}'); " ^
  "[IO.File]::WriteAllBytes('%NEBULA_DIR%\config.yml', \$bytes)"

echo [3/3] Registrando e iniciando servicio Nebula...
net stop Nebula >nul 2>&1
"%NEBULA_DIR%\nebula.exe" -service uninstall >nul 2>&1
"%NEBULA_DIR%\nebula.exe" -service install -config "%NEBULA_DIR%\config.yml"
net start Nebula

echo.
echo Listo! Tu equipo Windows ya esta conectado a NetCrew.
pause
BAT;

        $bat = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $bat));

        return [
            'filename'    => "instalar-netcrew-{$slug}.bat",
            'contentType' => 'application/x-bat',
            'body'        => $bat,
        ];
    }

    private static function buildWindowsPs1(string $ymlContent): string
    {
        $b64Yml = base64_encode($ymlContent);

        return <<<PS1
\$ErrorActionPreference = 'Stop'
\$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not \$isAdmin) {
    Write-Host "Se necesitan permisos de Administrador. Abre PowerShell como Administrador y vuelve a ejecutar el comando." -ForegroundColor Yellow
    return
}

\$nebulaDir = 'C:\\Program Files\\Nebula'
if (-not (Test-Path \$nebulaDir)) { New-Item -ItemType Directory -Path \$nebulaDir -Force | Out-Null }

if (-not (Test-Path (Join-Path \$nebulaDir 'nebula.exe'))) {
    Write-Host "[1/3] Descargando Nebula oficial para Windows..." -ForegroundColor Cyan
    \$ProgressPreference = 'SilentlyContinue'
    \$rel = Invoke-RestMethod 'https://api.github.com/repos/slackhq/nebula/releases/latest'
    \$asset = \$rel.assets | Where-Object { \$_.name -match 'nebula-windows-amd64\\.zip$' } | Select-Object -First 1
    \$zip = Join-Path \$env:TEMP 'nebula-win.zip'
    Invoke-WebRequest -Uri \$asset.browser_download_url -OutFile \$zip
    Expand-Archive -Path \$zip -DestinationPath \$nebulaDir -Force
    Remove-Item \$zip -Force
}

Write-Host "[2/3] Guardando configuracion de NetCrew..." -ForegroundColor Cyan
\$bytes = [Convert]::FromBase64String('{$b64Yml}')
[IO.File]::WriteAllBytes((Join-Path \$nebulaDir 'config.yml'), \$bytes)

Write-Host "[3/3] Iniciando servicio Nebula..." -ForegroundColor Cyan
net stop Nebula 2>\$null | Out-Null
& (Join-Path \$nebulaDir 'nebula.exe') -service uninstall 2>\$null | Out-Null
& (Join-Path \$nebulaDir 'nebula.exe') -service install -config (Join-Path \$nebulaDir 'config.yml')
net start Nebula

Write-Host "Listo! Tu equipo Windows ya esta conectado a NetCrew." -ForegroundColor Green
PS1;
    }

    private static function buildLinux(string $slug, string $ymlContent): array
    {
        $marker = 'NETCREW_YML_EOF_' . substr(md5($slug . $ymlContent), 0, 8);

        $sh = <<<BASH
#!/usr/bin/env bash
set -e

if [ "\$(id -u)" -ne 0 ]; then
  exec sudo bash "\$0" "\$@"
fi

echo "=========================================="
echo "  NetCrew - Configurador Automático Linux "
echo "=========================================="
echo ""

ARCH=\$(uname -m)
case "\$ARCH" in
  x86_64|amd64) N_ARCH="amd64" ;;
  aarch64|arm64) N_ARCH="arm64" ;;
  *) echo "Arquitectura no soportada: \$ARCH"; exit 1 ;;
esac

if ! command -v nebula >/dev/null 2>&1; then
  echo "[1/3] Descargando Nebula (\$N_ARCH)..."
  TMP_DIR=\$(mktemp -d)
  URL=\$(curl -fsSL https://api.github.com/repos/slackhq/nebula/releases/latest | grep -oE "https://[^\"]+nebula-linux-\${N_ARCH}\.tar\.gz" | head -n 1)
  curl -fsSL "\$URL" -o "\$TMP_DIR/nebula.tar.gz"
  tar -xzf "\$TMP_DIR/nebula.tar.gz" -C "\$TMP_DIR"
  install -m 0755 "\$TMP_DIR/nebula" /usr/local/bin/nebula
  rm -rf "\$TMP_DIR"
else
  echo "[1/3] Nebula ya está instalado."
fi

echo "[2/3] Guardando configuración en /etc/nebula/config.yml..."
mkdir -p /etc/nebula
cat > /etc/nebula/config.yml <<'{$marker}'
{$ymlContent}
{$marker}
chmod 600 /etc/nebula/config.yml

echo "[3/3] Configurando servicio systemd..."
cat > /etc/systemd/system/nebula.service <<'EOF'
[Unit]
Description=Nebula P2P Mesh (NetCrew)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/local/bin/nebula -config /etc/nebula/config.yml
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now nebula
systemctl restart nebula

echo ""
echo "¡Listo! Tu equipo Linux ya está conectado a NetCrew."
BASH;

        $sh = str_replace("\r\n", "\n", $sh);

        return [
            'filename'    => "instalar-netcrew-{$slug}.sh",
            'contentType' => 'text/x-shellscript; charset=utf-8',
            'body'        => $sh,
        ];
    }
}
