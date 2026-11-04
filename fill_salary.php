<?php
// Fills salary_monthly from employeemaster using PHP. Run from the command line, in this folder:
//     php fill_salary.php 2024 2026          (from year, to year)
//     php fill_salary.php 2024 2026 clear    (also empties salary_monthly first)
// Nothing is calculated: only values stored in employeemaster are copied (basic, and the extra pay fields added together).
if (PHP_SAPI !== 'cli') { exit("Run this from the command line, not the browser.\n"); }
require __DIR__ . '/config.php';

$fromY = (int)($argv[1] ?? 0);
$toY   = (int)($argv[2] ?? 0);
if ($fromY < 2002 || $toY < $fromY) { exit("Usage: php fill_salary.php <from year> <to year> [clear]\n"); }

$pdo = db();
$pdo->beginTransaction();
try {
    if (($argv[3] ?? '') === 'clear') { $pdo->exec('DELETE FROM salary_monthly'); echo "salary_monthly cleared.\n"; }

    $emps = $pdo->query('SELECT emp_number, basic, spl_pay, per_pay, spl_allow, otheramount, doi, dor FROM employeemaster WHERE basic IS NOT NULL')->fetchAll();
    $ins  = $pdo->prepare('INSERT IGNORE INTO salary_monthly (emp_number, pay_year, pay_month, basic_pay, other_allowances) VALUES (?,?,?,?,?)');

    $added = 0;
    foreach ($emps as $e) {
        $other = (float)$e['spl_pay'] + (float)$e['per_pay'] + (float)$e['spl_allow'] + (float)$e['otheramount'];
        $joined  = $e['doi'] ? substr($e['doi'], 0, 10) : null;   // yyyy-mm-dd
        $retired = $e['dor'] ? substr($e['dor'], 0, 10) : null;
        for ($y = $fromY; $y <= $toY; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $first = sprintf('%04d-%02d-01', $y, $m);
                $last  = date('Y-m-t', strtotime($first));
                if ($joined && $joined > $last) continue;       // not joined yet that month
                if ($retired && $retired < $first) continue;    // already retired
                $ins->execute([$e['emp_number'], $y, $m, $e['basic'], $other]);
                $added += $ins->rowCount();
            }
        }
    }
    $pdo->commit();
    echo "Done. $added row(s) added for " . count($emps) . " employee(s), years $fromY to $toY.\n";
} catch (Throwable $ex) {
    $pdo->rollBack();
    exit('Failed, nothing was saved: ' . $ex->getMessage() . "\n");
}
