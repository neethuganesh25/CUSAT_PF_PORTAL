<?php
// Downloads the logged-in employee's salary details as a paper-style PDF (A4, landscape).
require 'config.php';
require 'pdf.php';

$pf = require_login();
try {
    $emp = fetch_employee($pf);
    if (!$emp) { http_response_code(404); exit('Employee not found.'); }
    if (!is_permanent($emp)) { http_response_code(403); exit(NOT_PERMANENT_MSG); }
    $rows = fetch_salary($pf, null, null);
} catch (Throwable $e) { http_response_code(500); exit('Database error.'); }
log_event($pf, 'download');

function dmy($d): string { return ($d && $d !== '0000-00-00') ? date('d-m-Y', strtotime($d)) : '-'; }
function amt($x): string { return ($x === null || $x === '') ? '-' : number_format((float)$x, 2); }

// Logo (assets/cusat-logo.png) as JPEG bytes for the PDF. Needs PHP's GD extension; skipped quietly if missing.
function logo_jpeg(string $path): ?array {
    if (!is_file($path) || !function_exists('imagecreatefrompng')) { return null; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $src = $ext === 'png' ? @imagecreatefrompng($path)
         : (in_array($ext, ['jpg', 'jpeg'], true) ? @imagecreatefromjpeg($path) : false);
    if (!$src) { return null; }
    $w = imagesx($src); $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // transparent areas become white
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    ob_start(); imagejpeg($dst, null, 92); $data = ob_get_clean();
    return ['data' => $data, 'w' => $w, 'h' => $h];
}

// ---------- layout ----------
$NAVY = [0.07, 0.20, 0.36]; $GOLD = [0.78, 0.59, 0.18]; $GREY = [0.38, 0.40, 0.44];
$LINE = [0.75, 0.78, 0.82]; $ZEBRA = [0.955, 0.965, 0.98]; $WHITE = [1, 1, 1]; $CREAM = [1, 0.97, 0.88];
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

$pdf = new SimplePdf();
$ML = 36; $CW = $pdf->w - 2 * $ML;
$FOOT = $pdf->h - 44;
$ROW = 15; $HEAD = 19;

function table_header(SimplePdf $pdf, array $cols, float $x0, float $y, array $NAVY, array $WHITE, int $HEAD): float {
    $tw = array_sum(array_column($cols, 1));
    $pdf->rect($x0, $y, $tw, $HEAD, $NAVY);
    $x = $x0;
    foreach ($cols as [$label, $w, $al]) {
        if ($al === 'R') { $pdf->text($x + $w - 6, $y + 13, $label, 8.5, true, 'R', $WHITE); }
        else             { $pdf->text($x + 6, $y + 13, $label, 8.5, true, 'L', $WHITE); }
        $x += $w;
    }
    return $y + $HEAD;
}

// ---------- header ----------
$pdf->addPage();
$logo = logo_jpeg(__DIR__ . '/assets/cusat-logo.png');
if ($logo) {
    $lh = 46; $lw = min(120, $lh * $logo['w'] / $logo['h']); $lh = $lw * $logo['h'] / $logo['w'];
    $pdf->setImage($logo['data'], $logo['w'], $logo['h']);
    $pdf->image($ML, 30, $lw, $lh);
}
$pdf->text($pdf->w / 2, 52, 'Cochin University of Science and Technology', 17, true, 'C', $NAVY);
$pdf->text($pdf->w / 2, 72, 'Salary and Provident Fund Details', 11, false, 'C', $GREY);
$pdf->line($ML, 86, $ML + $CW, 86, 1.5, $GOLD);

// ---------- employee details box ----------
$type = employee_type($emp);
$cells = [
    ['Employee Name', $emp['name']], ['Employee Number', $emp['pf_no']], ['Employee Type', $type], ['Pay Scale', $emp['payscaledetails'] ?: '-'],
    ['Designation', $emp['desgdetails'] ?: '-'], ['Department', $emp['deptdetails'] ?: '-'],
    ['Date of Birth', dmy($emp['dob'])], ['Date of Joining', dmy($emp['doi'])],
];
$nRows = (int)ceil(count($cells) / 4);
$boxY = 98; $rowH = 36; $boxH = $nRows * $rowH + 8; $colW = $CW / 4;
$pdf->rect($ML, $boxY, $CW, $boxH, [0.975, 0.98, 0.99], $LINE);
foreach ($cells as $i => [$label, $value]) {
    $x = $ML + ($i % 4) * $colW + 10;
    $y = $boxY + 8 + intdiv($i, 4) * $rowH;
    $pdf->text($x, $y + 8, strtoupper($label), 7, true, 'L', $GREY);
    $pdf->text($x, $y + 22, $pdf->fit((string)$value, $colW - 18, 10.5, true), 10.5, true);
}

// ---------- current monthly pay (from the employee master) ----------
$y = $boxY + $boxH + 20;
$pdf->text($ML, $y, 'Current monthly pay', 11, true, 'L', $NAVY);
$y += 8;
$payCols = [['Component', 330, 'L'], ['Amount (Rs.)', 140, 'R']];
$y = table_header($pdf, $payCols, $ML, $y, $NAVY, $WHITE, $HEAD);
$lines = [['Basic pay', $emp['basic']], ['Increment', $emp['increment']], ['Special pay', $emp['spl_pay']], ['Personal pay', $emp['per_pay']],
          ['Special allowance', $emp['spl_allow']], ['Other amount', $emp['otheramount']], ['Water charges', $emp['watercharges']], ['Professional tax', $emp['pt']]];
$total = (float)$emp['basic'] + (float)$emp['spl_pay'] + (float)$emp['per_pay'] + (float)$emp['spl_allow'] + (float)$emp['otheramount'];
$lines[] = ['TOTAL EARNINGS', $total, true];
if ($type === 'Permanent') {
    $lines[] = ['PF - employee share (' . (PF_EMPLOYEE_RATE * 100) . '% of basic, calculated)', round((float)$emp['basic'] * PF_EMPLOYEE_RATE, 2)];
    $lines[] = ['PF - employer share (' . (PF_EMPLOYER_RATE * 100) . '% of basic, calculated)', round((float)$emp['basic'] * PF_EMPLOYER_RATE, 2)];
}
$n = 0;
foreach ($lines as $l) {
    $bold = !empty($l[2]);
    if ($bold) { $pdf->rect($ML, $y, 470, $ROW, $CREAM); } elseif ($n % 2 === 1) { $pdf->rect($ML, $y, 470, $ROW, $ZEBRA); }
    $pdf->text($ML + 6, $y + 10.5, $l[0], 8.5, $bold);
    $pdf->text($ML + 470 - 6, $y + 10.5, amt($l[1]), 8.5, $bold, 'R');
    $pdf->line($ML, $y + $ROW, $ML + 470, $y + $ROW, 0.3, $LINE);
    $y += $ROW; $n++;
}
$y += 6;
$pdf->text($ML, $y + 6, 'Total earnings = basic + special pay + personal pay + special allowance + other amount. PF amounts are calculated, for information only.', 7.5, false, 'L', $GREY);
$y += 24;

// ---------- month-by-month history (only if there are rows) ----------
if ($rows) {
    $cols = [['Year', 48, 'L'], ['Month', 55, 'L'], ['Basic', 80, 'R'], ['DA', 75, 'R'], ['HRA', 75, 'R'],
             ['Other', 75, 'R'], ['Gross', 85, 'R'], ['PF (Employee)', 92, 'R'], ['PF (Employer)', 95, 'R'], ['Net', 85, 'R']];
    $keys = [null, null, 'basic_pay', 'da', 'hra', 'other_allowances', 'gross_salary', 'pf_employee', 'pf_employer', 'net_salary'];
    $tw = array_sum(array_column($cols, 1));
    if ($y + 60 > $FOOT) { $pdf->addPage(); $y = 50; }
    $pdf->text($ML, $y, 'Month-by-month salary history', 11, true, 'L', $NAVY);
    $pdf->text($ML + $CW, $y, count($rows) . ' record(s)', 8.5, false, 'R', $GREY);
    $y = table_header($pdf, $cols, $ML, $y + 8, $NAVY, $WHITE, $HEAD);
    $n = 0;
    foreach ($rows as $r) {
        if ($y + $ROW > $FOOT) {
            $pdf->addPage();
            $pdf->text($ML, 40, $emp['name'] . '  -  Employee ' . $emp['pf_no'], 9.5, true, 'L', $NAVY);
            $pdf->text($ML + $CW, 40, 'Salary history (continued)', 8.5, false, 'R', $GREY);
            $y = table_header($pdf, $cols, $ML, 50, $NAVY, $WHITE, $HEAD);
            $n = 0;
        }
        if ($n % 2 === 1) { $pdf->rect($ML, $y, $tw, $ROW, $ZEBRA); }
        $x = $ML;
        foreach ($cols as $i => [$label, $w, $al]) {
            $v = $keys[$i] === null ? ($i === 0 ? (string)(int)$r['pay_year'] : ($months[(int)$r['pay_month']] ?? (string)$r['pay_month'])) : amt($r[$keys[$i]]);
            if ($al === 'R') { $pdf->text($x + $w - 6, $y + 10.5, $v, 8.5, false, 'R'); } else { $pdf->text($x + 6, $y + 10.5, $v, 8.5); }
            $x += $w;
        }
        $pdf->line($ML, $y + $ROW, $ML + $tw, $y + $ROW, 0.3, $LINE);
        $y += $ROW; $n++;
    }
}

// ---------- footer on every page ----------
$pages = $pdf->pageCount();
for ($p = 0; $p < $pages; $p++) {
    $pdf->line($ML, $pdf->h - 36, $ML + $CW, $pdf->h - 36, 0.5, $LINE, $p);
    $pdf->text($ML, $pdf->h - 22, 'Generated on ' . date('d-m-Y') . '  |  Computer-generated statement, no signature required.', 8, false, 'L', $GREY, $p);
    $pdf->text($ML + $CW, $pdf->h - 22, 'Page ' . ($p + 1) . ' of ' . $pages, 8, false, 'R', $GREY, $p);
}

// ---------- send ----------
$bytes = $pdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Salary_' . $pf . '.pdf"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, no-store');
echo $bytes;