# ARSIP-TA launcher.
# Installs PHP and cloudflared, creates shortcuts, then serves this folder.
# Offline: 127.0.0.1 only. Online: the local network and an HTTPS link for clients.
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('ensure', 'start', 'stop', 'share', 'unshare', 'install', 'uninstall', 'selftest')]
    [string]$Command,

    [switch]$Shell,

    [string]$Shortcut = ''
)

$ErrorActionPreference = 'Stop'
$OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$script:ExtraRoots = @()
$Assets = Join-Path $Root 'laravel-assets'
$Runtime = Join-Path $Assets 'runtime'
$CatalogPath = Join-Path $Assets 'catalog.json'
$StatusPath = Join-Path $Runtime 'status.json'
$LockPath = Join-Path $Runtime 'run.lock'
$PhpPathFile = Join-Path $Runtime 'php.path'
$AppTitle = 'ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ'

function Get-CatalogPort {
    $catalog = Get-Content -Path $CatalogPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $port = [int]$catalog.port
    if ($port -lt 1024 -or $port -gt 65535) {
        throw 'Port pada laravel-assets/catalog.json tidak valid.'
    }
    return $port
}

function Read-Status {
    if (-not (Test-Path $StatusPath)) {
        return @{}
    }
    try {
        $json = Get-Content -Path $StatusPath -Raw -Encoding UTF8 | ConvertFrom-Json
        $map = @{}
        foreach ($prop in $json.PSObject.Properties) {
            $map[$prop.Name] = $prop.Value
        }
        return $map
    } catch {
        return @{}
    }
}

function Write-Status([hashtable]$Patch) {
    New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
    $current = Read-Status
    foreach ($key in $Patch.Keys) {
        $current[$key] = $Patch[$key]
    }
    $current['updated_at'] = (Get-Date).ToString('o')
    if ($current.ContainsKey('message') -and $null -ne $current['message']) {
        $text = [string]$current['message']
        if ($text.Length -gt 400) {
            $current['message'] = $text.Substring(0, 400)
        }
    }
    ($current | ConvertTo-Json -Depth 6) | Set-Content -Path $StatusPath -Encoding UTF8
}

function Enter-RuntimeLock {
    New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
    if (Test-Path $LockPath) {
        $owner = (Get-Content -Path $LockPath -Raw -ErrorAction SilentlyContinue)
        $ownerId = 0
        if ($owner -match '\d+') {
            $ownerId = [int]$Matches[0]
        }
        if ($ownerId -gt 0 -and (Get-Process -Id $ownerId -ErrorAction SilentlyContinue)) {
            Write-Output 'Pemasangan sedang berjalan.'
            exit 0
        }
    }
    Set-Content -Path $LockPath -Value $PID -Encoding ASCII
}

function Exit-RuntimeLock {
    Remove-Item -Path $LockPath -Force -ErrorAction SilentlyContinue
}

function Test-PhpReady([string]$Exe) {
    if (-not $Exe -or -not (Test-Path -LiteralPath $Exe)) {
        return $false
    }
    $versionText = & $Exe -r "echo PHP_VERSION;"
    if ($versionText -notmatch '^(\d+)\.(\d+)') {
        return $false
    }
    $major = [int]$Matches[1]
    $minor = [int]$Matches[2]
    if ($major -lt 8 -or ($major -eq 8 -and $minor -lt 4)) {
        return $false
    }
    $modules = @(& $Exe -m)
    foreach ($name in @('openssl', 'mbstring', 'pdo_sqlite', 'curl', 'fileinfo')) {
        if ($modules -notcontains $name) {
            return $false
        }
    }
    return $true
}

function Find-InstalledPhp {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command -and (Test-PhpReady $command.Source)) {
        return $command.Source
    }
    $laragon = 'C:\laragon\bin\php'
    if (Test-Path $laragon) {
        $dirs = Get-ChildItem -Path $laragon -Directory | Sort-Object Name -Descending
        foreach ($dir in $dirs) {
            $exe = Join-Path $dir.FullName 'php.exe'
            if (Test-PhpReady $exe) {
                return $exe
            }
        }
    }
    return $null
}

function Install-VcRedist {
    $exe = Join-Path $Runtime 'vc_redist.x64.exe'
    if (-not (Test-Path -LiteralPath $exe) -or (Get-Item -LiteralPath $exe).Length -lt 1MB) {
        return $false
    }
    Write-Status @{ phase = 'php'; message = 'Memasang Microsoft Visual C++ Redistributable.' }
    $proc = Start-Process -FilePath $exe -ArgumentList '/install', '/quiet', '/norestart' -Wait -PassThru
    return $proc.ExitCode -in @(0, 1638, 3010)
}

function Ensure-CaBundle {
    $dest = Join-Path $Root 'laravel-assets\certs\cacert.pem'
    if ((Test-Path -LiteralPath $dest) -and ((Get-Item -LiteralPath $dest).Length -gt 100KB)) {
        return $dest
    }
    try {
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $dest) | Out-Null
        Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile $dest -UseBasicParsing
    } catch {
        return $null
    }
    if ((Test-Path -LiteralPath $dest) -and ((Get-Item -LiteralPath $dest).Length -gt 100KB)) {
        return $dest
    }
    return $null
}

function Enable-PhpHttps([string]$PhpExe) {
    $iniPath = Join-Path (Split-Path -Parent $PhpExe) 'php.ini'
    if (-not (Test-Path -LiteralPath $iniPath)) {
        return
    }
    $caPath = Ensure-CaBundle
    if (-not $caPath) {
        return
    }
    $ca = ($caPath -replace '\\', '/')
    $curl = "curl.cainfo=`"$ca`""
    $openssl = "openssl.cafile=`"$ca`""
    $lines = @(Get-Content -Path $iniPath -Encoding ASCII)
    $foundCurl = $false
    $foundSsl = $false
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match '^\s*curl\.cainfo=') {
            $lines[$i] = $curl
            $foundCurl = $true
        } elseif ($lines[$i] -match '^\s*openssl\.cafile=') {
            $lines[$i] = $openssl
            $foundSsl = $true
        }
    }
    if (-not $foundCurl) {
        $lines += $curl
    }
    if (-not $foundSsl) {
        $lines += $openssl
    }
    Set-Content -Path $iniPath -Value $lines -Encoding ASCII
}

function Get-OpcacheCacheId {
    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    try {
        $hash = $sha256.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Root.ToLowerInvariant()))
    } finally {
        $sha256.Dispose()
    }
    return -join ($hash[0..7] | ForEach-Object { $_.ToString('x2') })
}

function Get-OpcacheDirectives {
    $cacheDir = Join-Path $Runtime 'opcache'
    New-Item -ItemType Directory -Force -Path $cacheDir | Out-Null
    $cache = ($cacheDir -replace '\\', '/')
    # Each install needs its own Windows shared-memory name. PHP 8.5 fatals
    # when another php.exe reattaches to the default segment at a different
    # ASLR base. file_cache_fallback keeps that collision from killing the server.
    return [ordered]@{
        'opcache.enable'             = '1'
        'opcache.enable_cli'         = '0'
        'opcache.cache_id'           = (Get-OpcacheCacheId)
        'opcache.file_cache'         = "`"$cache`""
        'opcache.file_cache_fallback' = '1'
    }
}

function Enable-OpcacheIsolation([string]$PhpExe) {
    $iniPath = Join-Path (Split-Path -Parent $PhpExe) 'php.ini'
    if (-not (Test-Path -LiteralPath $iniPath)) {
        return
    }
    $settings = Get-OpcacheDirectives
    $lines = @(Get-Content -Path $iniPath -Encoding ASCII)
    $found = @{}
    for ($i = 0; $i -lt $lines.Count; $i++) {
        foreach ($key in @($settings.Keys)) {
            if ($lines[$i] -match ('^\s*' + [regex]::Escape($key) + '\s*=')) {
                $lines[$i] = "$key=$($settings[$key])"
                $found[$key] = $true
                break
            }
        }
    }
    foreach ($key in $settings.Keys) {
        if (-not $found.ContainsKey($key)) {
            $lines += "$key=$($settings[$key])"
        }
    }
    Set-Content -Path $iniPath -Value $lines -Encoding ASCII
}

function Publish-OpcacheScanDir([string]$PhpExe) {
    $dir = Join-Path $Runtime 'php-scan'
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $settings = Get-OpcacheDirectives
    $lines = foreach ($key in $settings.Keys) { "$key=$($settings[$key])" }
    Set-Content -Path (Join-Path $dir 'opcache.ini') -Value $lines -Encoding ASCII

    $previousScan = $env:PHP_INI_SCAN_DIR
    Remove-Item Env:PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue
    $reported = $null
    try {
        $output = & $PhpExe --ini 2>&1 | Out-String
        $match = [regex]::Match($output, 'Scan for additional \.ini files in:\s*(.+)')
        if ($match.Success) {
            $value = $match.Groups[1].Value.Trim()
            if ($value -and $value -ne '(none)') {
                $reported = $value
            }
        }
    } finally {
        if ($null -eq $previousScan) {
            Remove-Item Env:PHP_INI_SCAN_DIR -ErrorAction SilentlyContinue
        } else {
            $env:PHP_INI_SCAN_DIR = $previousScan
        }
    }

    if ($reported -and ($reported.TrimEnd('\') -ne $dir.TrimEnd('\'))) {
        $env:PHP_INI_SCAN_DIR = $reported + [System.IO.Path]::PathSeparator + $dir
    } else {
        $env:PHP_INI_SCAN_DIR = $dir
    }
}

function Complete-Php([string]$Php) {
    $runtimePhp = [System.IO.Path]::GetFullPath((Join-Path $Runtime 'php'))
    $actual = [System.IO.Path]::GetFullPath((Split-Path -Parent $Php))
    if ($actual.TrimEnd('\') -eq $runtimePhp.TrimEnd('\')) {
        Enable-PhpHttps $Php
        Enable-OpcacheIsolation $Php
    }
    return $Php
}

function Install-PortablePhp {
    Write-Status @{ phase = 'php'; message = 'Mengunduh PHP ke folder aplikasi.' }
    $releases = Invoke-RestMethod -Uri 'https://windows.php.net/downloads/releases/releases.json'
    $best = $null
    foreach ($prop in $releases.PSObject.Properties) {
        if ($prop.Name -notmatch '^8\.[45]$') {
            continue
        }
        $entry = $prop.Value
        $zip = $null
        foreach ($build in @('nts-vs17-x64', 'nts-vs16-x64')) {
            $candidate = $entry.$build
            if ($candidate -and $candidate.zip -and $candidate.zip.path) {
                $zip = [string]$candidate.zip.path
                break
            }
        }
        if (-not $zip) {
            continue
        }
        $versionText = [string]$entry.version
        if ($versionText -notmatch '^(\d+)\.(\d+)\.(\d+)') {
            continue
        }
        $version = [version]::new([int]$Matches[1], [int]$Matches[2], [int]$Matches[3])
        if (-not $best -or $version -gt $best.Version) {
            $best = @{ Version = $version; Zip = $zip }
        }
    }
    if (-not $best) {
        throw 'Rilis PHP Windows tidak ditemukan.'
    }

    $zipFile = Join-Path $Runtime 'php-download.zip'
    $dest = Join-Path $Runtime 'php'
    $url = 'https://windows.php.net/downloads/releases/' + $best.Zip
    Invoke-WebRequest -Uri $url -OutFile $zipFile -UseBasicParsing
    if (Test-Path $dest) {
        Remove-Item -Path $dest -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $dest | Out-Null
    Expand-Archive -Path $zipFile -DestinationPath $dest -Force
    Remove-Item -Path $zipFile -Force -ErrorAction SilentlyContinue

    $ini = @"
[PHP]
extension_dir="ext"
date.timezone=Asia/Jakarta
memory_limit=512M
upload_max_filesize=64M
post_max_size=64M
max_execution_time=120
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
"@
    $caPath = Ensure-CaBundle
    if ($caPath) {
        $ca = ($caPath -replace '\\', '/')
        $ini += "`r`ncurl.cainfo=`"$ca`"`r`nopenssl.cafile=`"$ca`"`r`n"
    }
    Set-Content -Path (Join-Path $dest 'php.ini') -Value $ini -Encoding ASCII
    $exe = Join-Path $dest 'php.exe'
    if (-not (Test-PhpReady $exe)) {
        Install-VcRedist | Out-Null
    }
    if (-not (Test-PhpReady $exe)) {
        throw 'PHP tidak dapat dijalankan. Pasang Microsoft Visual C++ Redistributable x64, lalu klik Pasang lagi.'
    }
    return $exe
}

function Resolve-Php {
    $bundled = Join-Path $Runtime 'php\php.exe'
    if ((Test-Path -LiteralPath $bundled) -and -not (Test-PhpReady $bundled)) {
        Install-VcRedist | Out-Null
    }
    if (Test-PhpReady $bundled) {
        Set-Content -Path $PhpPathFile -Value $bundled -Encoding ASCII
        return (Complete-Php $bundled)
    }
    if (Test-Path $PhpPathFile) {
        $stored = (Get-Content -Path $PhpPathFile -Raw -ErrorAction SilentlyContinue)
        if ($stored) {
            $stored = $stored.Trim()
            if (Test-PhpReady $stored) {
                return (Complete-Php $stored)
            }
        }
    }
    try {
        $downloaded = Install-PortablePhp
        Set-Content -Path $PhpPathFile -Value $downloaded -Encoding ASCII
        return (Complete-Php $downloaded)
    } catch {
        $fallback = Find-InstalledPhp
        if ($fallback) {
            Set-Content -Path $PhpPathFile -Value $fallback -Encoding ASCII
            Write-Status @{ message = 'PHP bawaan belum terunduh. Sementara memakai PHP yang sudah ada di komputer ini.' }
            return (Complete-Php $fallback)
        }
        throw
    }
}

function Install-Cloudflared {
    $exe = Join-Path $Runtime 'cloudflared.exe'
    if ((Test-Path -LiteralPath $exe) -and ((Get-Item -LiteralPath $exe).Length -gt 1MB)) {
        return $exe
    }
    Write-Status @{ phase = 'tunnel-binary'; message = 'Mengunduh cloudflared ke folder aplikasi.' }
    $url = 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe'
    Invoke-WebRequest -Uri $url -OutFile $exe -UseBasicParsing
    if (-not (Test-Path -LiteralPath $exe) -or (Get-Item -LiteralPath $exe).Length -lt 1MB) {
        throw 'cloudflared tidak terunduh.'
    }
    return $exe
}

function Get-AppShortcutName {
    return ($AppTitle + '.lnk')
}

function Get-LauncherFolders {
    @(
        [Environment]::GetFolderPath('Desktop'),
        [Environment]::GetFolderPath('Programs'),
        [Environment]::GetFolderPath('Startup'),
        [Environment]::GetFolderPath('CommonDesktopDirectory'),
        [Environment]::GetFolderPath('CommonPrograms'),
        [Environment]::GetFolderPath('CommonStartup'),
        (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch\User Pinned\TaskBar'),
        (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch\User Pinned\StartMenu'),
        (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch')
    ) | Where-Object { $_ } | Select-Object -Unique
}

function Get-ExtraLauncherNames {
    @(
        'ARSIP-TA.lnk',
        'Hentikan ARSIP-TA.lnk',
        'Copot ARSIP-TA.lnk',
        'QR-Akses-ARSIP-TA.pdf',
        'QR-Akses-ARSIP-TA.png',
        'Kode-QR-Akses-ARSIP-TA.pdf',
        'Kode-QR-Akses-ARSIP-TA.png',
        'ARSIP-TA.vbs'
    )
}

function Test-BundledBrowserShortcut([string]$Path) {
    try {
        $shell = New-Object -ComObject WScript.Shell
        $link = $shell.CreateShortcut($Path)
        $target = [string]$link.TargetPath
        $args = [string]$link.Arguments
    } catch {
        return $false
    }
    if (-not $target) {
        return $false
    }
    $leaf = Split-Path $target -Leaf
    if ($leaf -notin @('msedge.exe', 'msedge_proxy.exe', 'chrome.exe')) {
        return $false
    }
    $profile = Join-Path $Runtime 'app-window'
    $blob = $target + ' ' + $args
    if ($blob.IndexOf($profile, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
        return $true
    }
    return (Test-PathMatch $blob @($Root))
}

function Remove-ExtraLaunchers {
    $names = @(Get-ExtraLauncherNames)
    $keep = Get-AppShortcutName
    foreach ($folder in @(Get-LauncherFolders)) {
        if (-not (Test-Path -LiteralPath $folder)) {
            continue
        }
        foreach ($name in $names) {
            if ($name -eq $keep) {
                continue
            }
            Remove-InstalledFile (Join-Path $folder $name)
        }
        Get-ChildItem -LiteralPath $folder -Filter '*.lnk' -Force -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_.Name -eq $keep) {
                return
            }
            if (Test-BundledBrowserShortcut $_.FullName) {
                Remove-InstalledFile $_.FullName
            }
        }
    }
}

function Remove-BundledBrowserProfile {
    $profile = Join-Path $Runtime 'app-window'
    if (-not (Test-Path -LiteralPath $profile)) {
        return
    }
    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | ForEach-Object {
        $command = [string]$_.CommandLine
        if ($command -and $command.IndexOf($profile, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            & taskkill.exe /PID $_.ProcessId /T /F 2>$null | Out-Null
        }
    }
    Start-Sleep -Milliseconds 400
    Remove-Item -LiteralPath $profile -Recurse -Force -ErrorAction SilentlyContinue
}

function Ensure-AppIcon {
    $ico = Join-Path $Root 'ARSIP-TA.ico'
    if ((Test-Path -LiteralPath $ico) -and ((Get-Item -LiteralPath $ico).Length -gt 500)) {
        return $ico
    }
    $png = Join-Path $Root 'public\images\icons\icon-512.png'
    if (-not (Test-Path -LiteralPath $png)) {
        return $null
    }
    try {
        Add-Type -AssemblyName System.Drawing
        $source = [System.Drawing.Bitmap]::FromFile($png)
        $size = 256
        $bitmap = New-Object System.Drawing.Bitmap $size, $size
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.DrawImage($source, 0, 0, $size, $size)
        $graphics.Dispose()
        $source.Dispose()
        $stream = New-Object System.IO.MemoryStream
        $bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
        $bitmap.Dispose()
        $pngBytes = $stream.ToArray()
        $stream.Dispose()
        $offset = 22
        $header = New-Object System.IO.MemoryStream
        $writer = New-Object System.IO.BinaryWriter $header
        $writer.Write([uint16]0)
        $writer.Write([uint16]1)
        $writer.Write([uint16]1)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([byte]0)
        $writer.Write([uint16]1)
        $writer.Write([uint16]32)
        $writer.Write([uint32]$pngBytes.Length)
        $writer.Write([uint32]$offset)
        $writer.Flush()
        [System.IO.File]::WriteAllBytes($ico, ($header.ToArray() + $pngBytes))
        return $ico
    } catch {
        return $null
    }
}

function Install-Shortcuts([int]$Port) {
    Write-Status @{ phase = 'shortcuts'; message = 'Membuat pintasan aplikasi.' }
    Remove-BundledBrowserProfile
    Remove-ExtraLaunchers
    $shell = New-Object -ComObject WScript.Shell
    $script = Join-Path $PSScriptRoot 'runtime.ps1'
    $launcher = Join-Path $Root 'ARSIP-TA.vbs'
    $icon = Ensure-AppIcon
    $shortcutName = Get-AppShortcutName
    foreach ($folder in @(
            [Environment]::GetFolderPath('Desktop'),
            [Environment]::GetFolderPath('Programs')
        )) {
        if (-not $folder) {
            continue
        }
        New-Item -ItemType Directory -Force -Path $folder | Out-Null
        $link = $shell.CreateShortcut((Join-Path $folder $shortcutName))
        if (Test-Path -LiteralPath $launcher) {
            $link.TargetPath = $launcher
            $link.Arguments = ''
        } else {
            $link.TargetPath = 'powershell.exe'
            $link.Arguments = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`" -Command start"
        }
        $link.WorkingDirectory = $Root
        $link.WindowStyle = 7
        $link.Description = $AppTitle
        if ($icon) {
            $link.IconLocation = $icon
        }
        $link.Save()
    }
    Register-InstalledApp
    Write-Status @{ shortcuts = $true; port = $Port }
}

function Ensure-Vendor {
    $autoload = Join-Path $Root 'vendor\autoload.php'
    if (Test-Path $autoload) {
        return
    }
    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $composer) {
        throw 'Folder vendor belum ada dan Composer tidak ditemukan.'
    }
    Write-Status @{ phase = 'php'; message = 'Memasang pustaka Composer.' }
    & $composer.Source install --no-dev --prefer-dist --no-interaction --working-dir="$Root"
    if ($LASTEXITCODE -ne 0) {
        throw 'composer install gagal.'
    }
}

function Test-Online {
    try {
        $response = Invoke-WebRequest -Uri 'https://www.cloudflare.com/cdn-cgi/trace' -UseBasicParsing -TimeoutSec 4
        return [int]$response.StatusCode -ge 200 -and [int]$response.StatusCode -lt 400
    } catch {
        return $false
    }
}

function Test-PortListen([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $wait = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $ok = $wait.AsyncWaitHandle.WaitOne(300, $false)
        if (-not $ok) {
            return $false
        }
        $client.EndConnect($wait)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Test-OurProcess([int]$ProcessId, [string]$Token) {
    if ($ProcessId -le 0) {
        return $false
    }
    $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$ProcessId" -ErrorAction SilentlyContinue
    if (-not $proc) {
        return $false
    }
    $commandLine = [string]$proc.CommandLine
    return $commandLine -like "*$Token*"
}

function Stop-OurProcess([int]$ProcessId, [string]$Token) {
    if (Test-OurProcess $ProcessId $Token) {
        Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
    }
}

function Open-AppWindow([string]$Url) {
    try {
        Start-Process $Url
    } catch {
        Write-Status @{ message = 'Peladen berjalan. Buka pintasan ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ.' }
    }
}

function Invoke-Prepare([string]$Php) {
    Write-Status @{ phase = 'database'; message = 'Menyiapkan basis data di folder aplikasi.' }
    $copy = '1'
    $adopt = '0'
    $requestPath = Join-Path $Runtime 'request.json'
    if (Test-Path $requestPath) {
        try {
            $request = Get-Content -Path $requestPath -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($request.copy -eq $false) { $copy = '0' }
            if ($request.adopt -eq $true) { $adopt = '1' }
        } catch {
            $copy = '1'
        }
    }
    Set-Location $Root
    Invoke-ArtisanOrFail $Php @(
        'artisan', 'local-server:prepare', "--copy=$copy", "--adopt=$adopt", '--placement=1'
    )
}

function Invoke-ArtisanOrFail([string]$Php, [string[]]$Arguments) {
    $output = & $Php @Arguments 2>&1
    foreach ($line in @($output)) {
        Write-Host $line
    }
    if ($LASTEXITCODE -ne 0) {
        $detail = ((@($output) | ForEach-Object { "$_" }) -join "`n").Trim()
        if ($detail) {
            throw $detail
        }
        throw 'Penyiapan basis data gagal.'
    }
}

function Ensure-Schema([string]$Php) {
    Write-Status @{ phase = 'database'; message = 'Memeriksa basis data di folder aplikasi.' }
    Set-Location $Root
    Invoke-ArtisanOrFail $Php @('artisan', 'local-server:prepare', '--copy=0', '--adopt=0')
}

function Start-ServerProcess([string]$Php, [int]$Port, [string]$Bind) {
    $outLog = Join-Path $Runtime 'server.out.log'
    $errLog = Join-Path $Runtime 'server.err.log'
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = (Join-Path $Root 'database\database.sqlite')
    $env:APP_DEBUG = 'false'
    Set-Location $Root
    # artisan serve strips DB_* from the web process unless --no-reload is set.
    # Without those variables the bundled PHP reads MySQL from .env and fails
    # because that runtime has pdo_sqlite only.
    # The child php -S reads this scan directory from the inherited environment,
    # including when the binary is not the bundled copy and its php.ini is untouched.
    Publish-OpcacheScanDir $Php
    $cacheDir = Join-Path $Runtime 'opcache'
    if (Test-Path -LiteralPath $cacheDir) {
        Remove-Item -LiteralPath $cacheDir -Recurse -Force -ErrorAction SilentlyContinue
    }
    New-Item -ItemType Directory -Force -Path $cacheDir | Out-Null
    $proc = Start-Process -FilePath $Php -ArgumentList @(
        'artisan', 'serve', "--host=$Bind", "--port=$Port", '--no-reload'
    ) -WorkingDirectory $Root -WindowStyle Hidden -RedirectStandardOutput $outLog -RedirectStandardError $errLog -PassThru
    $ready = $false
    for ($i = 0; $i -lt 40; $i++) {
        if (Test-PortListen $Port) {
            $ready = $true
            break
        }
        if ($proc.HasExited) {
            break
        }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) {
        throw 'Peladen aplikasi tidak dapat dimulai. Lihat laravel-assets/runtime/server.err.log.'
    }
    return $proc
}

function Publish-OnlineQr([string]$Php) {
    if (-not $Php -or -not (Test-Path -LiteralPath $Php)) {
        return
    }
    try {
        Set-Location $Root
        & $Php artisan access-qr:publish
        if ($LASTEXITCODE -ne 0) {
            Write-Status @{ message = 'Tautan internet siap. Berkas QR belum tersimpan.' }
        }
    } catch {
        Write-Status @{ message = 'Tautan internet siap. Berkas QR belum tersimpan.' }
    }
}

function Remove-TunnelSecrets {
    foreach ($name in @('tunnel-token', 'tunnel.yml', 'tunnel.credentials.json')) {
        $path = Join-Path $Runtime $name
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force -ErrorAction SilentlyContinue
        }
    }
}

function Start-CloudflaredLogged([string]$Exe, [string[]]$Arguments) {
    $log = Join-Path $Runtime 'tunnel.log'
    if (Test-Path $log) {
        Remove-Item $log -Force
    }
    $proc = Start-Process -FilePath $Exe -ArgumentList $Arguments -WindowStyle Hidden -RedirectStandardError $log -PassThru
    $deadline = (Get-Date).AddSeconds(70)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
        if (Test-Path $log) {
            $text = Get-Content -Path $log -Raw -ErrorAction SilentlyContinue
            if ($text -match 'Registered tunnel connection') {
                return $proc
            }
        }
        if ($proc.HasExited) {
            break
        }
    }
    Stop-OurProcess $proc.Id 'cloudflared'
    return $null
}

function Read-StaticTunnel([string]$Php) {
    Remove-TunnelSecrets
    $tokenFile = Join-Path $Runtime 'tunnel-token'
    if (-not $Php -or -not (Test-Path -LiteralPath $Php)) {
        return $null
    }
    Push-Location $Root
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & $Php artisan cloudflare:prepare-tunnel 2>&1
        $code = $LASTEXITCODE
        $output = @($output) | ForEach-Object { "$_" }
    } finally {
        $ErrorActionPreference = $previousPreference
        Pop-Location
    }
    if ($code -eq 2) {
        if (Test-Path -LiteralPath $tokenFile) {
            Remove-Item -LiteralPath $tokenFile -Force -ErrorAction SilentlyContinue
        }
        return $null
    }
    if ($code -ne 0) {
        if (Test-Path -LiteralPath $tokenFile) {
            Remove-Item -LiteralPath $tokenFile -Force -ErrorAction SilentlyContinue
        }
        throw 'Tautan statis belum dapat dipakai. Periksa tautan dan kunci yang disimpan.'
    }
    $link = ''
    foreach ($line in @($output)) {
        $candidate = ([string]$line).Trim()
        if ($candidate -match '^https://[a-z0-9.-]+$') {
            $link = $candidate
        }
    }
    if (-not $link) {
        if (Test-Path -LiteralPath $tokenFile) {
            Remove-Item -LiteralPath $tokenFile -Force -ErrorAction SilentlyContinue
        }
        throw 'Tautan Worker belum dapat dipakai. Simpan tautan itu sekali lagi.'
    }
    if (-not (Test-Path -LiteralPath $tokenFile)) {
        return @{ link = $link; token = '' }
    }
    $token = (Get-Content -LiteralPath $tokenFile -Raw -ErrorAction SilentlyContinue)
    Remove-Item -LiteralPath $tokenFile -Force -ErrorAction SilentlyContinue
    if ($token) {
        $token = $token.Trim()
    }
    if (-not $token) {
        throw 'Tautan statis belum dapat dipakai. Periksa tautan dan kunci yang disimpan.'
    }
    return @{ link = $link; token = $token }
}

function Get-TunnelLinkArgs([string[]]$Head, [string[]]$Tail) {
    # auto tries QUIC, then HTTP/2. IPv4 avoids networks where IPv6 never answers.
    return $Head + @('--protocol', 'auto', '--edge-ip-version', '4', '--no-autoupdate') + $Tail
}

function Read-QuickOrigin {
    $log = Join-Path $Runtime 'tunnel.log'
    if (-not (Test-Path -LiteralPath $log)) {
        return ''
    }
    $text = Get-Content -Path $log -Raw -ErrorAction SilentlyContinue
    if ($text -and $text -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
        return $Matches[0]
    }
    return ''
}

function Send-WorkerOrigin([string]$Front, [string]$Origin) {
    $gate = Read-GateSecret
    $payload = '{"url":"' + $Origin + '"}'
    try {
        $response = Invoke-WebRequest -Method Post -Uri ($Front.TrimEnd('/') + '/__arsip/asal') -Headers @{ Authorization = "Bearer $gate" } -ContentType 'application/json; charset=utf-8' -Body $payload -UseBasicParsing -TimeoutSec 25
        return [int]$response.StatusCode -eq 200 -and [string]$response.Content -match '"ok"\s*:\s*true'
    } catch {
        return $false
    }
}

function Read-GateSecret {
    $path = Join-Path $Runtime 'gate.txt'
    if (-not (Test-Path -LiteralPath $path)) {
        throw 'Kunci pintu QR belum ada di laravel-assets/runtime/gate.txt.'
    }
    $gate = (Get-Content -LiteralPath $path -Raw -ErrorAction SilentlyContinue)
    if ($gate) {
        $gate = $gate.Trim()
    }
    if (-not $gate -or $gate.Length -lt 32) {
        throw 'Kunci pintu QR tidak valid.'
    }
    return $gate
}

function Start-WorkerDoor([string]$Link, [int]$Port, [string]$Php) {
    # Fail before opening a tunnel. Otherwise the quick address changes and
    # the printed QR keeps pointing at the previous computer.
    [void](Read-GateSecret)
    $status = Read-Status
    $tunnelPid = 0
    if ($status.ContainsKey('tunnel_pid') -and $status['tunnel_pid']) {
        $tunnelPid = [int]$status['tunnel_pid']
    }
    $running = Test-OurProcess $tunnelPid 'cloudflared'
    $public = ''
    if ($status.ContainsKey('public_url') -and $status['public_url']) {
        $public = ([string]$status['public_url']).TrimEnd('/')
    }
    $front = $Link.TrimEnd('/')
    if ($running -and $public -eq $front) {
        $kept = Read-QuickOrigin
        if ($kept) {
            try {
                if (-not (Send-WorkerOrigin $front $kept)) {
                    Write-Status @{ message = 'Tunnel tersambung. Pintu QR belum menerima alamat terbaru.' }
                }
            } catch {
                Write-Status @{ message = 'Tunnel tersambung. Pintu QR belum menerima alamat terbaru.' }
            }
        }
        Publish-OnlineQr $Php
        return
    }
    if ($running) {
        Stop-OurProcess $tunnelPid 'cloudflared'
    }
    $exe = Install-Cloudflared
    Write-Status @{ phase = 'sharing'; message = 'Menyambungkan komputer ini ke tautan QR.' }
    $log = Join-Path $Runtime 'tunnel.log'
    if (Test-Path $log) {
        Remove-Item $log -Force
    }
    $proc = Start-Process -FilePath $exe -ArgumentList (Get-TunnelLinkArgs @(
        'tunnel', '--url', "http://127.0.0.1:$Port"
    ) @()) -WindowStyle Hidden -RedirectStandardError $log -PassThru
    $origin = ''
    $ready = $false
    $deadline = (Get-Date).AddSeconds(70)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
        if (Test-Path $log) {
            $text = Get-Content -Path $log -Raw -ErrorAction SilentlyContinue
            if ($text -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
                $origin = $Matches[0]
            }
            if ($origin -and $text -match 'Registered tunnel connection') {
                $ready = $true
                break
            }
        }
        if ($proc.HasExited) {
            break
        }
    }
    if (-not $ready) {
        if (-not $proc.HasExited) {
            Stop-OurProcess $proc.Id 'cloudflared'
        }
        throw 'Tunnel ke tautan QR belum terbentuk. Periksa internet, lalu klik Bagikan lagi.'
    }
    $posted = Send-WorkerOrigin $front $origin
    if (-not $posted) {
        Stop-OurProcess $proc.Id 'cloudflared'
        throw 'Tautan QR belum menerima komputer ini. Worker masih Hello World, atau kunci pintu tidak cocok.'
    }
    Write-Status @{
        phase      = 'ready'
        tunnel_pid = $proc.Id
        public_url = $front
        message    = 'Kode QR memakai tautan Worker yang disimpan. Komputer ini sudah tersambung.'
    }
    Publish-OnlineQr $Php
}

function Start-NamedTunnel([hashtable]$Static, [string]$Php) {
    $exe = Install-Cloudflared
    Write-Status @{ phase = 'sharing'; message = 'Menyambungkan tautan Cloudflare yang disimpan.' }
    $config = Join-Path $Runtime 'tunnel.yml'
    $credentials = Join-Path $Runtime 'tunnel.credentials.json'
    $proc = $null
    $previousToken = $env:TUNNEL_TOKEN
    if ((Test-Path -LiteralPath $config) -and (Test-Path -LiteralPath $credentials)) {
        Remove-Item Env:TUNNEL_TOKEN -ErrorAction SilentlyContinue
        try {
            $proc = Start-CloudflaredLogged $exe (Get-TunnelLinkArgs @('tunnel', '--config', $config) @('run'))
        } finally {
            if ($null -ne $previousToken) {
                $env:TUNNEL_TOKEN = $previousToken
            }
        }
    }
    if ($null -eq $proc) {
        $env:TUNNEL_TOKEN = [string]$Static.token
        try {
            $proc = Start-CloudflaredLogged $exe (Get-TunnelLinkArgs @('tunnel') @('run'))
        } finally {
            if ($null -eq $previousToken) {
                Remove-Item Env:TUNNEL_TOKEN -ErrorAction SilentlyContinue
            } else {
                $env:TUNNEL_TOKEN = $previousToken
            }
        }
    }
    if ($null -eq $proc) {
        throw 'Tautan Cloudflare belum terhubung. Periksa kunci dari akun petugas, lalu klik Bagikan lagi.'
    }
    Write-Status @{
        phase      = 'ready'
        tunnel_pid = $proc.Id
        public_url = [string]$Static.link
        message    = 'Tautan internet statis siap. QR memakai tautan yang disimpan.'
    }
    Publish-OnlineQr $Php
}

function Start-TunnelProcess([int]$Port, [string]$Php) {
    $status = Read-Status
    $tunnelPid = 0
    if ($status.ContainsKey('tunnel_pid') -and $status['tunnel_pid']) {
        $tunnelPid = [int]$status['tunnel_pid']
    }
    $running = Test-OurProcess $tunnelPid 'cloudflared'
    $public = ''
    if ($status.ContainsKey('public_url') -and $status['public_url']) {
        $public = ([string]$status['public_url']).TrimEnd('/')
    }
    $static = Read-StaticTunnel $Php
    if ($null -eq $static) {
        if ($running) {
            Stop-OurProcess $tunnelPid 'cloudflared'
        }
        throw 'Simpan satu tautan Worker Cloudflare. Tautan itu yang dipakai kode QR selamanya.'
    }
    if ([string]$static.token -eq '') {
        Start-WorkerDoor ([string]$static.link) $Port $Php
        return
    }
    if ($running -and $public -eq [string]$static.link) {
        return
    }
    if ($running) {
        Stop-OurProcess $tunnelPid 'cloudflared'
    }
    Start-NamedTunnel $static $Php
}

function Stop-Tunnel {
    $status = Read-Status
    $pidValue = 0
    if ($status.ContainsKey('tunnel_pid') -and $status['tunnel_pid']) {
        $pidValue = [int]$status['tunnel_pid']
    }
    Stop-OurProcess $pidValue 'cloudflared'
    Remove-TunnelSecrets
    Write-Status @{ tunnel_pid = 0; public_url = $null }
}

function Stop-Server {
    $status = Read-Status
    $pidValue = 0
    if ($status.ContainsKey('server_pid') -and $status['server_pid']) {
        $pidValue = [int]$status['server_pid']
    }
    Stop-OurProcess $pidValue 'artisan'
    Stop-Tunnel
    Write-Status @{
        phase      = 'stopped'
        server_pid = 0
        bind       = ''
        online     = $false
        lan_urls   = ''
        public_url = $null
        message    = 'Peladen di komputer ini dihentikan.'
    }
}

function Start-LocalServer([bool]$OpenWindow) {
    $port = Get-CatalogPort
    if (-not [Environment]::Is64BitOperatingSystem) {
        throw 'ARSIP-TA membutuhkan Windows 64-bit.'
    }
    Ensure-Vendor
    $php = Resolve-Php
    Ensure-Schema $php
    $online = Test-Online
    $bind = '127.0.0.1'
    $status = Read-Status
    $sameBind = $status.ContainsKey('bind') -and [string]$status['bind'] -eq $bind
    if ((Test-PortListen $port) -and $sameBind) {
        if ($online) {
            Write-Status @{ online = $true; lan_urls = '' }
            try {
                Start-TunnelProcess $port $php
            } catch {
                Write-Status @{
                    phase   = 'ready'
                    message = 'Peladen berjalan di komputer ini. Tautan internet belum tersedia: ' + $_.Exception.Message
                }
            }
        } else {
            Stop-Tunnel
            Write-Status @{
                phase      = 'ready'
                online     = $false
                bind       = $bind
                lan_urls   = ''
                public_url = $null
                message    = 'Komputer luring. Hanya petugas di komputer ini yang dapat membuka aplikasi.'
            }
        }
    } else {
        if ($status.ContainsKey('server_pid') -and $status['server_pid']) {
            Stop-OurProcess ([int]$status['server_pid']) 'artisan'
            Start-Sleep -Milliseconds 400
        }
        Write-Status @{ phase = 'server'; message = 'Menjalankan peladen aplikasi.'; port = $port; bind = $bind; online = $online }
        $proc = Start-ServerProcess $php $port $bind
        Write-Status @{
            server_pid = $proc.Id
            local_url  = "http://127.0.0.1:$port"
            lan_urls   = ''
            php        = $php
            online     = $online
            bind       = $bind
        }
        if ($online) {
            Write-Status @{ phase = 'sharing'; message = 'Menyambungkan tautan Cloudflare yang disimpan.' }
            try {
                Start-TunnelProcess $port $php
            } catch {
                Write-Status @{
                    phase   = 'ready'
                    message = 'Peladen berjalan di komputer ini. Tautan internet belum tersedia: ' + $_.Exception.Message
                }
            }
        } else {
            Write-Status @{
                phase      = 'ready'
                public_url = $null
                tunnel_pid = 0
                message    = 'Komputer luring. Hanya petugas di komputer ini yang dapat membuka aplikasi.'
            }
        }
    }
    if ($OpenWindow) {
        Open-AppWindow "http://127.0.0.1:$port"
    }
    $finalStatus = Read-Status
    if (-not $finalStatus.ContainsKey('phase') -or $finalStatus['phase'] -eq 'server' -or $finalStatus['phase'] -eq 'sharing') {
        Write-Status @{ phase = 'ready'; message = 'ARSIP-TA berjalan di komputer ini.' }
    }
}

function Test-PathMatch([string]$Value, [string[]]$Roots) {
    if (-not $Value) {
        return $false
    }
    $trimmed = $Value.Trim().Trim('"').Trim("'")
    foreach ($root in $Roots) {
        if (-not $root) {
            continue
        }
        $full = $root.Trim().TrimEnd('\')
        if (-not $full -or $full.Length -lt 4) {
            continue
        }
        if ($trimmed.Equals($full, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
        if ($trimmed.IndexOf($full + '\', [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            return $true
        }
        if ($trimmed.IndexOf($full + '/', [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            return $true
        }
    }
    return $false
}

function Get-RecordedInstallRoots {
    $found = @()
    foreach ($key in @(
            'HKCU:\Software\ARSIP-TA',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA',
            'HKLM:\Software\ARSIP-TA',
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA',
            'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA'
        )) {
        try {
            $props = Get-ItemProperty -LiteralPath $key -ErrorAction Stop
        } catch {
            continue
        }
        $value = [string]$props.InstallLocation
        if ($value) {
            $found += $value
        }
    }
    return $found
}

function Get-FolderSizeKb([string]$Path) {
    $sum = [int64]0
    $stack = New-Object System.Collections.Generic.Stack[string]
    $stack.Push($Path)
    while ($stack.Count -gt 0) {
        $current = $stack.Pop()
        try {
            foreach ($dir in [System.IO.Directory]::EnumerateDirectories($current)) {
                $stack.Push($dir)
            }
            foreach ($file in [System.IO.Directory]::EnumerateFiles($current)) {
                try {
                    $sum += [System.IO.FileInfo]::new($file).Length
                } catch {
                }
            }
        } catch {
        }
    }
    $kb = [int64][Math]::Floor($sum / 1KB)
    if ($kb -gt [int32]::MaxValue) {
        return [int32]::MaxValue
    }
    if ($kb -lt 0) {
        return 0
    }
    return [int32]$kb
}

function Get-UninstallCommand([switch]$FromShortcut) {
    $ps = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $script = Join-Path $Root 'laravel-assets\scripts\runtime.ps1'
    $command = "`"$ps`" -NoProfile -STA -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`" -Command uninstall -Shell"
    if ($FromShortcut) {
        $command += ' -Shortcut "%1"'
    }
    return $command
}

function Register-InstalledApp {
    $location = $Root.TrimEnd('\')
    $iconFile = Join-Path $Root 'ARSIP-TA.ico'
    if (Test-Path -LiteralPath $iconFile) {
        $icon = $iconFile
    } else {
        $icon = Join-Path $Root 'ARSIP-TA.vbs'
        if (Test-Path -LiteralPath $icon) {
            $icon = "$icon,0"
        } else {
            $icon = Join-Path $env:SystemRoot 'System32\shell32.dll,1'
        }
    }
    $size = 0
    try {
        $size = Get-FolderSizeKb $location
    } catch {
        $size = 0
    }
    $values = @{
        DisplayName     = $AppTitle
        DisplayVersion  = '1.0.0'
        Publisher       = 'Perpustakaan FMIPA UNJ'
        InstallLocation = $location
        UninstallString = (Get-UninstallCommand)
        DisplayIcon     = $icon
        InstallDate     = (Get-Date -Format 'yyyyMMdd')
        EstimatedSize   = $size
        NoModify        = 1
        NoRepair        = 1
    }
    foreach ($key in @(
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA',
            'HKCU:\Software\ARSIP-TA'
        )) {
        New-Item -Path $key -Force | Out-Null
        foreach ($name in $values.Keys) {
            $type = 'String'
            if ($name -in @('NoModify', 'NoRepair', 'EstimatedSize')) {
                $type = 'DWord'
            }
            New-ItemProperty -LiteralPath $key -Name $name -Value $values[$name] -PropertyType $type -Force | Out-Null
        }
    }
    $appPaths = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\App Paths\ARSIP-TA.vbs'
    New-Item -Path $appPaths -Force | Out-Null
    Set-ItemProperty -LiteralPath $appPaths -Name '(default)' -Value (Join-Path $Root 'ARSIP-TA.vbs')
    Set-ItemProperty -LiteralPath $appPaths -Name 'Path' -Value $location

    $verb = 'HKCU:\Software\Classes\lnkfile\shell\ARSIPTAUninstall'
    New-Item -Path $verb -Force | Out-Null
    New-ItemProperty -LiteralPath $verb -Name 'MUIVerb' -Value 'Copot' -PropertyType String -Force | Out-Null
    $applies = 'System.FileName:"ARSIP-TA.lnk" OR System.FileName:"' + (Get-AppShortcutName) + '"'
    New-ItemProperty -LiteralPath $verb -Name 'AppliesTo' -Value $applies -PropertyType String -Force | Out-Null
    New-ItemProperty -LiteralPath $verb -Name 'Position' -Value 'Bottom' -PropertyType String -Force | Out-Null
    New-Item -Path "$verb\command" -Force | Out-Null
    Set-ItemProperty -LiteralPath "$verb\command" -Name '(default)' -Value (Get-UninstallCommand -FromShortcut)

    foreach ($machine in @(
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA',
            'HKLM:\Software\ARSIP-TA'
        )) {
        try {
            New-Item -Path $machine -Force -ErrorAction Stop | Out-Null
            foreach ($name in $values.Keys) {
                $type = 'String'
                if ($name -in @('NoModify', 'NoRepair', 'EstimatedSize')) {
                    $type = 'DWord'
                }
                New-ItemProperty -LiteralPath $machine -Name $name -Value $values[$name] -PropertyType $type -Force -ErrorAction Stop | Out-Null
            }
        } catch {
        }
    }
}

function Confirm-Uninstall {
    try {
        Add-Type -AssemblyName System.Windows.Forms
        $result = [System.Windows.Forms.MessageBox]::Show(
            "Copot pemasangan menghapus ARSIP-TA dari komputer ini.`r`n`r`nYang dihapus: program, basis data di dalam folder aplikasi, registri, catatan, pintasan, dan sisa berkas pemasangan.`r`nIsi berkas ditimpa sebelum dihapus.`r`nFolder arsip yang berada di luar folder aplikasi tidak dihapus.",
            'Copot ARSIP-TA',
            [System.Windows.Forms.MessageBoxButtons]::YesNo,
            [System.Windows.Forms.MessageBoxIcon]::Warning,
            [System.Windows.Forms.MessageBoxDefaultButton]::Button2
        )
        return $result -eq [System.Windows.Forms.DialogResult]::Yes
    } catch {
        return $false
    }
}

function Get-ShortcutInstallRoot([string]$ShortcutPath) {
    $path = $ShortcutPath.Trim().Trim('"')
    if (-not $path -or -not (Test-Path -LiteralPath $path)) {
        return ''
    }
    try {
        $shell = New-Object -ComObject WScript.Shell
        $link = $shell.CreateShortcut($path)
        $candidate = [string]$link.WorkingDirectory
        if (-not $candidate) {
            $target = [string]$link.TargetPath
            if ($target) {
                $candidate = Split-Path -Parent $target
            }
        }
    } catch {
        return ''
    }
    if (Test-SafeAppRoot $candidate) {
        return ([System.IO.Path]::GetFullPath($candidate).TrimEnd('\'))
    }
    return ''
}

function Convert-Rot13([string]$Text) {
    $chars = $Text.ToCharArray()
    for ($i = 0; $i -lt $chars.Length; $i++) {
        $code = [int]$chars[$i]
        if ($code -ge 65 -and $code -le 90) {
            $chars[$i] = [char](((($code - 65) + 13) % 26) + 65)
        } elseif ($code -ge 97 -and $code -le 122) {
            $chars[$i] = [char](((($code - 97) + 13) % 26) + 97)
        }
    }
    return -join $chars
}

function Get-RegistryText($Item, [string]$Name) {
    try {
        $kind = $Item.GetValueKind($Name)
        $raw = $Item.GetValue($Name)
    } catch {
        return ''
    }
    if ($kind -in @('String', 'ExpandString')) {
        return [string]$raw
    }
    if ($kind -eq 'MultiString') {
        return ([string[]]$raw) -join "`n"
    }
    if ($kind -eq 'Binary' -and $raw -is [byte[]] -and $raw.Length -le 512) {
        $unicode = [System.Text.Encoding]::Unicode.GetString($raw).Trim([char]0)
        $ascii = [System.Text.Encoding]::ASCII.GetString($raw).Trim([char]0)
        return "$unicode`n$ascii"
    }
    return ''
}

function Remove-MatchingValues([string]$KeyPath, [string[]]$Roots) {
    if (-not (Test-Path -LiteralPath $KeyPath)) {
        return
    }
    $item = Get-Item -LiteralPath $KeyPath -ErrorAction SilentlyContinue
    if (-not $item) {
        return
    }
    foreach ($name in @($item.GetValueNames())) {
        $text = Get-RegistryText $item $name
        if ((Test-PathMatch $name $Roots) -or (Test-PathMatch $text $Roots)) {
            Remove-ItemProperty -LiteralPath $KeyPath -Name $name -ErrorAction SilentlyContinue
        }
    }
}

function Remove-UserAssist([string[]]$Roots) {
    $base = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\UserAssist'
    if (-not (Test-Path -LiteralPath $base)) {
        return
    }
    Get-ChildItem -LiteralPath $base -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
        $item = $_
        foreach ($name in @($item.GetValueNames())) {
            $decoded = Convert-Rot13 $name
            if ((Test-PathMatch $decoded $Roots) -or (Test-PathMatch $name $Roots)) {
                Remove-ItemProperty -LiteralPath $item.PSPath -Name $name -ErrorAction SilentlyContinue
            }
        }
    }
}

function Remove-InstalledFile([string]$Path) {
    if (-not $Path -or -not (Test-Path -LiteralPath $Path)) {
        return
    }
    Remove-Item -LiteralPath $Path -Force -ErrorAction SilentlyContinue
}

function Test-SafeAppRoot([string]$Path) {
    if (-not $Path) {
        return $false
    }
    try {
        $full = [System.IO.Path]::GetFullPath($Path).TrimEnd('\')
    } catch {
        return $false
    }
    $drive = [System.IO.Path]::GetPathRoot($full)
    if (-not $drive) {
        return $false
    }
    $drive = $drive.TrimEnd('\')
    if ($full.Equals($drive, [System.StringComparison]::OrdinalIgnoreCase)) {
        return $false
    }
    foreach ($blocked in @($env:SystemRoot, $env:USERPROFILE, $env:ProgramFiles, ${env:ProgramFiles(x86)})) {
        if (-not $blocked) {
            continue
        }
        $blocked = $blocked.TrimEnd('\')
        if ($full.Equals($blocked, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $false
        }
        if ($blocked -eq $env:SystemRoot.TrimEnd('\') -and $full.StartsWith($blocked + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
            return $false
        }
    }
    $runtime = Join-Path $full 'laravel-assets\scripts\runtime.ps1'
    $setup = Join-Path $full 'Pasang-ke-Perangkat.cmd'
    $legacy = Join-Path $full 'Pasang-ke-Windows.cmd'
    if ((Test-Path -LiteralPath $runtime) -or (Test-Path -LiteralPath $setup) -or (Test-Path -LiteralPath $legacy)) {
        return $true
    }
    $leaf = Split-Path $full -Leaf
    if (-not $leaf.Equals('ARSIP-TA', [System.StringComparison]::OrdinalIgnoreCase)) {
        return $false
    }
    if (-not (Test-Path -LiteralPath $full -PathType Container)) {
        return $false
    }
    return -not (Test-AppFolderHasFile $full)
}

function Test-AppFolderHasFile([string]$Directory) {
    if (-not $Directory -or -not (Test-Path -LiteralPath $Directory)) {
        return $false
    }
    $pending = New-Object System.Collections.Generic.Stack[string]
    $pending.Push($Directory)
    while ($pending.Count -gt 0) {
        $current = $pending.Pop()
        try {
            foreach ($entry in [System.IO.Directory]::EnumerateFileSystemEntries($current)) {
                if ([System.IO.Directory]::Exists($entry)) {
                    $pending.Push($entry)
                } else {
                    return $true
                }
            }
        } catch {
            return $true
        }
    }
    return $false
}

function Close-ExplorerAt([string[]]$Roots) {
    try {
        $shell = New-Object -ComObject Shell.Application
    } catch {
        return
    }
    foreach ($window in @($shell.Windows())) {
        try {
            $path = [string]$window.Document.Folder.Self.Path
        } catch {
            continue
        }
        if (Test-PathMatch $path $Roots) {
            try { $window.Quit() } catch {}
        }
    }
}

function Get-AppInstallRoots {
    $candidates = @(
        $Root,
        'C:\ARSIP-TA',
        $(if ($env:ProgramFiles) { Join-Path $env:ProgramFiles 'ARSIP-TA' }),
        $(if (${env:ProgramFiles(x86)}) { Join-Path ${env:ProgramFiles(x86)} 'ARSIP-TA' }),
        $(if ($env:LOCALAPPDATA) { Join-Path $env:LOCALAPPDATA 'Programs\ARSIP-TA' })
    ) + @($script:ExtraRoots) + @(Get-RecordedInstallRoots)
    $unique = @{}
    foreach ($candidate in $candidates) {
        if (-not (Test-SafeAppRoot $candidate)) {
            continue
        }
        $full = [System.IO.Path]::GetFullPath($candidate).TrimEnd('\')
        $unique[$full.ToLowerInvariant()] = $full
    }
    return @($unique.Values)
}

function Get-ProcessAncestors {
    $ids = New-Object System.Collections.Generic.List[int]
    $current = $PID
    for ($i = 0; $i -lt 12; $i++) {
        try {
            $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$current" -ErrorAction SilentlyContinue
        } catch {
            break
        }
        if (-not $proc) {
            break
        }
        $parent = 0
        try {
            $parent = [int]$proc.ParentProcessId
        } catch {
            break
        }
        if ($parent -le 4 -or $ids.Contains($parent)) {
            break
        }
        $ids.Add($parent)
        $current = $parent
    }
    return @($ids)
}

function Test-InstallProcess($Process, [string[]]$Roots, [int[]]$ExceptIds) {
    if (-not $Process) {
        return $false
    }
    $procId = 0
    try {
        $procId = [int]$Process.ProcessId
    } catch {
        return $false
    }
    if ($procId -le 4 -or $procId -eq $PID) {
        return $false
    }
    if ($ExceptIds -and ($ExceptIds -contains $procId)) {
        return $false
    }
    $command = [string]$Process.CommandLine
    $executable = [string]$Process.ExecutablePath
    if (-not $executable) {
        try {
            $live = Get-Process -Id $procId -ErrorAction SilentlyContinue
            if ($live -and $live.Path) {
                $executable = [string]$live.Path
            }
        } catch {
        }
    }
    if ($command -like '*-Command uninstall*' -or $command -like '*arsip-ta-wipe-*' -or $command -like '*arsip-ta-empty-*' -or $command -like '*Copot-ARSIP-TA*') {
        return $false
    }
    if (Test-PathMatch $executable $Roots) {
        return $true
    }
    if (-not (Test-PathMatch $command $Roots)) {
        return $false
    }
    $name = [string]$Process.Name
    if (-not $name -and $executable) {
        $name = Split-Path $executable -Leaf
    }
    $holder = $name -in @('php.exe', 'cloudflared.exe', 'wscript.exe', 'cscript.exe', 'powershell.exe', 'pwsh.exe')
    if (-not $holder) {
        return $false
    }
    foreach ($marker in @('artisan', 'server.php', 'cloudflared', 'app-window', 'ARSIP-TA.vbs', 'runtime.ps1', '-Command start', '-Command install', '-Command share', '-Command ensure')) {
        if ($command -like "*$marker*") {
            return $true
        }
    }
    return $false
}

function Stop-ProcessId([int]$ProcessId) {
    if ($ProcessId -le 4 -or $ProcessId -eq $PID) {
        return
    }
    & taskkill.exe /PID $ProcessId /T /F 2>$null | Out-Null
    Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
}

function Stop-RootServices([string[]]$Roots) {
    try {
        $services = @(Get-CimInstance Win32_Service -ErrorAction SilentlyContinue)
    } catch {
        return
    }
    foreach ($service in $services) {
        $pathName = [string]$service.PathName
        if (-not (Test-PathMatch $pathName $Roots)) {
            continue
        }
        $name = [string]$service.Name
        if (-not $name) {
            continue
        }
        & sc.exe stop $name 2>$null | Out-Null
        & sc.exe delete $name 2>$null | Out-Null
    }
}

function Get-RuntimeLockFiles([string[]]$Roots) {
    $files = New-Object System.Collections.Generic.List[string]
    foreach ($root in $Roots) {
        if (-not $root) {
            continue
        }
        foreach ($rel in @(
                'laravel-assets\runtime\cloudflared.exe',
                'laravel-assets\runtime\tunnel.log',
                'laravel-assets\runtime\config.yml',
                'laravel-assets\runtime\php\php.exe'
            )) {
            $path = Join-Path $root $rel
            if (Test-Path -LiteralPath $path) {
                $files.Add($path)
            }
        }
    }
    return @($files)
}

function Get-FileLockers([string[]]$Files) {
    $existing = @($Files | Where-Object { $_ -and (Test-Path -LiteralPath $_) })
    if ($existing.Count -eq 0) {
        return @()
    }
    if (-not ('ArsipTa.RestartManager' -as [type])) {
        $signature = @'
using System;
using System.Runtime.InteropServices;
namespace ArsipTa {
public static class RestartManager {
    [StructLayout(LayoutKind.Sequential)]
    public struct RM_UNIQUE_PROCESS {
        public int dwProcessId;
        public System.Runtime.InteropServices.ComTypes.FILETIME ProcessStartTime;
    }
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public struct RM_PROCESS_INFO {
        public RM_UNIQUE_PROCESS Process;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)]
        public string strAppName;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 64)]
        public string strServiceShortName;
        public uint ApplicationType;
        public uint AppStatus;
        public uint TSSessionId;
        [MarshalAs(UnmanagedType.Bool)]
        public bool bRestartable;
    }
    [DllImport("rstrtmgr.dll", CharSet = CharSet.Unicode)]
    public static extern int RmStartSession(out uint pSessionHandle, int dwSessionFlags, System.Text.StringBuilder strSessionKey);
    [DllImport("rstrtmgr.dll")]
    public static extern int RmEndSession(uint pSessionHandle);
    [DllImport("rstrtmgr.dll", CharSet = CharSet.Unicode)]
    public static extern int RmRegisterResources(uint pSessionHandle, uint nFiles, string[] rgsFilenames, uint nApplications, IntPtr rgApplications, uint nServices, string[] rgsServiceNames);
    [DllImport("rstrtmgr.dll", CharSet = CharSet.Unicode)]
    public static extern int RmGetList(uint dwSessionHandle, out uint pnProcInfoNeeded, ref uint pnProcInfo, [In, Out] RM_PROCESS_INFO[] rgAffectedApps, ref uint lpdwRebootReasons);
}
}
'@
        try {
            Add-Type -TypeDefinition $signature -Language CSharp | Out-Null
        } catch {
            return @()
        }
    }
    $handle = [uint32]0
    $sessionKey = New-Object System.Text.StringBuilder 64
    $started = [ArsipTa.RestartManager]::RmStartSession([ref]$handle, 0, $sessionKey)
    if ($started -ne 0) {
        return @()
    }
    try {
        $registered = [ArsipTa.RestartManager]::RmRegisterResources($handle, [uint32]$existing.Count, [string[]]$existing, 0, [IntPtr]::Zero, 0, $null)
        if ($registered -ne 0) {
            return @()
        }
        $needed = [uint32]0
        $count = [uint32]0
        $reason = [uint32]0
        $probe = New-Object 'ArsipTa.RestartManager+RM_PROCESS_INFO[]' 1
        $listed = [ArsipTa.RestartManager]::RmGetList($handle, [ref]$needed, [ref]$count, $probe, [ref]$reason)
        if ($listed -eq 0) {
            if ($count -eq 0) {
                return @()
            }
            return @($probe[0].Process.dwProcessId)
        }
        if ($listed -ne 234 -or $needed -lt 1) {
            return @()
        }
        $items = New-Object 'ArsipTa.RestartManager+RM_PROCESS_INFO[]' ([int]$needed)
        $count = $needed
        $listed = [ArsipTa.RestartManager]::RmGetList($handle, [ref]$needed, [ref]$count, $items, [ref]$reason)
        if ($listed -ne 0) {
            return @()
        }
        $ids = New-Object System.Collections.Generic.List[int]
        $limit = [Math]::Min([int]$count, $items.Length)
        for ($i = 0; $i -lt $limit; $i++) {
            $ids.Add([int]$items[$i].Process.dwProcessId)
        }
        return @($ids)
    } catch {
        return @()
    } finally {
        try {
            [ArsipTa.RestartManager]::RmEndSession($handle) | Out-Null
        } catch {
        }
    }
}

function Stop-CatalogListener([string[]]$Roots) {
    $port = 0
    try {
        $port = Get-CatalogPort
    } catch {
        return
    }
    $lines = @()
    try {
        $lines = @(& netstat.exe -ano -p tcp)
    } catch {
        return
    }
    $except = @(Get-ProcessAncestors)
    foreach ($line in $lines) {
        if ($line -notmatch "^\s*TCP\s+\S+:$port\s+\S+\s+LISTENING\s+(\d+)\s*$") {
            continue
        }
        $procId = [int]$Matches[1]
        $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$procId" -ErrorAction SilentlyContinue
        if (-not (Test-InstallProcess $proc $Roots $except)) {
            continue
        }
        Stop-ProcessId $procId
    }
}

function Stop-InstalledProcesses([string[]]$Roots) {
    $except = @(Get-ProcessAncestors)
    Stop-RootServices $Roots
    for ($try = 0; $try -lt 8; $try++) {
        Stop-CatalogListener $Roots
        $ids = New-Object System.Collections.Generic.List[int]
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | ForEach-Object {
            if (Test-InstallProcess $_ $Roots $except) {
                $ids.Add([int]$_.ProcessId)
            }
        }
        try {
            $lockers = @(Get-FileLockers (Get-RuntimeLockFiles $Roots))
        } catch {
            $lockers = @()
        }
        foreach ($locker in $lockers) {
            $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$locker" -ErrorAction SilentlyContinue
            if (Test-InstallProcess $proc $Roots $except) {
                $ids.Add([int]$locker)
            }
        }
        $unique = @($ids | Select-Object -Unique)
        if ($unique.Count -eq 0) {
            return
        }
        foreach ($procId in $unique) {
            Stop-ProcessId ([int]$procId)
        }
        Start-Sleep -Milliseconds 400
    }
}

function Test-ShortcutHits([string]$Path, [string[]]$Roots) {
    try {
        $shell = New-Object -ComObject WScript.Shell
        $link = $shell.CreateShortcut($Path)
        $text = (([string]$link.TargetPath) + ' ' + ([string]$link.Arguments) + ' ' + ([string]$link.WorkingDirectory))
    } catch {
        return $false
    }
    return Test-PathMatch $text $Roots
}

function Remove-AppShortcuts([string[]]$Roots) {
    $names = @(Get-ExtraLauncherNames) + @(Get-AppShortcutName)
    $folders = @(
        [Environment]::GetFolderPath('Desktop'),
        [Environment]::GetFolderPath('Programs'),
        [Environment]::GetFolderPath('Startup'),
        [Environment]::GetFolderPath('CommonDesktopDirectory'),
        [Environment]::GetFolderPath('CommonPrograms'),
        [Environment]::GetFolderPath('CommonStartup'),
        (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch\User Pinned\TaskBar'),
        (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch\User Pinned\StartMenu')
    )
    foreach ($folder in $folders) {
        if (-not $folder -or -not (Test-Path -LiteralPath $folder)) {
            continue
        }
        foreach ($name in $names) {
            Remove-InstalledFile (Join-Path $folder $name)
        }
        Get-ChildItem -LiteralPath $folder -Filter '*.lnk' -Force -ErrorAction SilentlyContinue | ForEach-Object {
            if (Test-ShortcutHits $_.FullName $Roots) {
                Remove-InstalledFile $_.FullName
            }
        }
        $group = Join-Path $folder 'ARSIP-TA'
        if (Test-Path -LiteralPath $group) {
            Get-ChildItem -LiteralPath $group -Force -ErrorAction SilentlyContinue | ForEach-Object {
                if ($names -contains $_.Name -or ($_.Extension -eq '.lnk' -and (Test-ShortcutHits $_.FullName $Roots))) {
                    Remove-InstalledFile $_.FullName
                }
            }
            $left = @(Get-ChildItem -LiteralPath $group -Force -ErrorAction SilentlyContinue)
            if ($left.Count -eq 0) {
                Remove-Item -LiteralPath $group -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Remove-JumpLists([string[]]$Roots, [string]$Directory = '') {
    $dirs = @()
    if ($Directory) {
        $dirs = @($Directory)
    } else {
        $recent = Join-Path $env:APPDATA 'Microsoft\Windows\Recent'
        $dirs = @(
            (Join-Path $recent 'AutomaticDestinations'),
            (Join-Path $recent 'CustomDestinations')
        )
    }
    foreach ($dir in $dirs) {
        if (-not $dir -or -not (Test-Path -LiteralPath $dir)) {
            continue
        }
        Get-ChildItem -LiteralPath $dir -File -Force -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_.Length -gt 2MB) {
                return
            }
            $text = [System.Text.Encoding]::Unicode.GetString([System.IO.File]::ReadAllBytes($_.FullName))
            $own = $false
            $foreign = $false
            foreach ($match in [regex]::Matches($text, '[A-Za-z]:\\[^\u0000]{2,220}')) {
                $path = $match.Value.Trim()
                if (Test-PathMatch $path $Roots) {
                    $own = $true
                    continue
                }
                $leaf = Split-Path $path -Leaf
                if ($leaf -in @('powershell.exe', 'pwsh.exe', 'cmd.exe', 'wscript.exe', 'cscript.exe')) {
                    continue
                }
                $foreign = $true
            }
            if ($own -and -not $foreign) {
                Remove-InstalledFile $_.FullName
            }
        }
    }
}

function Remove-RecycleResiduals([string[]]$Roots) {
    try {
        $shell = New-Object -ComObject Shell.Application
        $bin = $shell.NameSpace(10)
    } catch {
        return
    }
    if (-not $bin) {
        return
    }
    $names = @(Get-ExtraLauncherNames) + @(Get-AppShortcutName)
    foreach ($item in @($bin.Items())) {
        $name = [string]$item.Name
        $from = ''
        try {
            $from = [string]$item.ExtendedProperty('System.Recycle.DeletedFrom')
        } catch {
        }
        if (($names -contains $name) -or (Test-PathMatch $from $Roots)) {
            Remove-InstalledFile ([string]$item.Path)
        }
    }
}

function Remove-BitsJobs([string[]]$Roots) {
    try {
        Import-Module BitsTransfer -ErrorAction Stop
        $jobs = @(Get-BitsTransfer -ErrorAction SilentlyContinue)
    } catch {
        return
    }
    foreach ($job in $jobs) {
        $hit = [string]$job.DisplayName -like '*ARSIP-TA*'
        foreach ($file in @($job.FileList)) {
            if ((Test-PathMatch ([string]$file.LocalName) $Roots) -or (Test-PathMatch ([string]$file.RemoteName) $Roots)) {
                $hit = $true
            }
        }
        if ($hit) {
            try {
                Remove-BitsTransfer -BitsJob $job -ErrorAction SilentlyContinue
            } catch {
            }
        }
    }
}

function Remove-RecentShortcuts([string[]]$Roots) {
    $recent = [Environment]::GetFolderPath('Recent')
    if (-not $recent -or -not (Test-Path -LiteralPath $recent)) {
        return
    }
    $shell = New-Object -ComObject WScript.Shell
    Get-ChildItem -LiteralPath $recent -Filter '*.lnk' -Force -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            $link = $shell.CreateShortcut($_.FullName)
            $target = ([string]$link.TargetPath) + ' ' + ([string]$link.Arguments) + ' ' + ([string]$link.WorkingDirectory)
        } catch {
            return
        }
        if (Test-PathMatch $target $Roots) {
            Remove-InstalledFile $_.FullName
        }
    }
}

function Remove-AppRegistry([string[]]$Roots) {
    foreach ($key in @(
            'HKCU:\Software\ARSIP-TA',
            'HKLM:\Software\ARSIP-TA',
            'HKCU:\Software\Classes\lnkfile\shell\ARSIPTAUninstall',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\App Paths\ARSIP-TA.vbs',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Notifications\Settings\ARSIP-TA',
            'HKCU:\Software\Classes\AppUserModelId\ARSIP-TA'
        )) {
        if (Test-Path -LiteralPath $key) {
            Remove-Item -LiteralPath $key -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    foreach ($parent in @(
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall',
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\Uninstall',
            'HKLM:\Software\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall'
        )) {
        if (-not (Test-Path -LiteralPath $parent)) {
            continue
        }
        Get-ChildItem -LiteralPath $parent -ErrorAction SilentlyContinue | ForEach-Object {
            $props = Get-ItemProperty -LiteralPath $_.PSPath -ErrorAction SilentlyContinue
            if (-not $props) {
                return
            }
            $display = [string]$props.DisplayName
            $location = [string]$props.InstallLocation
            $command = ([string]$props.UninstallString) + ' ' + ([string]$props.QuietUninstallString)
            $match = $display -eq 'ARSIP-TA' -or $display -eq $AppTitle
            if (-not $match) {
                $match = (Test-PathMatch $location $Roots) -or (Test-PathMatch $command $Roots)
            }
            if ($match) {
                Remove-Item -LiteralPath $_.PSPath -Recurse -Force -ErrorAction SilentlyContinue
            }
        }
    }
    foreach ($key in @(
            'HKCU:\Software\Classes\Local Settings\Software\Microsoft\Windows\Shell\MuiCache',
            'HKCU:\Software\Microsoft\Windows\ShellNoRoam\MUICache',
            'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\AppCompatFlags\Compatibility Assistant\Store',
            'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\AppCompatFlags\Layers',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\FeatureUsage\AppSwitched',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\FeatureUsage\AppLaunch',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\FeatureUsage\ShowJumpView',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\FeatureUsage\AppBadgeUpdated',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Search\JumplistData',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\UFH\SHC',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\ApplicationAssociationToasts',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\Run',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\StartupApproved\StartupFolder',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\TypedPaths',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\WordWheelQuery',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run',
            'HKCU:\Software\Microsoft\Windows\CurrentVersion\RunOnce',
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\Run',
            'HKLM:\Software\Microsoft\Windows\CurrentVersion\RunOnce'
        )) {
        Remove-MatchingValues $key $Roots
    }
    Remove-UserAssist $Roots
}

function Remove-AppLeftovers([string[]]$Roots) {
    $temps = @($env:TEMP, $env:TMP, (Join-Path $env:LOCALAPPDATA 'Temp'), (Join-Path $env:SystemRoot 'Temp')) | Where-Object { $_ } | Select-Object -Unique
    foreach ($temp in $temps) {
        if (-not (Test-Path -LiteralPath $temp)) {
            continue
        }
        Get-ChildItem -LiteralPath $temp -Force -ErrorAction SilentlyContinue | Where-Object {
            $_.Name -like 'arsip-ta-*' -or $_.Name -like 'arsip-keep-*'
        } | ForEach-Object {
            Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    foreach ($dir in @(
            (Join-Path $env:LOCALAPPDATA 'ARSIP-TA'),
            (Join-Path $env:APPDATA 'ARSIP-TA'),
            (Join-Path $env:PROGRAMDATA 'ARSIP-TA')
        )) {
        if ($dir -and (Test-Path -LiteralPath $dir)) {
            Remove-Item -LiteralPath $dir -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    $prefetch = Join-Path $env:SystemRoot 'Prefetch'
    if (Test-Path -LiteralPath $prefetch) {
        Get-ChildItem -LiteralPath $prefetch -Filter 'ARSIP-TA*' -Force -ErrorAction SilentlyContinue | ForEach-Object {
            Remove-InstalledFile $_.FullName
        }
    }
    $dumps = Join-Path $env:LOCALAPPDATA 'CrashDumps'
    if (Test-Path -LiteralPath $dumps) {
        Get-ChildItem -LiteralPath $dumps -Force -ErrorAction SilentlyContinue | Where-Object { $_.Name -like '*ARSIP-TA*' } | ForEach-Object {
            Remove-InstalledFile $_.FullName
        }
    }
    foreach ($wer in @(
            (Join-Path $env:LOCALAPPDATA 'Microsoft\Windows\WER\ReportArchive'),
            (Join-Path $env:LOCALAPPDATA 'Microsoft\Windows\WER\ReportQueue'),
            (Join-Path $env:PROGRAMDATA 'Microsoft\Windows\WER\ReportArchive'),
            (Join-Path $env:PROGRAMDATA 'Microsoft\Windows\WER\ReportQueue')
        )) {
        try {
            if (-not (Test-Path -LiteralPath $wer)) {
                continue
            }
            $reports = @(Get-ChildItem -LiteralPath $wer -Directory -Force -ErrorAction SilentlyContinue)
        } catch {
            continue
        }
        foreach ($reportDir in $reports) {
            try {
                $report = Join-Path $reportDir.FullName 'Report.wer'
                $text = ''
                if (Test-Path -LiteralPath $report) {
                    try {
                        $text = [System.IO.File]::ReadAllText($report)
                    } catch {
                        $text = ''
                    }
                }
                if ($reportDir.Name -like '*ARSIP-TA*' -or (Test-PathMatch $text $Roots)) {
                    Remove-Item -LiteralPath $reportDir.FullName -Recurse -Force -ErrorAction SilentlyContinue
                }
            } catch {
            }
        }
    }
    try {
        & netsh.exe advfirewall firewall delete rule name="ARSIP-TA" 2>$null | Out-Null
    } catch {
    }
    try {
        Get-NetFirewallRule -DisplayName 'ARSIP-TA' -ErrorAction SilentlyContinue | ForEach-Object {
            Remove-NetFirewallRule -Name $_.Name -ErrorAction SilentlyContinue
        }
    } catch {
    }
    try {
        $service = Get-Service -Name 'ARSIP-TA' -ErrorAction SilentlyContinue
        if ($service) {
            Stop-Service -Name 'ARSIP-TA' -Force -ErrorAction SilentlyContinue
            & sc.exe delete 'ARSIP-TA' | Out-Null
        }
    } catch {
    }
    try {
        Get-ScheduledTask -ErrorAction SilentlyContinue | ForEach-Object {
            $task = $_
            $hit = $task.TaskName -like '*ARSIP-TA*'
            foreach ($action in @($task.Actions)) {
                $execute = ''
                $arguments = ''
                if ($action.PSObject.Properties.Name -contains 'Execute') {
                    $execute = [string]$action.Execute
                }
                if ($action.PSObject.Properties.Name -contains 'Arguments') {
                    $arguments = [string]$action.Arguments
                }
                if ((Test-PathMatch $execute $Roots) -or (Test-PathMatch $arguments $Roots)) {
                    $hit = $true
                }
            }
            if ($hit) {
                Unregister-ScheduledTask -TaskName $task.TaskName -TaskPath $task.TaskPath -Confirm:$false -ErrorAction SilentlyContinue
            }
        }
    } catch {
    }
}

function Start-PermanentWipe([string[]]$Roots) {
    $id = [guid]::NewGuid().ToString('n')
    $job = Join-Path $env:TEMP "arsip-ta-wipe-$id.ps1"
    $list = Join-Path $env:TEMP "arsip-ta-wipe-$id.txt"
    [System.IO.File]::WriteAllLines($list, [string[]]$Roots)
    $worker = @'
param(
    [Parameter(Mandatory = $true)][int]$ParentId,
    [Parameter(Mandatory = $true)][string]$ListPath,
    [Parameter(Mandatory = $true)][string]$SelfPath
)
$ErrorActionPreference = 'Continue'
try { Set-Location -LiteralPath $env:SystemRoot } catch {}
try { [System.IO.Directory]::SetCurrentDirectory($env:SystemRoot) } catch {}
while (Get-Process -Id $ParentId -ErrorAction SilentlyContinue) {
    Start-Sleep -Milliseconds 400
}
try { Set-Location -LiteralPath $env:SystemRoot } catch {}
try { [System.IO.Directory]::SetCurrentDirectory($env:SystemRoot) } catch {}
function Test-EmptyTree([string]$Directory) {
    if (-not (Test-Path -LiteralPath $Directory)) { return $false }
    $pending = New-Object System.Collections.Generic.Stack[string]
    $pending.Push($Directory)
    while ($pending.Count -gt 0) {
        $current = $pending.Pop()
        foreach ($child in @(Get-ChildItem -LiteralPath $current -Force -ErrorAction SilentlyContinue)) {
            if ($child.PSIsContainer) { $pending.Push($child.FullName) } else { return $false }
        }
    }
    return $true
}
function Test-Safe([string]$Path) {
    if (-not $Path) { return $false }
    try { $full = [System.IO.Path]::GetFullPath($Path).TrimEnd('\') } catch { return $false }
    $drive = [System.IO.Path]::GetPathRoot($full).TrimEnd('\')
    if ($full.Equals($drive, [System.StringComparison]::OrdinalIgnoreCase)) { return $false }
    $windows = $env:SystemRoot.TrimEnd('\')
    if ($full.Equals($windows, [System.StringComparison]::OrdinalIgnoreCase) -or $full.StartsWith($windows + '\', [System.StringComparison]::OrdinalIgnoreCase)) { return $false }
    foreach ($blocked in @($env:USERPROFILE, $env:ProgramFiles, ${env:ProgramFiles(x86)})) {
        if ($blocked -and $full.Equals($blocked.TrimEnd('\'), [System.StringComparison]::OrdinalIgnoreCase)) { return $false }
    }
    $runtime = Join-Path $full 'laravel-assets\scripts\runtime.ps1'
    $setup = Join-Path $full 'Pasang-ke-Perangkat.cmd'
    $legacy = Join-Path $full 'Pasang-ke-Windows.cmd'
    if ((Test-Path -LiteralPath $runtime) -or (Test-Path -LiteralPath $setup) -or (Test-Path -LiteralPath $legacy)) { return $true }
    $leaf = Split-Path $full -Leaf
    if (-not $leaf.Equals('ARSIP-TA', [System.StringComparison]::OrdinalIgnoreCase)) { return $false }
    return Test-EmptyTree $full
}
function Close-ExplorerAt([string]$Directory) {
    try { $shell = New-Object -ComObject Shell.Application } catch { return }
    $prefix = $Directory.TrimEnd('\')
    foreach ($window in @($shell.Windows())) {
        try { $path = [string]$window.Document.Folder.Self.Path } catch { continue }
        if (-not $path) { continue }
        $fullPath = $path.TrimEnd('\')
        $inside = $fullPath.Equals($prefix, [System.StringComparison]::OrdinalIgnoreCase) -or $fullPath.StartsWith($prefix + '\', [System.StringComparison]::OrdinalIgnoreCase)
        if ($inside) { try { $window.Quit() } catch {} }
    }
}
function Get-AppFiles([string]$Directory) {
    $pending = New-Object System.Collections.Generic.Stack[string]
    $pending.Push($Directory)
    while ($pending.Count -gt 0) {
        $current = $pending.Pop()
        foreach ($child in @(Get-ChildItem -LiteralPath $current -Force -ErrorAction SilentlyContinue)) {
            if ($child.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { continue }
            if ($child.PSIsContainer) { $pending.Push($child.FullName) } else { $child.FullName }
        }
    }
}
function Clear-FileBits([string]$File) {
    try { $info = Get-Item -LiteralPath $File -Force } catch { return }
    try { $info.Attributes = [System.IO.FileAttributes]::Normal } catch {}
    try {
        Get-Item -LiteralPath $File -Stream * -Force -ErrorAction SilentlyContinue | ForEach-Object {
            if ($_.Stream -and $_.Stream -ne ':$DATA') {
                Remove-Item -LiteralPath $File -Stream $_.Stream -Force -ErrorAction SilentlyContinue
            }
        }
    } catch {}
    $stream = $null
    try {
        $stream = [System.IO.File]::Open($File, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
        $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        $left = $stream.Length
        while ($left -gt 0) {
            $size = [int][Math]::Min(65536, $left)
            $chunk = New-Object byte[] $size
            $rng.GetBytes($chunk)
            $stream.Write($chunk, 0, $size)
            $left -= $size
        }
        $stream.Flush($true)
    } catch {
    } finally {
        if ($stream) { $stream.Dispose() }
    }
}
$roots = @(Get-Content -LiteralPath $ListPath -Encoding UTF8 | Where-Object { $_ -and (Test-Safe $_) })
$remaining = @()
function Test-HasFile([string]$Directory) {
    if (-not (Test-Path -LiteralPath $Directory)) { return $false }
    $pending = New-Object System.Collections.Generic.Stack[string]
    $pending.Push($Directory)
    while ($pending.Count -gt 0) {
        $current = $pending.Pop()
        foreach ($child in @(Get-ChildItem -LiteralPath $current -Force -ErrorAction SilentlyContinue)) {
            if ($child.PSIsContainer) { $pending.Push($child.FullName) } else { return $true }
        }
    }
    return $false
}
function Invoke-RemoveCmd([string]$Directory, [switch]$EmptyOnly) {
    try { Set-Location -LiteralPath $env:SystemRoot } catch {}
    try { [System.IO.Directory]::SetCurrentDirectory($env:SystemRoot) } catch {}
    $cmdPath = Join-Path $env:TEMP ('arsip-ta-rm-' + [guid]::NewGuid().ToString('n') + '.cmd')
    $verb = 'rmdir /s /q'
    if ($EmptyOnly) { $verb = 'rmdir' }
    Set-Content -LiteralPath $cmdPath -Value @('@echo off', 'cd /d %SystemRoot%', ($verb + ' "' + $Directory + '"')) -Encoding ASCII
    Start-Process -FilePath $env:ComSpec -ArgumentList @('/d', '/c', $cmdPath) -WorkingDirectory $env:SystemRoot -WindowStyle Hidden -Wait | Out-Null
    Remove-Item -LiteralPath $cmdPath -Force -ErrorAction SilentlyContinue
}
function Remove-InstallDirectory([string]$Directory) {
    if (-not (Test-Path -LiteralPath $Directory)) { return $true }
    try { Set-Location -LiteralPath $env:SystemRoot } catch {}
    try { [System.IO.Directory]::SetCurrentDirectory($env:SystemRoot) } catch {}
    try {
        $rootItem = Get-Item -LiteralPath $Directory -Force
        $rootItem.Attributes = [System.IO.FileAttributes]::Directory
    } catch {}
    Get-ChildItem -LiteralPath $Directory -Recurse -Force -ErrorAction SilentlyContinue | ForEach-Object {
        try { $_.Attributes = [System.IO.FileAttributes]::Normal } catch {}
    }
    Remove-Item -LiteralPath $Directory -Recurse -Force -ErrorAction SilentlyContinue
    if (-not (Test-Path -LiteralPath $Directory)) { return $true }
    try { [System.IO.Directory]::Delete($Directory, $true) } catch {}
    if (-not (Test-Path -LiteralPath $Directory)) { return $true }
    Invoke-RemoveCmd $Directory
    if (-not (Test-Path -LiteralPath $Directory)) { return $true }
    if (-not (Test-HasFile $Directory)) {
        $dirs = @(Get-ChildItem -LiteralPath $Directory -Recurse -Force -Directory -ErrorAction SilentlyContinue | Sort-Object { $_.FullName.Length } -Descending)
        foreach ($dir in $dirs) { Invoke-RemoveCmd $dir.FullName -EmptyOnly }
        Invoke-RemoveCmd $Directory -EmptyOnly
        try { [System.IO.Directory]::Delete($Directory, $false) } catch {}
    }
    return -not (Test-Path -LiteralPath $Directory)
}
function Stop-HeldProcesses([string]$Directory) {
    $prefix = $Directory.TrimEnd('\')
    $needle = $prefix + '\'
    $except = New-Object System.Collections.Generic.List[int]
    $except.Add($PID)
    $current = $PID
    for ($i = 0; $i -lt 12; $i++) {
        try { $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$current" -ErrorAction SilentlyContinue } catch { break }
        if (-not $proc) { break }
        $parent = 0
        try { $parent = [int]$proc.ParentProcessId } catch { break }
        if ($parent -le 4 -or $except.Contains($parent)) { break }
        $except.Add($parent)
        $current = $parent
    }
    try {
        Get-CimInstance Win32_Service -ErrorAction SilentlyContinue | ForEach-Object {
            $pathName = [string]$_.PathName
            if (-not $pathName) { return }
            $bare = $pathName.Trim().Trim('"')
            $hit = $bare.Equals($prefix, [System.StringComparison]::OrdinalIgnoreCase) -or $bare.IndexOf($needle, [System.StringComparison]::OrdinalIgnoreCase) -ge 0 -or $pathName.IndexOf($needle, [System.StringComparison]::OrdinalIgnoreCase) -ge 0
            if (-not $hit) { return }
            $serviceName = [string]$_.Name
            if (-not $serviceName) { return }
            & sc.exe stop $serviceName 2>$null | Out-Null
            & sc.exe delete $serviceName 2>$null | Out-Null
        }
    } catch {}
    $ids = New-Object System.Collections.Generic.List[int]
    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | ForEach-Object {
        $procId = [int]$_.ProcessId
        if ($procId -le 4 -or $except.Contains($procId)) { return }
        $command = [string]$_.CommandLine
        $executable = [string]$_.ExecutablePath
        if (-not $executable) {
            try {
                $live = Get-Process -Id $procId -ErrorAction SilentlyContinue
                if ($live -and $live.Path) { $executable = [string]$live.Path }
            } catch {}
        }
        if ($command -like '*-Command uninstall*' -or $command -like '*arsip-ta-wipe-*' -or $command -like '*arsip-ta-empty-*' -or $command -like '*Copot-ARSIP-TA*') { return }
        $pathHit = $false
        if ($executable) {
            $bareExe = $executable.Trim().Trim('"')
            $pathHit = $bareExe.Equals($prefix, [System.StringComparison]::OrdinalIgnoreCase) -or $bareExe.StartsWith($needle, [System.StringComparison]::OrdinalIgnoreCase)
        }
        if (-not $pathHit -and $command -and $command.IndexOf($needle, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
            $name = [string]$_.Name
            $holder = $name -in @('php.exe', 'cloudflared.exe', 'wscript.exe', 'cscript.exe', 'powershell.exe', 'pwsh.exe')
            $marker = $command -like '*artisan*' -or $command -like '*cloudflared*' -or $command -like '*runtime.ps1*' -or $command -like '*ARSIP-TA.vbs*' -or $command -like '*-Command start*' -or $command -like '*-Command install*' -or $command -like '*-Command share*'
            $pathHit = $holder -and $marker
        }
        if ($pathHit) { $ids.Add($procId) }
    }
    $killed = $false
    foreach ($procId in @($ids | Select-Object -Unique)) {
        & taskkill.exe /PID $procId /T /F 2>$null | Out-Null
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
        $killed = $true
    }
    if ($killed) { Start-Sleep -Milliseconds 400 }
}
foreach ($root in $roots) {
    $gone = $false
    for ($try = 0; $try -lt 20; $try++) {
        Stop-HeldProcesses $root
        Close-ExplorerAt $root
        if ($try -eq 0) {
            foreach ($file in @(Get-AppFiles $root)) { Clear-FileBits $file }
            Get-ChildItem -LiteralPath $root -Recurse -Force -Directory -ErrorAction SilentlyContinue | ForEach-Object {
                try { $_.Attributes = [System.IO.FileAttributes]::Directory } catch {}
            }
        }
        if (Remove-InstallDirectory $root) { $gone = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $gone) { $remaining += $root }
}
$emptyLeft = @($remaining | Where-Object { -not (Test-HasFile $_) })
if ($emptyLeft.Count -gt 0) {
    $sweep = Join-Path $env:TEMP ('arsip-ta-empty-' + [guid]::NewGuid().ToString('n') + '.ps1')
    $emptyList = Join-Path $env:TEMP ('arsip-ta-empty-' + [guid]::NewGuid().ToString('n') + '.txt')
    [System.IO.File]::WriteAllLines($emptyList, [string[]]$emptyLeft)
    $sweepBody = @(
        'param([Parameter(Mandatory = $true)][string]$ListPath)',
        '$ErrorActionPreference = ''SilentlyContinue''',
        'Set-Location -LiteralPath $env:SystemRoot',
        '[System.IO.Directory]::SetCurrentDirectory($env:SystemRoot)',
        '$roots = @(Get-Content -LiteralPath $ListPath -Encoding UTF8 | Where-Object { $_ })',
        'function Test-HasFile([string]$Directory) {',
        '    if (-not (Test-Path -LiteralPath $Directory)) { return $false }',
        '    $pending = New-Object System.Collections.Generic.Stack[string]',
        '    $pending.Push($Directory)',
        '    while ($pending.Count -gt 0) {',
        '        $current = $pending.Pop()',
        '        foreach ($child in @(Get-ChildItem -LiteralPath $current -Force -ErrorAction SilentlyContinue)) {',
        '            if ($child.PSIsContainer) { $pending.Push($child.FullName) } else { return $true }',
        '        }',
        '    }',
        '    return $false',
        '}',
        'for ($try = 0; $try -lt 60; $try++) {',
        '    $pending = $false',
        '    try {',
        '        $shell = New-Object -ComObject Shell.Application',
        '        foreach ($window in @($shell.Windows())) {',
        '            try { $path = [string]$window.Document.Folder.Self.Path } catch { continue }',
        '            foreach ($root in $roots) {',
        "                `$prefix = `$root.TrimEnd('\')",
        "                `$fullPath = `$path.TrimEnd('\')",
        "                `$inside = `$fullPath.Equals(`$prefix, [System.StringComparison]::OrdinalIgnoreCase) -or `$fullPath.StartsWith(`$prefix + '\', [System.StringComparison]::OrdinalIgnoreCase)",
        '                if ($inside) { try { $window.Quit() } catch {} }',
        '            }',
        '        }',
        '    } catch {}',
        '    foreach ($root in $roots) {',
        '        if (-not (Test-Path -LiteralPath $root)) { continue }',
        '        if (Test-HasFile $root) { continue }',
        '        $cmd = Join-Path $env:TEMP ("arsip-ta-rm-" + [guid]::NewGuid().ToString("n") + ".cmd")',
        '        Set-Content -LiteralPath $cmd -Value @("@echo off", "cd /d %SystemRoot%", ("rmdir /s /q """ + $root + """")) -Encoding ASCII',
        '        Start-Process -FilePath $env:ComSpec -ArgumentList @("/d", "/c", $cmd) -WorkingDirectory $env:SystemRoot -WindowStyle Hidden -Wait | Out-Null',
        '        Remove-Item -LiteralPath $cmd -Force -ErrorAction SilentlyContinue',
        '        if (Test-Path -LiteralPath $root) { $pending = $true }',
        '    }',
        '    if (-not $pending) { break }',
        '    Start-Sleep -Seconds 2',
        '}',
        'Remove-Item -LiteralPath $ListPath, $PSCommandPath -Force -ErrorAction SilentlyContinue'
    )
    Set-Content -LiteralPath $sweep -Value $sweepBody -Encoding UTF8
    Start-Process -FilePath 'powershell.exe' -ArgumentList @('-NoProfile', '-STA', '-ExecutionPolicy', 'Bypass', '-File', $sweep, '-ListPath', $emptyList) -WorkingDirectory $env:SystemRoot -WindowStyle Hidden | Out-Null
}
Remove-Item -LiteralPath $ListPath, $SelfPath -Force -ErrorAction SilentlyContinue
try {
    Add-Type -AssemblyName System.Windows.Forms
    if ($remaining.Count -eq 0) {
        [System.Windows.Forms.MessageBox]::Show('ARSIP-TA sudah dihapus dari komputer ini. Berkas pemasangan ditimpa, lalu folder aplikasi, registri, catatan, pintasan, dan sisa berkas dihapus.', 'ARSIP-TA')
    } else {
        [System.Windows.Forms.MessageBox]::Show('Sebagian folder masih dipakai program yang berjalan, jadi belum terhapus. Tutup program itu, lalu jalankan Copot-ARSIP-TA.cmd lagi.' + [Environment]::NewLine + ($remaining -join [Environment]::NewLine), 'ARSIP-TA')
    }
} catch {
}
'@
    Set-Content -LiteralPath $job -Value $worker -Encoding UTF8
    $launchDir = $env:TEMP
    if (-not $launchDir -or -not (Test-Path -LiteralPath $launchDir)) {
        $launchDir = $env:SystemRoot
    }
    Start-Process -FilePath 'powershell.exe' -ArgumentList @(
        '-NoProfile', '-STA', '-ExecutionPolicy', 'Bypass', '-File', $job,
        '-ParentId', $PID, '-ListPath', $list, '-SelfPath', $job
    ) -WorkingDirectory $launchDir -WindowStyle Hidden | Out-Null
}

function Invoke-UninstallStep([scriptblock]$Action) {
    try {
        & $Action
    } catch {
    }
}

function Uninstall-App {
    $installRoots = @(Get-AppInstallRoots)
    if ($installRoots.Count -eq 0) {
        throw 'Folder aplikasi ARSIP-TA tidak ditemukan.'
    }
    Write-Status @{ phase = 'removing'; message = 'Menghapus ARSIP-TA secara permanen dari komputer ini.' }
    Invoke-UninstallStep { Stop-Server }
    Invoke-UninstallStep { Stop-InstalledProcesses $installRoots }
    Start-Sleep -Milliseconds 400
    Invoke-UninstallStep { Remove-AppShortcuts $installRoots }
    Invoke-UninstallStep { Remove-RecentShortcuts $installRoots }
    Invoke-UninstallStep { Remove-JumpLists $installRoots }
    Invoke-UninstallStep { Remove-RecycleResiduals $installRoots }
    Invoke-UninstallStep { Remove-BitsJobs $installRoots }
    Invoke-UninstallStep { Remove-AppRegistry $installRoots }
    Invoke-UninstallStep { Remove-AppLeftovers $installRoots }
    Invoke-UninstallStep { Close-ExplorerAt $installRoots }
    try { Set-Location -LiteralPath $env:SystemRoot } catch {}
    try { [System.IO.Directory]::SetCurrentDirectory($env:SystemRoot) } catch {}
    Start-PermanentWipe $installRoots
}

try {
    New-Item -ItemType Directory -Force -Path $Runtime | Out-Null
    Set-Location $Root
    switch ($Command) {
        'ensure' {
            Enter-RuntimeLock
            try {
                Write-Status @{ phase = 'starting'; message = 'Memeriksa runtime aplikasi.' }
                $php = Resolve-Php
                Install-Cloudflared | Out-Null
                Install-Shortcuts (Get-CatalogPort)
                Ensure-Vendor
                Write-Status @{ phase = 'ready'; php = $php; message = 'Runtime aplikasi siap di folder ini.' }
                Write-Output 'Runtime siap.'
            } finally {
                Exit-RuntimeLock
            }
        }
        'install' {
            Enter-RuntimeLock
            try {
                Write-Status @{ phase = 'starting'; message = 'Memasang peladen, basis data, dan tautan internet.' }
                Stop-Server
                Stop-InstalledProcesses @($Root)
                Start-Sleep -Milliseconds 700
                $php = Resolve-Php
                Install-Cloudflared | Out-Null
                Install-Shortcuts (Get-CatalogPort)
                Ensure-Vendor
                Invoke-Prepare $php
                Exit-RuntimeLock
                Start-LocalServer $true
                Write-Output 'ARSIP-TA terpasang.'
            } catch {
                Exit-RuntimeLock
                throw
            }
        }
        'start' {
            Enter-RuntimeLock
            try {
                Start-LocalServer $true
                Write-Output 'ARSIP-TA berjalan.'
            } finally {
                Exit-RuntimeLock
            }
        }
        'stop' {
            Stop-Server
            Write-Output 'ARSIP-TA dihentikan.'
        }
        'uninstall' {
            if ($Shell) {
                if (-not (Confirm-Uninstall)) {
                    Write-Output 'Penghapusan dibatalkan.'
                    exit 0
                }
            }
            if ($Shortcut) {
                $linked = Get-ShortcutInstallRoot $Shortcut
                if ($linked) {
                    $script:ExtraRoots += $linked
                }
            }
            Enter-RuntimeLock
            try {
                Uninstall-App
                Write-Output 'ARSIP-TA sedang dihapus secara permanen. Isi berkas ditimpa, lalu folder aplikasi, registri, catatan, pintasan, dan sisa berkas dihapus.'
            } finally {
                Exit-RuntimeLock
            }
        }
        'selftest' {
            $installedKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ARSIP-TA'
            $verbKey = 'HKCU:\Software\Classes\lnkfile\shell\ARSIPTAUninstall'
            $lockFixture = Join-Path $env:TEMP ('arsip-ta-lock-' + [guid]::NewGuid().ToString('n'))
            $held = $null
            $outsider = $null
            try {
                $lockDir = Join-Path $lockFixture 'laravel-assets\runtime'
                New-Item -ItemType Directory -Force -Path $lockDir | Out-Null
                $lockerExe = Join-Path $lockDir 'cloudflared.exe'
                Copy-Item -LiteralPath (Join-Path $env:SystemRoot 'System32\cmd.exe') -Destination $lockerExe -Force
                Set-Content -LiteralPath (Join-Path $lockDir 'config.yml') -Value 'url: http://127.0.0.1:8765' -Encoding ASCII
                Set-Content -LiteralPath (Join-Path $lockDir 'tunnel.log') -Value 'tunnel' -Encoding ASCII
                $held = Start-Process -FilePath $lockerExe -ArgumentList @('/d', '/c', 'ping 127.0.0.1 -n 60 >nul') -WorkingDirectory $lockDir -WindowStyle Hidden -PassThru
                $outsider = Start-Process -FilePath (Join-Path $env:SystemRoot 'System32\cmd.exe') -ArgumentList @('/d', '/c', 'ping 127.0.0.1 -n 60 >nul') -WindowStyle Hidden -PassThru
                Start-Sleep -Milliseconds 700
                if (-not $held -or $held.HasExited) {
                    throw 'Proses pengunci uji tidak berjalan.'
                }
                if (-not $outsider -or $outsider.HasExited) {
                    throw 'Proses pembanding di luar folder tidak berjalan.'
                }
                Stop-InstalledProcesses @($lockFixture)
                $stillHeld = Get-Process -Id $held.Id -ErrorAction SilentlyContinue
                if ($stillHeld -and -not $stillHeld.HasExited) {
                    throw 'Proses yang mengunci folder pemasangan masih berjalan.'
                }
                $stillOutside = Get-Process -Id $outsider.Id -ErrorAction SilentlyContinue
                if (-not $stillOutside -or $stillOutside.HasExited) {
                    throw 'Proses di luar folder pemasangan terhenti.'
                }
                Remove-Item -LiteralPath $lockerExe -Force -ErrorAction Stop
                Remove-Item -LiteralPath (Join-Path $lockDir 'config.yml') -Force -ErrorAction Stop
                Remove-Item -LiteralPath (Join-Path $lockDir 'tunnel.log') -Force -ErrorAction Stop
                if (Test-Path -LiteralPath $lockerExe) {
                    throw 'Berkas cloudflared yang terkunci masih ada.'
                }
                Write-Output 'SELFTEST LOCK OK'
            } finally {
                if ($held -and -not $held.HasExited) {
                    Stop-Process -Id $held.Id -Force -ErrorAction SilentlyContinue
                }
                if ($outsider -and -not $outsider.HasExited) {
                    Stop-Process -Id $outsider.Id -Force -ErrorAction SilentlyContinue
                }
                if ($lockFixture -and (Test-Path -LiteralPath $lockFixture)) {
                    Remove-Item -LiteralPath $lockFixture -Recurse -Force -ErrorAction SilentlyContinue
                }
            }
            if ((Test-Path -LiteralPath $installedKey) -or (Test-Path -LiteralPath $verbKey)) {
                Write-Output 'SELFTEST SKIP sudah terpasang'
                break
            }
            $fixture = Join-Path $env:TEMP ('arsip-ta-selftest-' + [guid]::NewGuid().ToString('n'))
            $previousRoot = $Root
            $trace = 'HKCU:\Software\ARSIP-TA-SelfTestTrace'
            try {
                New-Item -ItemType Directory -Force -Path (Join-Path $fixture 'laravel-assets\scripts') | Out-Null
                Copy-Item -LiteralPath $PSCommandPath -Destination (Join-Path $fixture 'laravel-assets\scripts\runtime.ps1') -Force
                Set-Content -LiteralPath (Join-Path $fixture 'Pasang-ke-Perangkat.cmd') -Value '@echo off' -Encoding ASCII
                Set-Content -LiteralPath (Join-Path $fixture 'ARSIP-TA.vbs') -Value 'WScript.Quit 0' -Encoding ASCII
                $script:Root = $fixture
                Register-InstalledApp
                $shellLink = New-Object -ComObject WScript.Shell
                $lnk = Join-Path $fixture 'ARSIP-TA.lnk'
                $link = $shellLink.CreateShortcut($lnk)
                $link.TargetPath = Join-Path $fixture 'ARSIP-TA.vbs'
                $link.WorkingDirectory = $fixture
                $link.Save()
                $other = Join-Path $fixture 'Catatan.lnk'
                $note = $shellLink.CreateShortcut($other)
                $note.TargetPath = Join-Path $env:SystemRoot 'System32\notepad.exe'
                $note.Save()
                $app = New-Object -ComObject Shell.Application
                $folder = $app.NameSpace($fixture)
                $verbs = @($folder.ParseName('ARSIP-TA.lnk').Verbs() | ForEach-Object { $_.Name -replace '&', '' })
                $otherVerbs = @($folder.ParseName('Catatan.lnk').Verbs() | ForEach-Object { $_.Name -replace '&', '' })
                if ($verbs -notcontains 'Copot') {
                    throw 'Menu Copot tidak muncul pada pintasan ARSIP-TA.'
                }
                if ($otherVerbs -contains 'Copot') {
                    throw 'Menu Copot muncul pada pintasan lain.'
                }
                New-Item -Path $trace -Force | Out-Null
                New-ItemProperty -LiteralPath $trace -Name 'Keep' -Value 'aman' -PropertyType String -Force | Out-Null
                New-ItemProperty -LiteralPath $trace -Name 'Hit' -Value $fixture -PropertyType String -Force | Out-Null
                Remove-MatchingValues $trace @($fixture)
                $left = Get-ItemProperty -LiteralPath $trace
                if ($left.Hit) {
                    throw 'Jejak registri masih ada.'
                }
                if ([string]$left.Keep -ne 'aman') {
                    throw 'Nilai registri lain terhapus.'
                }
                $lists = Join-Path $fixture 'jumplists'
                New-Item -ItemType Directory -Force -Path $lists | Out-Null
                $ownFile = Join-Path $lists 'own.automaticDestinations-ms'
                $mixedFile = Join-Path $lists 'mixed.automaticDestinations-ms'
                $ownBytes = New-Object System.Collections.Generic.List[byte]
                $ownBytes.AddRange([byte[]][System.Text.Encoding]::Unicode.GetBytes((Join-Path $fixture 'ARSIP-TA.vbs')))
                $ownBytes.Add(0)
                $ownBytes.Add(0)
                [System.IO.File]::WriteAllBytes($ownFile, $ownBytes.ToArray())
                $mixedBytes = New-Object System.Collections.Generic.List[byte]
                $mixedBytes.AddRange([byte[]][System.Text.Encoding]::Unicode.GetBytes((Join-Path $fixture 'ARSIP-TA.vbs')))
                $mixedBytes.Add(0)
                $mixedBytes.Add(0)
                $mixedBytes.AddRange([byte[]][System.Text.Encoding]::Unicode.GetBytes('C:\Users\admin\Documents\catatan.txt'))
                $mixedBytes.Add(0)
                $mixedBytes.Add(0)
                [System.IO.File]::WriteAllBytes($mixedFile, $mixedBytes.ToArray())
                Remove-JumpLists @($fixture) $lists
                if (Test-Path -LiteralPath $ownFile) {
                    throw 'Jump list aplikasi masih ada.'
                }
                if (-not (Test-Path -LiteralPath $mixedFile)) {
                    throw 'Jump list lain terhapus.'
                }
                $recorded = [string](Get-ItemProperty -LiteralPath $installedKey).InstallLocation
                if ($recorded.TrimEnd('\') -ne $fixture.TrimEnd('\')) {
                    throw 'Lokasi pemasangan tidak tercatat.'
                }
                $verbItem = Get-Item -LiteralPath "$verbKey\command"
                $verbCommand = [string]$verbItem.GetValue('')
                if ($verbCommand -notlike "*$fixture*") {
                    throw 'Perintah Uninstall tidak mengarah ke folder pemasangan.'
                }
                if ($verbCommand -notlike '*-Shell*') {
                    throw 'Perintah Uninstall tanpa konfirmasi.'
                }
                Remove-AppRegistry @($fixture)
                if (Test-Path -LiteralPath $installedKey) {
                    throw 'Kunci uninstall masih ada.'
                }
                if (Test-Path -LiteralPath $verbKey) {
                    throw 'Menu Copot masih ada.'
                }
                if (Test-Path -LiteralPath 'HKCU:\Software\ARSIP-TA') {
                    throw 'Kunci aplikasi masih ada.'
                }
                Write-Output 'SELFTEST OK'
            } finally {
                $script:Root = $previousRoot
                if (Test-Path -LiteralPath $trace) {
                    Remove-Item -LiteralPath $trace -Recurse -Force -ErrorAction SilentlyContinue
                }
                if ($fixture) {
                    Remove-AppRegistry @($fixture)
                }
                if ($fixture -and (Test-Path -LiteralPath $fixture)) {
                    Remove-Item -LiteralPath $fixture -Recurse -Force -ErrorAction SilentlyContinue
                }
            }
        }
        'share' {
            Enter-RuntimeLock
            try {
                if (-not (Test-Online)) {
                    Write-Status @{
                        phase   = 'error'
                        message = 'Komputer sedang luring. Tautan internet tidak dibuat. Hanya petugas di komputer ini yang dapat membuka aplikasi.'
                    }
                    exit 2
                }
                if (-not (Test-PortListen (Get-CatalogPort))) {
                    Exit-RuntimeLock
                    Start-LocalServer $false
                    Enter-RuntimeLock
                }
                $php = Resolve-Php
                Start-TunnelProcess (Get-CatalogPort) $php
                Write-Output 'Tautan internet siap.'
            } finally {
                Exit-RuntimeLock
            }
        }
        'unshare' {
            Stop-Tunnel
            Write-Status @{
                phase   = 'ready'
                message = 'Tautan internet dihentikan. Aplikasi tetap berjalan di komputer ini.'
            }
            Write-Output 'Tautan internet dihentikan.'
        }
    }
} catch {
    Write-Status @{ phase = 'error'; message = $_.Exception.Message }
    Write-Error $_.Exception.Message
    exit 1
}
