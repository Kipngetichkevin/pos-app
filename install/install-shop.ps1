<#
.SYNOPSIS
    Installs the POS on a shop's Windows PC so it runs on its own, with or without internet.

.DESCRIPTION
    Run once, as Administrator, from the unzipped POS folder:

        powershell -ExecutionPolicy Bypass -File install\install-shop.ps1 -ShopName "Kahawa Mini Mart" -BranchCode KAH

    It will:
      1. Check PHP and its extensions, and that MySQL answers.
      2. Write .env (database, Kenyan time zone, production settings, sync mode).
      3. Create the database and tables, the branch, tills and the owner's login.
      4. Register Windows tasks that start with the PC:
           "POS Server"    - the POS itself, on port 8000 (other tills on the shop network can use it)
           "POS Scheduler" - every minute: cloud sync, KRA eTIMS sending, nightly backup
      5. Open the firewall for the other tills, and put a "POS" shortcut on the desktop
         (opens full-window and prints receipts straight to the default printer).

    Safe to run again: existing data is kept.
#>
param(
    [Parameter(Mandatory = $true)][string]$ShopName,
    [string]$BranchCode = 'MAIN',
    [string]$Phone = '',
    [string]$Address = '',
    [string]$KraPin = '',
    [int]$Tills = 1,
    [string]$AdminName = 'Owner',
    [string]$AdminUsername = 'admin',

    [string]$Php = 'php',
    [string]$Composer = 'composer',
    [string]$MysqlDump = 'mysqldump',
    [string]$DbHost = '127.0.0.1',
    [int]$DbPort = 3306,
    [string]$DbName = 'pos',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [int]$Port = 8000,

    # standalone = this PC only; branch = this PC syncs with the cloud (get the values from the cloud: php artisan pos:branch-token CODE)
    [ValidateSet('standalone', 'branch')][string]$Mode = 'standalone',
    [string]$CloudUrl = '',
    [string]$BranchId = '',
    [string]$SyncToken = ''
)

$ErrorActionPreference = 'Stop'
$AppDir = Split-Path -Parent $PSScriptRoot
Set-Location $AppDir

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Ok($text) { Write-Host "    OK  $text" -ForegroundColor Green }
function Fail($text) { Write-Host "    ERROR  $text" -ForegroundColor Red; exit 1 }

function Set-EnvValue([string]$Key, [string]$Value) {
    $path = Join-Path $AppDir '.env'
    $lines = Get-Content $path
    $quoted = if ($Value -match '\s|#') { '"' + $Value + '"' } else { $Value }
    $found = $false
    $lines = $lines | ForEach-Object {
        if ($_ -match "^\s*#?\s*$([regex]::Escape($Key))=") { $found = $true; "$Key=$quoted" } else { $_ }
    }
    if (-not $found) { $lines += "$Key=$quoted" }
    Set-Content -Path $path -Value $lines -Encoding utf8
}

function Artisan([string[]]$ArgList) {
    & $Php artisan @ArgList
    if ($LASTEXITCODE -ne 0) { Fail "php artisan $($ArgList -join ' ') failed" }
}

# ---------------------------------------------------------------------------
Step 'Checking this computer'

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { Fail 'Run PowerShell as Administrator (right-click > Run as administrator).' }

try { $phpVersion = & $Php -r 'echo PHP_VERSION;' } catch { Fail "PHP not found. Install Laragon (laragon.org) or PHP 8.3+, or pass -Php C:\path\to\php.exe" }
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.3') { Fail "PHP $phpVersion is too old. PHP 8.3 or newer is needed." }
Ok "PHP $phpVersion"

$modules = (& $Php -m) -join ' '
foreach ($ext in 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'fileinfo', 'tokenizer', 'xml', 'bcmath') {
    if ($modules -notmatch "\b$ext\b") { Fail "PHP extension '$ext' is off. Enable it in php.ini." }
}
Ok 'PHP extensions'

if (-not (Test-Path (Join-Path $AppDir 'public\build\manifest.json'))) {
    Fail 'The screens are not built (public\build missing). Use the package made by install\make-package.ps1.'
}

# ---------------------------------------------------------------------------
Step 'Settings (.env)'

if (-not (Test-Path (Join-Path $AppDir '.env'))) { Copy-Item (Join-Path $AppDir '.env.example') (Join-Path $AppDir '.env') }

$hostName = [System.Net.Dns]::GetHostName()
Set-EnvValue 'APP_NAME' $ShopName
Set-EnvValue 'APP_ENV' 'production'
Set-EnvValue 'APP_DEBUG' 'false'
Set-EnvValue 'APP_URL' "http://${hostName}:$Port"
Set-EnvValue 'APP_TIMEZONE' 'Africa/Nairobi'
Set-EnvValue 'DB_CONNECTION' 'mysql'
Set-EnvValue 'DB_HOST' $DbHost
Set-EnvValue 'DB_PORT' "$DbPort"
Set-EnvValue 'DB_DATABASE' $DbName
Set-EnvValue 'DB_USERNAME' $DbUser
Set-EnvValue 'DB_PASSWORD' $DbPassword
Set-EnvValue 'SESSION_DRIVER' 'database'
Set-EnvValue 'CACHE_STORE' 'database'
Set-EnvValue 'QUEUE_CONNECTION' 'database'
Set-EnvValue 'MYSQLDUMP_PATH' $MysqlDump
Set-EnvValue 'POS_MODE' $Mode
if ($Mode -eq 'branch') {
    if (-not $CloudUrl -or -not $SyncToken -or -not $BranchId) { Fail 'Branch mode needs -CloudUrl, -BranchId and -SyncToken (from: php artisan pos:branch-token CODE on the cloud).' }
    Set-EnvValue 'POS_CLOUD_URL' $CloudUrl
    Set-EnvValue 'POS_BRANCH_ID' $BranchId
    Set-EnvValue 'POS_SYNC_TOKEN' $SyncToken
}
Ok ".env written (mode: $Mode)"

# ---------------------------------------------------------------------------
Step 'Database'

$env:POS_DB_HOST = $DbHost; $env:POS_DB_PORT = "$DbPort"; $env:POS_DB_USER = $DbUser; $env:POS_DB_PASS = $DbPassword; $env:POS_DB_NAME = $DbName
& $Php -r 'try { $p = new PDO("mysql:host=".getenv("POS_DB_HOST").";port=".getenv("POS_DB_PORT"), getenv("POS_DB_USER"), getenv("POS_DB_PASS")); $p->exec("CREATE DATABASE IF NOT EXISTS `".getenv("POS_DB_NAME")."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }'
if ($LASTEXITCODE -ne 0) { Fail 'Cannot reach MySQL. Start MySQL (in Laragon: Start All) and check -DbUser / -DbPassword.' }
Ok "Database '$DbName' ready"

if (-not (Test-Path (Join-Path $AppDir 'vendor\autoload.php'))) {
    Step 'Installing PHP packages'
    & $Composer install --no-dev --optimize-autoloader --no-interaction
    if ($LASTEXITCODE -ne 0) { Fail 'composer install failed' }
}

Step 'Setting up the shop'
$installArgs = @('pos:install', "--shop=$ShopName", "--branch-code=$BranchCode", "--tills=$Tills", "--admin-name=$AdminName", "--admin-username=$AdminUsername")
if ($Phone) { $installArgs += "--phone=$Phone" }
if ($Address) { $installArgs += "--address=$Address" }
if ($KraPin) { $installArgs += "--kra-pin=$KraPin" }
Artisan $installArgs

Artisan @('storage:link', '--force')
Artisan @('optimize')
Ok 'Shop set up'

# ---------------------------------------------------------------------------
Step 'Windows tasks (start with the PC)'

$phpPath = (Get-Command $Php).Source
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

$serverAction = New-ScheduledTaskAction -Execute $phpPath -Argument "artisan serve --host=0.0.0.0 --port=$Port" -WorkingDirectory $AppDir
Register-ScheduledTask -TaskName 'POS Server' -Action $serverAction -Trigger (New-ScheduledTaskTrigger -AtStartup) `
    -Settings $settings -Principal $principal -Description 'Runs the POS for this shop' -Force | Out-Null
Ok 'POS Server task'

$scheduleAction = New-ScheduledTaskAction -Execute $phpPath -Argument 'artisan schedule:run' -WorkingDirectory $AppDir
$everyMinute = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName 'POS Scheduler' -Action $scheduleAction -Trigger $everyMinute `
    -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew) `
    -Principal $principal -Description 'POS: cloud sync, KRA eTIMS, nightly backup' -Force | Out-Null
Ok 'POS Scheduler task (every minute)'

Step 'Network and shortcut'
if (-not (Get-NetFirewallRule -DisplayName 'POS Server' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName 'POS Server' -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Private, Domain | Out-Null
}
Ok "Other tills can open http://${hostName}:$Port"

$edge = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($edge) {
    $shell = New-Object -ComObject WScript.Shell
    $link = $shell.CreateShortcut((Join-Path ([Environment]::GetFolderPath('CommonDesktopDirectory')) 'POS.lnk'))
    $link.TargetPath = $edge
    # --kiosk-printing: receipts print straight to the default (receipt) printer without a dialog
    $link.Arguments = "--app=http://localhost:$Port --kiosk-printing"
    $link.IconLocation = "$edge,0"
    $link.Save()
    Ok 'Desktop shortcut "POS"'
}

Start-ScheduledTask -TaskName 'POS Server'
Start-Sleep -Seconds 3
Start-ScheduledTask -TaskName 'POS Scheduler'

Write-Host "`nAll done." -ForegroundColor Green
Write-Host "  This PC:      http://localhost:$Port"
Write-Host "  Other tills:  http://${hostName}:$Port"
Write-Host "  Set the receipt printer as the Windows default printer, then use the POS shortcut on the desktop."
