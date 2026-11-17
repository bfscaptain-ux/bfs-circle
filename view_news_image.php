<?php
// view_news_image.php - Production Static & Dynamic Image Server for BFS News Engine
// Serves uploaded news images from CRM or Website storage with instant caching & CORS

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$file = basename($_GET['file'] ?? $_GET['img'] ?? '');
if (empty($file)) {
    http_response_code(400);
    echo 'File parameter is required';
    exit;
}

$candidates = [
    __DIR__ . '/uploads/news/' . $file,
    __DIR__ . '/uploads/' . $file,
    '/var/www/bfsfin/public/uploads/news/' . $file,
    '/var/www/bfsfin/public/news/' . $file,
    '/var/www/bfsfin.com/public/uploads/news/' . $file,
    '/var/www/bfsfin.com/public/news/' . $file,
    '/var/www/website/public/uploads/news/' . $file,
    'E:/Downloads/2026-08-06-bfsfin/public/uploads/news/' . $file,
    'E:/Downloads/2026-08-06-bfsfin/public/news/' . $file,
    'E:/Downloads/client mgmt2/uploads/news/' . $file,
];

$found = null;
foreach ($candidates as $c) {
    if (file_exists($c) && is_file($c)) {
        $found = $c;
        break;
    }
}

if ($found) {
    $ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
    $mimes = [
        'webp' => 'image/webp',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'gif'  => 'image/gif',
    ];
    $mime = $mimes[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($found));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($found);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Image not found on server';
