<?php
require 'config.php';
if (!empty($_SESSION['pf'])) { log_event($_SESSION['pf'], 'logout'); }
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
