<?php

namespace App\Setup;

/**
 * Surat Keterangan Bebas Pinjaman Buku — format mengikuti template resmi FMIPA UNJ.
 */
class LibraryClearancePdf extends \FPDF
{
    use PdfText;

    private const PAGE_W = 595.28;

    private const MARGIN_L = 72.0;

    private const MARGIN_R = 72.0;

    private const LABEL_W = 110.0;

    private const COLON_W = 14.0;

    private const LINE_H = 18.0;

    public function __construct()
    {
        parent::__construct('P', 'pt', 'A4');
        $this->SetMargins(self::MARGIN_L, 54.0, self::MARGIN_R);
        $this->SetAutoPageBreak(false);
    }

    /**
     * @param  array{
     *     student_name: string,
     *     nim: string,
     *     study_program: string,
     *     degree_level: string,
     *     email: string,
     *     phone_number: string,
     *     date_label: string,
     *     coordinator_name: string,
     *     coordinator_identity_number: string,
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
     *     study_program: string,
     *     degree_level: string,
     *     email: string,
     *     phone_number: string,
     *     date_label: string,
     *     coordinator_name: string,
     *     coordinator_identity_number: string,
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
        $this->SetXY(self::MARGIN_L, 54.0);
        $this->Cell($contentW, 18, $this->txt('SURAT KETERANGAN BEBAS PINJAMAN BUKU'), 0, 1, 'C');
        $this->SetX(self::MARGIN_L);
        $this->Cell($contentW, 18, $this->txt('PERPUSTAKAAN FMIPA UNJ'), 0, 1, 'C');

        $this->Ln(12);

        // Pembuka
        $this->SetFont('Helvetica', '', 11);
        $this->SetX(self::MARGIN_L);
        $this->MultiCell(
            $contentW,
            14,
            $this->txt('Pustakawan Fakultas Matematika dan Ilmu Pengetahuan Alam Universitas Negeri Jakarta menerangkan bahwa :'),
            0,
            'J'
        );

        $this->Ln(8);
        $y = $this->GetY();

        $y = $this->drawRow($y, 'Nama', (string) $data['student_name'], $valueX, $valueW);
        $y = $this->drawRow($y, 'No. Registrasi', (string) $data['nim'], $valueX, $valueW);

        // Program Studi + jenjang (jarak ~2 tab setelah nama prodi)
        $degree = trim((string) $data['degree_level']);
        $degreeLabel = $degree !== '' ? '( '.$degree.' )' : '( S1 / S2 )';
        $this->SetFont('Helvetica', '', 11);
        $program = $this->truncate((string) $data['study_program'], $valueW - 80.0);
        $tabGap = $this->GetStringWidth('        '); // ~2 tab

        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, self::LINE_H, $this->txt('Program Studi'), 0, 0, 'L');
        $this->Cell(self::COLON_W, self::LINE_H, ':', 0, 0, 'L');
        $this->Cell($this->GetStringWidth($program), self::LINE_H, $program, 0, 0, 'L');
        $this->Cell($tabGap, self::LINE_H, '', 0, 0, 'L');
        $this->Cell($this->GetStringWidth($this->txt($degreeLabel)) + 2, self::LINE_H, $this->txt($degreeLabel), 0, 1, 'L');
        $y = $this->GetY();

        // E-mail + No HP (email fleksibel, No HP kolom kanan tetap)
        $phoneBlockW = 150.0;
        $emailW = max(80.0, $valueW - $phoneBlockW - 10.0);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, self::LINE_H, $this->txt('E-mail'), 0, 0, 'L');
        $this->Cell(self::COLON_W, self::LINE_H, ':', 0, 0, 'L');
        $this->Cell($emailW, self::LINE_H, $this->truncate((string) $data['email'], $emailW), 0, 0, 'L');

        $phoneX = $valueX + $emailW + 10.0;
        $this->SetXY($phoneX, $y);
        $phoneLabel = $this->txt('No HP : ');
        $phoneLabelW = $this->GetStringWidth($phoneLabel);
        $this->Cell($phoneLabelW, self::LINE_H, $phoneLabel, 0, 0, 'L');
        $this->Cell(
            max(40.0, $phoneBlockW - $phoneLabelW),
            self::LINE_H,
            $this->truncate((string) $data['phone_number'], max(40.0, $phoneBlockW - $phoneLabelW)),
            0,
            1,
            'L'
        );

        $this->Ln(8);
        $this->SetX(self::MARGIN_L);
        $this->MultiCell(
            $contentW,
            14,
            $this->txt('Tidak mempunyai tunggakan/pinjaman buku dari Perpustakaan Fakultas Matematika dan Ilmu Pengetahuan Alam Universitas Negeri Jakarta.'),
            0,
            'J'
        );
        $this->Ln(2);
        $this->SetX(self::MARGIN_L);
        $this->MultiCell(
            $contentW,
            14,
            $this->txt('Demikian surat keterangan ini diberikan kepada mahasiswa tersebut diatas.'),
            0,
            'L'
        );

        $this->Ln(14);

        // Dua kolom tanda tangan (text-align left), kolom kanan rata tepi konten A4
        // Tanggal tepat di atas "Petugas Perpustakaan FMIPA"
        $leftColW = 250.0;
        $rightColW = 160.0;
        $leftX = self::MARGIN_L;
        $rightX = self::PAGE_W - self::MARGIN_R - $rightColW;
        $sigY = $this->GetY();

        $this->SetFont('Helvetica', '', 11);
        $this->SetXY($rightX, $sigY);
        $this->Cell($rightColW, 14, $this->txt('Jakarta, '.(string) $data['date_label']), 0, 1, 'L');

        $this->Ln(2);
        $sigY = $this->GetY();

        $this->SetXY($leftX, $sigY);
        $this->Cell($leftColW, 14, $this->txt('Mengetahui:'), 0, 1, 'L');

        $roleY = $this->GetY();
        $this->SetXY($leftX, $roleY);
        $this->MultiCell($leftColW, 13, $this->txt('Koordinator Layanan Administrasi'), 0, 'L');
        $leftRoleBottom = $this->GetY();

        $this->SetXY($rightX, $roleY);
        $this->MultiCell($rightColW, 13, $this->txt('Petugas Perpustakaan FMIPA'), 0, 'L');

        $nameY = max($leftRoleBottom, $roleY + 13.0) + 56.0;

        $this->SetFont('Helvetica', 'B', 11);
        $this->SetXY($leftX, $nameY);
        $this->Cell($leftColW, 14, $this->txt((string) $data['coordinator_name']), 0, 0, 'L');
        $this->SetXY($rightX, $nameY);
        $this->Cell($rightColW, 14, $this->txt((string) $data['officer_name']), 0, 1, 'L');

        $this->SetXY($leftX, $nameY + 14.0);
        $this->Cell($leftColW, 14, $this->txt('NIP. '.(string) $data['coordinator_identity_number']), 0, 0, 'L');
        $this->SetXY($rightX, $nameY + 14.0);
        $this->Cell($rightColW, 14, $this->txt('NIP. '.(string) $data['officer_identity_number']), 0, 1, 'L');
    }

    private function drawRow(float $y, string $label, string $value, float $valueX, float $valueW): float
    {
        $this->SetFont('Helvetica', '', 11);
        $this->SetXY(self::MARGIN_L, $y);
        $this->Cell(self::LABEL_W, self::LINE_H, $this->txt($label), 0, 0, 'L');
        $this->Cell(self::COLON_W, self::LINE_H, ':', 0, 0, 'L');
        $this->SetXY($valueX, $y);
        $this->Cell($valueW, self::LINE_H, $this->truncate($value, $valueW), 0, 1, 'L');

        return $this->GetY();
    }
}
