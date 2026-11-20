#!/bin/sh
# ARSIP-TA launcher for Linux, macOS, BSD, and other Unix systems.
# Windows uses runtime.ps1. Commands: ensure, start, stop, share, unshare, install, uninstall.
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
ASSETS="$ROOT/laravel-assets"
RUNTIME="$ASSETS/runtime"
CATALOG="$ASSETS/catalog.json"
STATUS="$RUNTIME/status.json"
LOCK="$RUNTIME/run.lock"
PHP_PATH_FILE="$RUNTIME/php.path"
COMMAND=${1:-}

case "$COMMAND" in
    ensure|start|stop|share|unshare|install|uninstall|__wipe) ;;
    *)
        echo 'Perintah peluncur tidak dikenal.' >&2
        exit 1
        ;;
esac

HOME=${HOME:-/tmp}

port_from_catalog() {
    port=$(sed -n 's/.*"port"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' "$CATALOG" | head -n 1)
    if [ -z "$port" ]; then
        echo 'Port pada laravel-assets/catalog.json tidak valid.' >&2
        exit 1
    fi
    if [ "$port" -lt 1024 ] || [ "$port" -gt 65535 ]; then
        echo 'Port pada laravel-assets/catalog.json tidak valid.' >&2
        exit 1
    fi
    printf '%s' "$port"
}

write_status() {
    patch=$1
    mkdir -p "$RUNTIME"
    if command -v php >/dev/null 2>&1; then
        php -r '
            $path = $argv[1];
            $patch = json_decode($argv[2], true);
            if (!is_array($patch)) { $patch = []; }
            $current = [];
            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) { $current = $decoded; }
            }
            $merged = array_merge($current, $patch);
            $merged["updated_at"] = date("c");
            if (isset($merged["message"])) {
                $merged["message"] = substr((string) $merged["message"], 0, 400);
            }
            file_put_contents($path, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        ' "$STATUS" "$patch"
        return
    fi
    printf '%s\n' "$patch" > "$STATUS"
}

enter_lock() {
    mkdir -p "$RUNTIME"
    if [ -f "$LOCK" ]; then
        owner=$(tr -cd '0-9' < "$LOCK" || true)
        if [ -n "$owner" ] && kill -0 "$owner" 2>/dev/null; then
            echo 'Pemasangan sedang berjalan.'
            exit 0
        fi
    fi
    printf '%s\n' "$$" > "$LOCK"
}

exit_lock() {
    rm -f "$LOCK"
}

php_ready() {
    exe=$1
    if [ -z "$exe" ] || [ ! -x "$exe" ]; then
        return 1
    fi
    version=$("$exe" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null || true)
    major=$(printf '%s' "$version" | cut -d. -f1)
    minor=$(printf '%s' "$version" | cut -d. -f2)
    if [ -z "$major" ] || [ -z "$minor" ]; then
        return 1
    fi
    if [ "$major" -lt 8 ] || { [ "$major" -eq 8 ] && [ "$minor" -lt 4 ]; }; then
        return 1
    fi
    modules=$("$exe" -m 2>/dev/null || true)
    for name in openssl mbstring pdo_sqlite curl fileinfo; do
        printf '%s\n' "$modules" | grep -qx "$name" || return 1
    done
    return 0
}

machine_arch() {
    machine=$(uname -m)
    case "$machine" in
        x86_64|amd64) printf '%s' 'x86_64' ;;
        aarch64|arm64) printf '%s' 'aarch64' ;;
        armv7*|armv6*) printf '%s' 'arm' ;;
        i386|i686) printf '%s' 'i386' ;;
        *) printf '%s' "$machine" ;;
    esac
}

find_installed_php() {
    if command -v php >/dev/null 2>&1; then
        found=$(command -v php)
        if php_ready "$found"; then
            printf '%s' "$found"
            return 0
        fi
    fi
    for candidate in \
        /usr/bin/php /usr/local/bin/php /opt/homebrew/bin/php \
        /opt/local/bin/php /usr/local/php/bin/php
    do
        if php_ready "$candidate"; then
            printf '%s' "$candidate"
            return 0
        fi
    done
    return 1
}

install_php_packages() {
    if [ -n "${PREFIX:-}" ] && [ -x "${PREFIX}/bin/pkg" ]; then
        "${PREFIX}/bin/pkg" install -y php php-sqlite php-curl php-mbstring php-xml php-openssl || true
        return 0
    fi
    run_root() {
        if [ "$(id -u)" -eq 0 ]; then
            "$@"
        elif command -v sudo >/dev/null 2>&1; then
            sudo -n "$@"
        else
            return 1
        fi
    }
    if command -v brew >/dev/null 2>&1; then
        brew install php || true
        return 0
    fi
    if command -v apt-get >/dev/null 2>&1; then
        run_root apt-get update || true
        run_root apt-get install -y php-cli php-mbstring php-sqlite3 php-curl php-xml php-zip || true
    elif command -v dnf >/dev/null 2>&1; then
        run_root dnf install -y php-cli php-mbstring php-pdo php-xml php-process php-zip || true
    elif command -v yum >/dev/null 2>&1; then
        run_root yum install -y php-cli php-mbstring php-pdo php-xml php-process || true
    elif command -v pacman >/dev/null 2>&1; then
        run_root pacman -Sy --noconfirm php php-sqlite || true
    elif command -v apk >/dev/null 2>&1; then
        run_root apk add --no-cache php php-sqlite3 php-curl php-mbstring php-xml php-openssl php-phar php-zip || true
    elif command -v zypper >/dev/null 2>&1; then
        run_root zypper --non-interactive install php-cli php-mbstring php-sqlite php-curl php-xml php-zip || true
    elif command -v pkg >/dev/null 2>&1; then
        run_root pkg install -y php84 php84-mbstring php84-sqlite3 php84-curl php84-xml || true
    elif command -v pkg_add >/dev/null 2>&1; then
        run_root pkg_add php php-sqlite3 php-curl php-mbstring || true
    fi
}

install_portable_php() {
    write_status '{"phase":"php","message":"Mengunduh PHP ke folder aplikasi."}'
    os=$(uname -s)
    arch=$(machine_arch)
    case "$os" in
        Linux) platform='linux' ;;
        Darwin) platform='macos' ;;
        *) return 1 ;;
    esac
    case "$arch" in
        x86_64|aarch64) ;;
        *) return 1 ;;
    esac
    json=$(curl -fsSL --max-time 30 'https://www.php.net/releases/index.php?json&version=8.4' || true)
    version=$(printf '%s' "$json" | sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([0-9][0-9.]*\)".*/\1/p' | head -n 1)
    if [ -z "$version" ]; then
        return 1
    fi
    url="https://dl.static-php.dev/static-php-cli/common/php-${version}-cli-${platform}-${arch}.tar.gz"
    archive="$RUNTIME/php-download.tar.gz"
    staging="$RUNTIME/php-staging"
    curl -fL --max-time 180 "$url" -o "$archive" || return 1
    rm -rf "$staging"
    mkdir -p "$staging" "$RUNTIME/php"
    tar -xzf "$archive" -C "$staging"
    rm -f "$archive"
    binary=$(find "$staging" -type f -name php | head -n 1 || true)
    if [ -z "$binary" ]; then
        rm -rf "$staging"
        return 1
    fi
    cp "$binary" "$RUNTIME/php/php"
    chmod +x "$RUNTIME/php/php"
    rm -rf "$staging"
    if php_ready "$RUNTIME/php/php"; then
        printf '%s' "$RUNTIME/php/php"
        return 0
    fi
    return 1
}

resolve_php() {
    if php_ready "$RUNTIME/php/php"; then
        printf '%s\n' "$RUNTIME/php/php" > "$PHP_PATH_FILE"
        printf '%s' "$RUNTIME/php/php"
        return 0
    fi
    if [ -f "$PHP_PATH_FILE" ]; then
        stored=$(tr -d '\r\n' < "$PHP_PATH_FILE")
        if php_ready "$stored"; then
            printf '%s' "$stored"
            return 0
        fi
    fi
    if downloaded=$(install_portable_php 2>/dev/null); then
        if php_ready "$downloaded"; then
            printf '%s\n' "$downloaded" > "$PHP_PATH_FILE"
            printf '%s' "$downloaded"
            return 0
        fi
    fi
    install_php_packages || true
    hash -r 2>/dev/null || true
    if found=$(find_installed_php); then
        printf '%s\n' "$found" > "$PHP_PATH_FILE"
        write_status '{"message":"PHP bawaan belum terunduh. Sementara memakai PHP yang sudah ada di komputer ini."}'
        printf '%s' "$found"
        return 0
    fi
    echo 'PHP 8.4 atau lebih baru dengan pdo_sqlite, mbstring, curl, openssl, dan fileinfo belum tersedia.' >&2
    exit 1
}

install_cloudflared() {
    dest="$RUNTIME/cloudflared"
    if [ -x "$dest" ]; then
        printf '%s' "$dest"
        return 0
    fi
    write_status '{"phase":"tunnel-binary","message":"Mengunduh cloudflared ke folder aplikasi."}'
    os=$(uname -s)
    arch=$(machine_arch)
    name=''
    packed=0
    case "$os:$arch" in
        Linux:x86_64) name='cloudflared-linux-amd64' ;;
        Linux:aarch64) name='cloudflared-linux-arm64' ;;
        Linux:arm) name='cloudflared-linux-arm' ;;
        Linux:i386) name='cloudflared-linux-386' ;;
        Darwin:x86_64) name='cloudflared-darwin-amd64.tgz'; packed=1 ;;
        Darwin:aarch64) name='cloudflared-darwin-arm64.tgz'; packed=1 ;;
        FreeBSD:x86_64) name='cloudflared-freebsd-amd64' ;;
        *) return 1 ;;
    esac
    url="https://github.com/cloudflare/cloudflared/releases/latest/download/${name}"
    mkdir -p "$RUNTIME"
    if [ "$packed" -eq 1 ]; then
        curl -fL --max-time 180 "$url" -o "$RUNTIME/cloudflared.tgz" || return 1
        tar -xzf "$RUNTIME/cloudflared.tgz" -C "$RUNTIME"
        rm -f "$RUNTIME/cloudflared.tgz"
    else
        curl -fL --max-time 180 "$url" -o "$dest" || return 1
    fi
    chmod +x "$dest" 2>/dev/null || true
    if [ ! -x "$dest" ]; then
        return 1
    fi
    printf '%s' "$dest"
}

xdg_data_home() {
    printf '%s\n' "${XDG_DATA_HOME:-$HOME/.local/share}"
}

user_desktop() {
    if [ -n "${XDG_DESKTOP_DIR:-}" ]; then
        printf '%s\n' "$XDG_DESKTOP_DIR"
        return
    fi
    config=${XDG_CONFIG_HOME:-$HOME/.config}/user-dirs.dirs
    if [ -f "$config" ]; then
        line=$(grep '^XDG_DESKTOP_DIR=' "$config" | head -n 1 | cut -d= -f2- | tr -d '"' || true)
        case "$line" in
            '$HOME'*)
                line=$HOME${line#'$HOME'}
                ;;
        esac
        if [ -n "$line" ]; then
            printf '%s\n' "$line"
            return
        fi
    fi
    printf '%s\n' "$HOME/Desktop"
}

record_install_path() {
    dir=${XDG_CONFIG_HOME:-$HOME/.config}/arsip-ta
    mkdir -p "$dir"
    printf '%s\n' "$ROOT" > "$dir/install.path"
}

confirm_uninstall() {
    message='Copot pemasangan menghapus ARSIP-TA dari komputer ini.

Yang dihapus: program, basis data di dalam folder aplikasi, catatan, pintasan, dan sisa berkas pemasangan.
Isi berkas ditimpa sebelum dihapus.
Folder arsip yang berada di luar folder aplikasi tidak dihapus.'
    if command -v zenity >/dev/null 2>&1; then
        if zenity --question --title='Copot ARSIP-TA' --text="$message"; then
            return 0
        fi
        return 1
    fi
    if command -v kdialog >/dev/null 2>&1; then
        if kdialog --title 'Copot ARSIP-TA' --yesno "$message"; then
            return 0
        fi
        return 1
    fi
    if command -v yad >/dev/null 2>&1; then
        if yad --title='Copot ARSIP-TA' --button=Ya:0 --button=Tidak:1 --text="$message"; then
            return 0
        fi
        return 1
    fi
    if command -v qarma >/dev/null 2>&1; then
        if qarma --question --title='Copot ARSIP-TA' --text="$message"; then
            return 0
        fi
        return 1
    fi
    if command -v osascript >/dev/null 2>&1; then
        answer=$(osascript <<'APPLESCRIPT'
display dialog "Copot pemasangan menghapus ARSIP-TA dari komputer ini." & return & return & "Folder arsip di luar folder aplikasi tidak dihapus." buttons {"Tidak", "Ya"} default button "Tidak" with icon caution
APPLESCRIPT
        ) || answer=''
        case "$answer" in
            *Ya*) return 0 ;;
            *) return 1 ;;
        esac
    fi
    if [ -t 0 ]; then
        printf 'Ketik HAPUS untuk melanjutkan: '
        read -r confirm || confirm=''
        if [ "$confirm" = 'HAPUS' ]; then
            return 0
        fi
        return 1
    fi
    if [ "${ARSIP_TA_UNINSTALL_TERMINAL:-}" = 1 ]; then
        echo 'Penghapusan dibatalkan. Jalankan sh copot.sh.' >&2
        return 1
    fi
    for term in x-terminal-emulator gnome-terminal konsole xfce4-terminal mate-terminal xterm; do
        if command -v "$term" >/dev/null 2>&1; then
            case "$term" in
                gnome-terminal)
                    gnome-terminal -- env ARSIP_TA_UNINSTALL_TERMINAL=1 sh "$0" uninstall shell
                    ;;
                konsole)
                    konsole -e env ARSIP_TA_UNINSTALL_TERMINAL=1 sh "$0" uninstall shell
                    ;;
                *)
                    "$term" -e env ARSIP_TA_UNINSTALL_TERMINAL=1 sh "$0" uninstall shell
                    ;;
            esac
            return 2
        fi
    done
    echo 'Penghapusan dibatalkan. Jalankan sh copot.sh.' >&2
    return 1
}

write_shortcut_file() {
    path=$1
    title=$2
    target=$3
    mkdir -p "$(dirname "$path")"
    if printf '%s' "$path" | grep -q '\.desktop$'; then
        if [ "$title" = 'ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ' ]; then
            cat > "$path" <<EOF
[Desktop Entry]
Type=Application
Name=$title
Exec=sh "$target"
Path=$ROOT
Terminal=false
Categories=Office;
StartupNotify=false
Actions=Uninstall;

[Desktop Action Uninstall]
Name=Copot
Icon=user-trash
Exec=sh "$ROOT/laravel-assets/scripts/runtime.sh" uninstall shell
Terminal=false
EOF
        else
            cat > "$path" <<EOF
[Desktop Entry]
Type=Application
Name=$title
Exec=sh "$target"
Path=$ROOT
Terminal=false
Categories=Office;
StartupNotify=false
EOF
        fi
        return 0
    fi
    cat > "$path" <<EOF
#!/bin/sh
exec sh "$target"
EOF
    chmod +x "$path"
}

write_macos_app() {
    dest=$1
    [ "$(uname -s)" = 'Darwin' ] || return 0
    [ -n "$dest" ] || return 0
    app="$dest/ARSIP-TA.app"
    mkdir -p "$app/Contents/MacOS"
    cat > "$app/Contents/Info.plist" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>CFBundleName</key>
  <string>ARSIP-TA</string>
  <key>CFBundleDisplayName</key>
  <string>ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ</string>
  <key>CFBundleIdentifier</key>
  <string>id.arsip.ta</string>
  <key>CFBundleExecutable</key>
  <string>ARSIP-TA</string>
  <key>CFBundlePackageType</key>
  <string>APPL</string>
  <key>CFBundleVersion</key>
  <string>1.0.0</string>
  <key>CFBundleShortVersionString</key>
  <string>1.0.0</string>
</dict>
</plist>
EOF
    cat > "$app/Contents/MacOS/ARSIP-TA" <<EOF
#!/bin/sh
exec sh "$ROOT/mulai.sh"
EOF
    chmod +x "$app/Contents/MacOS/ARSIP-TA"
}

write_macos_service() {
    [ "$(uname -s)" = 'Darwin' ] || return 0
    rm -rf "$HOME/Library/Services/Uninstall ARSIP-TA.workflow"
    service="$HOME/Library/Services/Copot ARSIP-TA.workflow"
    mkdir -p "$service/Contents"
    cat > "$service/Contents/Info.plist" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>NSServices</key>
  <array>
    <dict>
      <key>NSMenuItem</key>
      <dict>
        <key>default</key>
        <string>Copot ARSIP-TA</string>
      </dict>
      <key>NSMessage</key>
      <string>runWorkflowAsService</string>
      <key>NSRequiredContext</key>
      <dict>
        <key>NSApplicationIdentifier</key>
        <string>com.apple.finder</string>
      </dict>
      <key>NSSendFileTypes</key>
      <array>
        <string>public.item</string>
      </array>
    </dict>
  </array>
</dict>
</plist>
EOF
    command_xml=$(printf '%s' "ok=0
for item in \"\$@\"; do
  name=\$(basename \"\$item\")
  case \"\$name\" in
    ARSIP-TA|ARSIP-TA.app|ARSIP-TA.command|ARSIP-TA.desktop) ok=1 ;;
  esac
done
if [ \"\$ok\" != 1 ]; then
  exit 0
fi
exec sh \"$ROOT/laravel-assets/scripts/runtime.sh\" uninstall shell" | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g')
    cat > "$service/Contents/document.wflow" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>AMApplicationBuild</key>
  <string>523</string>
  <key>AMApplicationVersion</key>
  <string>2.10</string>
  <key>AMDocumentVersion</key>
  <string>2</string>
  <key>actions</key>
  <array>
    <dict>
      <key>action</key>
      <dict>
        <key>AMAccepts</key>
        <dict>
          <key>Container</key>
          <string>List</string>
          <key>Optional</key>
          <true/>
          <key>Types</key>
          <array>
            <string>com.apple.cocoa.string</string>
          </array>
        </dict>
        <key>ActionBundlePath</key>
        <string>/System/Library/Automator/Run Shell Script.action</string>
        <key>ActionName</key>
        <string>Run Shell Script</string>
        <key>ActionParameters</key>
        <dict>
          <key>COMMAND_STRING</key>
          <string>$command_xml</string>
          <key>CheckedForUserDefaultShell</key>
          <true/>
          <key>inputMethod</key>
          <integer>1</integer>
          <key>shell</key>
          <string>/bin/sh</string>
          <key>source</key>
          <string></string>
        </dict>
        <key>AMProvides</key>
        <dict>
          <key>Container</key>
          <string>List</string>
          <key>Types</key>
          <array>
            <string>com.apple.cocoa.string</string>
          </array>
        </dict>
      </dict>
    </dict>
  </array>
  <key>connectors</key>
  <dict/>
  <key>workflowMetaData</key>
  <dict>
    <key>serviceApplicationBundleID</key>
    <string>com.apple.finder</string>
    <key>serviceInputTypeIdentifier</key>
    <string>com.apple.Automator.fileSystemObject</string>
    <key>serviceProcessesInput</key>
    <true/>
    <key>workflowTypeIdentifier</key>
    <string>com.apple.Automator.servicesMenu</string>
  </dict>
</dict>
</plist>
EOF
}

install_shortcuts() {
    write_status '{"phase":"shortcuts","message":"Membuat pintasan aplikasi."}'
    data_home=$(xdg_data_home)
    desktop=$(user_desktop)
    title='ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ'
    mkdir -p "$data_home/applications" "$HOME/Applications" "$desktop" 2>/dev/null || true
    rm -f \
        "$data_home/applications/Hentikan ARSIP-TA.desktop" \
        "$data_home/applications/Copot ARSIP-TA.desktop" \
        "$desktop/Hentikan ARSIP-TA.desktop" \
        "$desktop/Copot ARSIP-TA.desktop" \
        "$desktop/ARSIP-TA.command" \
        "$HOME/Applications/Hentikan ARSIP-TA.command" \
        "$HOME/Applications/Copot ARSIP-TA.command" \
        "$desktop/Kode-QR-Akses-ARSIP-TA.pdf" \
        "$desktop/Kode-QR-Akses-ARSIP-TA.png" \
        "$HOME/Desktop/Kode-QR-Akses-ARSIP-TA.pdf" \
        "$HOME/Desktop/Kode-QR-Akses-ARSIP-TA.png"
    write_shortcut_file "$data_home/applications/ARSIP-TA.desktop" "$title" "$ROOT/mulai.sh"
    write_shortcut_file "$desktop/ARSIP-TA.desktop" "$title" "$ROOT/mulai.sh"
    if [ "$(uname -s)" = 'Darwin' ]; then
        write_shortcut_file "$HOME/Applications/ARSIP-TA.command" "$title" "$ROOT/mulai.sh"
        write_macos_app "$HOME/Applications"
    fi
    write_macos_service
    if command -v update-desktop-database >/dev/null 2>&1; then
        update-desktop-database "$data_home/applications" >/dev/null 2>&1 || true
    fi
    record_install_path
    write_status '{"shortcuts":true}'
}

ensure_vendor() {
    if [ -f "$ROOT/vendor/autoload.php" ]; then
        return 0
    fi
    if ! command -v composer >/dev/null 2>&1; then
        echo 'Folder vendor belum ada dan Composer tidak ditemukan.' >&2
        exit 1
    fi
    write_status '{"phase":"php","message":"Memasang pustaka Composer."}'
    composer install --no-dev --prefer-dist --no-interaction --working-dir="$ROOT"
}

prepare_database() {
    copy=$1
    adopt=$2
    request="$RUNTIME/request.json"
    if [ -f "$request" ] && command -v php >/dev/null 2>&1; then
        flags=$(php -r '
            $data = json_decode((string) file_get_contents($argv[1]), true);
            if (!is_array($data)) { $data = []; }
            $copy = array_key_exists("copy", $data) && $data["copy"] === false ? "0" : "1";
            $adopt = !empty($data["adopt"]) ? "1" : "0";
            if ($argv[2] === "schema") { $copy = "0"; $adopt = "0"; }
            echo $copy, " ", $adopt;
        ' "$request" "$3")
        copy=$(printf '%s' "$flags" | cut -d' ' -f1)
        adopt=$(printf '%s' "$flags" | cut -d' ' -f2)
    fi
    (
        cd "$ROOT" || exit 1
        "$PHP_BIN" artisan local-server:prepare --copy="$copy" --adopt="$adopt" --placement=1
    )
}

online() {
    curl -fsS --max-time 4 'https://www.cloudflare.com/cdn-cgi/trace' >/dev/null 2>&1
}

port_open() {
    port=$1
    if command -v php >/dev/null 2>&1; then
        if php -r 'exit(@fsockopen("127.0.0.1", (int) $argv[1]) ? 0 : 1);' "$port"; then
            return 0
        fi
    fi
    return 1
}

open_url() {
    url=$1
    if command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$url" >/dev/null 2>&1 || true
    elif command -v open >/dev/null 2>&1; then
        open "$url" >/dev/null 2>&1 || true
    fi
}

start_server() {
    open_window=$1
    port=$(port_from_catalog)
    PHP_BIN=$(resolve_php)
    prepare_database 0 0 schema
    bind='127.0.0.1'
    is_online=0
    if online; then
        is_online=1
    fi
    if port_open "$port"; then
        write_status "{\"phase\":\"ready\",\"bind\":\"$bind\",\"online\":$is_online,\"lan_urls\":\"\",\"local_url\":\"http://127.0.0.1:$port\",\"port\":$port}"
    else
        write_status '{"phase":"server","message":"Menjalankan server aplikasi."}'
        mkdir -p "$RUNTIME"
        (
            cd "$ROOT" || exit 1
            export DB_CONNECTION=sqlite
            export DB_DATABASE="$ROOT/database/database.sqlite"
            export APP_DEBUG=false
            export PHP_CLI_SERVER_WORKERS=4
            export PHP_INI_SCAN_DIR=":$ROOT/laravel-assets/php"
            nohup "$PHP_BIN" -d variables_order=EGPCS artisan serve --host="$bind" --port="$port" --no-reload >>"$RUNTIME/server.out.log" 2>>"$RUNTIME/server.err.log" &
            printf '%s\n' "$!" > "$RUNTIME/server.pid"
        )
        ready=0
        i=0
        while [ "$i" -lt 40 ]; do
        if port_open "$port"; then
            ready=1
            break
        fi
        i=$((i + 1))
        sleep 1
    done
        if [ "$ready" -ne 1 ]; then
            echo 'Peladen aplikasi tidak dapat dimulai. Lihat laravel-assets/runtime/server.err.log.' >&2
            exit 1
        fi
        server_pid=$(tr -d '\r\n' < "$RUNTIME/server.pid")
        write_status "{\"phase\":\"ready\",\"server_pid\":$server_pid,\"bind\":\"$bind\",\"online\":$is_online,\"lan_urls\":\"\",\"local_url\":\"http://127.0.0.1:$port\",\"port\":$port,\"php\":\"$PHP_BIN\"}"
    fi
    if [ "$is_online" -eq 1 ]; then
        start_tunnel "$port" || write_status '{"phase":"ready","message":"Peladen berjalan di komputer ini. Simpan satu tautan Cloudflare petugas, lalu klik Bagikan."}'
    fi
    if [ "$open_window" -eq 1 ]; then
        open_url "http://127.0.0.1:$port"
    fi
}

remove_tunnel_secrets() {
    rm -f "$RUNTIME/tunnel-token" "$RUNTIME/tunnel.yml" "$RUNTIME/tunnel.credentials.json"
}

read_static_link() {
    token_file="$RUNTIME/tunnel-token"
    remove_tunnel_secrets
    if [ -z "${PHP_BIN:-}" ] || [ ! -f "$PHP_BIN" ]; then
        return 1
    fi
    status=1
    text=$(
        cd "$ROOT" || exit 1
        "$PHP_BIN" artisan cloudflare:prepare-tunnel 2>/dev/null
    ) && status=0 || status=$?
    if [ "$status" -eq 2 ]; then
        remove_tunnel_secrets
        return 1
    fi
    link=$(printf '%s\n' "$text" | sed -n 's#.*\(https://[A-Za-z0-9.-][A-Za-z0-9.-]*\).*#\1#p' | head -n 1)
    case "$link" in
        *[!A-Za-z0-9:/.-]*)
            link=''
            ;;
    esac
    if [ "$status" -ne 0 ] || [ -z "$link" ]; then
        remove_tunnel_secrets
        return 2
    fi
    if [ ! -f "$token_file" ]; then
        printf '%s' "$link"
        return 3
    fi
    printf '%s' "$link"
}

static_tunnel_current() {
    want=$1
    [ -f "$STATUS" ] || return 1
    [ -f "$RUNTIME/tunnel.pid" ] || return 1
    pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid" || true)
    [ -n "$pid" ] || return 1
    if ! kill -0 "$pid" 2>/dev/null; then
        return 1
    fi
    if "$PHP_BIN" -r '
        $status = json_decode((string) file_get_contents($argv[1]), true);
        $have = is_array($status) ? rtrim((string) ($status["public_url"] ?? ""), "/") : "";
        exit($have === $argv[2] ? 0 : 1);
    ' "$STATUS" "$want"; then
        return 0
    fi
    return 1
}

wait_tunnel_log() {
    log=$1
    i=0
    while [ "$i" -lt 45 ]; do
        if [ -f "$log" ] && grep -i -q 'Registered tunnel connection' "$log"; then
            return 0
        fi
        tunnel_pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid" 2>/dev/null || true)
        if [ -z "$tunnel_pid" ] || ! kill -0 "$tunnel_pid" 2>/dev/null; then
            return 1
        fi
        i=$((i + 1))
        sleep 1
    done
    return 1
}

start_named_tunnel() {
    link=$1
    token_file="$RUNTIME/tunnel-token"
    if [ ! -f "$token_file" ]; then
        return 1
    fi
    token=$(tr -d '\r\n' < "$token_file")
    rm -f "$token_file"
    if [ -z "$token" ]; then
        return 1
    fi
    binary=$(install_cloudflared) || {
        token=''
        return 1
    }
    log="$RUNTIME/tunnel.log"
    rm -f "$log"
    write_status '{"phase":"sharing","message":"Menyambungkan tautan Cloudflare yang disimpan."}'
    config="$RUNTIME/tunnel.yml"
    creds="$RUNTIME/tunnel.credentials.json"
    if [ -f "$config" ] && [ -f "$creds" ]; then
        unset TUNNEL_TOKEN
        nohup "$binary" tunnel --config "$config" --protocol http2 --no-autoupdate run >>"$log" 2>&1 &
        printf '%s\n' "$!" > "$RUNTIME/tunnel.pid"
        if wait_tunnel_log "$log"; then
            token=''
            tunnel_pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid")
            write_status "{\"phase\":\"ready\",\"tunnel_pid\":$tunnel_pid,\"public_url\":\"$link\",\"message\":\"Tautan internet statis siap. QR memakai tautan yang disimpan.\"}"
            (
                cd "$ROOT" || exit 1
                "$PHP_BIN" artisan access-qr:publish >/dev/null 2>&1 || true
            )
            return 0
        fi
        stop_pid_file "$RUNTIME/tunnel.pid"
        rm -f "$log"
    fi
    TUNNEL_TOKEN="$token" nohup "$binary" tunnel --protocol http2 --no-autoupdate run >>"$log" 2>&1 &
    printf '%s\n' "$!" > "$RUNTIME/tunnel.pid"
    token=''
    unset TUNNEL_TOKEN
    if ! wait_tunnel_log "$log"; then
        stop_pid_file "$RUNTIME/tunnel.pid"
        return 1
    fi
    tunnel_pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid")
    write_status "{\"phase\":\"ready\",\"tunnel_pid\":$tunnel_pid,\"public_url\":\"$link\",\"message\":\"Tautan internet statis siap. QR memakai tautan yang disimpan.\"}"
    (
        cd "$ROOT" || exit 1
        "$PHP_BIN" artisan access-qr:publish >/dev/null 2>&1 || true
    )
}

publish_worker_origin() {
    front=$1
    origin=$2
    gate_file="$RUNTIME/gate.txt"
    if [ ! -f "$gate_file" ]; then
        echo 'Kunci pintu QR belum ada di laravel-assets/runtime/gate.txt.' >&2
        return 1
    fi
    gate=$(tr -d '\r\n' < "$gate_file")
    if [ "${#gate}" -lt 32 ]; then
        echo 'Kunci pintu QR tidak valid.' >&2
        return 1
    fi
    body="$RUNTIME/origin-post.json"
    answer="$RUNTIME/origin-post.txt"
    printf '{"url":"%s"}' "$origin" > "$body"
    code=$(curl -sS -o "$answer" -w '%{http_code}' --max-time 25 \
        -X POST "$front/__arsip/asal" \
        -H "Authorization: Bearer $gate" \
        -H 'Content-Type: application/json; charset=utf-8' \
        --data-binary @"$body" || true)
    rm -f "$body"
    ok=0
    if [ "$code" = "200" ] && grep -q '"ok":true' "$answer"; then
        ok=1
    fi
    rm -f "$answer"
    gate=''
    if [ "$ok" -ne 1 ]; then
        echo 'Tautan QR belum menerima komputer ini. Worker masih Hello World, atau kunci pintu tidak cocok.' >&2
        return 1
    fi
}

start_worker_door() {
    link=$1
    port=$2
    front=$(printf '%s' "$link" | sed 's#/*$##')
    if static_tunnel_current "$front"; then
        (
            cd "$ROOT" || exit 1
            "$PHP_BIN" artisan access-qr:publish >/dev/null 2>&1 || true
        )
        return 0
    fi
    stop_pid_file "$RUNTIME/tunnel.pid"
    binary=$(install_cloudflared) || return 1
    log="$RUNTIME/tunnel.log"
    rm -f "$log"
    write_status '{"phase":"sharing","message":"Menyambungkan komputer ini ke tautan QR."}'
    nohup "$binary" tunnel --url "http://127.0.0.1:$port" --protocol http2 --no-autoupdate >>"$log" 2>&1 &
    printf '%s\n' "$!" > "$RUNTIME/tunnel.pid"
    origin=''
    i=0
    while [ "$i" -lt 50 ]; do
        if [ -f "$log" ]; then
            origin=$(sed -n 's#.*\(https://[a-z0-9-]*\.trycloudflare\.com\).*#\1#p' "$log" | head -n 1)
            if [ -n "$origin" ] && grep -q 'Registered tunnel connection' "$log"; then
                break
            fi
        fi
        tunnel_pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid" 2>/dev/null || true)
        if [ -z "$tunnel_pid" ] || ! kill -0 "$tunnel_pid" 2>/dev/null; then
            origin=''
            break
        fi
        i=$((i + 1))
        sleep 1
    done
    if [ -z "$origin" ] || ! grep -q 'Registered tunnel connection' "$log" 2>/dev/null; then
        stop_pid_file "$RUNTIME/tunnel.pid"
        echo 'Tunnel ke tautan QR belum terbentuk. Periksa internet, lalu klik Bagikan lagi.' >&2
        return 1
    fi
    if ! publish_worker_origin "$front" "$origin"; then
        stop_pid_file "$RUNTIME/tunnel.pid"
        return 1
    fi
    tunnel_pid=$(tr -d '\r\n' < "$RUNTIME/tunnel.pid")
    write_status "{\"phase\":\"ready\",\"tunnel_pid\":$tunnel_pid,\"public_url\":\"$front\",\"message\":\"Kode QR memakai tautan Worker yang disimpan. Komputer ini sudah tersambung.\"}"
    (
        cd "$ROOT" || exit 1
        "$PHP_BIN" artisan access-qr:publish >/dev/null 2>&1 || true
    )
}

start_tunnel() {
    port=$1
    status=0
    link=$(read_static_link) || status=$?
    if [ "$status" -eq 3 ]; then
        start_worker_door "$link" "$port"
        return $?
    fi
    if [ "$status" -eq 0 ]; then
        if static_tunnel_current "$link"; then
            rm -f "$RUNTIME/tunnel-token"
            return 0
        fi
        stop_pid_file "$RUNTIME/tunnel.pid"
        if start_named_tunnel "$link"; then
            return 0
        fi
        rm -f "$RUNTIME/tunnel-token"
        echo 'Tautan statis belum terhubung. Periksa kunci dari akun petugas, lalu klik Bagikan lagi.' >&2
        return 1
    fi
    if [ "$status" -eq 2 ]; then
        echo 'Tautan statis belum dapat dipakai. Periksa tautan dan kunci yang disimpan.' >&2
        return 1
    fi
    echo 'Simpan satu tautan https dan kunci terowongan dari akun Cloudflare petugas. Tautan itu yang dipakai selamanya.' >&2
    stop_pid_file "$RUNTIME/tunnel.pid"
    return 1
}

stop_pid_file() {
    file=$1
    if [ ! -f "$file" ]; then
        return 0
    fi
    pid=$(tr -d '\r\n' < "$file" || true)
    if [ -n "$pid" ]; then
        kill "$pid" 2>/dev/null || true
        pkill -P "$pid" 2>/dev/null || true
    fi
    rm -f "$file"
}

stop_matching_processes() {
    root=$1
    ps_output=''
    if ps -A -o pid= -o args= >/dev/null 2>&1; then
        ps_output=$(ps -A -o pid= -o args= 2>/dev/null || true)
    elif ps -ax -o pid= -o args= >/dev/null 2>&1; then
        ps_output=$(ps -ax -o pid= -o args= 2>/dev/null || true)
    fi
    printf '%s\n' "$ps_output" | while IFS= read -r line; do
        pid=$(printf '%s' "$line" | awk '{print $1}')
        args=$(printf '%s' "$line" | cut -d' ' -f2-)
        [ -n "$pid" ] || continue
        [ "$pid" = "$$" ] && continue
        [ "$pid" = "$PPID" ] && continue
        printf '%s' "$args" | grep -F -q 'uninstall' && continue
        printf '%s' "$args" | grep -F -q "$root" || continue
        printf '%s' "$args" | grep -E -q 'artisan serve|cloudflared|mulai.sh|runtime.sh|ARSIP-TA.app' || continue
        kill "$pid" 2>/dev/null || true
        if command -v pkill >/dev/null 2>&1; then
            pkill -P "$pid" 2>/dev/null || true
        fi
    done
}

stop_server() {
    stop_pid_file "$RUNTIME/server.pid"
    stop_pid_file "$RUNTIME/tunnel.pid"
    remove_tunnel_secrets
    stop_matching_processes "$ROOT"
    write_status '{"phase":"stopped","server_pid":0,"tunnel_pid":0,"public_url":null,"online":false,"message":"Peladen di komputer ini dihentikan."}'
}

safe_root() {
    candidate=$1
    if [ -z "$candidate" ] || [ ! -d "$candidate" ]; then
        return 1
    fi
    case "$candidate" in
        /|/usr|/usr/local|/opt|/bin|/sbin|/etc|/var|/home|/Users|/System|/Library|"$HOME")
            return 1
            ;;
    esac
    if [ -f "$candidate/laravel-assets/scripts/runtime.sh" ] || [ -f "$candidate/Pasang-ke-Perangkat.cmd" ] || [ -f "$candidate/pasang.sh" ]; then
        return 0
    fi
    return 1
}

remove_app_bundle() {
    bundle=$1
    plist="$bundle/Contents/Info.plist"
    if [ -f "$plist" ] && grep -F -q 'id.arsip.ta' "$plist"; then
        rm -rf "$bundle"
    fi
}

remove_named_shortcuts() {
    data_home=$(xdg_data_home)
    desktop=$(user_desktop)
    for dir in \
        "$data_home/applications" \
        "${XDG_CONFIG_HOME:-$HOME/.config}/autostart" \
        "$desktop" \
        "$HOME/Desktop" \
        "$HOME/Applications" \
        /Applications
    do
        [ -n "$dir" ] && [ -d "$dir" ] || continue
        rm -f \
            "$dir/ARSIP-TA.desktop" \
            "$dir/Hentikan ARSIP-TA.desktop" \
            "$dir/Copot ARSIP-TA.desktop" \
            "$dir/ARSIP-TA.command" \
            "$dir/Hentikan ARSIP-TA.command" \
            "$dir/Copot ARSIP-TA.command" \
            "$dir/QR-Akses-ARSIP-TA.pdf" \
            "$dir/QR-Akses-ARSIP-TA.png" \
            "$dir/Kode-QR-Akses-ARSIP-TA.pdf" \
            "$dir/Kode-QR-Akses-ARSIP-TA.png"
        remove_app_bundle "$dir/ARSIP-TA.app"
    done
    rm -rf "$HOME/Library/Services/Uninstall ARSIP-TA.workflow" "$HOME/Library/Services/Copot ARSIP-TA.workflow"
    for plist in \
        "$HOME/Library/LaunchAgents/id.arsip.ta.plist" \
        "$HOME/Library/LaunchAgents/id.fmipa.unj.arsip-ta.plist"
    do
        if [ -f "$plist" ]; then
            if command -v launchctl >/dev/null 2>&1; then
                launchctl bootout "gui/$(id -u)" "$plist" >/dev/null 2>&1 || launchctl unload "$plist" >/dev/null 2>&1 || true
            fi
            rm -f "$plist"
        fi
    done
    service_dir=${XDG_CONFIG_HOME:-$HOME/.config}/systemd/user
    if [ -f "$service_dir/arsip-ta.service" ]; then
        if command -v systemctl >/dev/null 2>&1; then
            systemctl --user disable --now arsip-ta.service >/dev/null 2>&1 || true
        fi
        rm -f "$service_dir/arsip-ta.service"
        if command -v systemctl >/dev/null 2>&1; then
            systemctl --user daemon-reload >/dev/null 2>&1 || true
        fi
    fi
    rm -rf \
        "$HOME/.cache/ARSIP-TA" \
        "$HOME/.cache/arsip-ta" \
        "${XDG_CACHE_HOME:-$HOME/.cache}/ARSIP-TA" \
        "${XDG_CACHE_HOME:-$HOME/.cache}/arsip-ta" \
        "${XDG_CONFIG_HOME:-$HOME/.config}/arsip-ta" \
        "$HOME/Library/Caches/ARSIP-TA" \
        "$HOME/Library/Logs/ARSIP-TA" \
        "$HOME/Library/Application Support/ARSIP-TA" \
        "$HOME/Library/Saved Application State/id.arsip.ta.savedState" \
        "$HOME/Library/Preferences/id.arsip.ta.plist"
    if command -v update-desktop-database >/dev/null 2>&1; then
        update-desktop-database "$data_home/applications" >/dev/null 2>&1 || true
    fi
}

remove_residuals() {
    root=$1
    if command -v crontab >/dev/null 2>&1; then
        current=$(crontab -l 2>/dev/null || true)
        if printf '%s\n' "$current" | grep -F -q "$root"; then
            filtered=$(printf '%s\n' "$current" | grep -F -v "$root" || true)
            printf '%s\n' "$filtered" | crontab - || true
        fi
    fi
    recent=${XDG_DATA_HOME:-$HOME/.local/share}/recently-used.xbel
    if [ -f "$recent" ]; then
        php_bin=''
        if [ -x "$ROOT/laravel-assets/runtime/php/php" ]; then
            php_bin=$ROOT/laravel-assets/runtime/php/php
        elif command -v php >/dev/null 2>&1; then
            php_bin=php
        fi
        if [ -n "$php_bin" ]; then
            "$php_bin" -r '
                $file = $argv[1];
                $root = $argv[2];
                $text = file_get_contents($file);
                if ($text === false) { exit(0); }
                $pattern = "#<bookmark\\b[^>]*href=\"[^\"]*" . preg_quote($root, "#") . "[^\"]*\"[^>]*>.*?</bookmark>#s";
                $updated = preg_replace($pattern, "", $text);
                if (is_string($updated) && $updated !== $text) { file_put_contents($file, $updated); }
            ' "$recent" "$root" || true
        fi
    fi
    recent_docs=${XDG_DATA_HOME:-$HOME/.local/share}/RecentDocuments
    if [ -d "$recent_docs" ]; then
        find "$recent_docs" -iname '*ARSIP-TA*' -exec rm -f {} + 2>/dev/null || true
    fi
    info_dir=$HOME/.local/share/Trash/info
    if [ -d "$info_dir" ]; then
        for info in "$info_dir"/*.trashinfo; do
            [ -f "$info" ] || continue
            if grep -F -q "$root" "$info"; then
                base=$(basename "$info" .trashinfo)
                rm -f "$HOME/.local/share/Trash/files/$base" "$info"
            fi
        done
    fi
    for trash in "$HOME/.local/share/Trash/files" "$HOME/.Trash"; do
        if [ -d "$trash" ]; then
            rm -f \
                "$trash/ARSIP-TA.desktop" \
                "$trash/ARSIP-TA.command" \
                "$trash/ARSIP-TA.app" \
                "$trash/QR-Akses-ARSIP-TA.pdf" \
                "$trash/QR-Akses-ARSIP-TA.png" \
                "$trash/Kode-QR-Akses-ARSIP-TA.pdf" \
                "$trash/Kode-QR-Akses-ARSIP-TA.png"
        fi
    done
    for tmp in "${TMPDIR:-/tmp}" /tmp /var/tmp; do
        [ -d "$tmp" ] || continue
        for item in "$tmp"/arsip-ta-* "$tmp"/arsip-keep-*; do
            [ -e "$item" ] || continue
            rm -rf "$item" || true
        done
    done
}

wipe_roots() {
    list=$1
    parent=$2
    while kill -0 "$parent" 2>/dev/null; do
        sleep 1
    done
    remaining=''
    while IFS= read -r root; do
        [ -n "$root" ] || continue
        safe_root "$root" || continue
        if command -v chattr >/dev/null 2>&1; then
            chattr -R -i -a "$root" 2>/dev/null || true
        fi
        if command -v chflags >/dev/null 2>&1; then
            chflags -R nouchg "$root" 2>/dev/null || true
        fi
        find "$root" -type f -print | while IFS= read -r file; do
            chmod u+w "$file" 2>/dev/null || true
            bytes=$(wc -c < "$file" 2>/dev/null | tr -d ' ' || true)
            if [ -z "$bytes" ] || [ "$bytes" -eq 0 ]; then
                continue
            fi
            blocks=$(( (bytes + 65535) / 65536 ))
            if [ -r /dev/urandom ]; then
                dd if=/dev/urandom of="$file" bs=65536 count="$blocks" conv=notrunc >/dev/null 2>&1 || true
            else
                dd if=/dev/zero of="$file" bs=65536 count="$blocks" conv=notrunc >/dev/null 2>&1 || true
            fi
        done
        gone=0
        try=0
        while [ "$try" -lt 5 ]; do
            rm -rf "$root" || true
            if [ ! -e "$root" ]; then
                gone=1
                break
            fi
            try=$((try + 1))
            sleep 1
        done
        if [ "$gone" -ne 1 ]; then
            remaining="$remaining $root"
        fi
    done < "$list"
    rm -f "$list"
    rm -f "$0" 2>/dev/null || true
    if [ -z "$remaining" ]; then
        echo 'ARSIP-TA sudah dihapus dari komputer ini.'
    else
        echo "Sebagian folder masih terkunci:$remaining"
    fi
    notice='ARSIP-TA sudah dihapus dari komputer ini. Berkas pemasangan ditimpa, lalu folder aplikasi, catatan, pintasan, dan sisa berkas dihapus.'
    if [ -z "$remaining" ]; then
        if command -v notify-send >/dev/null 2>&1; then
            notify-send 'ARSIP-TA' "$notice" || true
        elif command -v zenity >/dev/null 2>&1; then
            zenity --info --title='ARSIP-TA' --text="$notice" || true
        elif command -v osascript >/dev/null 2>&1; then
            osascript -e 'display dialog "ARSIP-TA sudah dihapus dari komputer ini." buttons {"OK"} default button 1' >/dev/null 2>&1 || true
        fi
    fi
}

uninstall_app() {
    roots=$RUNTIME/wipe-roots.txt
    : > "$roots"
    config_path=${XDG_CONFIG_HOME:-$HOME/.config}/arsip-ta/install.path
    recorded=''
    if [ -f "$config_path" ]; then
        recorded=$(tr -d '\r' < "$config_path" || true)
    fi
    for candidate in \
        "$ROOT" \
        "$recorded" \
        "$HOME/.local/share/ARSIP-TA" \
        "$HOME/ARSIP-TA" \
        /opt/ARSIP-TA \
        /usr/local/ARSIP-TA \
        /usr/local/lib/ARSIP-TA
    do
        if safe_root "$candidate"; then
            printf '%s\n' "$candidate" >> "$roots"
        fi
    done
    if [ ! -s "$roots" ]; then
        echo 'Folder aplikasi ARSIP-TA tidak ditemukan.' >&2
        exit 1
    fi
    write_status '{"phase":"removing","message":"Menghapus permanen ARSIP-TA dari komputer ini."}'
    stop_server
    while IFS= read -r root; do
        stop_matching_processes "$root"
    done < "$roots"
    remove_named_shortcuts
    while IFS= read -r root; do
        [ -n "$root" ] || continue
        remove_residuals "$root"
    done < "$roots"
    list_copy="${TMPDIR:-/tmp}/arsip-ta-wipe-$$.txt"
    worker="${TMPDIR:-/tmp}/arsip-ta-wipe-$$.sh"
    cp "$roots" "$list_copy"
    cp "$0" "$worker"
    chmod +x "$worker" 2>/dev/null || true
    sh "$worker" __wipe "$list_copy" "$$" >/dev/null 2>&1 &
}

PHP_BIN=''

case "$COMMAND" in
    __wipe)
        wipe_roots "$2" "$3"
        ;;
    ensure)
        enter_lock
        PHP_BIN=$(resolve_php)
        install_cloudflared || true
        install_shortcuts
        ensure_vendor
        write_status "{\"phase\":\"ready\",\"php\":\"$PHP_BIN\",\"message\":\"Runtime aplikasi siap di folder ini.\"}"
        exit_lock
        echo 'Runtime siap.'
        ;;
    install)
        enter_lock
        stop_server || true
        PHP_BIN=$(resolve_php)
        install_cloudflared || write_status '{"message":"cloudflared belum terunduh. Peladen lokal tetap dapat dipasang."}'
        install_shortcuts
        ensure_vendor
        prepare_database 1 0 install
        exit_lock
        start_server 1
        echo 'ARSIP-TA terpasang.'
        ;;
    start)
        enter_lock
        PHP_BIN=$(resolve_php)
        start_server 1
        exit_lock
        echo 'ARSIP-TA berjalan.'
        ;;
    stop)
        stop_server
        echo 'ARSIP-TA dihentikan.'
        ;;
    share)
        port=$(port_from_catalog)
        if ! online; then
            write_status '{"phase":"error","message":"Komputer sedang luring. Tautan internet tidak dibuat."}'
            exit 2
        fi
        if ! port_open "$port"; then
            PHP_BIN=$(resolve_php)
            start_server 0
        fi
        PHP_BIN=$(resolve_php)
        if ! start_tunnel "$port"; then
            echo 'Tautan internet belum terbentuk.' >&2
            exit 1
        fi
        echo 'Tautan internet siap.'
        ;;
    unshare)
        stop_pid_file "$RUNTIME/tunnel.pid"
        remove_tunnel_secrets
        write_status '{"phase":"ready","tunnel_pid":0,"public_url":null,"message":"Tautan internet dihentikan. Aplikasi tetap berjalan di komputer ini."}'
        echo 'Tautan internet dihentikan.'
        ;;
    uninstall)
        if [ "${2:-}" = 'shell' ]; then
            status=0
            confirm_uninstall || status=$?
            if [ "$status" -eq 2 ]; then
                exit 0
            fi
            if [ "$status" -ne 0 ]; then
                echo 'Penghapusan dibatalkan.'
                exit 0
            fi
        fi
        enter_lock
        uninstall_app
        exit_lock
        echo 'ARSIP-TA sedang dihapus secara permanen. Isi berkas ditimpa, lalu folder aplikasi, catatan, pintasan, dan sisa berkas dihapus.'
        ;;
esac
