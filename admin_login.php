<?php
require 'config.php';
header('Cache-Control: no-store');
if (!empty($_SESSION['admin'])) { header('Location: admin.php'); exit; }

$error = $_SESSION['admin_error'] ?? ''; unset($_SESSION['admin_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['a_tries'] = $_SESSION['a_tries'] ?? 0;
    if ($_SESSION['a_tries'] >= 5 && time() - ($_SESSION['a_last'] ?? 0) >= 300) { $_SESSION['a_tries'] = 0; }
    if ($_SESSION['a_tries'] >= 5) {
        $_SESSION['admin_error'] = 'Too many failed attempts. Try again in a few minutes.';
    } else {
        $u = trim($_POST['username'] ?? ''); $p = $_POST['password'] ?? '';
        $row = null;
        try { $st = db()->prepare('SELECT username, full_name, password_hash FROM admin_users WHERE username = ?'); $st->execute([$u]); $row = $st->fetch(); }
        catch (Throwable $e) { $_SESSION['admin_error'] = 'Database error. Check config.php and that admin_users exists.' . (DEBUG ? ' [' . $e->getMessage() . ']' : ''); header('Location: admin_login.php'); exit; }
        if ($row && password_verify($p, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $row['username']; $_SESSION['admin_name'] = $row['full_name'] ?: $row['username'];
            $_SESSION['a_tries'] = 0;
            header('Location: admin.php'); exit;
        }
        $_SESSION['a_tries']++; $_SESSION['a_last'] = time();
        $_SESSION['admin_error'] = 'Username or password is incorrect.';
    }
    header('Location: admin_login.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login - CUSAT PF</title><?= favicon_tag() ?>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#12325b,#1c4a85);font-family:"Segoe UI",system-ui,Arial,sans-serif}
.box{background:#fff;border-radius:16px;padding:30px;width:min(380px,92vw);box-shadow:0 20px 50px rgba(0,0,0,.3);border-top:5px solid #c8962e}
h1{margin:0 0 4px;color:#12325b;font-size:1.3rem} p.s{margin:0 0 18px;color:#6b7a90;font-size:.9rem}
label{display:block;font-size:.8rem;color:#6b7a90;margin:12px 0 4px}
input{width:100%;padding:10px 12px;border:1px solid #d5dce8;border-radius:8px;font:inherit;box-sizing:border-box}
button{margin-top:18px;width:100%;padding:11px;border:0;border-radius:8px;background:#12325b;color:#fff;font:inherit;font-weight:700;cursor:pointer}
.err{background:#fdecea;color:#b3261e;border-radius:8px;padding:9px 12px;margin-top:12px;font-size:.9rem}
</style></head><body>
<form class="box" method="post" action="admin_login.php" autocomplete="off">
  <h1>Administrator login</h1><p class="s">CUSAT Provident Fund portal</p>
  <label for="u">Username</label><input id="u" name="username" required autofocus>
  <label for="p">Password</label><input id="p" name="password" type="password" required>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <button type="submit">Log in</button>
</form></body></html>