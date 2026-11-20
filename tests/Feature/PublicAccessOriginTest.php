<?php

namespace Tests\Feature;

use App\Models\Cloudflare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccessOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_phone_on_the_tunnel_stays_on_the_saved_cloudflare_link(): void
    {
        $this->storeLink();

        $response = $this->call('GET', 'http://random-name.trycloudflare.com/', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'random-name.trycloudflare.com',
            'HTTP_CF_RAY' => '8f0-SIN',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.50',
            'HTTP_X_FORWARDED_PROTO' => 'http',
            'HTTP_X_FORWARDED_HOST' => 'evil.test',
            'HTTP_X_FORWARDED_PREFIX' => '/salah',
        ]);

        $response->assertOk();
        $response->assertSee('https://arsip.nama.workers.dev/login', false);
        $response->assertDontSee('evil.test', false);
        $response->assertDontSee('trycloudflare.com', false);
        $response->assertDontSee('http://127.0.0.1', false);
    }

    public function test_the_marked_front_door_uses_the_saved_link(): void
    {
        $this->storeLink();

        $this->call('GET', 'http://127.0.0.1:8765/', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8765',
            'HTTP_X_ARSIP_FRONT' => 'https://arsip.nama.workers.dev',
        ])->assertOk()
            ->assertSee('https://arsip.nama.workers.dev/login', false);
    }

    public function test_the_officer_computer_keeps_its_own_address(): void
    {
        $this->storeLink();

        $this->call('GET', 'http://127.0.0.1:8765/', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8765',
        ])->assertOk()
            ->assertDontSee('arsip.nama.workers.dev', false);
    }

    public function test_a_remote_address_cannot_switch_the_host_with_cloudflare_headers(): void
    {
        $this->storeLink();

        $this->call('GET', 'http://10.1.1.1/', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.8',
            'HTTP_HOST' => '10.1.1.1',
            'HTTP_CF_RAY' => '8f0-SIN',
            'HTTP_X_FORWARDED_HOST' => 'arsip.nama.workers.dev',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->assertOk()
            ->assertSee('http://10.1.1.1/login', false)
            ->assertDontSee('arsip.nama.workers.dev', false);
    }

    private function storeLink(): void
    {
        Cloudflare::query()->create([
            'link' => 'https://arsip.nama.workers.dev',
            'key' => '',
        ]);
    }
}
