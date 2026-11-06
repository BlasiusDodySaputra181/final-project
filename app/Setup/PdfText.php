<?php

namespace App\Setup;

/**
 * Konversi teks UTF-8 ke Latin-1 untuk FPDF + pemotongan lebar string.
 *
 * @mixin \FPDF
 */
trait PdfText
{
    protected function truncate(string $text, float $maxWidth): string
    {
        $text = $this->txt(trim($text));

        if ($text === '') {
            return '';
        }

        if ($this->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }

        while ($text !== '' && $this->GetStringWidth($text.'...') > $maxWidth) {
            $text = substr($text, 0, -1);
        }

        return $text.'...';
    }

    protected function txt(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

        return $converted !== false ? $converted : (preg_replace('/[^\x20-\x7E]/', '?', $text) ?: '');
    }
}
