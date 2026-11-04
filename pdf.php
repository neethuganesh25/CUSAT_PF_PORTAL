<?php
// Minimal PDF writer: no libraries needed. Uses the built-in Helvetica fonts.
// Supports text, lines, filled boxes and one JPEG picture (the logo). Page size: A4 landscape.
class SimplePdf
{
    public float $w = 841.89;   // A4 landscape, in points (1 pt = 1/72 inch)
    public float $h = 595.28;

    private array $pages = [];       // one content stream per page
    private int $cur = -1;
    private ?array $img = null;      // ['data' => jpeg bytes, 'w' => px, 'h' => px]

    // Helvetica character widths (per 1000 units) for ASCII 32..126
    private static array $cw = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,
    ];

    public function addPage(): int { $this->pages[] = ''; return ++$this->cur; }
    public function pageCount(): int { return count($this->pages); }
    public function currentPage(): int { return $this->cur; }

    public function setImage(string $jpegData, int $pxW, int $pxH): void
    {
        $this->img = ['data' => $jpegData, 'w' => $pxW, 'h' => $pxH];
    }

    // The core fonts only hold plain Latin letters: anything else becomes "?"
    public function clean(string $s): string
    {
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) { $s = $t; }
        }
        return preg_replace('/[^\x20-\x7E]/', '?', $s);
    }

    public function width(string $s, float $size, bool $bold = false): float
    {
        $s = $this->clean($s);
        $t = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) { $t += self::$cw[ord($s[$i]) - 32] ?? 556; }
        return $t * $size / 1000 * ($bold ? 1.07 : 1.0);
    }

    // Shorten text with "..." so it fits in $maxW
    public function fit(string $s, float $maxW, float $size, bool $bold = false): string
    {
        $s = $this->clean($s);
        if ($this->width($s, $size, $bold) <= $maxW) { return $s; }
        while ($s !== '' && $this->width($s . '...', $size, $bold) > $maxW) { $s = substr($s, 0, -1); }
        return $s . '...';
    }

    private function put(string $s, ?int $page): void
    {
        $this->pages[$page ?? $this->cur] .= $s . "\n";
    }

    private function rgb(array $c, string $op): string
    {
        return sprintf('%.3F %.3F %.3F %s', $c[0], $c[1], $c[2], $op);
    }

    // $y is the text baseline measured from the TOP of the page.
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false,
                         string $align = 'L', array $color = [0, 0, 0], ?int $page = null): void
    {
        $s = $this->clean($s);
        $wd = $this->width($s, $size, $bold);
        if ($align === 'R') { $x -= $wd; } elseif ($align === 'C') { $x -= $wd / 2; }
        $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        $this->put(sprintf('BT %s /%s %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $this->rgb($color, 'rg'), $bold ? 'F2' : 'F1', $size, $x, $this->h - $y, $s), $page);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $lw = 0.5,
                         array $color = [0, 0, 0], ?int $page = null): void
    {
        $this->put(sprintf('%s %.2F w %.2F %.2F m %.2F %.2F l S', $this->rgb($color, 'RG'),
            $lw, $x1, $this->h - $y1, $x2, $this->h - $y2), $page);
    }

    // Box with its top-left corner at ($x, $y)
    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null,
                         ?array $stroke = null, float $lw = 0.5): void
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $s = '';
        if ($fill)   { $s .= $this->rgb($fill, 'rg') . ' '; }
        if ($stroke) { $s .= $this->rgb($stroke, 'RG') . sprintf(' %.2F w ', $lw); }
        $this->put(sprintf('%s%.2F %.2F %.2F %.2F re %s', $s, $x, $this->h - $y - $h, $w, $h, $op), null);
    }

    public function image(float $x, float $y, float $w, float $h): void
    {
        $this->put(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q', $w, $h, $x, $this->h - $y - $h), null);
    }

    public function output(): string
    {
        $n = count($this->pages);
        $hasImg = $this->img !== null;
        $first = $hasImg ? 6 : 5;               // object number of the first page
        $o = [];
        $kids = [];
        for ($i = 0; $i < $n; $i++) { $kids[] = ($first + 2 * $i) . ' 0 R'; }

        $o[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $o[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $o[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $o[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        if ($hasImg) {
            $o[5] = '<< /Type /XObject /Subtype /Image /Width ' . $this->img['w'] . ' /Height ' . $this->img['h']
                  . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                  . strlen($this->img['data']) . " >>\nstream\n" . $this->img['data'] . "\nendstream";
        }
        $xo = $hasImg ? ' /XObject << /Im1 5 0 R >>' : '';
        for ($i = 0; $i < $n; $i++) {
            $p = $first + 2 * $i;
            $o[$p] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >>%s >> /Contents %d 0 R >>',
                $this->w, $this->h, $xo, $p + 1);
            $o[$p + 1] = '<< /Length ' . strlen($this->pages[$i]) . " >>\nstream\n" . $this->pages[$i] . 'endstream';
        }

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($o);
        foreach ($o as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $count = max(array_keys($o)) + 1;
        $out .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) { $out .= sprintf("%010d 00000 n \n", $offsets[$i]); }
        $out .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}
