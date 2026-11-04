<?php
require 'config.php';
unset($_SESSION['admin'], $_SESSION['admin_name']);
header('Location: admin_login.php');
exit;
