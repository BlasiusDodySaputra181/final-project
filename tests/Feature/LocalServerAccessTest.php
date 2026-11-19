<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalConsole;
use App\Support\RoleSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class LocalServerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('page-local-server'))
            ->assertRedirect(route('login'));
    }

    public function test_officer_on_the_installed_computer_can_open_the_server_page(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('page-local-server'))
            ->assertOk()
            ->assertSee('Pasang semua')
            ->assertSee('Copot pemasangan')
            ->assertSee('Pasang-ke-Perangkat.cmd')
            ->assertSee('Copot-ARSIP-TA.cmd')
            ->assertSee('sh copot.sh')
            ->assertSee('secara permanen')
            ->assertSee('Panel basis data')
            ->assertSee('Buat basis data baru')
            ->assertSee('Pakai berkas basis data yang sudah ada')
            ->assertSee('Buat folder ARSIP TUGAS AKHIR baru')
            ->assertSee('Ekspor basis data')
            ->assertSee('Kapasitas memori perangkat')
            ->assertSee('Memori masih tersedia')
            ->assertSee('Berkas arsip tugas akhir');
    }

    public function test_remote_client_cannot_uninstall_the_server(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson(route('uninstall-local-server'))
            ->assertForbidden()
            ->assertJsonPath('message', LocalConsole::DENIED);
    }

    public function test_remote_client_cannot_open_the_server_page(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson(route('status-local-server'))
            ->assertForbidden()
            ->assertJsonPath('message', LocalConsole::DENIED);
    }

    public function test_internet_tunnel_cannot_open_the_database_panel(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('CF-Connecting-IP', '203.0.113.8')
            ->get(route('page-local-database'))
            ->assertForbidden();
    }

    public function test_coordinator_cannot_install_the_server(): void
    {
        $coordinator = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($coordinator, 'coordinator')
            ->withSession([RoleSession::ACTIVE_KEY => RoleSession::COORDINATOR])
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('page-local-server'))
            ->assertForbidden();
    }

    public function test_database_panel_hides_passwords(): void
    {
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'email' => 'petugas@example.test',
        ]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('page-local-database', ['table' => 'users']))
            ->assertOk()
            ->assertSee('petugas@example.test')
            ->assertSee('disembunyikan')
            ->assertSee('Impor basis data')
            ->assertDontSee($officer->password);
    }

    public function test_officer_can_export_a_sqlite_snapshot(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $response = $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('export-local-database'));

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $header = file_get_contents($path, false, null, 0, 16);
        $this->assertSame("SQLite format 3\0", $header);
    }

    public function test_import_rejects_a_file_that_is_not_sqlite(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post(route('import-local-database'), [
                'database' => UploadedFile::fake()->createWithContent('catatan.txt', 'bukan basis data'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Berkas yang dipilih bukan basis data SQLite.');
    }

    public function test_install_rejects_an_empty_existing_database_choice(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('install-local-server'), [
                'database_mode' => 'existing',
                'database_file' => '',
                'archive_mode' => 'keep',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pilih berkas basis data SQLite yang sudah ada.');
    }

    public function test_remote_client_cannot_export_or_browse_the_disk(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson(route('export-local-database'))
            ->assertForbidden();

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson(route('browse-local-server', ['files' => 1]))
            ->assertForbidden();
    }

    public function test_officer_can_browse_drives_on_this_computer(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson(route('browse-local-server', ['path' => '__computer__', 'files' => 1]))
            ->assertOk()
            ->assertJsonStructure(['entries', 'shortcuts']);
    }
}
