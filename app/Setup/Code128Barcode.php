<?php

namespace App\Setup;

/**
 * Encoder Code128 (subset B) untuk digambar sebagai batang di FPDF.
 */
class Code128Barcode
{
    /**
     * Pola batang Code128 nilai 0–105 (lebar modul bergantian batang/spasi).
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232',
    ];

    private const START_B = 104;

    private const STOP_PATTERN = '2331112';

    /**
     * Kembalikan urutan lebar modul untuk digambar (batang/spasi bergantian, mulai batang).
     *
     * @return list<int>
     */
    public function moduleWidths(string $code): array
    {
        $code = $this->toCode128B($code);
        $values = [self::START_B];

        for ($i = 0, $len = strlen($code); $i < $len; $i++) {
            $values[] = ord($code[$i]) - 32;
        }

        $checksum = self::START_B;
        for ($i = 1, $count = count($values); $i < $count; $i++) {
            $checksum += $values[$i] * $i;
        }
        $values[] = $checksum % 103;

        $widths = [];
        foreach ($values as $value) {
            $pattern = self::PATTERNS[$value];
            for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
                $widths[] = (int) $pattern[$i];
            }
        }

        for ($i = 0, $len = strlen(self::STOP_PATTERN); $i < $len; $i++) {
            $widths[] = (int) self::STOP_PATTERN[$i];
        }

        return $widths;
    }

    /**
     * SVG vektor dengan rasio lebar/tinggi alami (bukan diregang ke penuh sel).
     */
    public function toSvg(string $code, int $quietZoneModules = 10): string
    {
        $widths = $this->moduleWidths($code);
        $modules = array_sum($widths);

        if ($modules <= 0) {
            return '';
        }

        $total = $modules + ($quietZoneModules * 2);
        $barUnits = max(1, (int) round($total / 4));
        $x = $quietZoneModules;
        $bar = true;

        $document = new \DOMDocument('1.0', 'UTF-8');
        $svg = $document->createElementNS('http://www.w3.org/2000/svg', 'svg');
        $svg->setAttribute('viewBox', "0 0 {$total} {$barUnits}");
        $svg->setAttribute('width', (string) $total);
        $svg->setAttribute('height', (string) $barUnits);
        $svg->setAttribute('preserveAspectRatio', 'xMidYMid meet');
        $svg->setAttribute('aria-hidden', 'true');
        $document->appendChild($svg);

        foreach ($widths as $width) {
            if ($bar) {
                $rect = $document->createElementNS('http://www.w3.org/2000/svg', 'rect');
                $rect->setAttribute('x', (string) $x);
                $rect->setAttribute('y', '0');
                $rect->setAttribute('width', (string) $width);
                $rect->setAttribute('height', (string) $barUnits);
                $svg->appendChild($rect);
            }

            $x += $width;
            $bar = ! $bar;
        }

        return $document->saveXML($svg) ?: '';
    }

    private function toCode128B(string $code): string
    {
        $normalized = '';

        for ($i = 0, $len = strlen($code); $i < $len; $i++) {
            $ord = ord($code[$i]);
            $normalized .= ($ord >= 32 && $ord <= 126) ? $code[$i] : '?';
        }

        return $normalized !== '' ? $normalized : '?';
    }
}
