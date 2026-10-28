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
        // Check if there are any transit files in uploads/vault_transit/
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
        $stmt = $db->query("SELECT id, applicant_id, document_category, document_name, file_path, uploaded_at FROM applicant_documents WHERE file_path LIKE 'uploads/%' OR file_path LIKE '%vault_transit%' ORDER BY id ASC");
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'count' => count($pending), 'documents' => $pending]);
        break;

    case 'pull_to_vault':
        // Moves all legacy or transit uploads into physical F:\BFS\Vault_Leads
        $stmt = $db->query("
            SELECT d.id, d.applicant_id, d.document_category, d.document_name, d.file_path, a.customer_name 
            FROM applicant_documents d 
            JOIN applicants a ON d.applicant_id = a.id 
            WHERE d.file_path NOT LIKE 'F:%' AND d.file_path != ''
        ");
        $legacyDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $migrated = 0;
        $failed = 0;

        foreach ($legacyDocs as $d) {
            $currentPhys = BfsVaultEngine::resolvePhysicalPath($d['file_path']);
            if (file_exists($currentPhys) && is_file($currentPhys)) {
                $res = BfsVaultEngine::storeDocument(
                    $d['applicant_id'],
                    $d['customer_name'],
                    $d['document_category'] ?: 'General',
                    $d['document_name'] ?: 'Document',
                    $currentPhys,
                    basename($currentPhys)
                );

                if ($res['success']) {
                    // Update database to point to physical F:\BFS
                    $upd = $db->prepare("UPDATE applicant_documents SET file_path = ?, file_size = ?, file_ext = ? WHERE id = ?");
                    $upd->execute([$res['full_path'], $res['file_size'], $res['ext'], $d['id']]);
                    // Clean up source file from local uploads
                    if (file_exists($currentPhys) && strpos($currentPhys, 'F:') !== 0) {
                        @unlink($currentPhys);
                    }
                    $migrated++;
                } else {
                    $failed++;
                }
            }
        }

        echo json_encode([
            'success'   => true,
            'migrated'  => $migrated,
            'failed'    => $failed,
            'message'   => "Successfully transferred $migrated document(s) to F:\\BFS\\Vault_Leads!"
        ]);
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
        $phys = BfsVaultEngine::resolvePhysicalPath($row['file_path']);
        if (!file_exists($phys)) {
            http_response_code(404);
            die(json_encode(['error' => 'Physical file not found']));
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($phys) . '"');
        header('Content-Length: ' . filesize($phys));
        readfile($phys);
        exit;

    case 'purge_file':
        $docId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        $destPath = trim($_POST['dest_path'] ?? '');
        $stmt = $db->prepare("SELECT file_path FROM applicant_documents WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $phys = BfsVaultEngine::resolvePhysicalPath($row['file_path']);
            if (file_exists($phys) && strpos($phys, 'uploads') !== false) {
                @unlink($phys);
            }
            if (!empty($destPath)) {
                $upd = $db->prepare("UPDATE applicant_documents SET file_path = ? WHERE id = ?");
                $upd->execute([$destPath, $docId]);
            }
        }
        echo json_encode(['success' => true, 'message' => "Purged file for doc #$docId from VPS disk"]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown bridge action']);
        break;
}
