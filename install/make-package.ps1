<#
.SYNOPSIS
    Builds a ready-to-install zip of the POS for a shop PC (no Node.js or internet needed at the shop).

.DESCRIPTION
    Run on the development PC:   powershell -ExecutionPolicy Bypass -File install\make-package.ps1
    Produces dist\pos-<date>.zip containing the app with built screens and production PHP packages,
    and without development files, tests, .env or your local data.
#>
param([string]$Composer = 'composer')

$ErrorActionPreference = 'Stop'
$AppDir = Split-Path -Parent $PSScriptRoot
Set-Location $AppDir

Write-Host '==> Building screens (CSS / JS)' -ForegroundColor Cyan
npm ci --no-audit --no-fund
if ($LASTEXITCODE -ne 0) { throw 'npm ci failed' }
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }

$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$stage = Join-Path $env:TEMP "pos-package-$stamp"
$dist = Join-Path $AppDir 'dist'
New-Item -ItemType Directory -Force -Path $stage, $dist | Out-Null

Write-Host '==> Copying files' -ForegroundColor Cyan
$exclude = @('.git', 'node_modules', 'vendor', 'tests', 'dist', 'storage\logs', '.env', '.env.backup', 'public\hot', 'public\hot.dev-stale', '.phpunit.cache')
robocopy $AppDir $stage /E /NFL /NDL /NJH /NJS /XD ($exclude | ForEach-Object { Join-Path $AppDir $_ }) /XF .env .env.backup *.log | Out-Null

# Fresh, empty runtime folders
Get-ChildItem (Join-Path $stage 'storage') -Recurse -File -Include *.php, *.sql -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -match 'framework\\(views|cache|sessions)|backups' } | Remove-Item -Force
New-Item -ItemType Directory -Force -Path (Join-Path $stage 'storage\logs') | Out-Null

Write-Host '==> Production PHP packages' -ForegroundColor Cyan
Push-Location $stage
& $Composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'composer install failed' }
Pop-Location

$zip = Join-Path $dist "pos-$stamp.zip"
Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $zip -Force
Remove-Item $stage -Recurse -Force

Write-Host "`nPackage ready: $zip" -ForegroundColor Green
Write-Host 'Copy it to the shop PC, unzip (e.g. to C:\POS), then run install\install-shop.ps1 as Administrator.'
