<?php

namespace App\Support;

class SimplePdf
{
    private string $content = '';

    public function __construct(
        private readonly float $width = 595.28,
        private readonly float $height = 841.89,
    ) {
    }

    public function text(float $x, float $y, string $text, int $size = 10, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->content .= sprintf(
            "BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    public function wrappedText(float $x, float $y, string $text, float $maxWidth, int $size = 10, bool $bold = false, float $lineHeight = 12): float
    {
        foreach ($this->wrap($text, $maxWidth, $size) as $line) {
            $this->text($x, $y, $line, $size, $bold);
            $y -= $lineHeight;
        }

        return $y;
    }

    public function rect(float $x, float $y, float $width, float $height, bool $fill = false): void
    {
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $y, $width, $height, $fill ? 'f' : 'S');
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->content .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    }

    public function lineWidth(float $width): void
    {
        $this->content .= sprintf("%.2F w\n", $width);
    }

    public function strokeColor(float $gray): void
    {
        $this->content .= sprintf("%.2F G\n", $gray);
    }

    public function fillColor(float $gray): void
    {
        $this->content .= sprintf("%.2F g\n", $gray);
    }

    public function output(): string
    {
        $stream = $this->content;
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
                $this->width,
                $this->height
            ),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function wrap(string $text, float $maxWidth, int $size): array
    {
        $maxChars = max(12, (int) floor($maxWidth / ($size * 0.52)));
        $words = preg_split('/\s+/', trim($this->clean($text))) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            if (strlen($candidate) > $maxChars && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $this->clean($text));
    }

    private function clean(string $text): string
    {
        $text = str_replace(['–', '—', '×', '•', '’'], ['-', '-', 'x', '-', "'"], $text);

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }
}
