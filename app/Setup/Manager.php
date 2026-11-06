<?php

namespace App\Setup;

use App\Models\FinalProject;
use App\Support\CatalogCache;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Setup dasar: tanggal Indonesia, indeks tampilan, dan pembaca sampul.
 * Induk dari Loader.
 */
class Manager
{
    /**
     * Nama bulan Indonesia (penuh dan singkat) → nomor 1–12.
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

    // --- Tanggal ---

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
     * Apakah nilai sudah berupa tanggal Y-m-d yang sah.
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
     * Ubah tanggal tampilan (Y-m-d atau j F Y bahasa Indonesia) ke format basis data.
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
     * Gabungkan tempat lahir dan tanggal lahir untuk tampilan.
     * Contoh: Bekasi / 13 September 2002
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
     * Hitung sisa masa berlaku (maksimal 10 tahun dari tahun tugas akhir).
     */
    public function formatValidityDeadline($finalProjectYear, ?int $currentYear = null): string
    {
        return FinalProject::validityDeadlineLabel(
            $finalProjectYear,
            $currentYear ?? $this->listingYear()
        );
    }

    // --- Pembaca sampul ---

    public function chatWithArtificialIntelligence(string $prompt): array
    {
        $temperature = 0;
        $apiKey = trim((string) config('services.groq.key'));

        if ($apiKey === '') {
            throw new \RuntimeException('Kunci API Groq belum dikonfigurasi.');
        }

        $startTime = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(max(15, (int) config('services.groq.timeout', 90)))
                ->connectTimeout(10)
                ->acceptJson()
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model'),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => $temperature,
                    'response_format' => [
                        'type' => 'json_object',
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException(
                'Tidak dapat terhubung ke layanan kecerdasan buatan. Periksa koneksi internet, lalu coba lagi.',
                0,
                $e
            );
        }

        $processingTime = round((microtime(true) - $startTime) * 1000, 2);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Layanan kecerdasan buatan gagal merespons. Periksa koneksi dan kunci API.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Respons kecerdasan buatan kosong atau tidak valid.');
        }

        return [
            'temperature' => $temperature,
            'processing_time_ms' => $processingTime,
            'response' => $payload,
        ];
    }

    /**
     * Aturan jenjang untuk ekstraksi sampul: hanya level yang ada di final_project_types.
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
     * Ekstrak informasi sampul Tugas Akhir dari teks PDF menggunakan AI.
     */
    public function extractCoverInformation(string $text): array
    {
        // Batasi ukuran prompt agar request Groq stabil
        $documentText = mb_substr(trim($text), 0, 50000);
        $schema = '{"nim":null,"student_name":null,"final_project_title":null,"program_study":null,"degree_level":null,"final_project_year":null}';

        $prompt = <<<PROMPT
        Output only valid JSON matching:
        $schema

        Rules:
        - Use null if unknown.
        - Extract every field completely from the document.
        - Never omit, shorten, summarize, rewrite, or paraphrase any extracted text.
        - Preserve all words, their order, and their meaning.
        - Correct only obvious OCR errors, capitalization, spacing, and punctuation.
        - Merge words incorrectly split by OCR and split words incorrectly merged by OCR.
        - Preserve official names, abbreviations, acronyms, technical terms, foreign words, numbers, Roman numerals, and document wording.
        - student_name: use proper person name capitalization.
        - final_project_title: return the COMPLETE original title exactly as written from its beginning until the next document section (student name, NIM, study program, degree, or year); do not remove any word; only fix OCR, capitalization, spacing, and punctuation.
        - program_study: return only the study program name, removing prefixes such as Program Studi, Jurusan, Department, Study Program, etc., and use Indonesian title case.
        {$this->degreeLevelExtractionRule()}

        Document:
        $documentText
        PROMPT;

        $result = $this->chatWithArtificialIntelligence($prompt);

        $content = $result['response']['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Respons kecerdasan buatan kosong atau tidak valid.');
        }

        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Respons kecerdasan buatan bukan JSON yang valid.');
        }

        $totalFields = count($data);

        $filledFields = count(array_filter(
            $data,
            static fn ($value) => ! is_null($value) && $value !== ''
        ));

        return [
            $data['nim'] ?? null,
            $data['student_name'] ?? null,
            $data['final_project_title'] ?? null,
            $data['program_study'] ?? null,
            $data['degree_level'] ?? null,
            $data['final_project_year'] ?? null,

            config('services.groq.model'),
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

    // --- Nama berkas ---

    /**
     * Hilangkan prefix UUID dari nama file tersimpan.
     */
    public function stripUuidPrefix($path): string
    {
        return preg_replace('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\s*-\s*/i', '', basename($path));
    }
}
