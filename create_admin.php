<?php
// Creates (or resets the password of) an admin. Command line only:
//     php create_admin.php <username> <password> "<full name>"
if (PHP_SAPI !== 'cli') { exit("Run this from the command line.\n"); }
require __DIR__ . '/config.php';
[$u, $p, $n] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($u === '' || strlen($p) < 8) { exit("Usage: php create_admin.php <username> <password of 8+ characters> \"<full name>\"\n"); }
$st = db()->prepare('INSERT INTO admin_users (username, full_name, password_hash) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name)');
$st->execute([$u, $n ?: null, password_hash($p, PASSWORD_DEFAULT)]);
echo "Admin '$u' saved. Log in at admin_login.php\n";
