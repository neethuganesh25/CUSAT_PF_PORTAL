<?php
// Open http://localhost:8000/check_db.php to see the real reason for "Database error". Delete this file afterwards.
require 'config.php';
header('Content-Type: text/plain');
echo "PHP " . PHP_VERSION . "\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO  <- enable extension=pdo_mysql in php.ini') . "\n";
try {
    $pdo = db();
    echo "Connected to database '" . DB_NAME . "' as '" . DB_USER . "': OK\n";
    echo "employees rows: " . $pdo->query('SELECT COUNT(*) FROM employeemaster')->fetchColumn() . "\n";
    $e = fetch_employee('01000');
    echo "fetch_employee('01000'): " . ($e ? $e['name'] . ' | dob=' . $e['dob'] : 'NOT FOUND') . "\n";
    echo "access_log table: " . $pdo->query('SELECT COUNT(*) FROM access_log')->fetchColumn() . " rows\n";
} catch (Throwable $x) {
    echo "ERROR: " . $x->getMessage() . "\n";
}
