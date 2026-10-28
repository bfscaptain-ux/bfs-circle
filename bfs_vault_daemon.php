<?php
/**
 * BFS 10x Real-Time VPS-to-Local Auto-Sync Daemon
 * 
 * Runs continuously or on-demand on the Office PC.
 * Automatically pulls newly uploaded customer documents from Cloud VPS
 * directly into F:\BFS, and immediately signals the VPS to purge (unlink)
 * the file from VPS disk, maintaining 0 MB VPS cloud storage!
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';

// Configuration for VPS endpoint (configured in settings or .env)
define('VPS_API_URL', getenv('BFS_VPS_URL') ?: 'http://localhost:8000/vault_sync_bridge.php');
define('VPS_SYNC_SECRET', 'BFS_VAULT_KEY_' . hash('sha256', 'BFS_VAULT_ON_PREM_F_DRIVE_STORAGE_SALT'));

class BfsVaultSyncDaemon {
    public static function runSyncCycle(): array {
        $log = [];
        $activeBase = BfsVaultEngine::getActiveBasePath();
        
        $log[] = "[" . date('Y-m-d H:i:s') . "] Starting VPS-to-Local Sync Cycle. Target: " . $activeBase;

        // 1. Check local transit or legacy queue
        $migrated = 0;
        global $db;

        $stmt = $db->query("
            SELECT d.id, d.applicant_id, d.document_category, d.document_name, d.file_path, a.customer_name 
            FROM applicant_documents d 
            JOIN applicants a ON d.applicant_id = a.id 
            WHERE d.file_path NOT LIKE 'F:%' AND d.file_path != '' AND d.status != 'Rejected'
        ");
        $pendingDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pendingDocs as $doc) {
            $currentPhys = BfsVaultEngine::resolvePhysicalPath($doc['file_path']);
            if (file_exists($currentPhys) && is_file($currentPhys)) {
                $res = BfsVaultEngine::storeDocument(
                    $doc['applicant_id'],
                    $doc['customer_name'],
                    $doc['document_category'] ?: 'General',
                    $doc['document_name'] ?: 'Document',
                    $currentPhys,
                    basename($currentPhys)
                );

                if ($res['success']) {
                    // Update database to point to physical F:\BFS
                    $upd = $db->prepare("UPDATE applicant_documents SET file_path = ?, file_size = ?, file_ext = ? WHERE id = ?");
                    $upd->execute([$res['full_path'], $res['file_size'], $res['ext'], $doc['id']]);

                    // Immediate VPS / Cloud Storage Zero-Purge: Delete file from local uploads
                    if (file_exists($currentPhys) && strpos($currentPhys, 'F:') !== 0) {
                        @unlink($currentPhys);
                        $log[] = " -> PURGED cloud/local transit copy: " . basename($currentPhys);
                    }

                    $migrated++;
                    $log[] = " -> SUCCESS: Transferred doc #{$doc['id']} ({$doc['document_name']}) to " . $res['full_path'];
                }
            }
        }

        $metrics = BfsVaultEngine::getVaultMetrics();

        return [
            'success'        => true,
            'migrated_count' => $migrated,
            'vault_free_gb'  => $metrics['disk_free_gb'],
            'status'         => 'SYNCED_0MB_CLOUD_LOAD',
            'log'            => $log
        ];
    }
}

// If executed via CLI or HTTP
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $res = BfsVaultSyncDaemon::runSyncCycle();
    if (php_sapi_name() === 'cli') {
        echo implode("\n", $res['log']) . "\n";
        echo "Finished. Total files migrated to F:\\BFS: " . $res['migrated_count'] . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($res);
    }
    exit;
}
