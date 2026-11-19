<?php

namespace Tests\Unit;

use App\Models\ArtificialIntelligence;
use App\Models\User;
use App\Setup\Manager;
use App\Support\HttpsCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\TestCase;

class CoverReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_retired_groq_model_is_replaced_before_the_cover_is_read(): void
    {
        config([
            'services.groq.key' => 'testkey1234567890abcdef',
            'services.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        ArtificialIntelligence::query()->create([
            'key' => 'storedkey1234567890abcd',
            'model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'nim' => '1313025001',
                            'student_name' => 'Siti Aminah',
                            'final_project_title' => 'Sistem Arsip Tugas Akhir',
                            'program_study' => 'Matematika',
                            'degree_level' => 'S1',
                            'final_project_year' => 2024,
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ]),
        ]);

        $cover = app(Manager::class)->extractCoverInformation(<<<'TEXT'
        SKRIPSI
        SISTEM ARSIP TUGAS AKHIR
        SITI AMINAH
        1313025001
        PROGRAM STUDI MATEMATIKA
        2024
        TEXT);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $authorization === 'Bearer storedkey1234567890abcd'
                && $request['model'] === 'openai/gpt-oss-120b'
                && ($request['response_format']['type'] ?? null) === 'json_schema'
                && ($request['response_format']['json_schema']['strict'] ?? null) === true
                && ($request['reasoning_effort'] ?? null) === 'low'
                && str_contains((string) $request['messages'][0]['content'], 'format tugas akhir');
        });

        $this->assertSame('1313025001', $cover[0]);
        $this->assertSame('Siti Aminah', $cover[1]);
        $this->assertSame('Sistem Arsip Tugas Akhir', $cover[2]);
        $this->assertSame('Matematika', $cover[3]);
        $this->assertSame('S1', $cover[4]);
        $this->assertSame(2024, $cover[5]);
        $this->assertSame('openai/gpt-oss-120b', $cover[6]);
    }

    public function test_schema_rejection_is_retried_as_a_json_object(): void
    {
        config([
            'services.groq.key' => 'testkey1234567890abcdef',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        $cover = [
            'judul' => 'Analisis Data Iklim',
            'nama_mahasiswa' => 'Budi Santoso',
            'nim' => '1402019001',
            'program_studi' => 'Fisika',
            'jenjang' => 'S1',
            'tahun' => 'Tahun 2023',
        ];

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'response_format json_schema is not supported']], 400)
                ->push([
                    'choices' => [[
                        'message' => ['content' => "```json\n".json_encode(['tugas_akhir' => $cover])."\n```"],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
                ], 200),
        ]);

        $read = app(Manager::class)->extractCoverInformation('SKRIPSI ANALISIS DATA IKLIM BUDI SANTOSO 1402019001 FISIKA 2023');

        Http::assertSentCount(2);
        $sent = Http::recorded();
        $this->assertSame('json_object', $sent[1][0]['response_format']['type']);
        $this->assertArrayNotHasKey('reasoning_effort', $sent[1][0]->data());
        $this->assertSame('1402019001', $read[0]);
        $this->assertSame('Budi Santoso', $read[1]);
        $this->assertSame('Analisis Data Iklim', $read[2]);
        $this->assertSame('Fisika', $read[3]);
        $this->assertSame('S1', $read[4]);
        $this->assertSame(2023, $read[5]);
    }

    public function test_active_model_keeps_a_custom_model_and_reads_indonesian_keys(): void
    {
        $this->assertSame('openai/gpt-oss-120b', ArtificialIntelligence::activeModel('llama-3.3-70b-versatile'));
        $this->assertSame('openai/gpt-oss-20b', ArtificialIntelligence::activeModel('llama-3.1-8b-instant'));
        $this->assertSame('my-lab/model-a', ArtificialIntelligence::activeModel('my-lab/model-a'));

        $decoded = Manager::decodeCoverJson(<<<'JSON'
        Catatan model
        {"nama":"Dewi Lestari","judul":"Pemodelan Statistik","prodi":"Statistika","jenjang":"S2","tahun":2022,"nim":"5520118001"}
        JSON);

        $fields = Manager::coverFields($decoded ?? []);

        $this->assertSame('5520118001', $fields['nim']);
        $this->assertSame('Dewi Lestari', $fields['student_name']);
        $this->assertSame('Pemodelan Statistik', $fields['final_project_title']);
        $this->assertSame('Statistika', $fields['program_study']);
        $this->assertSame('S2', $fields['degree_level']);
        $this->assertSame(2022, $fields['final_project_year']);
    }

    public function test_import_reads_with_the_key_and_model_saved_from_settings(): void
    {
        config([
            'services.groq.key' => 'envkey1234567890abcdef',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        ArtificialIntelligence::query()->create([
            'key' => "  Bearer officerkey1234567890abcd\n",
            'model' => 'qwen/qwen3-32b',
        ]);

        $this->assertSame('officerkey1234567890abcd', ArtificialIntelligence::apiKey());
        $this->assertSame('qwen/qwen3-32b', ArtificialIntelligence::modelName());

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'response_format is not supported for this model']], 400)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'nim' => '1302018008',
                                'student_name' => 'Rina Kusuma',
                                'final_project_title' => 'Arsip Digital Tugas Akhir',
                                'program_study' => 'Biologi',
                                'degree_level' => 'S1',
                                'final_project_year' => 2025,
                            ]),
                        ],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 5, 'total_tokens' => 9],
                ], 200),
        ]);

        $cover = app(Manager::class)->extractCoverInformation('SKRIPSI ARSIP DIGITAL TUGAS AKHIR RINA KUSUMA 1302018008 BIOLOGI 2025');

        Http::assertSentCount(2);
        $sent = Http::recorded();

        foreach ($sent as [$request]) {
            $this->assertSame('Bearer officerkey1234567890abcd', $request->header('Authorization')[0] ?? '');
            $this->assertSame('qwen/qwen3-32b', $request['model']);
        }

        $this->assertSame(2048, $sent[0][0]['max_tokens']);
        $this->assertSame('json_object', $sent[0][0]['response_format']['type']);
        $this->assertArrayNotHasKey('max_completion_tokens', $sent[0][0]->data());
        $this->assertArrayNotHasKey('response_format', $sent[1][0]->data());
        $this->assertSame(2048, $sent[1][0]['max_tokens']);
        $this->assertSame('1302018008', $cover[0]);
        $this->assertSame('Rina Kusuma', $cover[1]);
        $this->assertSame('Arsip Digital Tugas Akhir', $cover[2]);
        $this->assertSame('qwen/qwen3-32b', $cover[6]);
    }

    public function test_portable_php_uses_the_bundled_certificate_for_groq(): void
    {
        $bundle = HttpsCertificate::bundle();

        $this->assertNotNull($bundle);
        $this->assertStringContainsString('BEGIN CERTIFICATE', (string) file_get_contents($bundle));

        $options = new ReflectionProperty(Factory::class, 'globalOptions');
        $configured = $options->getValue(app(Factory::class));
        $configured = $configured instanceof \Closure ? $configured() : $configured;

        $this->assertSame($bundle, $configured['verify'] ?? null);
    }

    public function test_import_alerts_when_the_database_has_no_key_or_model_yet(): void
    {
        config([
            'services.groq.key' => 'envkey1234567890abcdef',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        $this->assertFalse(ArtificialIntelligence::isStored());

        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $message = ArtificialIntelligence::missingStorageMessage();

        $this->actingAs($officer)
            ->get(route('page-import'))
            ->assertOk()
            ->assertSee('id="importpage"', false)
            ->assertSee('data-ai-stored="0"', false)
            ->assertSee($message, false);

        $this->actingAs($officer)
            ->postJson(route('reader-import'), [
                'text' => 'SKRIPSI SISTEM ARSIP SITI AMINAH 1313025001 MATEMATIKA 2024',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', $message);

        ArtificialIntelligence::query()->create([
            'key' => 'storedkey1234567890abcd',
            'model' => 'openai/gpt-oss-120b',
        ]);

        $this->assertTrue(ArtificialIntelligence::isStored());

        $this->actingAs($officer)
            ->get(route('page-import'))
            ->assertOk()
            ->assertSee('data-ai-stored="1"', false);
    }

    public function test_certificate_failure_is_not_described_as_an_internet_outage(): void
    {
        config([
            'services.groq.key' => 'testkey1234567890abcdef',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake(function () {
            throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate');
        });

        try {
            app(Manager::class)->extractCoverInformation('SKRIPSI SISTEM ARSIP SITI AMINAH 1313025001 MATEMATIKA 2024');
            $this->fail('A certificate error should stop the cover reader.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Sertifikat HTTPS', $exception->getMessage());
            $this->assertStringNotContainsString('koneksi internet', $exception->getMessage());
        }
    }
}
