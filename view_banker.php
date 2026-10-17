<?php
require_once 'config.php';
require_role(['Admin', 'RM']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bank_name = '';

if ($id > 0) {
    $stmt = $db->prepare("SELECT bank_name FROM bankers WHERE id = ?");
    $stmt->execute([$id]);
    $bank_name = $stmt->fetchColumn();
}

if (!empty($bank_name)) {
    header("Location: view_bank.php?bank_name=" . urlencode($bank_name) . "#tab-bankers");
} else {
    header("Location: bankers_list.php");
}
exit;
