<?php
/**
 * BFS 10x Bank Docket / Single Master PDF Compiler Bridge
 * 
 * Compiles all verified loan documents into a single executive bank submission PDF
 * with BFS Cover Sheet and auto-watermarking, saved directly to F:\BFS.
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';
require_role(['Admin', 'RM']);

$applicantId = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($applicantId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing applicant ID']);
    exit;
}

// 1. Fetch Applicant
$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$applicantId]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Applicant not found']);
    exit;
}

// Fetch Co-applicants
$co_stmt = $db->prepare("SELECT full_name, relationship FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$applicantId]);
$co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);
$co_str = 'None';
if (!empty($co_apps)) {
    $co_names = [];
    foreach ($co_apps as $c) {
        $co_names[] = $c['full_name'] . ' (' . ($c['relationship'] ?: 'Co-App') . ')';
    }
    $co_str = implode(', ', $co_names);
}
$applicant['co_applicant'] = $co_str;

// 2. Fetch Documents
$doc_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ? AND status != 'Rejected' ORDER BY id ASC");
$doc_stmt->execute([$applicantId]);
$raw_docs = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

$valid_docs = [];
foreach ($raw_docs as $d) {
    $phys = BfsVaultEngine::resolvePhysicalPath($d['file_path']);
    if (file_exists($phys) && is_file($phys)) {
        $d['file_path'] = $phys;
        $valid_docs[] = $d;
    }
}

if (empty($valid_docs)) {
    echo json_encode(['success' => false, 'error' => 'No valid document files found to compile. Please upload KYC or Income documents first.']);
    exit;
}

$targetBank = trim($_POST['bank_name'] ?? ($_GET['bank_name'] ?? ''));
if (empty($targetBank)) {
    $targetBank = $applicant['bank_name'] ?? 'Partner Bank';
}
$applyWatermark = isset($_POST['watermark']) ? (bool)$_POST['watermark'] : true;

// 3. Prepare Vault Output Path on F:\BFS
$leadDir = BfsVaultEngine::getLeadVaultDir($applicant['id'], $applicant['customer_name']);
$docketsDir = $leadDir . DIRECTORY_SEPARATOR . BfsVaultEngine::SUBFOLDERS['dockets'];
if (!is_dir($docketsDir)) {
    @mkdir($docketsDir, 0777, true);
}

$safeBank = BfsVaultEngine::sanitizeName($targetBank);
$timestamp = date('Ymd_His');
$outputFilename = "BFS_Bank_Docket_{$safeBank}_{$timestamp}.pdf";
$outputPath = $docketsDir . DIRECTORY_SEPARATOR . $outputFilename;

// 4. Write Configuration JSON for Python Compiler
$configData = [
    'applicant'       => $applicant,
    'documents'       => $valid_docs,
    'target_bank'     => $targetBank,
    'apply_watermark' => $applyWatermark,
    'output_path'     => $outputPath
];

$scratchDir = __DIR__ . DIRECTORY_SEPARATOR . 'scratch';
if (!is_dir($scratchDir)) {
    @mkdir($scratchDir, 0777, true);
}
$cfgFile = $scratchDir . DIRECTORY_SEPARATOR . "docket_cfg_{$applicantId}_" . time() . ".json";
file_put_contents($cfgFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// 5. Execute Python Engine
$pythonCmd = 'python ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'vault_docket_compiler.py') . ' ' . escapeshellarg($cfgFile);
$pyOutput = shell_exec($pythonCmd);

@unlink($cfgFile);

$pyResult = json_decode($pyOutput, true);

if (!$pyResult || empty($pyResult['success'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Compilation failed: ' . ($pyResult['error'] ?? $pyOutput)
    ]);
    exit;
}

// 6. Record New Master Docket into applicant_documents table
$db->exec("PRAGMA busy_timeout = 20000;");

$insStmt = $db->prepare("
    INSERT INTO applicant_documents 
    (applicant_id, client_mobile, client_pan, document_category, document_name, file_path, file_size, file_ext, status, qa_status, notes, owner_type)
    VALUES (?, ?, ?, 'Bank Login & Fee Receipts', ?, ?, ?, 'pdf', 'Verified', 'Approved', ?, 'Applicant')
");

$docketTitle = "Combined Bank Docket ({$targetBank})";
$note = "Auto-compiled dossier containing {$pyResult['docs_compiled']} documents across {$pyResult['total_pages']} pages with watermark.";
$fileSize = @filesize($outputPath) ?: ($pyResult['file_size'] ?? 0);

$maxRetries = 3;
$newDocId = 0;
for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $insStmt->execute([
            $applicant['id'],
            $applicant['mobile'],
            $applicant['pan_number'] ?? '',
            $docketTitle,
            $outputPath,
            $fileSize,
            $note
        ]);
        $newDocId = (int)$db->lastInsertId();
        break;
    } catch (PDOException $e) {
        if ($attempt === $maxRetries) throw $e;
        usleep(500000); // 500ms
    }
}

log_activity("Generated Master Bank Docket for {$applicant['customer_name']} ({$targetBank}) - {$pyResult['total_pages']} pages", "applicant_documents.php?id={$applicant['id']}");

$streamUrl = "vault_stream.php?id={$newDocId}";

header('Content-Type: application/json');
echo json_encode([
    'success'       => true,
    'message'       => "Master Bank Docket compiled successfully with {$pyResult['total_pages']} pages!",
    'doc_id'        => $newDocId,
    'stream_url'    => $streamUrl,
    'download_url'  => $streamUrl . '&download=1',
    'total_pages'   => $pyResult['total_pages'],
    'docs_compiled' => $pyResult['docs_compiled'],
    'file_path'     => $outputPath,
    'file_size_kb'  => round($fileSize / 1024, 1)
]);
