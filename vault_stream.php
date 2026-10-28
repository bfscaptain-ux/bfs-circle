<?php
/**
 * BFS Secure Vault File Streamer
 * 
 * Safely streams documents physically stored on F:\BFS or uploads/
 * to the browser with proper MIME headers, Range request support,
 * and access security.
 */

require_once 'config.php';
require_once 'bfs_vault_engine.php';

// Authentication: Admin/RM session or valid applicant tracking token
$isAuthenticated = false;
$userRole = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);
$userId = $_SESSION['user_id'] ?? null;
if (!empty($userRole) || !empty($userId)) {
    $isAuthenticated = true;
}

$token = $_GET['token'] ?? null;
if (!$isAuthenticated && !empty($token)) {
    $cleanToken = preg_replace('/[^a-f0-9]/i', '', $token);
    if (strlen($cleanToken) === 32) {
        $chk = $db->prepare("SELECT id FROM applicants WHERE tracking_token = ? AND datetime(tracking_token_created, '+30 days') > datetime('now')");
        $chk->execute([$cleanToken]);
        if ($chk->fetchColumn()) {
            $isAuthenticated = true;
        }
    }
}

if (!$isAuthenticated) {
    http_response_code(403);
    die("Access Denied. Please log in to view documents.");
}

$docId = (int)($_GET['id'] ?? 0);
$filePath = null;
$docName = 'Document';

if ($docId > 0) {
    $stmt = $db->prepare("SELECT d.*, a.customer_name FROM applicant_documents d LEFT JOIN applicants a ON d.applicant_id = a.id WHERE d.id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doc) {
        $filePath = $doc['file_path'];
        $docName = $doc['document_name'] ?: 'Document_' . $docId;
    }
} elseif (!empty($_GET['file'])) {
    // Direct safe path lookup within F:\BFS\Vault_Leads
    $reqFile = trim($_GET['file']);
    // Security: prevent directory traversal
    if (strpos($reqFile, '..') === false) {
        $resolved = BfsVaultEngine::resolvePhysicalPath($reqFile);
        if (file_exists($resolved)) {
            $filePath = $resolved;
            $docName = pathinfo($resolved, PATHINFO_FILENAME);
        }
    }
}

if (empty($filePath)) {
    http_response_code(404);
    die("Document record not found.");
}

$physicalPath = BfsVaultEngine::resolvePhysicalPath($filePath);

if (!file_exists($physicalPath) || !is_file($physicalPath)) {
    http_response_code(404);
    die("Physical file not found on disk at: " . htmlspecialchars($physicalPath));
}

$fileSize = filesize($physicalPath);
$ext = strtolower(pathinfo($physicalPath, PATHINFO_EXTENSION));

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'zip'  => 'application/zip',
    'txt'  => 'text/plain',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
$download = !empty($_GET['download']);
$cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $docName) . '.' . $ext;

// Clean output buffer to prevent corrupted binary streams
while (ob_get_level()) {
    ob_end_clean();
}

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mimeType);
header('Cache-Control: private, max-age=86400, must-revalidate');

if ($download) {
    header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $cleanFilename . '"');
}

// HTTP Range Requests Handling (for instant PDF page scrubbing & fast streaming)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
        $start = (int)$matches[1];
        $end = !empty($matches[2]) ? (int)$matches[2] : ($fileSize - 1);

        if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
        header("Content-Length: " . ($end - $start + 1));

        $fp = fopen($physicalPath, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        $chunkSize = 64 * 1024; // 64KB chunks

        while (!feof($fp) && $remaining > 0) {
            $readSize = min($remaining, $chunkSize);
            echo fread($fp, $readSize);
            flush();
            $remaining -= $readSize;
        }
        fclose($fp);
        exit;
    }
}

header('Content-Length: ' . $fileSize);
readfile($physicalPath);
exit;
