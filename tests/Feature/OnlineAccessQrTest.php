<?php

namespace Tests\Feature;

use App\Models\Cloudflare;
use App\Models\User;
use App\Support\LocalStack;
use App\Support\RoleSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineAccessQrTest extends TestCase
{
    use RefreshDatabase;

    private string $statusFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusFile = tempnam(sys_get_temp_dir(), 'arsip-status');
        LocalStack::$statusPath = $this->statusFile;
    }

    protected function tearDown(): void
    {
        LocalStack::$statusPath = null;
        LocalStack::$runtimeRoot = null;

        if (is_file($this->statusFile)) {
            unlink($this->statusFile);
        }

        parent::tearDown();
    }

    public function test_dashboard_offers_the_online_qr_download(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->get(route('page-dashboard'))
            ->assertOk()
            ->assertSee('Lihat dan cetak')
            ->assertSee('Tautan Worker belum tersimpan.')
            ->assertSee('Statistik Tugas Akhir')
            ->assertSee('Persentase Tugas Akhir')
            ->assertDontSee('Kapasitas memori perangkat')
            ->assertDontSee('id="dashboardaccessqr"', false);
    }

    public function test_dashboard_previews_the_stored_cloudflare_link_for_officer_and_coordinator(): void
    {
        $this->storeLink('https://arsip.universitas.ac.id');

        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($officer)
            ->get(route('page-dashboard'))
            ->assertOk()
            ->assertSee('https://arsip.universitas.ac.id')
            ->assertSee('id="dashboardaccessqr"', false)
            ->assertSee('Lihat dan cetak')
            ->assertDontSee('trycloudflare.com');

        $this->actingAs($coordinator, 'coordinator')
            ->withSession([RoleSession::ACTIVE_KEY => RoleSession::COORDINATOR])
            ->get(route('page-dashboard'))
            ->assertOk()
            ->assertSee('https://arsip.universitas.ac.id')
            ->assertSee('id="dashboardaccessqr"', false);
    }

    public function test_qr_image_is_hidden_until_the_cloudflare_link_is_stored(): void
    {
        LocalStack::writeStatus([
            'public_url' => 'https://perpustakaan-fmipa.trycloudflare.com',
        ]);

        $this->get(route('access-qr-png'))
            ->assertNotFound();
    }

    public function test_qr_image_uses_the_stored_cloudflare_link(): void
    {
        $this->storeLink('https://arsip.universitas.ac.id');
        LocalStack::writeStatus([
            'public_url' => 'https://perpustakaan-fmipa.trycloudflare.com',
        ]);

        $response = $this->get(route('access-qr-png'));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_pdf_waits_until_the_cloudflare_link_is_stored(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        LocalStack::writeStatus([
            'public_url' => 'https://perpustakaan-fmipa.trycloudflare.com',
        ]);

        $this->actingAs($officer)
            ->getJson(route('pdf-access-qrcode'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode QR belum siap. Simpan satu tautan Worker di peladen lokal. Tautan itu tidak berubah setelah tersimpan.');
    }

    public function test_pdf_uses_the_stored_cloudflare_link(): void
    {
        $this->storeLink('https://arsip.universitas.ac.id');
        LocalStack::writeStatus([
            'public_url' => 'https://perpustakaan-fmipa.trycloudflare.com',
        ]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $response = $this->actingAs($officer)
            ->get(route('pdf-access-qrcode'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('https://arsip.universitas.ac.id', $response->getContent());
        $this->assertStringNotContainsString('trycloudflare.com', $response->getContent());
        $this->assertStringNotContainsString('http://localhost', $response->getContent());
    }

    public function test_coordinator_can_open_the_printable_qr(): void
    {
        $this->storeLink('https://arsip.universitas.ac.id');
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($coordinator, 'coordinator')
            ->withSession([RoleSession::ACTIVE_KEY => RoleSession::COORDINATOR])
            ->get(route('pdf-access-qrcode'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_stored_cloudflare_link_cannot_be_replaced_or_cleared(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => 'https://arsip.nama.workers.dev',
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('success');

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => 'https://lain.nama.workers.dev',
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('error');

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => 'https://arsip.universitas.ac.id',
                'clear' => '1',
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('error');

        $this->assertSame('https://arsip.nama.workers.dev', Cloudflare::publicUrl());
        $this->assertNull(Cloudflare::tunnelToken());

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('page-local-server'))
            ->assertOk()
            ->assertSee('https://arsip.nama.workers.dev')
            ->assertSee('Tautan ini sudah terkunci.')
            ->assertDontSee('Kunci terowongan')
            ->assertDontSee('Perbarui kunci')
            ->assertDontSee('Hapus tautan');
    }

    public function test_install_command_and_hostname_are_stored_for_the_dashboard_qr(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $token = $this->connectorToken();

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => 'arsip.universitas.ac.id/dasbor',
                'key' => "cloudflared.exe service install {$token}",
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('success');

        $this->assertSame('https://arsip.universitas.ac.id', Cloudflare::publicUrl());
        $this->assertSame($token, Cloudflare::tunnelToken());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', Cloudflare::credentials()['tunnel'] ?? null);

        $this->actingAs($officer)
            ->get(route('page-dashboard'))
            ->assertOk()
            ->assertSee('https://arsip.universitas.ac.id')
            ->assertSee('id="dashboardaccessqr"', false)
            ->assertDontSee($token);
    }

    public function test_quoted_token_is_stored_and_a_quick_tunnel_is_rejected(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $token = $this->connectorToken();

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => '"https://arsip.universitas.ac.id"',
                'key' => '"'.$token.'"',
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('success');

        $this->assertSame($token, Cloudflare::tunnelToken());

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('store-local-cloudflare'), [
                'link' => 'https://golf-trend-tale-grip.trycloudflare.com',
                'key' => $token,
            ])
            ->assertRedirect(route('page-local-server'))
            ->assertSessionHas('error');

        $this->assertSame('https://arsip.universitas.ac.id', Cloudflare::publicUrl());
    }

    public function test_prepare_tunnel_routes_the_saved_host_to_this_computer(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-tunnel-'.bin2hex(random_bytes(4));
        mkdir($directory);
        LocalStack::$runtimeRoot = $directory;

        try {
            Cloudflare::query()->create([
                'link' => 'https://arsip.universitas.ac.id',
                'key' => $this->connectorToken(),
            ]);

            $this->artisan('cloudflare:prepare-tunnel')->assertOk();

            $config = file_get_contents($directory.DIRECTORY_SEPARATOR.'tunnel.yml');
            $this->assertIsString($config);
            $this->assertStringContainsString('hostname: arsip.universitas.ac.id', $config);
            $this->assertStringContainsString('service: http://127.0.0.1:'.LocalStack::port(), $config);
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'tunnel.credentials.json');
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'tunnel-token');
        } finally {
            LocalStack::$runtimeRoot = null;
            $this->deleteTree($directory);
        }
    }

    public function test_prepare_tunnel_keeps_a_worker_link_without_a_key(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-worker-'.bin2hex(random_bytes(4));
        mkdir($directory);
        LocalStack::$runtimeRoot = $directory;

        try {
            Cloudflare::query()->create([
                'link' => 'https://arsip.nama.workers.dev',
                'key' => '',
            ]);

            $this->artisan('cloudflare:prepare-tunnel')
                ->expectsOutput('https://arsip.nama.workers.dev')
                ->assertOk();

            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.'tunnel-token');
            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.'tunnel.yml');
        } finally {
            LocalStack::$runtimeRoot = null;
            $this->deleteTree($directory);
        }
    }

    private function connectorToken(): string
    {
        return base64_encode((string) json_encode([
            'a' => 'abc123account',
            't' => '550e8400-e29b-41d4-a716-446655440000',
            's' => base64_encode('rahasia-terowongan'),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function deleteTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function storeLink(string $link): void
    {
        Cloudflare::query()->create([
            'link' => $link,
            'key' => str_repeat('b', 48),
        ]);
    }
}
