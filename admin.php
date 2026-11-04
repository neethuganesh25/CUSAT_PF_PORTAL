<?php
require 'config.php';
$admin = require_admin();
if (DEBUG) { ini_set('display_errors', '1'); error_reporting(E_ALL); }

$q    = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 25;
$tab  = ($_GET['tab'] ?? 'employees') === 'activity' ? 'activity' : 'employees';
$err  = ''; $stats = ['total' => 0, 'users' => 0, 'today' => 0, 'failed' => 0]; $list = []; $count = 0; $acts = [];

try {
    $pdo   = db();
    $today = date('Y-m-d 00:00:00');
    $day   = date('Y-m-d H:i:s', time() - 86400);

    $stats['total']  = (int)$pdo->query('SELECT COUNT(*) FROM employeemaster e WHERE ' . permanent_sql('e'))->fetchColumn();
    $stats['users']  = (int)$pdo->query("SELECT COUNT(DISTINCT l.pf_no) FROM access_log l JOIN employeemaster e ON e.emp_number = l.pf_no WHERE l.event = 'login_success' AND " . permanent_sql('e'))->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM access_log WHERE event = 'login_success' AND created_at >= ?"); $st->execute([$today]); $stats['today'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM access_log WHERE event IN ('login_failed','login_blocked') AND created_at >= ?"); $st->execute([$day]); $stats['failed'] = (int)$st->fetchColumn();

    // Employees who have logged in, with their details
    $where = "l.event = 'login_success' AND " . permanent_sql('e'); $args = [];
    if ($q !== '') { $where .= ' AND (e.emp_number LIKE ? OR e.emp_name LIKE ? OR e.deptdetails LIKE ? OR e.desgdetails LIKE ?)'; $args = array_fill(0, 4, "%$q%"); }
    $st = $pdo->prepare("SELECT COUNT(*) FROM (SELECT e.emp_number FROM access_log l JOIN employeemaster e ON e.emp_number = l.pf_no WHERE $where GROUP BY e.emp_number) t");
    $st->execute($args); $count = (int)$st->fetchColumn();
    $off = ($page - 1) * $per;
    $st = $pdo->prepare("SELECT e.emp_number, e.emp_name, e.desgdetails, e.deptdetails, e.emp_status, e.doi, e.dor,
                                COUNT(*) AS logins, MAX(l.created_at) AS last_login
                         FROM access_log l JOIN employeemaster e ON e.emp_number = l.pf_no
                         WHERE $where GROUP BY e.emp_number, e.emp_name, e.desgdetails, e.deptdetails, e.emp_status, e.doi, e.dor
                         ORDER BY last_login DESC LIMIT $per OFFSET $off");
    $st->execute($args); $list = $st->fetchAll();

    if ($tab === 'activity') {
        $acts = $pdo->query('SELECT created_at, pf_no, name, event FROM access_log ORDER BY id DESC LIMIT 100')->fetchAll();
    }
} catch (Throwable $e) { $err = 'Database error.' . (DEBUG ? ' [' . $e->getMessage() . ']' : ''); }

$pages = max(1, (int)ceil($count / $per));
function dmy($d) { return ($d && $d !== '0000-00-00') ? date('d-m-Y', strtotime($d)) : '-'; }
function dt($d)  { return $d ? date('d-m-Y H:i', strtotime($d)) : '-'; }
function link_to(array $o): string { return 'admin.php?' . http_build_query(array_filter($o, fn($v) => $v !== '' && $v !== null)); }
$labels = ['login_success' => 'Logged in', 'login_failed' => 'Failed login', 'login_blocked' => 'Blocked', 'view' => 'Viewed details', 'download' => 'Downloaded PDF', 'logout' => 'Logged out'];
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard - CUSAT PF</title><?= favicon_tag() ?>
<style>
:root{--navy:#12325b;--navy2:#1c4a85;--gold:#c8962e;--bg:#eef2f7;--line:#e3e8f0;--muted:#6b7a90;--ink:#1b2a41}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",system-ui,Arial,sans-serif;font-size:15px}
.top{background:linear-gradient(135deg,var(--navy),var(--navy2));border-bottom:4px solid var(--gold);color:#fff}
.top .in{max-width:1200px;margin:0 auto;padding:14px 16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.top h1{margin:0;font-family:Georgia,serif;font-size:1.15rem;flex:1} .top small{color:#f1d9a0;display:block;font-family:"Segoe UI",sans-serif;font-size:.8rem}
.btn{display:inline-block;border:0;border-radius:8px;padding:8px 14px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;background:var(--navy);color:#fff}
.btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.5)} .btn.line{background:#fff;color:var(--navy);border:1px solid var(--line)}
.wrap{max-width:1200px;margin:0 auto;padding:18px 16px 40px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:18px}
.stat{background:#fff;border:1px solid var(--line);border-left:5px solid var(--gold);border-radius:12px;padding:14px 16px}
.stat small{display:block;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-size:.72rem} .stat strong{font-size:1.6rem;color:var(--navy)}
.tabs{display:flex;gap:6px;border-bottom:2px solid var(--line);margin-bottom:14px}
.tabs a{padding:10px 18px;font-weight:700;color:var(--muted);text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px}
.tabs a.on{color:var(--navy);border-bottom-color:var(--gold)}
form.s{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap} form.s input{flex:1;min-width:220px;padding:9px 12px;border:1px solid #d5dce8;border-radius:8px;font:inherit}
.tw{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:auto}
table{border-collapse:collapse;width:100%;min-width:900px}
th{background:var(--navy);color:#fff;text-align:left;padding:10px 12px;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:9px 12px;border-top:1px solid var(--line)} tbody tr:nth-child(even){background:#f7f9fc} tbody tr:hover{background:#fff7e0}
.num{text-align:right} .pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:700;background:#e8f1ff;color:#1c4a85}
.pill.bad{background:#fdecea;color:#b3261e} .pill.ok{background:#e6f4ea;color:#1e7a34}
.pg{display:flex;gap:6px;align-items:center;margin-top:12px;color:var(--muted)} .empty{padding:28px;text-align:center;color:var(--muted)}
.error{background:#fdecea;color:#b3261e;border-radius:8px;padding:10px 12px;margin-bottom:12px}
</style></head><body>
<header class="top"><div class="in">
  <h1>Admin Dashboard<small>Cochin University of Science and Technology &middot; Provident Fund portal</small></h1>
  <span>Signed in as <b><?= h($_SESSION['admin_name'] ?? $admin) ?></b></span>
  <a class="btn ghost" href="admin_logout.php">Log out</a>
</div></header>
<div class="wrap">
  <?php if ($err): ?><div class="error"><?= h($err) ?></div><?php endif; ?>
  <div class="stats">
    <div class="stat"><small>Permanent employees in database</small><strong><?= number_format($stats['total']) ?></strong></div>
    <div class="stat"><small>Employees who have logged in</small><strong><?= number_format($stats['users']) ?></strong></div>
    <div class="stat"><small>Logins today</small><strong><?= number_format($stats['today']) ?></strong></div>
    <div class="stat"><small>Failed logins (24 h)</small><strong><?= number_format($stats['failed']) ?></strong></div>
  </div>

  <div class="tabs">
    <a class="<?= $tab === 'employees' ? 'on' : '' ?>" href="admin.php">Employees logged in</a>
    <a class="<?= $tab === 'activity' ? 'on' : '' ?>" href="admin.php?tab=activity">Recent activity</a>
  </div>

<?php if ($tab === 'employees'): ?>
  <form class="s" method="get" action="admin.php">
    <input name="q" value="<?= h($q) ?>" placeholder="Search by employee number, name, designation or department">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn line" href="admin.php">Clear</a><?php endif; ?>
  </form>
  <div class="tw"><table>
    <thead><tr><th>Employee No.</th><th>Name</th><th>Designation</th><th>Department</th><th>Status</th><th>Joined</th><th>Retirement</th><th class="num">Logins</th><th>Last login</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr><td><b><?= h($r['emp_number']) ?></b></td><td><?= h($r['emp_name']) ?></td><td><?= h($r['desgdetails'] ?: '-') ?></td><td><?= h($r['deptdetails'] ?: '-') ?></td>
          <td><?= h($r['emp_status'] ?: '-') ?></td><td><?= h(dmy($r['doi'])) ?></td><td><?= h(dmy($r['dor'])) ?></td>
          <td class="num"><?= (int)$r['logins'] ?></td><td><?= h(dt($r['last_login'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="9" class="empty">No employees have logged in yet<?= $q !== '' ? ' for this search' : '' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <div class="pg"><?= number_format($count) ?> employee(s) &middot; page <?= $page ?> of <?= $pages ?>
    <?php if ($page > 1): ?><a class="btn line" href="<?= h(link_to(['q' => $q, 'page' => $page - 1])) ?>">&larr; Prev</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn line" href="<?= h(link_to(['q' => $q, 'page' => $page + 1])) ?>">Next &rarr;</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="tw"><table style="min-width:640px">
    <thead><tr><th>Time</th><th>Employee No.</th><th>Name</th><th>Event</th></tr></thead>
    <tbody>
    <?php foreach ($acts as $a): $bad = in_array($a['event'], ['login_failed', 'login_blocked'], true); ?>
      <tr><td><?= h(dt($a['created_at'])) ?></td><td><?= h($a['pf_no'] ?: '-') ?></td><td><?= h($a['name'] ?: 'Unknown') ?></td>
          <td><span class="pill <?= $bad ? 'bad' : ($a['event'] === 'login_success' ? 'ok' : '') ?>"><?= h($labels[$a['event']] ?? $a['event']) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$acts): ?><tr><td colspan="4" class="empty">No activity recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <p class="pg">Latest 100 events. Dates of birth are never recorded.</p>
<?php endif; ?>
</div></body></html>