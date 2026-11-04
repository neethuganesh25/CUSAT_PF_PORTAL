<?php
require 'config.php';

// Never let the browser keep this page: stops old values coming back with the Back button.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!empty($_SESSION['pf'])) { header('Location: result.php'); exit; }

// Message left by the last failed attempt: shown once, then forgotten.
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $pf  = clean_pf($_POST['pf_no'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $_SESSION['tries'] = $_SESSION['tries'] ?? 0;

    // Lockout lifts by itself 5 minutes after the last failed attempt.
    if ($_SESSION['tries'] >= 5 && time() - ($_SESSION['last_fail'] ?? 0) >= 300) { $_SESSION['tries'] = 0; }

    if ($_SESSION['tries'] >= 5) {
        $wait = max(1, (int)ceil((300 - (time() - ($_SESSION['last_fail'] ?? 0))) / 60));
        $error = "Too many failed attempts. Please try again in about $wait minute(s).";
        log_event($pf, 'login_blocked');
    } else {
        $ok = false; $notPermanent = false;
        try {
            if (pf_expected_type($pf) !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $emp = fetch_employee($pf);
                $ok = $emp && substr((string)$emp['dob'], 0, 10) === $dob;
                if ($ok && !is_permanent($emp)) { $ok = false; $notPermanent = true; }   // contract / re-employed: no access
            }
        } catch (Throwable $e) {
            $error = 'Database error. Check config.php settings.' . (DEBUG ? ' [' . $e->getMessage() . ']' : '');
        }
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['pf'] = $pf;
            $_SESSION['name'] = $emp['name'];
            log_event($pf, 'login_success');
            $_SESSION['tries'] = 0;
            header('Location: result.php'); exit;
        }
        if (!$error) {
            $_SESSION['tries']++;
            $_SESSION['last_fail'] = time();
            log_event($pf, 'login_failed');
            $error = $notPermanent ? NOT_PERMANENT_MSG : 'Employee number or date of birth is incorrect.';   // same message for both, on purpose
        }
    }

    // Failed attempt: start again on a clean page (empty fields, and a refresh will not resend the form).
    $_SESSION['login_error'] = $error;
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - CUSAT PF Details</title>
<link rel="stylesheet" href="login.css">
<link rel="icon" href="https://cusat.ac.in/files/pictures/pictures_1_logo.png" >
</head>
<body class="login-page">
<div class="shell">

    <aside class="brand">
        <div class="logo-wrap">
            <img src="assets/logo.png" alt="CUSAT logo" class="logo"
                 onerror="this.hidden=true;document.getElementById('logoFallback').hidden=false">
            <div id="logoFallback" class="logo-fallback" hidden>CUSAT</div>
        </div>
        <h1>Cochin University of Science and Technology</h1>
        <p class="tagline">Provident Fund Details Portal</p>
        <!-- <ul class="points">
            <li>View your PF and salary details</li>
            <li>Records from 2002 to 2026</li>
            <li>Permanent and contract staff</li>
        </ul> -->
    </aside>


    <main class="panel">
        <h2>Welcome</h2>
        <p class="hint">Log in with your employee (PF) number and date of birth.</p>

        <form id="loginForm" method="post" action="login.php" autocomplete="off" novalidate>
            <label for="pf_no">Employee / PF Number</label>
            <input type="text" id="pf_no" name="pf_no" inputmode="numeric" autocomplete="off"
                   placeholder="e.g. 90001" autofocus required>

            <label for="dob">Date of Birth</label>
            <input type="date" id="dob" name="dob" autocomplete="off" required>

            <!-- <p id="err" class="error" role="alert" <?= $error ? '' : 'hidden' ?>><?= h($error) ?></p> -->
            <button type="submit">Log in</button>
        </form>

        <p class="foot">&copy; <?= date('Y') ?> CUSAT. For employees only.</p>
    </main>

</div>
<script src="script.js"></script>
</body>
</html>