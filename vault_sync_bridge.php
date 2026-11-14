<?php
/**
 * BFS Hybrid VPS-to-Office Storage Bridge
 * 
 * Allows a cloud-hosted VPS CRM to securely offload all documents to
 * the local Office PC (F:\BFS), keeping VPS cloud storage at 0 MB.
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';

// Shared secret key for vault bridge sync authentication
$bridgeSecret = 'BFS_VAULT_KEY_' . hash('sha256', 'BFS_VAULT_ON_PREM_F_DRIVE_STORAGE_SALT');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');
$key = $_GET['key'] ?? ($_POST['key'] ?? '');

$isAdmin = !empty($_SESSION['role']) && $_SESSION['role'] === 'Admin';
$isAuthorizedBridge = (!empty($key) && hash_equals($bridgeSecret, $key));

if (!$isAdmin && !$isAuthorizedBridge) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized bridge request']);
    exit;
}

switch ($action) {
    case 'status':
        $metrics = BfsVaultEngine::getVaultMetrics();
        $transitDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'vault_transit';
        $transitCount = 0;
        $transitSize = 0;
        if (is_dir($transitDir)) {
            $files = scandir($transitDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $fPath = $transitDir . DIRECTORY_SEPARATOR . $f;
                if (is_file($fPath)) {
                    $transitCount++;
                    $transitSize += filesize($fPath);
                }
            }
        }

        echo json_encode([
            'success'        => true,
            'vault_metrics'  => $metrics,
            'transit_queue'  => [
                'pending_files' => $transitCount,
                'total_size_mb' => round($transitSize / (1024 * 1024), 2)
            ]
        ]);
        break;

    case 'list_pending':
        $stmt = $db->query("
            SELECT d.id, d.applicant_id, d.document_category, d.document_name, d.file_path, d.uploaded_at, a.customer_name 
            FROM applicant_documents d 
            JOIN applicants a ON d.applicant_id = a.id 
            WHERE d.file_path NOT LIKE 'F:%' AND d.file_path != '' AND d.status != 'Rejected' 
            ORDER BY d.id ASC
        ");
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'count' => count($pending), 'documents' => $pending]);
        break;

    case 'download_file':
        $docId = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT file_path, document_name FROM applicant_documents WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_path'])) {
            http_response_code(404);
            die(json_encode(['error' => 'File not found']));
        }
        $fpath = $row['file_path'];
        if (strpos($fpath, 'http://') === 0 || strpos($fpath, 'https://') === 0) {
            header('Location: ' . $fpath);
            exit;
        }
        $phys = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fpath), DIRECTORY_SEPARATOR);
        if (!file_exists($phys)) {
            http_response_code(404);
            die(json_encode(['error' => 'Physical file not found: ' . $phys]));
        }
        $ext = strtolower(pathinfo($phys, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($phys) . '"');
        header('Content-Length: ' . filesize($phys));
        readfile($phys);
        exit;

    case 'purge_file':
        $docId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $destPath = trim($_POST['dest_path'] ?? ($_GET['dest_path'] ?? ''));
        $stmt = $db->prepare("SELECT file_path FROM applicant_documents WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fpath = $row['file_path'];
            if (strpos($fpath, 'uploads/') !== false) {
                $phys = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fpath), DIRECTORY_SEPARATOR);
                if (file_exists($phys)) {
                    @unlink($phys);
                }
            }
            if (!empty($destPath)) {
                $upd = $db->prepare("UPDATE applicant_documents SET file_path = ? WHERE id = ?");
                $upd->execute([$destPath, $docId]);
            }
        }
        echo json_encode(['success' => true, 'message' => "Updated file path to $destPath and purged from VPS"]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown bridge action']);
        break;
}
