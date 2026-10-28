<?php
/**
 * BFS 10x On-Premise Document Vault Manager
 * 
 * Central Cockpit for F:\BFS Physical Document Storage,
 * Drive Metrics, Explorer Actions & Master Dossier Compilation.
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';
require_role(['Admin', 'RM']);

$page_title = 'On-Premise Document Vault';
$page_subtitle = 'Local Drive F:\\BFS • Zero Cloud Storage Cost • High-Speed Windows Explorer Integration';
require_once 'header.php';

$metrics = BfsVaultEngine::getVaultMetrics();
$activeBase = BfsVaultEngine::getActiveBasePath();

// Fetch all leads with folders on F:\BFS
$leadFolders = [];
if (is_dir($activeBase)) {
    $items = scandir($activeBase);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $itemPath = $activeBase . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath) && strpos($item, 'Lead_') === 0) {
            $parts = explode('_', $item, 3);
            $appId = isset($parts[1]) ? (int)$parts[1] : 0;
            
            // Count files in subfolders
            $subCounts = [];
            $totalFolderSize = 0;
            foreach (BfsVaultEngine::SUBFOLDERS as $key => $subName) {
                $subDir = $itemPath . DIRECTORY_SEPARATOR . $subName;
                $cnt = 0;
                if (is_dir($subDir)) {
                    $files = scandir($subDir);
                    foreach ($files as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $fPath = $subDir . DIRECTORY_SEPARATOR . $f;
                        if (is_file($fPath)) {
                            $cnt++;
                            $totalFolderSize += filesize($fPath);
                        }
                    }
                }
                $subCounts[$key] = $cnt;
            }

            // Lookup customer name in db
            $appRow = null;
            if ($appId > 0) {
                $stmt = $db->prepare("SELECT customer_name, loan_id, loan_type, loan_amount_requested, mobile FROM applicants WHERE id = ?");
                $stmt->execute([$appId]);
                $appRow = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $leadFolders[] = [
                'folder_name'  => $item,
                'folder_path'  => $itemPath,
                'applicant_id' => $appId,
                'customer_name'=> $appRow['customer_name'] ?? (isset($parts[2]) ? str_replace('_', ' ', $parts[2]) : 'Applicant'),
                'loan_id'      => $appRow['loan_id'] ?? 'REF-PENDING',
                'loan_type'    => $appRow['loan_type'] ?? 'Loan',
                'mobile'       => $appRow['mobile'] ?? '-',
                'sub_counts'   => $subCounts,
                'total_files'  => array_sum($subCounts),
                'size_kb'      => round($totalFolderSize / 1024, 1)
            ];
        }
    }
}
?>

<div class="view-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">

    <!-- 🌟 TOP MASTER VAULT STATS BANNER (DARK LUXURY THEME) -->
    <div style="background: #09090b; border: 1px solid #27272a; border-radius: 14px; padding: 24px; color: #ffffff; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 10px; background: #047857; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(4,120,87,0.4);">
                    <i data-lucide="hard-drive" style="width: 26px; height: 26px; color: #ffffff;"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
                        On-Premise Document Vault (F:\BFS)
                    </h2>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; font-size: 12px; color: #a1a1aa;">
                        <span>Target Root: <strong style="color: #38bdf8; font-family: monospace;"><?php echo htmlspecialchars($activeBase); ?></strong></span>
                        <span>•</span>
                        <span style="color: #10b981; font-weight: 700;">● Online &amp; Mounted</span>
                        <span>•</span>
                        <span>Zero Cloud Hosting Storage Load</span>
                    </div>
                </div>
            </div>

            <!-- Header Quick Action Group -->
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" onclick="openRootVaultFolder()" class="btn" style="background: #ffffff; color: #000000; border: none; padding: 9px 16px; border-radius: 7px; font-weight: 800; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(255,255,255,0.2);">
                    <i data-lucide="folder" style="width: 14px; height: 14px; color: #000000;"></i> 📂 Open F:\BFS in Explorer
                </button>
                <button type="button" onclick="runLegacyMigration()" id="btn-sync-legacy" class="btn" style="background: #18181b; color: #38bdf8; border: 1px solid #0284c7; padding: 9px 16px; border-radius: 7px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.15s ease;" onmouseover="this.style.background='#0369a1'; this.style.color='#fff';" onmouseout="this.style.background='#18181b'; this.style.color='#38bdf8';">
                    <i data-lucide="refresh-cw" style="width: 14px; height: 14px;"></i> ⚡ Sync to F:\BFS
                </button>
            </div>
        </div>

        <!-- 4-Metric Dark Glass Card Deck -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
            <!-- Metric 1: Free Space -->
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 10px; padding: 14px 18px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;">Physical Free Space</div>
                <div style="font-size: 22px; font-weight: 800; color: #10b981; font-family: 'Outfit', sans-serif; margin-top: 4px;">
                    <?php echo $metrics['disk_free_gb']; ?> GB
                </div>
                <div style="font-size: 11px; color: #71717a; margin-top: 4px;">
                    <?php echo $metrics['disk_free_percent']; ?>% available of <?php echo $metrics['disk_total_gb']; ?> GB
                </div>
            </div>

            <!-- Metric 2: Lead Folders -->
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 10px; padding: 14px 18px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;">Active Lead Folders</div>
                <div style="font-size: 22px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif; margin-top: 4px;">
                    <?php echo count($leadFolders); ?> Leads
                </div>
                <div style="font-size: 11px; color: #71717a; margin-top: 4px;">
                    Individually isolated &amp; categorized
                </div>
            </div>

            <!-- Metric 3: Total Files -->
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 10px; padding: 14px 18px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;">Indexed Documents</div>
                <div style="font-size: 22px; font-weight: 800; color: #38bdf8; font-family: 'Outfit', sans-serif; margin-top: 4px;">
                    <?php echo $metrics['total_files_in_vault']; ?> Files
                </div>
                <div style="font-size: 11px; color: #71717a; margin-top: 4px;">
                    Total stored size: <?php echo $metrics['vault_size_mb']; ?> MB
                </div>
            </div>

            <!-- Metric 4: Cloud VPS Cost Savings -->
            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 10px; padding: 14px 18px;">
                <div style="font-size: 11px; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.04em;">Cloud VPS Storage Load</div>
                <div style="font-size: 22px; font-weight: 800; color: #f59e0b; font-family: 'Outfit', sans-serif; margin-top: 4px;">
                    0 MB (100% Offloaded)
                </div>
                <div style="font-size: 11px; color: #71717a; margin-top: 4px;">
                    Zero server storage upgrade costs
                </div>
            </div>
        </div>

    </div>

    <!-- ⚡ ON-PREMISE LEAD VAULTS TABLE CARD -->
    <div class="card" style="padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif;">
                    <i data-lucide="folders" style="width: 18px; height: 18px; vertical-align: -2px; color: #047857;"></i> Physical Lead Vault Directories (F:\BFS\Vault_Leads)
                </h3>
                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                    Click "Reveal in Explorer" to instantly pop open the customer's folder on your office machine.
                </div>
            </div>
            
            <input type="text" id="vault-search-input" placeholder="🔍 Filter customer or folder..." onkeyup="filterVaultTable()" style="padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; width: 260px;">
        </div>

        <div class="table-responsive">
            <table class="data-table" id="vault-table" style="font-size: 13px; width: 100%;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 12px 14px; text-align: left;">Lead &amp; Customer Name</th>
                        <th style="padding: 12px 14px; text-align: left;">Loan Ref / Facility</th>
                        <th style="padding: 12px 14px; text-align: center;">01 KYC</th>
                        <th style="padding: 12px 14px; text-align: center;">02 Income</th>
                        <th style="padding: 12px 14px; text-align: center;">03 Property</th>
                        <th style="padding: 12px 14px; text-align: center;">05 Dockets</th>
                        <th style="padding: 12px 14px; text-align: right;">Folder Size</th>
                        <th style="padding: 12px 14px; text-align: right;">Explorer &amp; CRM Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leadFolders)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
                                No lead folders found on F:\BFS\Vault_Leads yet. Upload documents in Phase 2 to automatically provision folders.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leadFolders as $lf): ?>
                            <tr class="vault-row" style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px;">
                                    <strong style="color: #0f172a; font-size: 13.5px;"><?php echo htmlspecialchars($lf['customer_name']); ?></strong>
                                    <div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
                                        📂 <?php echo htmlspecialchars($lf['folder_name']); ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <span style="font-family: monospace; font-weight: 700; color: #0f172a; font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($lf['loan_id']); ?>
                                    </span>
                                    <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                                        <?php echo htmlspecialchars($lf['loan_type']); ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span class="badge" style="background: <?php echo $lf['sub_counts']['kyc'] > 0 ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $lf['sub_counts']['kyc'] > 0 ? '#166534' : '#64748b'; ?>; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                        <?php echo $lf['sub_counts']['kyc']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span class="badge" style="background: <?php echo $lf['sub_counts']['income'] > 0 ? '#dbeafe' : '#f1f5f9'; ?>; color: <?php echo $lf['sub_counts']['income'] > 0 ? '#1e40af' : '#64748b'; ?>; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                        <?php echo $lf['sub_counts']['income']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span class="badge" style="background: <?php echo $lf['sub_counts']['property'] > 0 ? '#fef3c7' : '#f1f5f9'; ?>; color: <?php echo $lf['sub_counts']['property'] > 0 ? '#92400e' : '#64748b'; ?>; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                        <?php echo $lf['sub_counts']['property']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: center;">
                                    <span class="badge" style="background: <?php echo $lf['sub_counts']['dockets'] > 0 ? '#f3e8ff' : '#f1f5f9'; ?>; color: <?php echo $lf['sub_counts']['dockets'] > 0 ? '#6b21a8' : '#64748b'; ?>; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                        <?php echo $lf['sub_counts']['dockets']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #475569;">
                                    <?php echo $lf['size_kb'] > 1024 ? round($lf['size_kb']/1024, 2) . ' MB' : $lf['size_kb'] . ' KB'; ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; white-space: nowrap;">
                                    <button type="button" onclick="openLeadFolder(<?php echo $lf['applicant_id']; ?>, '<?php echo addslashes($lf['customer_name']); ?>')" class="btn" style="background: #18181b; color: #38bdf8; border: 1px solid #0284c7; padding: 5px 11px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;" title="Open folder on F:\BFS in Windows Explorer">
                                        <i data-lucide="folder" style="width: 13px; height: 13px;"></i> 📂 Explorer
                                    </button>
                                    <?php if ($lf['applicant_id'] > 0): ?>
                                        <a href="applicant_documents.php?id=<?php echo $lf['applicant_id']; ?>" class="btn" style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 5px 11px; border-radius: 6px; font-weight: 700; font-size: 11.5px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; margin-left: 4px;">
                                            <i data-lucide="eye" style="width: 13px; height: 13px;"></i> Docs
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<script>
    async function openRootVaultFolder() {
        try {
            const res = await fetch('open_vault_folder.php?id=0&ajax=1');
            const data = await res.json();
            alert("📂 Opened Root Vault in Windows Explorer:\nF:\\BFS\\Vault_Leads");
        } catch (e) {
            window.open('open_vault_folder.php?id=0', '_blank');
        }
    }

    async function openLeadFolder(appId, custName) {
        try {
            const res = await fetch(`open_vault_folder.php?id=${appId}&ajax=1`);
            const data = await res.json();
            if (data.success) {
                alert(`📂 Opened Vault in Windows Explorer:\n${data.path}`);
            } else {
                alert(data.error || 'Failed to open folder');
            }
        } catch (e) {
            window.open(`open_vault_folder.php?id=${appId}`, '_blank');
        }
    }

    async function runLegacyMigration() {
        const btn = document.getElementById('btn-sync-legacy');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="animate-spin" style="width:14px; height:14px;"></i> Syncing...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const res = await fetch('vault_sync_bridge.php?action=pull_to_vault');
            const data = await res.json();
            if (data.success) {
                alert(data.message || 'Sync completed!');
                location.reload();
            } else {
                alert(data.error || 'Sync failed');
            }
        } catch (e) {
            alert('Server error occurred during sync');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    function filterVaultTable() {
        const query = document.getElementById('vault-search-input').value.toLowerCase();
        const rows = document.querySelectorAll('.vault-row');
        rows.forEach(r => {
            const text = r.innerText.toLowerCase();
            r.style.display = text.includes(query) ? '' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>

<?php require_once 'footer.php'; ?>
