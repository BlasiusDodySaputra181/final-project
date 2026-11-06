<?php

namespace App\Setup;

/**
 * Lembar stiker barcode TA — A4 landscape, 15 stiker per halaman (3 kolom × 5 baris).
 *
 * Batang dibuat lebar agar terbaca scanner, ponsel, atau kamera.
 * Tiap kotak dipotong lalu ditempel sebagai pengenal tugas akhir.
 */
class BarcodePdf extends \FPDF
{
    use PdfText {
        truncate as private truncatePdfText;
    }

    public const COLUMNS = 3;

    public const ROWS = 5;

    private const MARGIN = 5.0;

    private const GAP_X = 2.0;

    private const GAP_Y = 1.6;

    private const STICKER_WIDTH = 92.0;

    private const BAR_HEIGHT = 22.0;

    private const BAR_ASPECT = 3.8;

    private const PAD = 2.6;

    private const QUIET_ZONE_MODULES = 10;

    private Code128Barcode $encoder;

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4');
        $this->encoder = new Code128Barcode;
        $this->SetMargins(self::MARGIN, self::MARGIN);
        $this->SetAutoPageBreak(false);
        $this->AliasNbPages();
    }

    public static function cellsPerPage(): int
    {
        return self::COLUMNS * self::ROWS;
    }

    /**
     * @param  list<array{label: string, barcode: string}>  $items
     */
    public function build(array $items): string
    {
        return $this->buildFromIterable($items, count($items));
    }

    /**
     * Bangun PDF dari iterator (cursor) tanpa menampung seluruh baris di memori.
     *
     * @param  iterable<array{label?: string, barcode?: string}|object>  $items
     */
    public function buildFromIterable(iterable $items, int $total): string
    {
        $this->AddPage();

        if ($total <= 0) {
            $this->drawEmptyNotice();

            return $this->Output('S');
        }

        $stickerH = $this->stickerHeight();
        $cellsPerPage = self::cellsPerPage();
        $index = 0;

        foreach ($items as $item) {
            $payload = $this->normalizeItem($item);

            if ($payload['barcode'] === '') {
                continue;
            }

            $position = $index % $cellsPerPage;

            if ($index > 0 && $position === 0) {
                $this->AddPage();
            }

            $row = intdiv($position, self::COLUMNS);
            $col = $position % self::COLUMNS;
            $x = $this->stickerGridOriginX() + ($col * (self::STICKER_WIDTH + self::GAP_X));
            $y = $this->stickerGridOriginY() + ($row * ($stickerH + self::GAP_Y));
            $this->drawCell($x, $y, $payload);
            $index++;
        }

        if ($index === 0) {
            $this->drawEmptyNotice();
        }

        return $this->Output('S');
    }

    /**
     * @param  array{label?: string, barcode?: string}|object  $item
     * @return array{label: string, barcode: string}
     */
    private function normalizeItem(array|object $item): array
    {
        if (is_array($item)) {
            return [
                'label' => (string) ($item['label'] ?? ''),
                'barcode' => (string) ($item['barcode'] ?? ''),
            ];
        }

        return [
            'label' => (string) ($item->label ?? ''),
            'barcode' => (string) ($item->barcode ?? ''),
        ];
    }

    private function stickerHeight(): float
    {
        return (self::PAD * 2) + self::BAR_HEIGHT + 10.4;
    }

    public function stickerGridWidth(): float
    {
        return (self::COLUMNS * self::STICKER_WIDTH) + ((self::COLUMNS - 1) * self::GAP_X);
    }

    public function stickerGridHeight(): float
    {
        return (self::ROWS * $this->stickerHeight()) + ((self::ROWS - 1) * self::GAP_Y);
    }

    public function stickerGridOriginX(): float
    {
        return ($this->GetPageWidth() - $this->stickerGridWidth()) / 2;
    }

    public function stickerGridOriginY(): float
    {
        return ($this->GetPageHeight() - $this->stickerGridHeight()) / 2;
    }

    private function drawEmptyNotice(): void
    {
        $this->SetFont('Helvetica', '', 12);
        $this->SetXY(self::MARGIN, 50);
        $this->Cell(0, 8, 'Tidak ada data barcode untuk diunduh.', 0, 1, 'C');
    }

    /**
     * @param  array{label: string, barcode: string}  $item
     */
    private function drawCell(float $x, float $y, array $item): void
    {
        $stickerW = self::STICKER_WIDTH;
        $barHeight = self::BAR_HEIGHT;
        $stickerH = $this->stickerHeight();

        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(90, 90, 90);
        $this->SetLineWidth(0.22);
        $this->setDash(1.4, 1.0);
        $this->Rect($x, $y, $stickerW, $stickerH, 'DF');
        $this->setDash();

        $innerX = $x + self::PAD;
        $innerW = $stickerW - (self::PAD * 2);
        $barWidth = min($innerW, $barHeight * self::BAR_ASPECT);
        $barX = $x + (($stickerW - $barWidth) / 2);
        $barY = $y + self::PAD;
        $code = (string) $item['barcode'];
        $label = (string) $item['label'];

        $this->drawBarcode($barX, $barY, $barWidth, $barHeight, $code);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', '', 10);
        $this->SetXY($innerX, $barY + $barHeight + 1.4);
        $this->Cell($innerW, 4.4, $this->truncate($code, $innerW), 0, 2, 'C');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetX($innerX);
        $this->Cell($innerW, 4.4, $this->truncate($label, $innerW), 0, 0, 'C');
    }

    private function drawBarcode(float $x, float $y, float $width, float $height, string $code): void
    {
        $widths = $this->encoder->moduleWidths($code);
        $modules = array_sum($widths);

        if ($modules <= 0) {
            return;
        }

        $totalModules = $modules + (self::QUIET_ZONE_MODULES * 2);
        $moduleWidth = $width / $totalModules;
        $cursor = $x + (self::QUIET_ZONE_MODULES * $moduleWidth);
        $bar = true;

        $this->SetFillColor(0, 0, 0);

        foreach ($widths as $w) {
            $barWidth = $w * $moduleWidth;

            if ($bar) {
                $this->Rect($cursor, $y, $barWidth, $height, 'F');
            }

            $cursor += $barWidth;
            $bar = ! $bar;
        }
    }

    private function setDash(?float $black = null, ?float $white = null): void
    {
        if ($black !== null && $white !== null) {
            $this->_out(sprintf('[%.3F %.3F] 0 d', $black * $this->k, $white * $this->k));

            return;
        }

        $this->_out('[] 0 d');
    }

    protected function truncate(string $text, float $maxWidth): string
    {
        $text = $this->truncatePdfText($text, $maxWidth);

        return $text === '' ? '-' : $text;
    }
}
