# Build ARSIP-TA-Setup.zip. Install it with Pasang-ke-Perangkat.cmd.
param(
    [string]$OutputZip = ''
)

$ErrorActionPreference = 'Stop'
$Source = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

if (-not $OutputZip) {
    $OutputZip = Join-Path (Split-Path $Source -Parent) 'ARSIP-TA-Setup.zip'
}

if (-not (Test-Path -LiteralPath (Join-Path $Source 'vendor\autoload.php'))) {
    throw 'Folder vendor belum ada. Jalankan composer install sebelum membuat zip.'
}
if (-not (Test-Path -LiteralPath (Join-Path $Source 'Pasang-ke-Perangkat.cmd'))) {
    throw 'Pasang-ke-Perangkat.cmd tidak ditemukan.'
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$skipPrefixes = @(
    '.git/',
    'node_modules/',
    '.cursor/',
    '.idea/',
    '.vscode/',
    '.nova/',
    '.zed/',
    '.codex/',
    '.phpunit.cache/',
    'storage/logs/',
    'storage/framework/cache/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/pail/',
    'laravel-assets/runtime/'
)

function Test-Skipped([string]$RelativePath) {
    $rel = ($RelativePath -replace '\\', '/').TrimStart('/')
    $bundledRuntime = $rel -eq 'laravel-assets/runtime' -or
        $rel -eq 'laravel-assets/runtime/php' -or
        $rel -eq 'laravel-assets/runtime/cloudflared.exe' -or
        $rel -eq 'laravel-assets/runtime/vc_redist.x64.exe' -or
        $rel -eq 'laravel-assets/runtime/gate.txt' -or
        $rel -eq 'laravel-assets/runtime/.gitignore' -or
        $rel.StartsWith('laravel-assets/runtime/php/')
    if (-not $bundledRuntime) {
        foreach ($prefix in $script:skipPrefixes) {
            $dir = $prefix.TrimEnd('/')
            if ($rel -eq $dir -or $rel.StartsWith($prefix)) {
                return $true
            }
        }
    }
    $name = Split-Path $rel -Leaf
    if ($name -in @('.phpunit.result.cache', 'Thumbs.db', '.write-probe', '.env', 'auth.json')) {
        return $true
    }
    if ($name -like '.env.*' -or $name -like '*.sqlite-journal' -or $name -like '*.sqlite-wal' -or $name -like '*.sqlite-shm') {
        return $true
    }
    if ($rel -like 'bootstrap/cache/*.php') {
        return $true
    }
    return $false
}

if (Test-Path -LiteralPath $OutputZip) {
    Remove-Item -LiteralPath $OutputZip -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($OutputZip, [System.IO.Compression.ZipArchiveMode]::Create)
$count = 0

function Add-Tree([string]$Directory, [string]$Prefix) {
    Get-ChildItem -LiteralPath $Directory -Force | ForEach-Object {
        $relative = if ($Prefix) { "$Prefix/$($_.Name)" } else { $_.Name }
        if (Test-Skipped $relative) {
            return
        }
        if ($_.PSIsContainer) {
            Add-Tree $_.FullName $relative
            return
        }
        $entryName = 'ARSIP-TA/' + ($relative -replace '\\', '/')
        # ReadWrite share lets packaging continue while the local server holds SQLite open.
        $entry = $script:zip.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $outStream = $entry.Open()
        try {
            $inStream = [System.IO.File]::Open(
                $_.FullName,
                [System.IO.FileMode]::Open,
                [System.IO.FileAccess]::Read,
                [System.IO.FileShare]::ReadWrite
            )
            try {
                $inStream.CopyTo($outStream)
            } finally {
                $inStream.Dispose()
            }
        } finally {
            $outStream.Dispose()
        }
        $script:count++
        if (($script:count % 500) -eq 0) {
            Write-Host "$script:count berkas"
        }
    }
}

function New-AppKey {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    } finally {
        $rng.Dispose()
    }
    return 'base64:' + [Convert]::ToBase64String($bytes)
}

function Add-PortableEnv {
    $envFile = Join-Path $Source '.env'
    if (-not (Test-Path -LiteralPath $envFile)) {
        $envFile = Join-Path $Source '.env.example'
    }
    $clear = @{
        GROQ_API_KEY = ''
        AWS_ACCESS_KEY_ID = ''
        AWS_SECRET_ACCESS_KEY = ''
        DB_PASSWORD = ''
        MAIL_PASSWORD = ''
    }
    $set = @{
        APP_NAME = 'ARSIP-TA'
        APP_ENV = 'production'
        APP_DEBUG = 'false'
        APP_URL = 'http://127.0.0.1:8000'
        DB_CONNECTION = 'sqlite'
        DB_DATABASE = 'database/database.sqlite'
    }
    $seen = @{}
    $output = New-Object System.Collections.Generic.List[string]
    foreach ($line in [System.IO.File]::ReadAllLines($envFile)) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=') {
            $key = $Matches[1]
            $seen[$key] = $true
            if ($clear.ContainsKey($key)) {
                $output.Add("$key=$($clear[$key])")
                continue
            }
            if ($set.ContainsKey($key)) {
                $output.Add("$key=$($set[$key])")
                continue
            }
            if ($key -eq 'APP_KEY' -and $line -match '=\s*$') {
                $output.Add('APP_KEY=' + (New-AppKey))
                continue
            }
        }
        $output.Add($line)
    }
    if (-not $seen.ContainsKey('APP_KEY')) {
        $output.Add('APP_KEY=' + (New-AppKey))
    }
    foreach ($key in $set.Keys) {
        if (-not $seen.ContainsKey($key)) {
            $output.Add("$key=$($set[$key])")
        }
    }
    $text = ($output -join "`n") + "`n"
    $entry = $script:zip.CreateEntry('ARSIP-TA/.env', [System.IO.Compression.CompressionLevel]::Optimal)
    $stream = $entry.Open()
    try {
        $writer = New-Object System.IO.StreamWriter($stream, (New-Object System.Text.UTF8Encoding $false))
        $writer.Write($text)
        $writer.Dispose()
    } finally {
        $stream.Dispose()
    }
    $script:count++
}

try {
    Add-Tree $Source ''
    Add-PortableEnv
} finally {
    $zip.Dispose()
}

$info = Get-Item -LiteralPath $OutputZip
Write-Host ("Zip: {0}" -f $info.FullName)
Write-Host ("Ukuran: {0:N1} MB, {1} berkas" -f ($info.Length / 1MB), $count)

$desktop = Join-Path $env:USERPROFILE 'Desktop\ARSIP-TA-Setup.zip'
$sameFile = [System.IO.Path]::GetFullPath($OutputZip).Equals(
    [System.IO.Path]::GetFullPath($desktop),
    [System.StringComparison]::OrdinalIgnoreCase
)
if (-not $sameFile -and (Test-Path -LiteralPath (Split-Path $desktop -Parent))) {
    Copy-Item -LiteralPath $OutputZip -Destination $desktop -Force
    Write-Host "Salinan: $desktop"
}
