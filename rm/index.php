<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['RM']);
header("Location: dashboard.php");
exit;
?>
