<?php

namespace Tests\Unit;

use App\Setup\AccessQrPdf;
use App\Support\AccessUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccessUrlTest extends TestCase
{
    public function test_login_url_follows_the_request_instead_of_app_url(): void
    {
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        try {
            $request = Request::create(
                'http://10.9.8.7:8123/dasbor/qrcode/pdf',
                'GET',
                [],
                [],
                [],
                ['REMOTE_ADDR' => '203.0.113.10']
            );

            $this->assertSame('http://10.9.8.7:8123', AccessUrl::login($request));
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_login_url_keeps_domain_and_https(): void
    {
        $request = Request::create(
            'https://arsip.example.test/dasbor/qrcode/pdf',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.10']
        );

        $this->assertSame('https://arsip.example.test', AccessUrl::login($request));
    }

    public function test_login_url_includes_the_install_directory(): void
    {
        $request = Request::create(
            'http://192.168.10.8/final-project/public/dasbor/qrcode/pdf',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '203.0.113.10',
                'SCRIPT_NAME' => '/final-project/public/index.php',
                'SCRIPT_FILENAME' => 'C:/laragon/www/final-project/public/index.php',
            ]
        );

        $this->assertSame(
            'http://192.168.10.8/final-project/public',
            AccessUrl::login($request)
        );
    }

    public function test_login_url_follows_forwarded_public_address_from_a_local_proxy(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8000/dasbor/qrcode/pdf',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'arsip.fmipa.unj.ac.id',
                'HTTP_X_FORWARDED_PREFIX' => '/arsip',
            ]
        );

        $this->assertSame(
            'https://arsip.fmipa.unj.ac.id/arsip',
            AccessUrl::login($request)
        );
    }

    public function test_public_client_cannot_replace_the_login_url(): void
    {
        $request = Request::create(
            'http://10.4.5.6:8000/dasbor/qrcode/pdf',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '203.0.113.9',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'evil.test',
            ]
        );

        $this->assertSame('http://10.4.5.6:8000', AccessUrl::login($request));
    }

    public function test_pdf_embeds_the_supplied_login_url(): void
    {
        $loginUrl = 'http://10.9.8.7:8123';
        $logo = public_path('images/logo.png');
        $pdf = (new AccessQrPdf)->build($loginUrl, is_file($logo) ? $logo : '');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString($loginUrl, $pdf);
        $this->assertStringNotContainsString('http://localhost', $pdf);
    }
}
