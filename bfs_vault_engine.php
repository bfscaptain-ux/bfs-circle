<?php
/**
 * BFS 10x High-Performance On-Premise Document Vault Engine
 * 
 * Target Physical Storage: F:\BFS\Vault_Leads\
 * Stores all customer loan documents locally on office hard disk,
 * completely eliminating VPS cloud storage costs & server load.
 */

class BfsVaultEngine {
    const PRIMARY_VAULT_PATH = 'F:\\BFS\\Vault_Leads';
    const FALLBACK_VAULT_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'vault_leads';

    const SUBFOLDERS = [
        'kyc'        => '01_KYC_Documents',
        'income'     => '02_Income_Banking',
        'property'   => '03_Property_Legal',
        'business'   => '04_Business_Financials',
        'dockets'    => '05_Bank_Dockets'
    ];

    /**
     * Configuration file path for dynamic storage settings
     */
    public static function getConfigPath(): string {
        return __DIR__ . DIRECTORY_SEPARATOR . 'vault_config.json';
    }

    /**
     * Loads the dynamic vault configuration
     */
    public static function getVaultConfig(): array {
        $cfgPath = self::getConfigPath();
        if (file_exists($cfgPath)) {
            $json = @file_get_contents($cfgPath);
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['vault_root'])) {
                return $data;
            }
        }
        return [
            'vault_root' => self::PRIMARY_VAULT_PATH,
            'backup_mirror_root' => '',
            'auto_purge_vps' => true
        ];
    }

    /**
     * Persists vault configuration
     */
    public static function saveVaultConfig(array $config): bool {
        $cfgPath = self::getConfigPath();
        return (bool)@file_put_contents($cfgPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Resolves the active base vault path.
     * Uses configured vault_root if drive is available, else falls back to uploads/vault_leads/.
     */
    public static function getActiveBasePath(): string {
        $cfg = self::getVaultConfig();
        $target = trim($cfg['vault_root'] ?? self::PRIMARY_VAULT_PATH);

        if (!empty($target)) {
            if (preg_match('/^([a-zA-Z]:)/', $target, $m)) {
                $driveRoot = $m[1] . '\\';
                if (file_exists($driveRoot)) {
                    if (!is_dir($target)) {
                        @mkdir($target, 0777, true);
                    }
                    if (is_dir($target)) {
                        return rtrim($target, '\\/');
                    }
                }
            } elseif (is_dir($target)) {
                return rtrim($target, '\\/');
            }
        }

        // Fallback for safety
        if (!is_dir(self::FALLBACK_VAULT_PATH)) {
            @mkdir(self::FALLBACK_VAULT_PATH, 0777, true);
        }
        return self::FALLBACK_VAULT_PATH;
    }

    /**
     * Maps document category to standardized vault subfolder
     */
    public static function mapCategoryToSubfolder(string $category): string {
        $cat = strtolower(trim($category));
        
        if (strpos($cat, 'kyc') !== false || strpos($cat, 'pan') !== false || strpos($cat, 'aadhaar') !== false || strpos($cat, 'photo') !== false || strpos($cat, 'voter') !== false || strpos($cat, 'co-app') !== false) {
            return self::SUBFOLDERS['kyc'];
        }
        if (strpos($cat, 'income') !== false || strpos($cat, 'salary') !== false || strpos($cat, 'bank') !== false || strpos($cat, 'statement') !== false || strpos($cat, 'itr') !== false || strpos($cat, 'form 16') !== false || strpos($cat, 'obligation') !== false) {
            return self::SUBFOLDERS['income'];
        }
        if (strpos($cat, 'property') !== false || strpos($cat, 'asset') !== false || strpos($cat, 'registry') !== false || strpos($cat, 'vehicle') !== false || strpos($cat, 'deed') !== false || strpos($cat, 'electricity') !== false) {
            return self::SUBFOLDERS['property'];
        }
        if (strpos($cat, 'business') !== false || strpos($cat, 'gst') !== false || strpos($cat, 'msme') !== false || strpos($cat, 'trade') !== false || strpos($cat, 'audit') !== false || strpos($cat, 'financial') !== false) {
            return self::SUBFOLDERS['business'];
        }
        return self::SUBFOLDERS['dockets'];
    }

    /**
     * Cleans string for safe filesystem folder and file naming
     */
    public static function sanitizeName(string $str): string {
        $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($str));
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_');
    }

    /**
     * Gets or creates lead directory in F:\BFS\Vault_Leads\Lead_{ID}_{Name}\
     */
    public static function getLeadVaultDir(int $applicantId, string $customerName): string {
        $base = self::getActiveBasePath();
        $safeName = self::sanitizeName($customerName);
        if (empty($safeName)) $safeName = 'Applicant';
        
        $leadDir = $base . DIRECTORY_SEPARATOR . "Lead_{$applicantId}_{$safeName}";
        if (!is_dir($leadDir)) {
            @mkdir($leadDir, 0777, true);
        }

        // Ensure all standard subfolders exist
        foreach (self::SUBFOLDERS as $sub) {
            $subPath = $leadDir . DIRECTORY_SEPARATOR . $sub;
            if (!is_dir($subPath)) {
                @mkdir($subPath, 0777, true);
            }
        }

        return $leadDir;
    }

    /**
     * Stores an uploaded document physically into F:\BFS
     * 
     * @param int $applicantId
     * @param string $customerName
     * @param string $category
     * @param string $docName
     * @param string $tempFilePath
     * @param string $originalFilename
     * @return array [success => bool, full_path => string, relative_path => string, file_size => int, ext => string]
     */
    public static function storeDocument(
        int $applicantId,
        string $customerName,
        string $category,
        string $docName,
        string $tempFilePath,
        string $originalFilename
    ): array {
        if (!file_exists($tempFilePath)) {
            return ['success' => false, 'error' => 'Temp file not found'];
        }

        $leadDir = self::getLeadVaultDir($applicantId, $customerName);
        $subFolder = self::mapCategoryToSubfolder($category);
        $targetDir = $leadDir . DIRECTORY_SEPARATOR . $subFolder;

        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (empty($ext)) $ext = 'pdf';

        $safeDocName = self::sanitizeName($docName);
        $safeCustomer = self::sanitizeName($customerName);
        $timestamp = date('Ymd_His');

        // Professional bank-standard filename: Rahul_Sharma_PAN_Card_20261028_134500.pdf
        $cleanFilename = "{$safeCustomer}_{$safeDocName}_{$timestamp}.{$ext}";
        $destPath = $targetDir . DIRECTORY_SEPARATOR . $cleanFilename;

        $moved = false;
        if (is_uploaded_file($tempFilePath)) {
            $moved = @move_uploaded_file($tempFilePath, $destPath);
        }
        if (!$moved) {
            $moved = @copy($tempFilePath, $destPath);
            if ($moved && !is_uploaded_file($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }

        if (!$moved) {
            return ['success' => false, 'error' => 'Failed to write file to Vault at: ' . $destPath];
        }

        $fileSize = @filesize($destPath) ?: 0;

        return [
            'success'       => true,
            'full_path'     => $destPath,
            'filename'      => $cleanFilename,
            'file_size'     => $fileSize,
            'ext'           => $ext,
            'subfolder'     => $subFolder,
            'lead_dir'      => $leadDir
        ];
    }

    /**
     * Resolves the actual physical path of any document record
     * Handles F:\BFS paths, legacy uploads/, and relative paths
     */
    public static function resolvePhysicalPath(string $filePath): string {
        $trimmed = trim($filePath);
        if (empty($trimmed)) return '';

        // Check if already an absolute Windows path
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $trimmed)) {
            if (file_exists($trimmed)) return $trimmed;
        }

        // Check relative to project root
        $projectPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $trimmed), DIRECTORY_SEPARATOR);
        if (file_exists($projectPath)) return $projectPath;

        // Check in current dir
        $curPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $trimmed), DIRECTORY_SEPARATOR);
        if (file_exists($curPath)) return $curPath;

        return $trimmed;
    }

    /**
     * Launch Windows Explorer directly to the applicant's vault folder
     */
    public static function openExplorer(int $applicantId, string $customerName): array {
        $folderPath = self::getLeadVaultDir($applicantId, $customerName);
        if (!is_dir($folderPath)) {
            return ['success' => false, 'error' => 'Folder does not exist: ' . $folderPath];
        }

        // On Windows, trigger explorer.exe
        $cmd = 'explorer.exe ' . escapeshellarg($folderPath);
        @pclose(@popen("start \"\" " . $cmd, "r"));

        return [
            'success' => true,
            'path'    => $folderPath,
            'message' => 'Windows Explorer opened to ' . $folderPath
        ];
    }

    /**
     * Scans and returns all available Windows logical filesystem drives
     */
    public static function getAvailableSystemDrives(): array {
        $drives = [];
        $letters = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];
        foreach ($letters as $l) {
            $root = $l . ':\\';
            if (@file_exists($root)) {
                $free = @disk_free_space($root);
                $total = @disk_total_space($root);
                if ($total > 0) {
                    $drives[] = [
                        'letter'    => $l . ':',
                        'root'      => $root,
                        'free_gb'   => round($free / (1024 * 1024 * 1024), 1),
                        'total_gb'  => round($total / (1024 * 1024 * 1024), 1),
                        'free_pct'  => round(($free / $total) * 100, 1)
                    ];
                }
            }
        }
        return $drives;
    }

    /**
     * Returns real-time disk and vault metrics for the active target drive
     */
    public static function getVaultMetrics(): array {
        $activeBase = self::getActiveBasePath();
        
        $driveLetter = 'F:';
        if (preg_match('/^([a-zA-Z]:)/', $activeBase, $m)) {
            $driveLetter = strtoupper($m[1]);
        }
        $driveRoot = $driveLetter . '\\';
        $isMounted = file_exists($driveRoot) && (stripos($activeBase, $driveLetter) === 0);

        $diskTotal = 0;
        $diskFree = 0;
        $diskUsed = 0;
        $freePercent = 0;

        if (file_exists($driveRoot)) {
            $diskTotal = @disk_total_space($driveRoot) ?: 0;
            $diskFree  = @disk_free_space($driveRoot) ?: 0;
            $diskUsed  = $diskTotal - $diskFree;
            if ($diskTotal > 0) {
                $freePercent = round(($diskFree / $diskTotal) * 100, 1);
            }
        }

        // Count lead folders in Vault
        $leadFoldersCount = 0;
        $totalVaultFiles = 0;
        $totalVaultSize = 0;

        if (is_dir($activeBase)) {
            $items = @scandir($activeBase) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $itemPath = $activeBase . DIRECTORY_SEPARATOR . $item;
                if (is_dir($itemPath) && strpos($item, 'Lead_') === 0) {
                    $leadFoldersCount++;
                    // Scan subfolders
                    foreach (self::SUBFOLDERS as $sub) {
                        $subPath = $itemPath . DIRECTORY_SEPARATOR . $sub;
                        if (is_dir($subPath)) {
                            $files = @scandir($subPath) ?: [];
                            foreach ($files as $f) {
                                if ($f === '.' || $f === '..') continue;
                                $fPath = $subPath . DIRECTORY_SEPARATOR . $f;
                                if (is_file($fPath)) {
                                    $totalVaultFiles++;
                                    $totalVaultSize += @filesize($fPath) ?: 0;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'drive_letter'        => $driveLetter,
            'vault_root'          => $activeBase,
            'is_f_mounted'        => $isMounted,
            'disk_total_gb'       => round($diskTotal / (1024 * 1024 * 1024), 2),
            'disk_free_gb'        => round($diskFree / (1024 * 1024 * 1024), 2),
            'disk_used_gb'        => round($diskUsed / (1024 * 1024 * 1024), 2),
            'disk_free_percent'   => $freePercent,
            'lead_folders_count'  => $leadFoldersCount,
            'total_files_in_vault'=> $totalVaultFiles,
            'vault_size_mb'       => round($totalVaultSize / (1024 * 1024), 2),
            'status'              => $isMounted ? 'ONLINE_ACTIVE' : 'FALLBACK_LOCAL'
        ];
    }

    /**
     * Switch storage root to a new local drive / directory with checkpoints & migration
     */
    public static function switchVaultStoragePath(string $newPath, bool $migrateExisting = false): array {
        $cleanPath = trim(str_replace('/', DIRECTORY_SEPARATOR, $newPath));
        $cleanPath = rtrim($cleanPath, '\\/');

        // Checkpoint 1: Format validation
        if (!preg_match('/^[a-zA-Z]:[\\\\\/]/', $cleanPath)) {
            return ['success' => false, 'error' => 'Invalid Windows drive path format. Example: D:\\BFS\\Vault_Leads'];
        }

        // Checkpoint 2: Drive existence
        preg_match('/^([a-zA-Z]:)/', $cleanPath, $m);
        $driveLetter = strtoupper($m[1]);
        $driveRoot = $driveLetter . '\\';
        if (!file_exists($driveRoot)) {
            return ['success' => false, 'error' => "Drive $driveLetter does not exist or is disconnected on this machine."];
        }

        // Checkpoint 3: Directory creation & write permissions test
        if (!is_dir($cleanPath)) {
            $created = @mkdir($cleanPath, 0777, true);
            if (!$created && !is_dir($cleanPath)) {
                return ['success' => false, 'error' => "Could not create target directory: $cleanPath. Check Windows permissions."];
            }
        }

        $testFile = $cleanPath . DIRECTORY_SEPARATOR . '.bfs_permission_test_' . time() . '.tmp';
        $writeOk = @file_put_contents($testFile, 'BFS_WRITE_OK');
        if (!$writeOk) {
            return ['success' => false, 'error' => "Directory is read-only or permission denied: $cleanPath"];
        }
        @unlink($testFile);

        // Checkpoint 4: Minimum free disk space check (at least 1 GB)
        $freeBytes = @disk_free_space($driveRoot) ?: 0;
        $freeGb = round($freeBytes / (1024 * 1024 * 1024), 2);
        if ($freeGb < 1.0) {
            return ['success' => false, 'error' => "Drive $driveLetter has insufficient free space ($freeGb GB). Need at least 1 GB."];
        }

        $oldBase = self::getActiveBasePath();
        $migratedCount = 0;

        // Checkpoint 5: Migration of existing lead files
        if ($migrateExisting && is_dir($oldBase) && strcasecmp($oldBase, $cleanPath) !== 0) {
            global $db;
            $items = @scandir($oldBase) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $srcLead = $oldBase . DIRECTORY_SEPARATOR . $item;
                $dstLead = $cleanPath . DIRECTORY_SEPARATOR . $item;
                if (is_dir($srcLead) && strpos($item, 'Lead_') === 0) {
                    self::copyRecursive($srcLead, $dstLead);
                }
            }

            // Update database paths
            if (isset($db)) {
                $stmt = $db->prepare("SELECT id, file_path FROM applicant_documents WHERE file_path LIKE ?");
                $stmt->execute([$oldBase . '%']);
                $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($docs as $d) {
                    $newFilePath = str_replace($oldBase, $cleanPath, $d['file_path']);
                    $upd = $db->prepare("UPDATE applicant_documents SET file_path = ? WHERE id = ?");
                    $upd->execute([$newFilePath, $d['id']]);
                    $migratedCount++;
                }
            }
        }

        // Save new path to config
        $cfg = self::getVaultConfig();
        $cfg['vault_root'] = $cleanPath;
        $cfg['last_updated'] = date('Y-m-d H:i:s');
        self::saveVaultConfig($cfg);

        return [
            'success'        => true,
            'old_path'       => $oldBase,
            'new_path'       => $cleanPath,
            'drive_letter'   => $driveLetter,
            'free_gb'        => $freeGb,
            'migrated_files' => $migratedCount,
            'message'        => "Storage location successfully switched to $cleanPath! Drive $driveLetter has $freeGb GB free space."
        ];
    }

    private static function copyRecursive(string $src, string $dst): void {
        if (!is_dir($dst)) {
            @mkdir($dst, 0777, true);
        }
        $dir = @opendir($src);
        if (!$dir) return;
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . DIRECTORY_SEPARATOR . $file;
                $dstFile = $dst . DIRECTORY_SEPARATOR . $file;
                if (is_dir($srcFile)) {
                    self::copyRecursive($srcFile, $dstFile);
                } else {
                    @copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
}
