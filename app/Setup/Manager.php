<?php

namespace App\Setup;

use App\Models\ArtificialIntelligence;
use App\Models\FinalProject;
use App\Support\CatalogCache;
use App\Support\HttpsCertificate;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Base setup: Indonesian dates, index-page display helpers, and the cover reader.
 * Parent of Loader.
 */
class Manager
{
    /**
     * Indonesian month names, full and short, mapped to numbers 1–12.
     *
     * @var array<string, int>
     */
    private const INDONESIAN_MONTHS = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'ags' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nov' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    private const MONTH_NAMES_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const WEEKDAY_NAMES_ID = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    private ?int $listingYear = null;

    // --- Dates ---

    public function currentDate(): Carbon
    {
        return Carbon::now();
    }

    public function parseLocalizedDate(string $inputFormat, string $outputFormat, $value): string
    {
        if ($inputFormat === 'j F Y' && $outputFormat === 'Y-m-d') {
            $parsed = $this->toDatabaseDate($value);

            if ($this->isDatabaseDate($parsed)) {
                return $parsed;
            }

            throw new \InvalidArgumentException('Tanggal tidak valid.');
        }

        return Carbon::createFromLocaleFormat($inputFormat, 'id', $value)->format($outputFormat);
    }

    public function formatTranslatedDate(string $format, $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($value instanceof \DateTimeInterface) {
            $year = (int) $value->format('Y');

            if ($year < 1000) {
                return '-';
            }

            $fast = $this->formatIndonesianDateParts(
                $format,
                (int) $value->format('j'),
                (int) $value->format('n'),
                $year,
                (int) $value->format('w')
            );

            if ($fast !== null) {
                return $fast;
            }
        } elseif (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($value), $parts)) {
            $year = (int) $parts[1];
            $month = (int) $parts[2];
            $day = (int) $parts[3];

            if ($year < 1000 || ! checkdate($month, $day, $year)) {
                return '-';
            }

            $weekday = (int) date('w', mktime(0, 0, 0, $month, $day, $year));
            $fast = $this->formatIndonesianDateParts($format, $day, $month, $year, $weekday);

            if ($fast !== null) {
                return $fast;
            }
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            if ((int) $date->year < 1000) {
                return '-';
            }

            return $date->locale('id')->translatedFormat($format);
        } catch (\Throwable) {
            return '-';
        }
    }

    private function formatIndonesianDateParts(
        string $format,
        int $day,
        int $month,
        int $year,
        int $weekday
    ): ?string {
        $monthName = self::MONTH_NAMES_ID[$month] ?? null;

        if ($monthName === null) {
            return null;
        }

        if ($format === 'j F Y') {
            return $day.' '.$monthName.' '.$year;
        }

        if ($format === 'l, j F Y') {
            $dayName = self::WEEKDAY_NAMES_ID[$weekday] ?? null;

            return $dayName ? $dayName.', '.$day.' '.$monthName.' '.$year : null;
        }

        return null;
    }

    /**
     * Whether the value is already a valid Y-m-d date.
     */
    public function isDatabaseDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    /**
     * Convert a display date (Y-m-d or Indonesian j F Y) to the database format.
     */
    public function toDatabaseDate($value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $this->isDatabaseDate($value) ? $value : $value;
        }

        if (preg_match('/^(\d{1,2})\s+([A-Za-z.]+)\s+(\d{4})$/u', $value, $parts)) {
            $day = (int) $parts[1];
            $monthName = mb_strtolower(rtrim($parts[2], '.'));
            $year = (int) $parts[3];
            $month = self::INDONESIAN_MONTHS[$monthName] ?? 0;

            if ($month && checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return $value;
    }

    /**
     * Combine place of birth and date of birth for display.
     * Example: Bekasi / 13 September 2002
     */
    public function composePlaceDateOfBirth(string $place, mixed $date): string
    {
        return trim($place).' / '.$this->formatTranslatedDate('j F Y', $this->toDatabaseDate($date));
    }

    protected function listingYear(): int
    {
        return $this->listingYear ??= (int) $this->currentDate()->year;
    }

    protected function dateLabel(mixed $value): string
    {
        return $this->formatTranslatedDate('j F Y', $value);
    }

    protected function dash(mixed $value, string $fallback = '-'): mixed
    {
        return $value ?: $fallback;
    }

    protected function validityLabel(mixed $year): string
    {
        return FinalProject::validityStatusLabel($year, $this->listingYear());
    }

    /**
     * Format the remaining validity period, at most 10 years from the final-project year.
     */
    public function formatValidityDeadline($finalProjectYear, ?int $currentYear = null): string
    {
        return FinalProject::validityDeadlineLabel(
            $finalProjectYear,
            $currentYear ?? $this->listingYear()
        );
    }

    // --- Cover reader ---

    public function chatWithArtificialIntelligence(string $prompt): array
    {
        $temperature = 0;
        $apiKey = ArtificialIntelligence::apiKey();
        $model = ArtificialIntelligence::modelName();

        if ($apiKey === '') {
            throw new \RuntimeException('Kunci akses kecerdasan buatan belum diatur. Isi di Pengaturan.');
        }

        $startTime = microtime(true);
        $response = $this->requestCover($apiKey, $model, $prompt);

        if ($this->modelWasRejected($response) && $model !== ArtificialIntelligence::DEFAULT_MODEL) {
            $model = ArtificialIntelligence::DEFAULT_MODEL;
            $response = $this->requestCover($apiKey, $model, $prompt);
        }

        $processingTime = round((microtime(true) - $startTime) * 1000, 2);

        if (! $response->successful()) {
            throw new \RuntimeException($this->groqFailureMessage($response, $model));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Respons kecerdasan buatan kosong atau tidak valid.');
        }

        return [
            'model' => $model,
            'temperature' => $temperature,
            'processing_time_ms' => $processingTime,
            'response' => $payload,
        ];
    }

    /**
     * Read one cover with the key and model from Pengaturan.
     * A rejected payload is retried on that same model before any other model is considered.
     */
    private function requestCover(string $apiKey, string $model, string $prompt): Response
    {
        $response = null;

        foreach ($this->coverPayloads($model, $prompt) as $payload) {
            $response = $this->postCoverChat($apiKey, $payload);

            if ($response->successful() || ! $this->shouldTryNextPayload($response)) {
                return $response;
            }
        }

        if ($response === null) {
            throw new \RuntimeException('Kunci atau model kecerdasan buatan dari Pengaturan tidak dapat dipakai.');
        }

        return $response;
    }

    /**
     * Payloads for the stored model, from the preferred format down to a plain chat request.
     *
     * @return list<array<string, mixed>>
     */
    private function coverPayloads(string $model, string $prompt): array
    {
        $base = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0,
        ];

        if ($this->modelUsesReasoning($model)) {
            $preferred = $base;
            $preferred['max_completion_tokens'] = 2048;
            $preferred['response_format'] = $this->coverResponseFormat($model);
            $preferred['reasoning_effort'] = 'low';
            $preferred['include_reasoning'] = false;

            $compatible = $base;
            $compatible['max_completion_tokens'] = 2048;
            $compatible['response_format'] = ['type' => 'json_object'];

            return [$preferred, $compatible];
        }

        $json = $base;
        $json['max_tokens'] = 2048;
        $json['response_format'] = ['type' => 'json_object'];

        $plain = $base;
        $plain['max_tokens'] = 2048;

        $completion = $base;
        $completion['max_completion_tokens'] = 2048;
        $completion['response_format'] = ['type' => 'json_object'];

        return [$json, $plain, $completion];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postCoverChat(string $apiKey, array $payload): Response
    {
        try {
            $request = Http::withToken($apiKey)
                ->timeout(max(15, (int) config('services.groq.timeout', 90)))
                ->connectTimeout(10)
                ->acceptJson();

            $bundle = HttpsCertificate::bundle();

            if ($bundle !== null) {
                $request = $request->withOptions(['verify' => $bundle]);
            }

            return $request->post('https://api.groq.com/openai/v1/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->connectionFailureMessage($e), 0, $e);
        }
    }

    private function connectionFailureMessage(ConnectionException $exception): string
    {
        $detail = strtolower($exception->getMessage());

        if (str_contains($detail, 'certificate') || str_contains($detail, 'ssl')) {
            return 'Sertifikat HTTPS layanan kecerdasan buatan tidak dikenali. Muat ulang peladen, lalu ulangi impor.';
        }

        return 'Tidak dapat terhubung ke layanan kecerdasan buatan. Periksa koneksi internet, lalu coba lagi.';
    }

    /**
     * Degree-level rule for cover extraction: only levels that exist in final_project_types.
     */
    private function degreeLevelExtractionRule(): string
    {
        $types = CatalogCache::finalProjectTypes();
        $levels = $types
            ->map(static fn ($type): string => trim((string) $type->level))
            ->filter(static fn (string $level): bool => $level !== '')
            ->unique()
            ->values();

        if ($levels->isEmpty()) {
            return '- degree_level: return null. No degree levels are configured.';
        }

        $guide = $types
            ->map(static function ($type): string {
                $level = trim((string) $type->level);
                $name = trim((string) preg_replace('/\s+/', ' ', (string) $type->type));

                if ($level === '') {
                    return '';
                }

                return $name !== '' ? $name.' => '.$level : $level;
            })
            ->filter(static fn (string $item): bool => $item !== '')
            ->implode(', ');

        return '- degree_level: return only one of '.$levels->implode(', ')
            .'. Map a named final-project type with these jenis tugas akhir entries: '.$guide
            .'. Return null if none match.';
    }

    /**
     * Extract final-project cover information from PDF text with AI.
     */
    public function extractCoverInformation(string $text): array
    {
        // Cap the prompt size so the Groq request stays stable.
        $documentText = mb_substr(trim($text), 0, 50000);
        $schema = '{"nim":null,"student_name":null,"final_project_title":null,"program_study":null,"degree_level":null,"final_project_year":null}';

        $prompt = <<<PROMPT
        Output only valid JSON matching:
        $schema

        The document is an Indonesian final-project cover (format tugas akhir: skripsi, tesis, disertasi, or proyek akhir).
        Read the student work on the cover or title page. Use null if a field is not printed there.

        Rules:
        - Extract every field completely. Never shorten, summarize, rewrite, or paraphrase.
        - Correct only obvious OCR errors, capitalization, spacing, and punctuation.
        - Merge words incorrectly split by OCR and split words incorrectly merged by OCR.
        - Preserve official names, abbreviations, acronyms, technical terms, foreign words, numbers, Roman numerals, and document wording.
        - nim: the student number labeled NIM. Return digits only. Do not return NIP, a phone number, or a year.
        - student_name: the author, with proper person-name capitalization. Do not return pembimbing, penguji, ketua program studi, dekan, rektor, or koordinator.
        - final_project_title: the COMPLETE work title from its first word until the next section (student name, NIM, study program, degree, or year). Do not return only SKRIPSI, TESIS, DISERTASI, TUGAS AKHIR, or PROYEK AKHIR, and do not include the university or faculty name.
        - program_study: the study program name only. Remove prefixes such as Program Studi, Jurusan, Department, or Study Program. Use Indonesian title case.
        - final_project_year: the four-digit year printed on the cover, as a number. Use null if it is absent.
        {$this->degreeLevelExtractionRule()}

        Document:
        $documentText
        PROMPT;

        $result = $this->chatWithArtificialIntelligence($prompt);
        $message = $result['response']['choices'][0]['message'] ?? null;
        $content = $this->messageText(is_array($message) ? $message : []);
        $data = self::decodeCoverJson($content);

        if ($data === null) {
            $finish = $result['response']['choices'][0]['finish_reason'] ?? null;

            throw new \RuntimeException(
                $finish === 'length'
                    ? 'Jawaban kecerdasan buatan terpotong sebelum format tugas akhir selesai dibaca. Coba lagi.'
                    : 'Respons kecerdasan buatan bukan format tugas akhir yang valid.'
            );
        }

        $fields = self::coverFields($data);
        $values = array_values($fields);
        $totalFields = count($values);
        $filledFields = count(array_filter(
            $values,
            static fn ($value) => ! is_null($value) && $value !== ''
        ));

        return [
            $fields['nim'],
            $fields['student_name'],
            $fields['final_project_title'],
            $fields['program_study'],
            $fields['degree_level'],
            $fields['final_project_year'],

            $result['model'],
            $result['temperature'],
            $result['processing_time_ms'],
            $result['response']['usage']['prompt_tokens'] ?? 0,
            $result['response']['usage']['completion_tokens'] ?? 0,
            $result['response']['usage']['total_tokens'] ?? 0,
            $result['response']['choices'][0]['finish_reason'] ?? null,
            $filledFields,
            $totalFields,
            round(($filledFields / max($totalFields, 1)) * 100, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageText(array $message): string
    {
        $content = $message['content'] ?? null;

        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        if (is_array($content)) {
            $parts = [];

            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part) && is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }

            $joined = trim(implode("\n", $parts));

            if ($joined !== '') {
                return $joined;
            }
        }

        $reasoning = $message['reasoning'] ?? null;

        return is_string($reasoning) ? $reasoning : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeCoverJson(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Map the model JSON, including Indonesian key names, onto the cover columns.
     *
     * @param  array<string, mixed>  $data
     * @return array{nim: ?string, student_name: ?string, final_project_title: ?string, program_study: ?string, degree_level: ?string, final_project_year: ?int}
     */
    public static function coverFields(array $data): array
    {
        $data = self::unwrapCover($data);
        $nim = self::coverString($data, ['nim', 'student_nim', 'nomor_induk_mahasiswa', 'no_induk']);

        if ($nim !== null) {
            $nim = preg_replace('/\s+/', '', $nim) ?: null;
        }

        return [
            'nim' => $nim,
            'student_name' => self::coverString($data, ['student_name', 'nama', 'nama_mahasiswa', 'name']),
            'final_project_title' => self::coverString($data, ['final_project_title', 'judul', 'judul_tugas_akhir', 'title']),
            'program_study' => self::coverString($data, ['program_study', 'program_studi', 'prodi', 'study_program']),
            'degree_level' => self::coverString($data, ['degree_level', 'jenjang', 'level']),
            'final_project_year' => self::coverYear($data, ['final_project_year', 'tahun', 'tahun_tugas_akhir', 'year']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverResponseFormat(string $model): array
    {
        if (! $this->modelSupportsStrictSchema($model)) {
            return ['type' => 'json_object'];
        }

        $levels = CatalogCache::degreeLevels();
        $degree = ['type' => ['string', 'null']];

        if ($levels !== []) {
            $degree['enum'] = array_merge($levels, [null]);
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'final_project_cover',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'nim' => ['type' => ['string', 'null']],
                        'student_name' => ['type' => ['string', 'null']],
                        'final_project_title' => ['type' => ['string', 'null']],
                        'program_study' => ['type' => ['string', 'null']],
                        'degree_level' => $degree,
                        'final_project_year' => ['type' => ['integer', 'null']],
                    ],
                    'required' => [
                        'nim',
                        'student_name',
                        'final_project_title',
                        'program_study',
                        'degree_level',
                        'final_project_year',
                    ],
                ],
            ],
        ];
    }

    private function modelSupportsStrictSchema(string $model): bool
    {
        return in_array($model, [
            'openai/gpt-oss-20b',
            'openai/gpt-oss-120b',
            'qwen/qwen3.8-27b',
            'meta-models/muse-spark-open',
        ], true);
    }

    private function modelUsesReasoning(string $model): bool
    {
        return $this->modelSupportsStrictSchema($model)
            || in_array($model, [
                'openai/gpt-oss-safeguard-20b',
                'qwen/qwen3.6-27b',
            ], true);
    }

    private function shouldTryNextPayload(Response $response): bool
    {
        if ($response->successful() || $this->modelWasRejected($response)) {
            return false;
        }

        return in_array($response->status(), [400, 422], true);
    }

    private function errorText(Response $response): string
    {
        $body = $response->json();
        $message = data_get($body, 'error.message');

        if (! is_string($message) || trim($message) === '') {
            $error = data_get($body, 'error');
            $message = is_string($error) ? $error : $response->body();
        }

        $code = (string) data_get($body, 'error.code', '');

        return strtolower(trim($code.' '.$message));
    }

    private function modelWasRejected(Response $response): bool
    {
        if (! in_array($response->status(), [400, 404], true)) {
            return false;
        }

        $message = $this->errorText($response);

        return str_contains($message, 'decommission')
            || str_contains($message, 'model_not_found')
            || str_contains($message, 'model_decommissioned')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'no longer')
            || str_contains($message, 'invalid model')
            || str_contains($message, 'unknown model')
            || str_contains($message, 'model not found');
    }

    private function groqFailureMessage(Response $response, string $model): string
    {
        if ($this->modelWasRejected($response)) {
            return 'Model '.$model.' tidak tersedia. Di Pengaturan, simpan model yang masih aktif, misalnya openai/gpt-oss-120b.';
        }

        $message = $this->errorText($response);

        if ($response->status() === 401 || str_contains($message, 'invalid api key') || str_contains($message, 'invalid_api_key')) {
            return 'Kunci akses kecerdasan buatan yang tersimpan di Pengaturan ditolak. Periksa kunci, lalu simpan lagi.';
        }

        if ($response->status() === 429 || str_contains($message, 'rate limit')) {
            return 'Layanan kecerdasan buatan sedang membatasi permintaan. Tunggu sebentar, lalu ulangi impor.';
        }

        return 'Layanan kecerdasan buatan gagal membaca format tugas akhir dengan model '.$model.' yang tersimpan di Pengaturan. Periksa kunci API dan nama model, lalu simpan lagi.';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function unwrapCover(array $data): array
    {
        foreach (['nim', 'student_name', 'final_project_title', 'program_study', 'degree_level', 'final_project_year', 'nama', 'judul', 'program_studi', 'jenjang', 'tahun'] as $key) {
            if (array_key_exists($key, $data)) {
                return $data;
            }
        }

        foreach (['data', 'cover', 'final_project', 'result', 'tugas_akhir'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private static function coverString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || is_array($data[$key])) {
                continue;
            }

            $value = trim((string) preg_replace('/\s+/u', ' ', (string) $data[$key]));

            if ($value !== '' && strtolower($value) !== 'null') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private static function coverYear(array $data, array $keys): ?int
    {
        $value = self::coverString($data, $keys);

        if ($value === null || preg_match('/\b(19|20|21)\d{2}\b/', $value, $year) !== 1) {
            return null;
        }

        $year = (int) $year[0];

        if ($year < FinalProject::YEAR_MIN || $year > FinalProject::YEAR_MAX) {
            return null;
        }

        return $year;
    }

    // --- File names ---

    /**
     * Remove the UUID prefix from a stored file name.
     */
    public function stripUuidPrefix($path): string
    {
        return preg_replace('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\s*-\s*/i', '', basename($path));
    }
}
