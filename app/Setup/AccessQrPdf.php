<?php

namespace App\Setup;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * PDF A4 landscape: QR akses dengan batik sidebar yang dipudar.
 */
class AccessQrPdf extends \FPDF
{
    use PdfText;

    private const MARGIN = 16.0;

    private const QR_SIZE_MM = 96.0;

    private const FRAME_PAD = 9.0;

    private const LOGO_RATIO = 0.22;

    /** Ungu tua — modul QR, kontras cukup untuk dipindai */
    private const INK = [45, 27, 70];

    /** Ungu bidang */
    private const PURPLE = [93, 19, 133];

    /** Emas aksen, sama dengan garis kartu dasbor */
    private const GOLD = [212, 177, 90];

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4');
        $this->SetMargins(self::MARGIN, self::MARGIN);
        $this->SetAutoPageBreak(false);
    }

    public function build(string $appUrl, string $logoPath): string
    {
        if ($appUrl === '') {
            throw new \InvalidArgumentException('Tautan login untuk QR akses kosong.');
        }

        $qrPng = $this->createQrPng($appUrl, $logoPath);
        $tmpQr = tempnam(sys_get_temp_dir(), 'arsip-ta-qr-').'.png';
        file_put_contents($tmpQr, $qrPng);

        $header = $this->headerBackdrop();

        try {
            $this->AddPage();
            $this->drawBackground($header);
            $this->drawContent($tmpQr, $appUrl);
        } finally {
            @unlink($tmpQr);
            if (is_string($header)) {
                @unlink($header);
            }
        }

        return $this->Output('S');
    }

    private function createQrPng(string $appUrl, string $logoPath): string
    {
        $preparedLogo = $this->prepareLogoOnWhite($logoPath);
        $qrSizePx = 720;
        $logoWidth = (int) round($qrSizePx * self::LOGO_RATIO);

        try {
            $hasLogo = is_file($preparedLogo);

            $builder = new Builder(
                writer: new PngWriter,
                writerOptions: [],
                validateResult: false,
                data: $appUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $qrSizePx,
                margin: 18,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(self::INK[0], self::INK[1], self::INK[2]),
                backgroundColor: new Color(255, 255, 255),
                logoPath: $hasLogo ? $preparedLogo : '',
                logoResizeToWidth: $hasLogo ? $logoWidth : null,
                logoPunchoutBackground: $hasLogo,
            );

            return $builder->build()->getString();
        } finally {
            if ($preparedLogo !== $logoPath) {
                @unlink($preparedLogo);
            }
        }
    }

    /**
     * Ganti latar hitam logo UNJ menjadi putih agar cocok di tengah QR.
     */
    private function prepareLogoOnWhite(string $logoPath): string
    {
        if (! is_file($logoPath) || ! function_exists('imagecreatefrompng')) {
            return $logoPath;
        }

        $source = @imagecreatefrompng($logoPath);

        if ($source === false) {
            return $logoPath;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($source, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($a > 100 || ($r < 28 && $g < 28 && $b < 28)) {
                    continue;
                }

                $color = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $x, $y, $color);
            }
        }

        unset($source);

        $tmp = tempnam(sys_get_temp_dir(), 'unj-logo-').'.png';
        imagepng($canvas, $tmp);
        unset($canvas);

        return $tmp;
    }

    private function drawBackground(?string $headerPath): void
    {
        $pageW = $this->GetPageWidth();
        $pageH = $this->GetPageHeight();

        if (is_string($headerPath) && is_file($headerPath)) {
            $this->Image($headerPath, 0, 0, $pageW, $pageH, 'PNG');
        } else {
            $this->SetFillColor(self::PURPLE[0], self::PURPLE[1], self::PURPLE[2]);
            $this->Rect(0, 0, $pageW, $pageH, 'F');
        }

        $this->SetFillColor(self::GOLD[0], self::GOLD[1], self::GOLD[2]);
        $this->Rect(0, 0, $pageW, 1.6, 'F');
    }

    /**
     * Batik sidebar dipotong landscape, ditutup ungu supaya warna tetap dominan.
     */
    private function headerBackdrop(): ?string
    {
        $batik = $this->loadSidebarBatik();

        if ($batik === false || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = 1680;
        $height = 1188;
        $canvas = imagecreatetruecolor($width, $height);
        $this->coverCrop($canvas, $batik, $width, $height);
        unset($batik);

        $veil = imagecreatetruecolor($width, $height);
        $purple = imagecolorallocate($veil, self::PURPLE[0], self::PURPLE[1], self::PURPLE[2]);
        imagefill($veil, 0, 0, $purple);
        imagecopymerge($canvas, $veil, 0, 0, 0, 0, $width, $height, 94);
        unset($veil);

        $circles = imagecreatetruecolor($width, $height);
        imagecopy($circles, $canvas, 0, 0, 0, 0, $width, $height);
        $white = imagecolorallocate($circles, 255, 255, 255);
        imagefilledellipse($circles, (int) ($width * 0.08), (int) ($height * 0.12), 420, 420, $white);
        imagefilledellipse($circles, (int) ($width * 0.22), (int) ($height * 0.92), 300, 300, $white);
        imagecopymerge($canvas, $circles, 0, 0, 0, 0, $width, $height, 10);
        unset($circles);

        $tmp = tempnam(sys_get_temp_dir(), 'arsip-ta-qr-batik-').'.png';
        imagepng($canvas, $tmp);
        unset($canvas);

        return $tmp;
    }

    private function loadSidebarBatik(): \GdImage|false
    {
        $webp = public_path('images/batik.webp');
        $png = public_path('images/batik.png');

        if (is_file($webp) && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($webp);

            if ($image !== false) {
                return $image;
            }
        }

        if (is_file($png) && function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($png);

            if ($image !== false) {
                return $image;
            }
        }

        return false;
    }

    private function coverCrop(\GdImage $canvas, \GdImage $source, int $width, int $height): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $targetRatio = $width / $height;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
            $srcX = (int) (($srcW - $cropW) / 2);
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
            $srcX = 0;
            $srcY = (int) max(0, ($srcH - $cropH) / 2);
        }

        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $width, $height, $cropW, $cropH);
    }

    private function drawContent(string $qrPath, string $appUrl): void
    {
        $pageW = $this->GetPageWidth();
        $pageH = $this->GetPageHeight();
        $qrSize = self::QR_SIZE_MM;
        $pad = self::FRAME_PAD;
        $urlH = 8.0;
        $frameW = $qrSize + ($pad * 2);
        $frameH = $pad + $qrSize + 5 + $urlH + $pad;
        $frameX = $pageW - self::MARGIN - $frameW;
        $frameY = ($pageH - $frameH) / 2;

        $textW = $frameX - (self::MARGIN * 2);
        $textX = self::MARGIN;

        $this->SetTextColor(255, 244, 196);
        $this->SetFont('Helvetica', 'B', 26);
        $labelY = $frameY + 6;
        $this->SetXY($textX, $labelY);
        $this->Cell($textW, 12, $this->txt('PERPUSTAKAAN FMIPA UNJ'), 0, 1, 'L');

        $this->SetFillColor(self::GOLD[0], self::GOLD[1], self::GOLD[2]);
        $this->Rect($textX, $labelY + 13.5, 46, 1.6, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 18);
        $this->SetXY($textX, $labelY + 20);
        $this->MultiCell($textW, 8, $this->txt('QR Code Akses'), 0, 'L');

        $this->SetFont('Helvetica', '', 12);
        $this->SetXY($textX, $this->GetY() + 4);
        $this->MultiCell($textW, 6.5, $this->txt('Pindai kode ini untuk membuka aplikasi tanpa mengetik alamat.'), 0, 'L');

        $this->SetFont('Helvetica', '', 10);
        $this->SetXY($textX, $pageH - 24);
        $this->MultiCell($textW, 5.5, $this->txt('Perpustakaan FMIPA Universitas Negeri Jakarta'), 0, 'L');

        $this->SetFillColor(232, 223, 241);
        $this->RoundedRect($frameX + 1.4, $frameY + 1.6, $frameW, $frameH, 6, 'F');

        $this->SetFillColor(self::GOLD[0], self::GOLD[1], self::GOLD[2]);
        $this->RoundedRect($frameX, $frameY, $frameW, $frameH, 6, 'F');

        $this->SetFillColor(255, 255, 255);
        $this->RoundedRect($frameX, $frameY + 1.5, $frameW, $frameH - 1.5, 6, 'F');

        $this->Image(
            $qrPath,
            $frameX + $pad,
            $frameY + $pad + 1.5,
            $qrSize,
            $qrSize,
            'PNG',
            $appUrl
        );

        $this->SetTextColor(self::PURPLE[0], self::PURPLE[1], self::PURPLE[2]);
        $this->SetFont('Helvetica', '', 9);
        $url = $this->truncate($appUrl, $frameW - 12);
        $this->SetXY($frameX, $frameY + $pad + $qrSize + 6);
        $this->Cell($frameW, $urlH, $url, 0, 1, 'C', false, $appUrl);
    }

    /**
     * Persegi panjang membulat sederhana (aproksimasi sudut).
     */
    private function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k = $this->k;
        $hp = $this->h;
        $op = 'S';

        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        }

        $MyArc = 4 / 3 * (M_SQRT2 - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }
}
