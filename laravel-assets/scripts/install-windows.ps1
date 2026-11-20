# Windows step of Pasang-ke-Perangkat.cmd: copy ARSIP-TA, then prepare the server and the public QR.
param(
    [string]$Destination = ''
)

$ErrorActionPreference = 'Stop'
$OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$Source = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

function Test-IsAdmin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Start-Elevated([string]$Path) {
    $command = "-NoProfile -STA -ExecutionPolicy Bypass -File `"$PSCommandPath`" -Destination `"$Path`""
    try {
        return Start-Process -FilePath powershell.exe -Verb RunAs -ArgumentList $command -Wait -PassThru
    } catch {
        throw 'Izin admin ditolak. Pilih folder di dalam folder pengguna.'
    }
}

function Assert-Destination([string]$Path) {
    if ($Path -match '[<>|"?*]') {
        throw 'Nama folder mengandung karakter yang tidak diizinkan.'
    }

    $full = [System.IO.Path]::GetFullPath($Path).TrimEnd('\')
    $root = [System.IO.Path]::GetPathRoot($full).TrimEnd('\')
    if ($full -eq $root) {
        throw 'Pilih folder aplikasi, misalnya C:\ARSIP-TA, bukan akar kandar.'
    }

    $blocked = @(
        $env:SystemRoot,
        $env:ProgramFiles,
        ${env:ProgramFiles(x86)},
        $env:ProgramData
    ) | Where-Object { $_ } | ForEach-Object { $_.TrimEnd('\') }

    if ($blocked -contains $full) {
        throw 'Pilih folder khusus aplikasi, misalnya C:\ARSIP-TA atau C:\Program Files\ARSIP-TA.'
    }

    $windows = $env:SystemRoot.TrimEnd('\')
    if ($full.StartsWith($windows + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Folder Windows tidak dapat dipakai sebagai tempat pemasangan.'
    }

    $src = $Source.TrimEnd('\')
    if ($full.Equals($src, [System.StringComparison]::OrdinalIgnoreCase)) {
        return $full
    }
    if ($full.StartsWith($src + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Folder pemasangan tidak boleh berada di dalam paket yang sedang dibuka.'
    }
    if ($src.StartsWith($full + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Paket tidak boleh berada di dalam folder pemasangan.'
    }

    return $full
}

function Test-Writable([string]$Path) {
    try {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
        $probe = Join-Path $Path '.write-probe'
        [System.IO.File]::WriteAllText($probe, '1')
        Remove-Item -LiteralPath $probe -Force
        return $true
    } catch {
        return $false
    }
}

function Get-InstallDestination {
    try {
        Add-Type -AssemblyName System.Windows.Forms
        Add-Type -AssemblyName System.Drawing
    } catch {
        return Read-InstallDestination
    }

    $form = New-Object System.Windows.Forms.Form
    $form.Text = 'Pasang ARSIP-TA'
    $form.FormBorderStyle = 'FixedDialog'
    $form.StartPosition = 'CenterScreen'
    $form.MaximizeBox = $false
    $form.MinimizeBox = $false
    $form.ClientSize = New-Object System.Drawing.Size(640, 430)
    $form.Font = New-Object System.Drawing.Font('Segoe UI', 10)

    $intro = New-Object System.Windows.Forms.Label
    $intro.Text = 'Pilih tempat pemasangan. Langkah berikut memilih basis data lama atau baru, serta folder berkas ARSIP TUGAS AKHIR yang sudah ada atau baru.'
    $intro.SetBounds(20, 16, 600, 58)

    $hereLabel = $Source
    if ($hereLabel.Length -gt 62) {
        $hereLabel = $hereLabel.Substring(0, 28) + '...' + $hereLabel.Substring($hereLabel.Length - 28)
    }
    $userPrograms = Join-Path $env:LOCALAPPDATA 'Programs\ARSIP-TA'
    $options = @(
        @{ Label = "Folder ini: $hereLabel"; Path = $Source },
        @{ Label = 'C:\ARSIP-TA'; Path = 'C:\ARSIP-TA' },
        @{ Label = 'C:\Program Files\ARSIP-TA'; Path = 'C:\Program Files\ARSIP-TA' },
        @{ Label = 'Folder aplikasi pengguna (tanpa admin)'; Path = $userPrograms },
        @{ Label = 'Folder lain'; Path = '' }
    )

    $script:choiceButtons = @()
    $top = 84
    foreach ($option in $options) {
        $radio = New-Object System.Windows.Forms.RadioButton
        $radio.Text = $option.Label
        $radio.Tag = $option.Path
        $radio.SetBounds(28, $top, 580, 28)
        $script:choiceButtons += $radio
        $form.Controls.Add($radio)
        $top += 32
    }
    $preferHere = (Test-Path (Join-Path $Source 'laravel-assets\runtime\status.json')) -or (Test-Path (Join-Path $Source 'laravel-assets\runtime\php\php.exe'))
    $script:choiceButtons[$(if ($preferHere) { 0 } else { 1 })].Checked = $true

    $script:customPath = New-Object System.Windows.Forms.TextBox
    $script:customPath.SetBounds(48, $top, 420, 28)
    $browse = New-Object System.Windows.Forms.Button
    $browse.Text = 'Telusuri'
    $browse.SetBounds(480, ($top - 2), 130, 32)
    $browse.Add_Click({
        $dialog = New-Object System.Windows.Forms.FolderBrowserDialog
        $dialog.Description = 'Pilih folder pemasangan ARSIP-TA'
        if ($dialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $script:customPath.Text = $dialog.SelectedPath
            $script:choiceButtons[$script:choiceButtons.Count - 1].Checked = $true
        }
    })

    $install = New-Object System.Windows.Forms.Button
    $install.Text = 'Pasang'
    $install.SetBounds(360, 378, 120, 34)
    $cancel = New-Object System.Windows.Forms.Button
    $cancel.Text = 'Batal'
    $cancel.SetBounds(492, 378, 120, 34)
    $form.AcceptButton = $install
    $form.CancelButton = $cancel
    $script:installForm = $form

    $script:pickedPath = ''
    $install.Add_Click({
        $selected = $script:choiceButtons | Where-Object { $_.Checked } | Select-Object -First 1
        $path = ''
        if ($selected) {
            $path = [string]$selected.Tag
        }
        if ($path -eq '') {
            $path = $script:customPath.Text.Trim()
        }
        if (-not $path) {
            [System.Windows.Forms.MessageBox]::Show('Pilih folder pemasangan.')
            return
        }
        $script:pickedPath = $path
        $script:installForm.DialogResult = [System.Windows.Forms.DialogResult]::OK
        $script:installForm.Close()
    })

    $form.Controls.AddRange(@($intro, $script:customPath, $browse, $install, $cancel))
    $result = $form.ShowDialog()
    if ($result -ne [System.Windows.Forms.DialogResult]::OK -or -not $script:pickedPath) {
        return ''
    }
    return $script:pickedPath
}

function Read-InstallDestination {
    $userPrograms = Join-Path $env:LOCALAPPDATA 'Programs\ARSIP-TA'
    Write-Host "1. Folder ini: $Source"
    Write-Host '2. C:\ARSIP-TA'
    Write-Host '3. C:\Program Files\ARSIP-TA'
    Write-Host "4. $userPrograms"
    Write-Host '5. Ketik folder lain'
    $choice = Read-Host 'Pilihan'
    switch ($choice) {
        '1' { return $Source }
        '2' { return 'C:\ARSIP-TA' }
        '3' { return 'C:\Program Files\ARSIP-TA' }
        '4' { return $userPrograms }
        '5' { return (Read-Host 'Folder').Trim() }
        default { return '' }
    }
}

function Read-DataPlacement([bool]$HasDb, [string]$Documents) {
    Write-Host 'Basis data:'
    if ($HasDb) { Write-Host '1. Pakai basis data di folder pemasangan' }
    Write-Host '2. Buat basis data baru'
    Write-Host '3. Ketik lokasi berkas basis data yang sudah ada'
    $choice = Read-Host 'Pilihan'
    $dbMode = 'new'
    $dbFile = ''
    if ($choice -eq '1' -and $HasDb) {
        $dbMode = 'keep'
    } elseif ($choice -eq '3') {
        $dbMode = 'existing'
        $dbFile = (Read-Host 'Berkas basis data').Trim()
        if (-not $dbFile -or -not (Test-Path -LiteralPath $dbFile -PathType Leaf)) {
            throw 'Berkas basis data tidak ditemukan.'
        }
    }

    Write-Host 'Folder berkas:'
    Write-Host '1. Ikuti lokasi di basis data'
    Write-Host '2. Buat folder ARSIP TUGAS AKHIR baru'
    Write-Host '3. Pakai folder yang sudah ada'
    $folderChoice = Read-Host 'Pilihan'
    $archiveMode = 'keep'
    $archivePath = ''
    if ($folderChoice -eq '2') {
        $archiveMode = 'new'
        $archivePath = (Read-Host "Folder induk (kosong = bawaan, saran: $Documents)").Trim()
    } elseif ($folderChoice -eq '3') {
        $archiveMode = 'existing'
        $archivePath = (Read-Host 'Folder ARSIP TUGAS AKHIR').Trim()
        if (-not $archivePath -or -not (Test-Path -LiteralPath $archivePath -PathType Container)) {
            throw 'Folder berkas tidak ditemukan.'
        }
    }

    return @{
        DatabaseMode = $dbMode
        DatabaseFile = $dbFile
        ArchiveMode = $archiveMode
        ArchivePath = $archivePath
    }
}

function Get-DataPlacement([string]$TargetRoot) {
    $existingDb = Join-Path $TargetRoot 'database\database.sqlite'
    $hasDb = $false
    if (Test-Path -LiteralPath $existingDb) {
        $hasDb = (Get-Item -LiteralPath $existingDb).Length -gt 0
    }
    $documents = [Environment]::GetFolderPath('MyDocuments')
    if (-not $documents) {
        $documents = Join-Path $env:USERPROFILE 'Documents'
    }

    try {
        Add-Type -AssemblyName System.Windows.Forms
        Add-Type -AssemblyName System.Drawing
    } catch {
        return Read-DataPlacement $hasDb $documents
    }

    $form = New-Object System.Windows.Forms.Form
    $form.Text = 'Data ARSIP-TA'
    $form.FormBorderStyle = 'FixedDialog'
    $form.StartPosition = 'CenterScreen'
    $form.MaximizeBox = $false
    $form.MinimizeBox = $false
    $form.ClientSize = New-Object System.Drawing.Size(680, 560)
    $form.Font = New-Object System.Drawing.Font('Segoe UI', 10)

    $intro = New-Object System.Windows.Forms.Label
    $intro.Text = 'Pilih basis data dan folder berkas. Berkas basis data disalin ke folder aplikasi supaya dapat dipakai setelah pemasangan ulang. Untuk komputer lain, ekspor atau impor dari dalam aplikasi. Folder ARSIP TUGAS AKHIR dapat memakai folder yang sudah ada.'
    $intro.SetBounds(20, 12, 640, 64)

    $dbLabel = New-Object System.Windows.Forms.Label
    $dbLabel.Text = 'Basis data'
    $dbLabel.Font = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Bold)
    $dbLabel.SetBounds(20, 82, 640, 22)

    $script:dbKeep = New-Object System.Windows.Forms.RadioButton
    $script:dbKeep.Text = 'Pakai basis data di folder pemasangan'
    $script:dbKeep.SetBounds(28, 108, 620, 26)
    $script:dbKeep.Enabled = $hasDb
    $script:dbKeep.Checked = $hasDb

    $script:dbNew = New-Object System.Windows.Forms.RadioButton
    $script:dbNew.Text = 'Buat basis data baru (berkas lama disimpan sebagai cadangan)'
    $script:dbNew.SetBounds(28, 136, 620, 26)
    $script:dbNew.Checked = -not $hasDb

    $script:dbExisting = New-Object System.Windows.Forms.RadioButton
    $script:dbExisting.Text = 'Pilih berkas basis data yang sudah ada'
    $script:dbExisting.SetBounds(28, 164, 620, 26)

    $script:dbPath = New-Object System.Windows.Forms.TextBox
    $script:dbPath.SetBounds(48, 198, 460, 28)
    $script:dbBrowse = New-Object System.Windows.Forms.Button
    $script:dbBrowse.Text = 'Pilih berkas'
    $script:dbBrowse.SetBounds(518, 196, 130, 32)
    $script:dbBrowse.Add_Click({
        $dialog = New-Object System.Windows.Forms.OpenFileDialog
        $dialog.Title = 'Pilih berkas basis data'
        $dialog.Filter = 'Basis data SQLite (*.sqlite;*.db)|*.sqlite;*.db;*.sqlite3|Semua berkas (*.*)|*.*'
        if ($dialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $script:dbPath.Text = $dialog.FileName
            $script:dbExisting.Checked = $true
        }
    })

    $archiveLabel = New-Object System.Windows.Forms.Label
    $archiveLabel.Text = 'Folder berkas ARSIP TUGAS AKHIR'
    $archiveLabel.Font = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Bold)
    $archiveLabel.SetBounds(20, 244, 640, 22)

    $script:archiveKeep = New-Object System.Windows.Forms.RadioButton
    $script:archiveKeep.Text = 'Ikuti lokasi yang tersimpan di basis data'
    $script:archiveKeep.SetBounds(28, 270, 620, 26)
    $script:archiveKeep.Checked = $true

    $script:archiveNew = New-Object System.Windows.Forms.RadioButton
    $script:archiveNew.Text = 'Buat folder ARSIP TUGAS AKHIR baru'
    $script:archiveNew.SetBounds(28, 298, 620, 26)

    $script:archiveExisting = New-Object System.Windows.Forms.RadioButton
    $script:archiveExisting.Text = 'Pakai folder ARSIP TUGAS AKHIR yang sudah ada'
    $script:archiveExisting.SetBounds(28, 326, 620, 26)

    $hint = New-Object System.Windows.Forms.Label
    $hint.Text = "Buat baru: pilih folder induk, misalnya $documents. Kosong berarti folder bawaan aplikasi. Pakai yang sudah ada: pilih folder ARSIP TUGAS AKHIR atau folder induknya."
    $hint.SetBounds(28, 358, 620, 44)

    $script:archivePath = New-Object System.Windows.Forms.TextBox
    $script:archivePath.SetBounds(48, 408, 460, 28)
    $script:archiveBrowse = New-Object System.Windows.Forms.Button
    $script:archiveBrowse.Text = 'Pilih folder'
    $script:archiveBrowse.SetBounds(518, 406, 130, 32)
    $script:archiveBrowse.Add_Click({
        $dialog = New-Object System.Windows.Forms.FolderBrowserDialog
        $dialog.Description = 'Pilih folder berkas ARSIP TUGAS AKHIR'
        if ($dialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) {
            $script:archivePath.Text = $dialog.SelectedPath
            if (-not $script:archiveNew.Checked) {
                $script:archiveExisting.Checked = $true
            }
        }
    })

    $script:hasExistingDb = $hasDb

    $install = New-Object System.Windows.Forms.Button
    $install.Text = 'Lanjut'
    $install.SetBounds(400, 508, 120, 34)
    $cancel = New-Object System.Windows.Forms.Button
    $cancel.Text = 'Batal'
    $cancel.SetBounds(530, 508, 120, 34)
    $form.AcceptButton = $install
    $form.CancelButton = $cancel
    $script:dataForm = $form
    $script:dataOk = $false

    $install.Add_Click({
        $dbMode = 'keep'
        if ($script:dbNew.Checked) { $dbMode = 'new' }
        if ($script:dbExisting.Checked) { $dbMode = 'existing' }
        $archiveMode = 'keep'
        if ($script:archiveNew.Checked) { $archiveMode = 'new' }
        if ($script:archiveExisting.Checked) { $archiveMode = 'existing' }

        if ($dbMode -eq 'existing') {
            $file = $script:dbPath.Text.Trim()
            if (-not $file -or -not (Test-Path -LiteralPath $file -PathType Leaf)) {
                [System.Windows.Forms.MessageBox]::Show('Pilih berkas basis data yang sudah ada.')
                return
            }
        }
        if ($dbMode -eq 'new' -and $script:hasExistingDb) {
            $answer = [System.Windows.Forms.MessageBox]::Show(
                'Basis data di folder pemasangan akan disimpan sebagai cadangan, lalu berkas baru dibuat. Lanjutkan?',
                'Buat basis data baru',
                [System.Windows.Forms.MessageBoxButtons]::YesNo,
                [System.Windows.Forms.MessageBoxIcon]::Warning
            )
            if ($answer -ne [System.Windows.Forms.DialogResult]::Yes) {
                return
            }
        }
        if ($archiveMode -eq 'existing') {
            $folder = $script:archivePath.Text.Trim()
            if (-not $folder -or -not (Test-Path -LiteralPath $folder -PathType Container)) {
                [System.Windows.Forms.MessageBox]::Show('Pilih folder ARSIP TUGAS AKHIR yang sudah ada.')
                return
            }
        }

        $archiveValue = ''
        if ($archiveMode -ne 'keep') {
            $archiveValue = $script:archivePath.Text.Trim()
        }
        $databaseValue = ''
        if ($dbMode -eq 'existing') {
            $databaseValue = $script:dbPath.Text.Trim()
        }
        $script:pickedData = @{
            DatabaseMode = $dbMode
            DatabaseFile = $databaseValue
            ArchiveMode = $archiveMode
            ArchivePath = $archiveValue
        }
        $script:dataOk = $true
        $script:dataForm.DialogResult = [System.Windows.Forms.DialogResult]::OK
        $script:dataForm.Close()
    })

    $form.Controls.AddRange(@(
        $intro, $dbLabel, $script:dbKeep, $script:dbNew, $script:dbExisting, $script:dbPath, $script:dbBrowse,
        $archiveLabel, $script:archiveKeep, $script:archiveNew, $script:archiveExisting, $hint,
        $script:archivePath, $script:archiveBrowse, $install, $cancel
    ))
    $result = $form.ShowDialog()
    if ($result -ne [System.Windows.Forms.DialogResult]::OK -or -not $script:dataOk) {
        return $null
    }
    return $script:pickedData
}

function Write-InstallRequest([string]$TargetRoot, $Placement) {
    $runtime = Join-Path $TargetRoot 'laravel-assets\runtime'
    New-Item -ItemType Directory -Force -Path $runtime | Out-Null
    $payload = @{
        copy = $false
        adopt = $false
        database_mode = [string]$Placement.DatabaseMode
        database_file = [string]$Placement.DatabaseFile
        archive_mode = [string]$Placement.ArchiveMode
        archive_path = [string]$Placement.ArchivePath
    } | ConvertTo-Json -Compress
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText((Join-Path $runtime 'request.json'), $payload, $utf8)
}

function Copy-Preserved([string]$RelativePath, [string]$TargetRoot) {
    $path = Join-Path $TargetRoot $RelativePath
    if (-not (Test-Path -LiteralPath $path)) {
        return $null
    }
    $backup = Join-Path $env:TEMP ('arsip-keep-' + [guid]::NewGuid().ToString() + '-' + (Split-Path $RelativePath -Leaf))
    Copy-Item -LiteralPath $path -Destination $backup -Force
    return @{ Relative = $RelativePath; Backup = $backup }
}

function Restore-Preserved($Item, [string]$TargetRoot) {
    if (-not $Item) {
        return
    }
    $path = Join-Path $TargetRoot $Item.Relative
    $parent = Split-Path $path -Parent
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
    Copy-Item -LiteralPath $Item.Backup -Destination $path -Force
    Remove-Item -LiteralPath $Item.Backup -Force -ErrorAction SilentlyContinue
}

function Copy-Application([string]$TargetRoot) {
    if (-not (Test-Path -LiteralPath (Join-Path $Source 'vendor\autoload.php'))) {
        throw 'Folder vendor belum ada. Paket pemasangan tidak lengkap.'
    }

    $sourceFull = $Source.TrimEnd('\')
    if ($TargetRoot.Equals($sourceFull, [System.StringComparison]::OrdinalIgnoreCase)) {
        Write-Host 'Aplikasi sudah berada di folder ini. Penyalinan dilewati.'
        return
    }

    Write-Host "Menyalin aplikasi ke $TargetRoot"
    $kept = @(
        (Copy-Preserved 'database\database.sqlite' $TargetRoot),
        (Copy-Preserved '.env' $TargetRoot)
    )

    $excludeDirs = @(
        '.git', 'node_modules', '.cursor', '.idea', '.vscode', '.nova', '.zed', '.codex', '.phpunit.cache',
        'storage\logs', 'storage\framework\cache', 'storage\framework\sessions', 'storage\framework\views',
        'storage\pail', 'laravel-assets\runtime'
    ) | ForEach-Object { Join-Path $Source $_ }

    $copyArgs = @(
        $Source, $TargetRoot, '/E', '/R:1', '/W:1', '/NFL', '/NDL', '/NP', '/XD'
    ) + $excludeDirs + @('/XF', '.phpunit.result.cache', 'Thumbs.db')

    & robocopy @copyArgs | Out-Host
    if ($LASTEXITCODE -ge 8) {
        throw "Penyalinan gagal (kode $LASTEXITCODE)."
    }

    foreach ($relative in @(
        'laravel-assets\runtime\php',
        'laravel-assets\runtime\cloudflared.exe',
        'laravel-assets\runtime\vc_redist.x64.exe',
        'laravel-assets\runtime\gate.txt'
    )) {
        $from = Join-Path $Source $relative
        if (-not (Test-Path -LiteralPath $from)) {
            continue
        }
        $to = Join-Path $TargetRoot $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $to -Parent) | Out-Null
        Copy-Item -LiteralPath $from -Destination $to -Recurse -Force
    }

    foreach ($item in $kept) {
        Restore-Preserved $item $TargetRoot
    }
}

function Grant-AppWrite([string]$TargetRoot) {
    $programFiles = @($env:ProgramFiles, ${env:ProgramFiles(x86)}) | Where-Object { $_ }
    foreach ($root in $programFiles) {
        $prefix = $root.TrimEnd('\') + '\'
        if ($TargetRoot.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            & icacls $TargetRoot /grant '*S-1-5-32-545:(OI)(CI)M' /T /C | Out-Null
            return
        }
    }
}

function Ensure-RuntimeFolders([string]$TargetRoot) {
    @(
        'storage\logs',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        'storage\framework\testing',
        'storage\app\public',
        'storage\app\private',
        'bootstrap\cache',
        'laravel-assets\runtime'
    ) | ForEach-Object {
        New-Item -ItemType Directory -Force -Path (Join-Path $TargetRoot $_) | Out-Null
    }
}

function Stop-ExistingApp([string]$TargetRoot) {
    $launcher = Join-Path $TargetRoot 'laravel-assets\scripts\runtime.ps1'
    if (-not (Test-Path -LiteralPath $launcher)) {
        return
    }
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $launcher -Command stop
}

function Install-CopiedApp([string]$TargetRoot, $Placement) {
    Stop-ExistingApp $TargetRoot
    Copy-Application $TargetRoot
    Grant-AppWrite $TargetRoot
    Ensure-RuntimeFolders $TargetRoot
    Write-InstallRequest $TargetRoot $Placement
    $launcher = Join-Path $TargetRoot 'laravel-assets\scripts\runtime.ps1'
    Write-Host 'Menyiapkan PHP, basis data, pintasan, dan tautan internet.'
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $launcher -Command install
    if ($LASTEXITCODE -ne 0) {
        throw 'Pemasangan belum selesai. Baca pesan di atas, lalu jalankan Pasang-ke-Perangkat.cmd lagi.'
    }
    Write-Host ''
    Write-Host "ARSIP-TA terpasang di $TargetRoot"
    Write-Host 'Buka aplikasi dari pintasan di Desktop: ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ.'
    Write-Host 'Untuk menghapus seperti aplikasi lain, klik kanan pintasan itu, lalu pilih Copot.'
    Write-Host 'Copot-ARSIP-TA.cmd melakukan penghapusan yang sama.'
}

if (-not [Environment]::Is64BitOperatingSystem) {
    throw 'ARSIP-TA membutuhkan Windows 64-bit.'
}

$chosen = $Destination
if (-not $chosen) {
    $chosen = Get-InstallDestination
}
if (-not $chosen) {
    Write-Host 'Pemasangan dibatalkan.'
    exit 0
}

$chosen = Assert-Destination $chosen
if (-not (Test-Writable $chosen)) {
    if (Test-IsAdmin) {
        throw "Folder tidak dapat ditulis: $chosen"
    }
    Write-Host 'Meminta izin admin untuk menulis di folder itu.'
    $elevated = Start-Elevated $chosen
    exit $elevated.ExitCode
}

$shouldPause = $PSBoundParameters.ContainsKey('Destination')
try {
    $placement = Get-DataPlacement $chosen
    if (-not $placement) {
        Write-Host 'Pemasangan dibatalkan.'
        exit 0
    }
    Install-CopiedApp $chosen $placement
} catch {
    Write-Host ''
    Write-Host $_.Exception.Message
    if ($shouldPause) {
        Read-Host 'Tekan Enter untuk menutup'
    }
    exit 1
}

if ($shouldPause) {
    Read-Host 'Tekan Enter untuk menutup'
}
