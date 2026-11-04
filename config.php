<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Database settings - change to match your MySQL setup
const DB_HOST = '127.0.0.1';
const DB_NAME = 'cusat_pf_portal';
const DB_USER = 'root';
const DB_PASS = 'itcell@cusat';

// Set to false once everything works. While true, the login page shows the real database error.
const DEBUG = true;

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Browser-tab icon. Uses assets/favicon.png (a square image) if present, else assets/cusat-logo.png,
// else the built-in favicon.svg ("PF" badge).
function favicon_tag(): string {
    foreach (['assets/favicon.png', 'assets/cusat-logo.png'] as $f) {
        if (is_file(__DIR__ . '/' . $f)) { return '<link rel="icon" type="image/png" href="' . $f . '">'; }
    }
    return '<link rel="icon" type="image/svg+xml" href="favicon.svg">';
}

function fetch_employee(string $pf): ?array {
    $st = db()->prepare('SELECT *, emp_name AS name, emp_number AS pf_no FROM employeemaster WHERE emp_number = ?');
    $st->execute([$pf]);
    return $st->fetch() ?: null;
}

// Monthly salary rows for one employee, optionally between two years.
function fetch_salary(string $pf, ?int $from, ?int $to): array {
    $sql = 'SELECT * FROM salary_monthly WHERE emp_number = :pf';
    if ($from) { $sql .= ' AND pay_year >= :from'; }
    if ($to)   { $sql .= ' AND pay_year <= :to'; }
    $sql .= ' ORDER BY pay_year, pay_month';
    $st = db()->prepare($sql);
    $st->bindValue(':pf', $pf);
    if ($from) { $st->bindValue(':from', $from, PDO::PARAM_INT); }
    if ($to)   { $st->bindValue(':to',   $to,   PDO::PARAM_INT); }
    $st->execute();
    return $st->fetchAll();
}

// PF numbers are digits only. Keep them as strings so a leading 0 is never lost.
function clean_pf(string $v): string { return preg_replace('/\D/', '', $v); }

// Rule: contract staff PF starts with 0; permanent staff PF starts with 8 or 9.
function pf_expected_type(string $pf): ?string {
    if ($pf === '') return null;
    $first = $pf[0];
    if ($first === '0') return 'Contract';
    if ($first === '8' || $first === '9') return 'Permanent';
    return null;
}

// Writes one row to access_log: PF number (username), employee name, event, time.
// Never stops the page if logging fails. The date of birth is never logged.
function log_event(?string $pf, string $event): void {
    try {
        $pf = ($pf !== null && $pf !== '') ? $pf : null;
        $name = $pf !== null ? (fetch_employee($pf)['name'] ?? null) : null;   // null if the PF number is unknown
        $st = db()->prepare('INSERT INTO access_log (pf_no, name, event) VALUES (?, ?, ?)');
        $st->execute([$pf, $name, $event]);
    } catch (Throwable $e) { /* ignore: logging must not break the app */ }
}

// Returns the logged-in PF number, or sends the visitor to the login page.
function require_login(): string {
    if (empty($_SESSION['pf'])) { header('Location: login.php'); exit; }
    return $_SESSION['pf'];
}

const PF_RULE_MSG = 'Invalid PF number. Contract staff PF numbers start with 0; permanent staff PF numbers start with 8 or 9.';

// ---- PF calculation (permanent staff only). Confirm the scheme and rates with your accounts section, then edit here. ----
const PF_EMPLOYEE_RATE = 0.12;   // employee share: 12% of basic
const PF_EMPLOYER_RATE = 0.12;   // employer share: 12% of basic

// ---- Contract or Permanent, decided from the PAY SCALE ----
// Only PERMANENT employees can log in and see their data. Contract and re-employed staff are blocked.
// Pay-scale names: UGC SCALE, UGC-AICTE SCALE and STATE SCALE = Permanent; CONTRACT and RE-EMPLOYMENT = Contract.
// CONTRACT_PAYSCALE_WORDS: words in the pay-scale name (`payscaledetails`) that mean "not permanent" (EMPLOY covers RE-EMPLOYMENT).
// CONTRACT_PAYSCALE_CODES: optional pay-scale codes (the `payscale` column) of contract staff, e.g. [90, 91].
const CONTRACT_PAYSCALE_WORDS = ['CONTRACT', 'EMPLOY'];
const CONTRACT_PAYSCALE_CODES = [];

function employee_type(array $emp): string {
    $code = trim((string)($emp['payscale'] ?? ''));
    if ($code !== '' && in_array($code, array_map('strval', CONTRACT_PAYSCALE_CODES), true)) { return 'Contract'; }
    $text = strtoupper((string)($emp['payscaledetails'] ?? ''));
    foreach (CONTRACT_PAYSCALE_WORDS as $w) { if (strpos($text, $w) !== false) { return 'Contract'; } }
    return 'Permanent';
}
function is_permanent(array $emp): bool { return employee_type($emp) === 'Permanent'; }

// Same rule as a SQL condition, for lists and counts. $alias is the employee table alias, e.g. 'e'.
function permanent_sql(string $alias = 'e'): string {
    $parts = [];
    foreach (CONTRACT_PAYSCALE_WORDS as $w) { $parts[] = "COALESCE($alias.payscaledetails,'') NOT LIKE '%" . $w . "%'"; }
    if (CONTRACT_PAYSCALE_CODES) {
        $codes = implode(',', array_map(fn($x) => "'" . preg_replace('/[^A-Za-z0-9]/', '', (string)$x) . "'", CONTRACT_PAYSCALE_CODES));
        $parts[] = "COALESCE($alias.payscale,'') NOT IN ($codes)";
    }
    return '(' . implode(' AND ', $parts) . ')';
}

const NOT_PERMANENT_MSG = 'Online access is available only for permanent employees.';

// ---- Admin dashboard helper: separate session key from employee login ----
function require_admin(): string {
    if (empty($_SESSION['admin'])) { header('Location: admin_login.php'); exit; }
    return $_SESSION['admin'];
}