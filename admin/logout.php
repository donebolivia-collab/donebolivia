<?php
session_start();
require_once '../admin/admin_functions.php';

registrarAccionAdmin('logout');

session_destroy();
header('Location: /admin/login.php');
exit;
?>
