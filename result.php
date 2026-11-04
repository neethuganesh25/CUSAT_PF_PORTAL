<?php
require 'config.php';
if (DEBUG) { ini_set('display_errors', '1'); error_reporting(E_ALL); }   // shows PHP errors while testing
$pf = require_login();
$emp = null; $error = ''; $rows = [];
$months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
try {
    if (pf_expected_type($pf) === null) { $error = PF_RULE_MSG; }
    else { $emp = fetch_employee($pf); if (!$emp) $error = 'No employee found for number ' . $pf . '.'; elseif (!is_permanent($emp)) { $error = NOT_PERMANENT_MSG; $emp = null; } else $rows = fetch_salary($pf, null, null); }
} catch (Throwable $e) { $error = 'Database error. Check config.php settings.' . (DEBUG ? ' [' . $e->getMessage() . ']' : ''); }
if ($emp) { log_event($pf, 'view'); }

function dmy($d) { return ($d && $d !== '0000-00-00') ? date('d-m-Y', strtotime($d)) : '-'; }
function v($x) { return ($x === null || $x === '') ? '-' : h($x); }
function money($x) { return ($x === null || $x === '') ? '-' : number_format((float)$x, 2); }

// Sections shown to the employee: label => [column, kind]  (kind: t text, d date, m money, n number)
$sections = $emp ? [
  'Personal' => [['Employee number','emp_number','t'],['Audit number','auditno','t'],['Sex','sex','t'],['Marital status','marital_status','t'],
                 ['Date of birth','dob','d'],['PAN','pan_gir_number','t'],['PRAN','pran_number','t']],
  'Service'  => [['Department','deptdetails','t'],['Designation','desgdetails','t'],['Grade','gradedetails','t'],['Level','leveldetails','t'],
                 ['Category','categroydetails','t'],['Class','classdetails','t'],['Status','emp_status','t'],
                 ['Date of entry','doe','d'],['Date of joining','doi','d'],['Date of retirement','dor','d']],
  'Pay'      => [['Pay scale','payscaledetails','t'],['HRA eligible','hra_eligible','t'],
                 ['Basic','basic','m'],['Increment','increment','m'],['Special pay','spl_pay','m'],['Personal pay','per_pay','m'],
                 ['Special allowance','spl_allow','m'],['Other amount','otheramount','m'],['Water charges','watercharges','m'],
                 ['Professional tax','pt','m'],['Old basic','oldbasic','m'],['New basic','new_basic','m']],
  'Leave balance (days)' => [['Earned leave','curr_el_bal','n'],['Half-pay leave','curr_hpl_bal','n'],['Casual leave','current_cl_bal','n']],
] : [];
function initials(string $n): string { $p = preg_split('/[\s.]+/', trim($n), -1, PREG_SPLIT_NO_EMPTY); return strtoupper(substr($p[0] ?? '?',0,1) . (count($p)>1 ? substr($p[1],0,1) : '')); }
function show(array $e, array $f) {
  $val = $e[$f[1]] ?? null;
  return $f[2]==='d' ? dmy($val) : ($f[2]==='m' ? money($val) : v($val));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PF Details - <?= h($pf) ?></title>
<?= favicon_tag() ?>
<style>
:root{--navy:#12325b;--navy2:#1c4a85;--gold:#c8962e;--bg:#eef2f7;--card:#fff;--line:#e3e8f0;--muted:#6b7a90;--ink:#1b2a41}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;font-size:15px;line-height:1.45}
.wrap{max-width:1120px;margin:0 auto;padding:0 16px 40px}
.btn{display:inline-block;border:0;border-radius:8px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;background:var(--navy);color:#fff}
.btn:hover{background:var(--navy2)} .btn.alt{background:#2e7d32} .btn.ghost{background:transparent;color:var(--navy);border:1px solid var(--line)}
.btn.light{color:#fff;border-color:rgba(255,255,255,.45)}
/* top bar */
.topbar{background:linear-gradient(135deg,var(--navy),var(--navy2));border-bottom:4px solid var(--gold);color:#fff}
.topbar .in{max-width:1120px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;gap:14px}
.logo{background:#fff;border-radius:10px;padding:6px 10px;min-width:56px;height:52px;display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--navy)}
.logo img{height:40px;width:auto;display:block}
.titles{flex:1;min-width:0} .titles strong{display:block;font-family:Georgia,serif;font-size:1.05rem}
.titles span{color:#f1d9a0;font-size:.85rem}
/* hero */
.hero{background:var(--card);border-radius:16px;box-shadow:0 6px 22px rgba(18,50,91,.10);margin-top:-1px;padding:22px;display:flex;gap:18px;align-items:center;flex-wrap:wrap;transform:translateY(16px);margin-bottom:34px}
.avatar{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#e6bd6a);color:var(--navy);font-weight:800;font-size:1.6rem;display:flex;align-items:center;justify-content:center;flex:none}
.hero .who{flex:1;min-width:220px}
.hero h1{margin:0;font-family:Georgia,serif;font-size:1.5rem;color:var(--navy)}
.hero .sub{color:var(--muted);margin:2px 0 8px}
.badge{display:inline-block;background:#fff4d6;color:#8a5a00;border:1px solid #efd48a;border-radius:999px;padding:2px 11px;font-size:.78rem;font-weight:700;vertical-align:middle;margin-left:8px}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{background:#f3f6fb;border:1px solid var(--line);border-radius:8px;padding:5px 10px;font-size:.82rem}
.chip b{color:var(--navy)}
/* stat tiles */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:18px}
.stat{background:var(--card);border:1px solid var(--line);border-left:5px solid var(--gold);border-radius:12px;padding:14px 16px}
.stat small{display:block;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-size:.72rem}
.stat strong{font-size:1.35rem;color:var(--navy)}
/* tabs */
.tabs{display:flex;gap:6px;border-bottom:2px solid var(--line);margin-bottom:16px}
.tab{background:none;border:0;padding:10px 18px;font:inherit;font-weight:700;color:var(--muted);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab[aria-selected=true]{color:var(--navy);border-bottom-color:var(--gold)}
.panel[hidden]{display:none}
/* info cards */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 20px}
.card h2{margin:0 0 12px;font-size:.95rem;color:var(--navy);text-transform:uppercase;letter-spacing:.06em;padding-bottom:8px;border-bottom:2px solid var(--gold)}
.card dl{margin:0;display:grid;grid-template-columns:1fr 1fr;gap:12px 18px}
.card dt{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.card dd{margin:1px 0 0;font-weight:600;word-break:break-word}
/* salary */
.filter{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px}
.filter label{display:block;font-size:.75rem;color:var(--muted);margin-bottom:3px}
.filter input{width:110px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}
.count{color:var(--muted);margin:0 0 8px}
.table-wrap{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:auto;max-height:520px}
table{border-collapse:collapse;width:100%;min-width:820px}
th{position:sticky;top:0;background:var(--navy);color:#fff;text-align:left;padding:10px 12px;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:9px 12px;border-top:1px solid var(--line);white-space:nowrap}
tbody tr:nth-child(even){background:#f7f9fc} tbody tr:hover{background:#fff7e0}
.num{text-align:right;font-variant-numeric:tabular-nums}
.empty{background:var(--card);border:1px dashed var(--line);border-radius:12px;padding:26px;text-align:center;color:var(--muted)}
.error{color:#b3261e;background:#fdecea;border-radius:8px;padding:10px 12px}
.errcard{max-width:520px;margin:60px auto;background:#fff;border-radius:14px;padding:24px}
@media(max-width:600px){.hero{padding:16px}.card dl{grid-template-columns:1fr 1fr}.titles strong{font-size:.9rem}}
@media print{
  @page{size:A4 portrait;margin:12mm}
  body{background:#fff} .no-print,.tabs{display:none !important}
  .panel[hidden]{display:block !important}
  .hero{transform:none;box-shadow:none;border:1px solid var(--line);margin:12px 0}
  .topbar{background:#fff;color:#000;border-bottom:2px solid var(--navy)} .titles span{color:#444}
  .card,.stat,.table-wrap{break-inside:avoid} .table-wrap{max-height:none;overflow:visible}
  th{position:static;background:#e8edf5;color:#000} table{min-width:0;font-size:11px}
}
</style>
</head>
<body>
<?php if ($error): ?>
<main class="errcard"><h1>PF Details</h1><p class="error"><?= h($error) ?></p><a class="btn" href="logout.php">&larr; Back to login</a></main>
<?php else: ?>
<header class="topbar"><div class="in">
  <div class="logo"><img src="assets/logo.png" alt="CUSAT" onerror="this.hidden=true;document.getElementById('lf').hidden=false"><span id="lf" hidden>CUSAT</span></div>
  <div class="titles"><strong>Cochin University of Science and Technology</strong><span>Provident Fund Details</span></div>
  <a class="btn ghost light no-print" href="logout.php">Log out</a>
</div></header>

<div class="wrap">
  <section class="hero">
    <div class="avatar"><?= h(initials($emp['name'])) ?></div>
    <div class="who">
      <h1><?= h($emp['name']) ?><span class="badge"><?= h(employee_type($emp)) ?></span></h1>
      <div class="sub"><?= v($emp['desgdetails']) ?> &middot; <?= v($emp['deptdetails']) ?></div>
      <div class="chips">
        <span class="chip">Employee No. <b><?= h($emp['pf_no']) ?></b></span>
        <span class="chip">Joined <b><?= h(dmy($emp['doi'])) ?></b></span>
        <span class="chip">Retirement <b><?= h(dmy($emp['dor'])) ?></b></span>
      </div>
    </div>
    <span class="no-print" style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="download.php">Download salary PDF</a><button type="button" class="btn alt" onclick="window.print()">Print</button></span>
  </section>

  <div class="stats">
    <div class="stat"><small>Basic pay</small><strong><?= money($emp['basic']) ?></strong></div>
    <div class="stat"><small>Earned leave (days)</small><strong><?= v($emp['curr_el_bal']) ?></strong></div>
    <div class="stat"><small>Half-pay leave (days)</small><strong><?= v($emp['curr_hpl_bal']) ?></strong></div>
    <div class="stat"><small>Casual leave (days)</small><strong><?= v($emp['current_cl_bal']) ?></strong></div>
  </div>

  <?php $salaryFirst = false; ?>
  <div class="tabs no-print" role="tablist">
    <button class="tab" role="tab" id="t-over" aria-selected="<?= $salaryFirst ? 'false' : 'true' ?>" data-panel="p-over">Overview</button>
    <button class="tab" role="tab" id="t-all" aria-selected="false" data-panel="p-all">All details (every field)</button>
    <button class="tab" role="tab" id="t-sal"  aria-selected="<?= $salaryFirst ? 'true' : 'false' ?>" data-panel="p-sal">Monthly salary / PF</button>
  </div>

  <section class="panel" id="p-over" <?= $salaryFirst ? 'hidden' : '' ?>>
    <div class="cards">
      <?php foreach ($sections as $title => $fields): if (strpos($title, 'Leave') === 0) continue; ?>
      <div class="card"><h2><?= h($title) ?></h2>
        <dl><?php foreach ($fields as $f): ?><div><dt><?= h($f[0]) ?></dt><dd><?= show($emp, $f) ?></dd></div><?php endforeach; ?></dl>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel" id="p-all" hidden>
    <div class="card"><h2>All fields in the employee record</h2>
      <dl style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">
      <?php
      $dateCols = ['dob','dor','doe','doi','date_of_stay_from','date_of_stay_to','date_of_change','date','dor_new'];
      foreach ($emp as $col => $val):
          if ($col === 'name' || $col === 'pf_no') continue;          // helper aliases, not real columns
          $shown = in_array($col, $dateCols, true) ? dmy($val) : v($val);
      ?>
        <div><dt><?= h(ucwords(str_replace('_', ' ', $col))) ?></dt><dd><?= $shown ?></dd></div>
      <?php endforeach; ?>
      </dl>
    </div>
  </section>

  <section class="panel" id="p-sal" <?= $salaryFirst ? '' : 'hidden' ?>>
    <div class="card" style="margin-bottom:16px"><h2>Current monthly pay (from employee master)</h2>
      <div class="table-wrap" style="border:0;max-height:none"><table style="min-width:0">
        <thead><tr><th>Component</th><th class="num">Amount (Rs.)</th></tr></thead>
        <tbody>
        <?php foreach ([['Basic pay','basic'],['Increment','increment'],['Special pay','spl_pay'],['Personal pay','per_pay'],['Special allowance','spl_allow'],['Other amount','otheramount'],['Water charges','watercharges'],['Professional tax','pt']] as $c): ?>
          <tr><td><?= h($c[0]) ?></td><td class="num"><?= money($emp[$c[1]]) ?></td></tr>
        <?php endforeach; ?>
        <tr style="font-weight:700;background:#fff7e0"><td>Total earnings</td><td class="num"><?= money((float)$emp['basic'] + (float)$emp['spl_pay'] + (float)$emp['per_pay'] + (float)$emp['spl_allow'] + (float)$emp['otheramount']) ?></td></tr>
        <?php if (employee_type($emp) === 'Permanent'): $pfBase = (float)$emp['basic']; ?>
        <tr><td>PF &ndash; employee share (<?= PF_EMPLOYEE_RATE * 100 ?>% of basic, calculated)</td><td class="num"><?= money(round($pfBase * PF_EMPLOYEE_RATE, 2)) ?></td></tr>
        <tr><td>PF &ndash; employer share (<?= PF_EMPLOYER_RATE * 100 ?>% of basic, calculated)</td><td class="num"><?= money(round($pfBase * PF_EMPLOYER_RATE, 2)) ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
      <p class="count" style="margin:10px 0 0">Total earnings = Basic + Special pay + Personal pay + Special allowance + Other amount. PF amounts are calculated on basic pay (not stored in the employee master) and are for information only.</p>
    </div>
    <?php if (!$rows): ?>
      <div class="empty">No monthly salary records found.</div>
    <?php else: ?>
      <p class="count">Month-by-month salary history: <?= count($rows) ?> record(s)</p>
      <div class="table-wrap"><table>
        <thead><tr><th>Year</th><th>Month</th><th class="num">Basic</th><th class="num">DA</th><th class="num">HRA</th><th class="num">Other</th><th class="num">Gross</th><th class="num">PF (Employee)</th><th class="num">PF (Employer)</th><th class="num">Net</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= (int)$r['pay_year'] ?></td><td><?= h($months[(int)$r['pay_month']] ?? $r['pay_month']) ?></td>
          <?php foreach (['basic_pay','da','hra','other_allowances','gross_salary','pf_employee','pf_employer','net_salary'] as $c): ?><td class="num"><?= money($r[$c]) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<script src="script.js"></script>
<script>
document.querySelectorAll('.tab').forEach(function (t) {
  t.addEventListener('click', function () {
    document.querySelectorAll('.tab').forEach(function (x) { x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
    document.querySelectorAll('.panel').forEach(function (p) { p.hidden = (p.id !== t.dataset.panel); });
  });
});
</script>
</body>
</html>