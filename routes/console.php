<?php

use App\Models\Cloudflare;
use App\Setup\AccessQrPdf;
use App\Setup\PortableDatabase;
use App\Support\LocalStack;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('local-server:prepare {--copy=1} {--adopt=0} {--placement=0}', function () {
    $copy = filter_var($this->option('copy'), FILTER_VALIDATE_BOOLEAN);
    $adopt = filter_var($this->option('adopt'), FILTER_VALIDATE_BOOLEAN);
    $placement = filter_var($this->option('placement'), FILTER_VALIDATE_BOOLEAN);
    $result = app(PortableDatabase::class)->prepare($copy, $adopt, $placement);

    foreach ($result['lines'] as $line) {
        $this->line($line);
    }

    return $result['ok'] ? 0 : 1;
})->purpose('Prepare the SQLite database in the application folder');

Artisan::command('cloudflare:prepare-tunnel', function () {
    $names = ['tunnel-token', 'tunnel.yml', 'tunnel.credentials.json'];
    $forget = function () use ($names): void {
        foreach ($names as $name) {
            $file = LocalStack::runtimePath($name);

            if (is_file($file)) {
                unlink($file);
            }
        }
    };
    $link = Cloudflare::publicUrl();
    $token = Cloudflare::tunnelToken();

    if ($link === null && $token === null) {
        $forget();

        return 2;
    }

    if ($link === null) {
        $forget();
        $this->error('Tautan Worker belum tersimpan.');

        return 1;
    }

    if ($token === null) {
        $forget();
        $this->line($link);

        return 0;
    }

    $directory = LocalStack::runtimePath();

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $tokenPath = LocalStack::runtimePath('tunnel-token');
    file_put_contents($tokenPath, $token, LOCK_EX);
    @chmod($tokenPath, 0600);

    $credentials = Cloudflare::credentials($token);
    $host = parse_url($link, PHP_URL_HOST);
    $configPath = LocalStack::runtimePath('tunnel.yml');
    $credentialsPath = LocalStack::runtimePath('tunnel.credentials.json');

    if (is_array($credentials) && is_string($host) && $host !== '') {
        $storedCredentials = str_replace('\\', '/', $credentialsPath);
        file_put_contents($credentialsPath, json_encode([
            'AccountTag' => $credentials['account'],
            'TunnelSecret' => $credentials['secret'],
            'TunnelID' => $credentials['tunnel'],
        ], JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($credentialsPath, 0600);
        $yaml = "tunnel: {$credentials['tunnel']}\n"
            .'credentials-file: "'.$storedCredentials."\"\n"
            ."ingress:\n"
            ."  - hostname: {$host}\n"
            .'    service: http://127.0.0.1:'.LocalStack::port()."\n"
            ."  - service: http_status:404\n";
        file_put_contents($configPath, $yaml, LOCK_EX);
        @chmod($configPath, 0600);
    } else {
        foreach (['tunnel.yml', 'tunnel.credentials.json'] as $name) {
            $file = LocalStack::runtimePath($name);

            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    $this->line($link);

    return 0;
})->purpose('Write the saved Cloudflare tunnel key for the local launcher');

Artisan::command('access-qr:publish', function () {
    $url = Cloudflare::publicUrl();

    if ($url === null) {
        $this->error('Tautan Worker belum tersimpan di basis data.');

        return 1;
    }

    $logo = public_path('images/logo.png');
    $logo = is_file($logo) ? $logo : '';
    $maker = new AccessQrPdf;
    $runtime = LocalStack::runtimePath();

    if (! is_dir($runtime)) {
        mkdir($runtime, 0777, true);
    }

    $pngPath = $runtime.DIRECTORY_SEPARATOR.'access-qr-online.png';
    $pdfPath = $runtime.DIRECTORY_SEPARATOR.'access-qr-online.pdf';
    file_put_contents($pngPath, $maker->png($url, $logo));
    file_put_contents($pdfPath, $maker->build($url, $logo));
    $this->line($url);

    return 0;
})->purpose('Save the internet-link QR code inside the application');
