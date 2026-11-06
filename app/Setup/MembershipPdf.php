<?php

namespace App\Setup;

/**
 * Formulir pendaftaran keanggotaan perpustakaan — mengikuti blanko resmi FMIPA UNJ.
 */
class MembershipPdf extends \FPDF
{
    use PdfText;

    private const PAGE_W = 595.28;

    private const MARGIN_L = 56.0;

    private const MARGIN_R = 56.0;

    private const LABEL_W = 148.0;

    private const COLON_W = 16.0;

    public function __construct()
    {
        parent::__construct('P', 'pt', 'A4');
        $this->SetMargins(self::MARGIN_L, 36.0, self::MARGIN_R);
        $this->SetAutoPageBreak(false);
    }

    /**
     * @param  array{
     *     date_label: string,
     *     student_name: string,
     *     nim: string,
     *     gender: string,
     *     cluster: string,
     *     study_program: string,
     *     place_date_of_birth: string,
     *     origin_address: string,
     *     current_address: string,
     *     phone_number: string,
     *     email: string,
     *     logo_path?: string
     * }  $data
     */
    public function build(array $data): string
    {
        $this->AddPage();
        $this->drawLetter($data);

        return $this->Output('S');
    }

    /**
     * @param  array{
     *     date_label: string,
     *     student_name: string,
     *     nim: string,
     *     gender: string,
     *     cluster: string,
     *     study_program: string,
     *     place_date_of_birth: string,
     *     origin_address: string,
     *     current_address: string,
     *     phone_number: string,
     *     email: string,
     *     logo_path?: string
     * }  $data
     */
    private function drawLetter(array $data): void
    {
        $contentW = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $valueX = self::MARGIN_L + self::LABEL_W + self::COLON_W;
        $valueW = $contentW - self::LABEL_W - self::COLON_W;

        $this->SetTextColor(0, 0, 0);
        $y = $this->drawLetterhead((string) ($data['logo_path'] ?? ''));
        $family = $this->bodyFontFamily();
        $titleH = $this->wordLineHeight(16.0, 1.5);
        $bodyH = $this->bodyLineHeight();

        $this->SetFont($family, 'B', 16);
        $this->SetXY(self::MARGIN_L, $y + $titleH);
        $this->Cell($contentW, $titleH, $this->txt('FORMULIR PENDAFTARAN'), 0, 1, 'C');
        $this->SetX(self::MARGIN_L);
        $this->Cell($contentW, $titleH, $this->txt('KEANGGOTAAN PERPUSTAKAAN FMIPA UNJ'), 0, 1, 'C');

        $this->Ln($bodyH * 0.5);
        $y = $this->GetY();
        $y = $this->drawField($y, 'Hari dan Tanggal', (string) $data['date_label'], $valueX, $valueW, true);
        $y = $this->drawField($y, 'Nama Mahasiswa', (string) $data['student_name'], $valueX, $valueW);
        $y = $this->drawField($y, 'NIM', (string) $data['nim'], $valueX, $valueW);
        $y = $this->drawGenderRow($y, (string) $data['gender'], $valueX);
        $y = $this->drawField($y, 'Rumpun', (string) $data['cluster'], $valueX, $valueW);
        $y = $this->drawField($y, 'Program Studi', (string) $data['study_program'], $valueX, $valueW);
        $y = $this->drawField($y, 'Tempat/Tanggal Lahir', (string) $data['place_date_of_birth'], $valueX, $valueW);
        $y = $this->drawWrappedField($y, 'Alamat Asal', (string) $data['origin_address'], $valueX, $valueW, 2);
        $y += $bodyH;
        $y = $this->drawWrappedField($y, 'Alamat Sekarang', (string) $data['current_address'], $valueX, $valueW, 2);
        $y += $bodyH;
        $y = $this->drawField($y, 'Hp/ Telpon', (string) $data['phone_number'], $valueX, $valueW);
        $y = $this->drawField($y, 'E-mail', (string) $data['email'], $valueX, $valueW);

        $this->drawVerification($y + $bodyH);
    }

    private function drawLetterhead(string $logoPath): float
    {
        $headerL = 24.0;
        $headerR = 24.0;
        $top = 24.0;
        $logoW = 86.0;
        $logoGap = 3.0;
        $family = $this->kopFontFamily();

        $kementLines = ['KEMENTERIAN PENDIDIKAN, KEBUDAYAAN,', 'RISET DAN TEKNOLOGI'];
        $unit = 'PERPUSTAKAAN FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM';
        $addressLines = [
            'Kampus A, Gedung Hasjim Asj\'arie Rawamangun, Jakarta Timur 13220',
            'Telp/Fax : (021) 4894909, E-mail : dekanfmipa@unj.ac.id, www.fmipa.unj.ac.id',
        ];

        $this->SetFont($family, '', 17);
        $textW = $this->GetStringWidth($this->txt($kementLines[0]));
        $textW = max($textW, $this->GetStringWidth($this->txt($kementLines[1])));
        $this->SetFont($family, 'B', 14);
        $textW = max($textW, $this->GetStringWidth($this->txt('UNIVERSITAS NEGERI JAKARTA')));
        $this->SetFont($family, 'B', 11);
        $textW = max($textW, $this->GetStringWidth($this->txt($unit)));
        $this->SetFont($family, '', 11);
        foreach ($addressLines as $line) {
            $textW = max($textW, $this->GetStringWidth($this->txt($line)));
        }

        $textX = $headerL + $logoW + $logoGap;
        $textW = min($textW + 1.0, self::PAGE_W - $headerR - $textX);
        $y = $top;

        // Kementerian: Times New Roman 17pt, line/paragraf 1.0 (Word Single).
        $kementH = $this->wordLineHeight(17.0, 1.0);
        $this->SetFont($family, '', 17);
        foreach ($kementLines as $line) {
            $this->SetXY($textX, $y);
            $this->Cell($textW, $kementH, $this->txt($line), 0, 1, 'C');
            $y += $kementH;
        }

        // UNJ: Times New Roman bold 14pt, 1.15 ke atas, 1.0 ke bawah.
        $y += $this->wordLineHeight(14.0, 0.15);
        $unjH = $this->wordLineHeight(14.0, 1.0);
        $this->SetFont($family, 'B', 14);
        $this->SetXY($textX, $y);
        $this->Cell($textW, $unjH, $this->txt('UNIVERSITAS NEGERI JAKARTA'), 0, 1, 'C');
        $y += $unjH;

        // Unit: Times New Roman bold 11pt, 1.0 ke atas, 1.15 ke bawah.
        $unitH = $this->wordLineHeight(11.0, 1.0);
        $this->SetFont($family, 'B', 11);
        $this->SetXY($textX, $y);
        $this->Cell($textW, $unitH, $this->txt($unit), 0, 1, 'C');
        $y += $unitH + $this->wordLineHeight(11.0, 0.15);

        // Alamat: Times New Roman 11pt, line/paragraf 1.0.
        $addrH = $this->wordLineHeight(11.0, 1.0);
        $this->SetFont($family, '', 11);
        $this->SetXY($textX, $y);
        $this->Cell($textW, $addrH, $this->txt($addressLines[0]), 0, 1, 'C');
        $y += $addrH;
        $this->drawHeaderEmailLine($family, $textX, $y, $textW, $addrH);
        $y += $addrH;

        $logoH = $logoW;
        if ($logoPath !== '' && is_file($logoPath)) {
            $info = @getimagesize($logoPath);
            if (is_array($info) && ($info[0] ?? 0) > 0) {
                $logoH = $logoW * ((float) $info[1] / (float) $info[0]);
            }

            $logoY = $top;
            $this->Image($logoPath, $headerL, $logoY, $logoW, $logoH);
        }

        $lineY = max($top + $logoH, $y) + 6.0;
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(2.4);
        $this->Line($headerL, $lineY, self::PAGE_W - $headerR, $lineY);
        $this->SetLineWidth(0.4);

        return $lineY;
    }

    private function drawHeaderEmailLine(string $family, float $textX, float $y, float $textW, float $addrH): void
    {
        $before = $this->txt('Telp/Fax : (021) 4894909, E-mail : ');
        $email = $this->txt('dekanfmipa@unj.ac.id');
        $after = $this->txt(', www.fmipa.unj.ac.id');

        $this->SetFont($family, '', 11);
        $beforeW = $this->GetStringWidth($before);
        $this->SetFont($family, 'U', 11);
        $emailW = $this->GetStringWidth($email);
        $this->SetFont($family, '', 11);
        $afterW = $this->GetStringWidth($after);
        $x = $textX + max(0.0, ($textW - ($beforeW + $emailW + $afterW)) / 2);
        $savedMargin = $this->cMargin;
        $this->cMargin = 0.0;

        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $y);
        $this->Cell($beforeW, $addrH, $before, 0, 0, 'L');

        $this->SetTextColor(5, 99, 193);
        $this->SetFont($family, 'U', 11);
        $this->Cell($emailW, $addrH, $email, 0, 0, 'L', false, 'mailto:dekanfmipa@unj.ac.id');

        $this->SetTextColor(0, 0, 0);
        $this->SetFont($family, '', 11);
        $this->Cell($afterW, $addrH, $after, 0, 0, 'L');

        $this->cMargin = $savedMargin;
    }

    /**
     * Word Single ≈ 1.15em; multiplier 1.0 / 1.15 mengikuti spasi baris kop.
     */
    private function wordLineHeight(float $fontSize, float $spacing): float
    {
        return $fontSize * 1.15 * $spacing;
    }

    /**
     * Times New Roman tersemat jika file font sudah digenerate; jika tidak, Times PDF.
     */
    private function kopFontFamily(): string
    {
        $dir = $this->fontDir();
        $regular = $dir.DIRECTORY_SEPARATOR.'timesnewroman.json';
        $bold = $dir.DIRECTORY_SEPARATOR.'timesnewromanb.json';

        if (! is_file($regular) || ! is_file($bold)) {
            return 'Times';
        }

        $this->AddFont('TimesNewRoman', '', 'timesnewroman.json', $dir);
        $this->AddFont('TimesNewRoman', 'B', 'timesnewromanb.json', $dir);

        return 'TimesNewRoman';
    }

    /**
     * Cambria (Headings) untuk isi formulir; fallback Times jika file font belum ada.
     */
    private function bodyFontFamily(): string
    {
        $dir = $this->fontDir();
        $regular = $dir.DIRECTORY_SEPARATOR.'cambria.json';
        $bold = $dir.DIRECTORY_SEPARATOR.'cambriab.json';
        $italic = $dir.DIRECTORY_SEPARATOR.'cambriai.json';

        if (! is_file($regular) || ! is_file($bold) || ! is_file($italic)) {
            return 'Times';
        }

        $this->AddFont('Cambria', '', 'cambria.json', $dir);
        $this->AddFont('Cambria', 'B', 'cambriab.json', $dir);
        $this->AddFont('Cambria', 'I', 'cambriai.json', $dir);

        return 'Cambria';
    }

    private function fontDir(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'fonts';
    }

    private function bodyLineHeight(): float
    {
        return $this->wordLineHeight(12.0, 2.0);
    }

    private function drawField(
        float $y,
        string $label,
        string $value,
        float $valueX,
        float $valueW,
        bool $bold = false
    ): float {
        $family = $this->bodyFontFamily();
        $lineH = $this->bodyLineHeight();

        $style = $bold ? 'B' : '';
        $this->SetFont($family, $style, 12);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, $lineH, $this->txt($label), 0, 0, 'L');
        $this->Cell(self::COLON_W, $lineH, ':', 0, 0, 'L');
        $this->SetXY($valueX, $y);
        $this->Cell($valueW, $lineH, $this->truncate($value, $valueW), 0, 0, 'L');

        return $y + $lineH;
    }

    private function drawWrappedField(
        float $y,
        string $label,
        string $value,
        float $valueX,
        float $valueW,
        int $maxLines
    ): float {
        $family = $this->bodyFontFamily();
        $lineH = $this->bodyLineHeight();
        $this->SetFont($family, '', 12);
        $lines = $this->wrapText($value, $valueW, $maxLines);
        $used = 1;

        foreach ($lines as $index => $line) {
            if ($index > 0 && $line === '') {
                break;
            }

            $used = $index + 1;
        }

        $this->SetFont($family, '', 12);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, $lineH, $this->txt($label), 0, 0, 'L');
        $this->Cell(self::COLON_W, $lineH, ':', 0, 0, 'L');

        for ($index = 0; $index < $used; $index++) {
            $this->SetXY($valueX, $y + ($index * $lineH));
            $this->Cell($valueW, $lineH, $lines[$index], 0, 0, 'L');
        }

        return $y + (max($used, 2) * $lineH);
    }

    private function drawGenderRow(float $y, string $gender, float $valueX): float
    {
        $family = $this->bodyFontFamily();
        $lineH = $this->bodyLineHeight();

        $this->SetFont($family, '', 12);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, $lineH, $this->txt('Jenis Kelamin'), 0, 0, 'L');
        $this->Cell(self::COLON_W, $lineH, ':', 0, 0, 'L');

        $prefixP = $this->txt('P');
        $prefixUntilL = $this->txt('P / ');
        $letterL = $this->txt('L');
        $genderText = $this->txt('P / L (coret yang tidak perlu)');
        $this->SetXY($valueX, $y);
        $this->Cell($this->GetStringWidth($genderText) + (2 * $this->cMargin), $lineH, $genderText, 0, 0, 'L');

        $textX = $valueX + $this->cMargin;
        $baseline = $y + (0.5 * $lineH) + (0.3 * $this->FontSize);
        $strikeY = $baseline - (0.32 * $this->FontSize);
        $pW = $this->GetStringWidth($prefixP);
        $lX = $textX + $this->GetStringWidth($prefixUntilL);
        $lW = $this->GetStringWidth($letterL);
        $pad = 0.7;

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(1.15);

        if ($this->isMaleGender($gender)) {
            $this->Line($textX - $pad, $strikeY, $textX + $pW + $pad, $strikeY);
        } elseif ($this->isFemaleGender($gender)) {
            $this->Line($lX - $pad, $strikeY, $lX + $lW + $pad, $strikeY);
        }

        $this->SetLineWidth(0.4);

        return $y + $lineH;
    }

    private function isMaleGender(string $gender): bool
    {
        $normalized = mb_strtolower(trim($gender));

        return in_array($normalized, ['laki-laki', 'laki laki', 'l', 'male', 'pria'], true);
    }

    private function isFemaleGender(string $gender): bool
    {
        $normalized = mb_strtolower(trim($gender));

        return in_array($normalized, ['perempuan', 'p', 'female', 'wanita'], true);
    }

    private function drawVerification(float $y): void
    {
        $family = $this->bodyFontFamily();
        $lineH = $this->bodyLineHeight();

        $this->SetFont($family, 'I', 12);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(300.0, $lineH, $this->txt('Diverifikasi oleh Petugas'), 0, 1, 'L');

        $lineH = $this->wordLineHeight(13.0, 2.0);
        $box = 17.0;
        $items = [
            'Kelengkapan: FC KTM/KTP, Pas Foto 3x4, Form',
            'Registrasi',
            'Pencetakan Kartu',
        ];

        $rowY = $this->GetY();
        $this->SetFont($family, 'B', 13);

        foreach ($items as $item) {
            $this->SetLineWidth(0.8);
            $this->Rect(self::MARGIN_L, $rowY + (($lineH - $box) / 2), $box, $box);
            $this->SetLineWidth(0.4);
            $this->SetXY(self::MARGIN_L + $box + 10.0, $rowY);
            $this->Cell(400.0, $lineH, $this->txt($item), 0, 0, 'L');
            $rowY += $lineH;
        }
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, float $maxWidth, int $maxLines): array
    {
        $text = $this->txt(trim($text));
        $lines = array_fill(0, $maxLines, '');

        if ($text === '') {
            return $lines;
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $current = '';
        $lineIndex = 0;

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->GetStringWidth($candidate) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($lineIndex >= $maxLines - 1) {
                $current = $this->truncate($candidate, $maxWidth);
                break;
            }

            $lines[$lineIndex] = $current;
            $lineIndex++;
            $current = $word;
        }

        $lines[$lineIndex] = $current;

        return $lines;
    }
}
