<?php

namespace App\Setup;

/**
 * Tanda Terima Penyerahan Hibah Buku — format mengikuti template resmi FMIPA UNJ.
 */
class BookGrantPdf extends \FPDF
{
    use PdfText;

    private const PAGE_W = 595.28;

    private const MARGIN_L = 72.0;

    private const MARGIN_R = 54.0;

    private const LABEL_W = 120.0;

    private const COLON_W = 14.0;

    private const LINE_H = 20.0;

    public function __construct()
    {
        parent::__construct('P', 'pt', 'A4');
        $this->SetMargins(self::MARGIN_L, 48.0, self::MARGIN_R);
        $this->SetAutoPageBreak(false);
    }

    /**
     * @param  array{
     *     student_name: string,
     *     nim: string,
     *     cluster: string,
     *     study_program: string,
     *     book_title: string,
     *     author: string,
     *     book_year: string,
     *     date_label: string,
     *     officer_name: string,
     *     officer_identity_number: string
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
     *     student_name: string,
     *     nim: string,
     *     cluster: string,
     *     study_program: string,
     *     book_title: string,
     *     author: string,
     *     book_year: string,
     *     date_label: string,
     *     officer_name: string,
     *     officer_identity_number: string
     * }  $data
     */
    private function drawLetter(array $data): void
    {
        $contentW = self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
        $valueX = self::MARGIN_L + self::LABEL_W + self::COLON_W;
        $valueW = $contentW - self::LABEL_W - self::COLON_W;

        $this->SetTextColor(0, 0, 0);

        // Judul
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetXY(self::MARGIN_L, 48.0);
        $this->Cell($contentW, 18, $this->txt('TANDA TERIMA'), 0, 1, 'C');
        $this->SetX(self::MARGIN_L);
        $this->Cell($contentW, 18, $this->txt('PENYERAHAN HIBAH BUKU'), 0, 1, 'C');

        $this->Ln(14);

        $y = $this->GetY();
        $y = $this->drawField($y, 'Telah diterima buku dari', '', $valueX, $valueW);
        $y = $this->drawField($y, 'Nama', (string) $data['student_name'], $valueX, $valueW);
        $y = $this->drawField($y, 'NRM', (string) $data['nim'], $valueX, $valueW);
        $y = $this->drawField($y, 'Jurusan', (string) $data['cluster'], $valueX, $valueW);
        $y = $this->drawField($y, 'Program Studi', (string) $data['study_program'], $valueX, $valueW);

        // Judul Buku — dua baris seperti template
        $titleLines = $this->wrapText((string) $data['book_title'], $valueW, 2);
        $this->SetFont('Helvetica', '', 11);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, self::LINE_H, $this->txt('Judul Buku'), 0, 0, 'L');
        $this->Cell(self::COLON_W, self::LINE_H, ':', 0, 0, 'L');
        $this->SetXY($valueX, $y);
        $this->Cell($valueW, self::LINE_H, $titleLines[0] ?? '', 0, 0, 'L');
        $y += self::LINE_H;

        $this->SetXY($valueX, $y);
        $this->Cell($valueW, self::LINE_H, $titleLines[1] ?? '', 0, 0, 'L');
        $y += self::LINE_H;

        $y = $this->drawField($y, 'Pengarang', (string) $data['author'], $valueX, $valueW);
        $yearW = min(120.0, $valueW);
        $y = $this->drawField($y, 'Tahun', (string) $data['book_year'], $valueX, $yearW);

        // Blok tanggal & petugas (kanan, text-align left), rata tepi konten A4
        $sigW = 170.0;
        $sigX = self::PAGE_W - self::MARGIN_R - $sigW;
        $sigY = $y + 28.0;

        $this->SetFont('Helvetica', '', 11);
        $this->SetXY($sigX, $sigY);
        $this->Cell($sigW, 14, $this->txt('Jakarta, '.(string) $data['date_label']), 0, 1, 'L');

        $this->SetXY($sigX, $sigY + 18.0);
        $this->Cell($sigW, 14, $this->txt('Petugas Perpustakaan FMIPA'), 0, 1, 'L');

        $nameY = $sigY + 78.0;
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetXY($sigX, $nameY);
        $this->Cell($sigW, 14, $this->txt((string) $data['officer_name']), 0, 1, 'L');

        $this->SetXY($sigX, $nameY + 14.0);
        $this->Cell($sigW, 14, $this->txt('NIP. '.(string) $data['officer_identity_number']), 0, 1, 'L');
    }

    private function drawField(
        float $y,
        string $label,
        string $value,
        float $valueX,
        float $valueW
    ): float {
        $this->SetFont('Helvetica', '', 11);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, self::LINE_H, $this->txt($label), 0, 0, 'L');
        $this->Cell(self::COLON_W, self::LINE_H, ':', 0, 0, 'L');

        if ($value !== '') {
            $this->SetXY($valueX, $y);
            $this->Cell($valueW, self::LINE_H, $this->truncate($value, $valueW), 0, 0, 'L');
        }

        return $y + self::LINE_H;
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
