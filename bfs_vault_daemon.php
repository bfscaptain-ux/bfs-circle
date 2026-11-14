<?php
/**
 * BFS 10x Real-Time VPS-to-Local Auto-Sync Daemon
 * 
 * Runs continuously or on-demand on the Office PC.
 * Automatically pulls newly uploaded customer documents from Cloud VPS (crm.bfsfin.cloud)
 * directly into F:\BFS\Vault_Leads, and immediately signals the VPS to update the file path
 * and purge temporary copies from VPS disk, maintaining 0 MB VPS cloud storage!
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bfs_vault_engine.php';

define('VPS_BASE_URL', getenv('BFS_VPS_URL') ?: 'https://crm.bfsfin.cloud');
define('VPS_SYNC_SECRET', 'BFS_VAULT_KEY_' . hash('sha256', 'BFS_VAULT_ON_PREM_F_DRIVE_STORAGE_SALT'));

class BfsVaultSyncDaemon {
    public static function runSyncCycle(): array {
        $log = [];
        $activeBase = BfsVaultEngine::getActiveBasePath();
        
        $log[] = "[" . date('Y-m-d H:i:s') . "] Starting VPS-to-Local Sync Cycle. Target: " . $activeBase;

        $apiUrl = VPS_BASE_URL . '/vault_sync_bridge.php?action=list_pending&key=' . VPS_SYNC_SECRET;
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($res)) {
            $log[] = " -> [WARN] Unable to fetch pending list from VPS (HTTP $httpCode).";
            return ['success' => false, 'error' => "HTTP $httpCode from VPS", 'log' => $log];
        }

        $data = json_decode($res, true);
        $pendingDocs = $data['documents'] ?? [];
        $migrated = 0;

        if (empty($pendingDocs)) {
            $log[] = " -> [INFO] No pending documents on VPS to sync. All up to date!";
        } else {
            $log[] = " -> [INFO] Found " . count($pendingDocs) . " pending document(s) on VPS to sync.";
        }

        $tempDownloadDir = __DIR__ . '/uploads/temp_sync/';
        if (!is_dir($tempDownloadDir)) {
            @mkdir($tempDownloadDir, 0777, true);
        }

        foreach ($pendingDocs as $doc) {
            $docId = (int)$doc['id'];
            $appId = (int)$doc['applicant_id'];
            $customerName = trim($doc['customer_name'] ?: 'Applicant_' . $appId);
            $cat = trim($doc['document_category'] ?: 'General');
            $docName = trim($doc['document_name'] ?: 'Document');
            $remotePath = $doc['file_path'];

            $log[] = "Processing Doc #{$docId}: {$docName} for {$customerName}...";

            // Determine download URL
            if (strpos($remotePath, 'http://') === 0 || strpos($remotePath, 'https://') === 0) {
                $downloadUrl = $remotePath;
            } else {
                $downloadUrl = VPS_BASE_URL . '/vault_sync_bridge.php?action=download_file&id=' . $docId . '&key=' . VPS_SYNC_SECRET;
            }

            $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $docName);
            $tempFile = $tempDownloadDir . 'doc_' . $docId . '_' . time() . '_' . $cleanName;
            
            $fp = fopen($tempFile, 'w+');
            $ch = curl_init($downloadUrl);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_exec($ch);
            $dlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($dlCode === 200 && file_exists($tempFile) && filesize($tempFile) > 0) {
                // Store in local F:\BFS\Vault_Leads
                $storeRes = BfsVaultEngine::storeDocument(
                    $appId,
                    $customerName,
                    $cat,
                    $docName,
                    $tempFile,
                    basename($docName)
                );

                @unlink($tempFile);

                if ($storeRes['success']) {
                    $localFullPath = $storeRes['full_path'];
                    $log[] = " -> SUCCESS: Stored in {$localFullPath} (" . round($storeRes['file_size']/1024) . " KB)";

                    // Notify VPS to update path to F:\BFS and purge from VPS disk
                    $purgeUrl = VPS_BASE_URL . '/vault_sync_bridge.php';
                    $postFields = http_build_query([
                        'action' => 'purge_file',
                        'key' => VPS_SYNC_SECRET,
                        'id' => $docId,
                        'dest_path' => $localFullPath
                    ]);
                    $chP = curl_init($purgeUrl);
                    curl_setopt($chP, CURLOPT_POST, true);
                    curl_setopt($chP, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($chP, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chP, CURLOPT_TIMEOUT, 10);
                    curl_exec($chP);
                    curl_close($chP);

                    $migrated++;
                } else {
                    $log[] = " -> [ERROR] BfsVaultEngine store failed: " . ($storeRes['error'] ?? 'Unknown');
                }
            } else {
                @unlink($tempFile);
                $log[] = " -> [ERROR] Download failed for Doc #{$docId} (HTTP $dlCode)";
            }
        }

        $metrics = BfsVaultEngine::getVaultMetrics();

        return [
            'success'        => true,
            'migrated_count' => $migrated,
            'vault_free_gb'  => $metrics['disk_free_gb'] ?? 0,
            'log'            => $log
        ];
    }
}

// If executed via CLI or HTTP
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $res = BfsVaultSyncDaemon::runSyncCycle();
    if (php_sapi_name() === 'cli') {
        echo implode("\n", $res['log']) . "\n";
        echo "Finished. Total files migrated to F:\\BFS: " . ($res['migrated_count'] ?? 0) . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($res, JSON_PRETTY_PRINT);
    }
    exit;
}
