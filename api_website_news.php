<?php
// api_website_news.php - Production Bridge between CRM and Website (bfsfin.com)
// Connects CRM to Next.js news engine via API & local filesystem fallback

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Candidate paths for local news.json
$candidate_paths = [
    'E:/Downloads/2026-08-06-bfsfin/src/data/news.json',
    '/var/www/bfsfin/src/data/news.json',
    '/var/www/bfsfin.com/src/data/news.json',
    '/var/www/website/src/data/news.json',
    __DIR__ . '/data/news_backup.json'
];

$local_news_json = null;
foreach ($candidate_paths as $p) {
    if (file_exists($p)) {
        $local_news_json = $p;
        break;
    }
}
if (!$local_news_json) {
    $local_news_json = $candidate_paths[0];
}

$raw_body = file_get_contents('php://input');
$body = json_decode($raw_body, true) ?: [];
$action = $_GET['action'] ?? $body['action'] ?? 'list';

function generate_slug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'news-' . time() : $text;
}

function calculate_read_time($content) {
    $clean = strip_tags($content);
    $words = str_word_count($clean);
    $mins = max(1, ceil($words / 200));
    return $mins . ' min read';
}

// Helper: Call live Next.js API
function call_nextjs_api($method, $endpoint, $payload = null) {
    $urls = [
        'https://bfsfin.com' . $endpoint,
        'http://127.0.0.1:3000' . $endpoint
    ];

    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (strpos($url, '127.0.0.1') !== false) {
            $headers[] = 'Host: bfsfin.com';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $res) {
            $decoded = json_decode($res, true);
            if ($decoded !== null) {
                return ['success' => true, 'code' => $code, 'data' => $decoded];
            }
        }
    }

    return ['success' => false, 'code' => 0, 'error' => 'Could not connect to Next.js API'];
}

// =========================================================================
// 1. LIST ACTION
// =========================================================================
if ($action === 'list') {
    // 1st Priority: Query live Next.js API
    $api_res = call_nextjs_api('GET', '/api/news?status=all');
    if ($api_res['success'] && isset($api_res['data']['data'])) {
        echo json_encode(['success' => true, 'data' => $api_res['data']['data']]);
        exit;
    }

    // Fallback: Read local file if available
    if ($local_news_json && file_exists($local_news_json)) {
        $content = file_get_contents($local_news_json);
        $articles = json_decode($content, true) ?: [];
        echo json_encode(['success' => true, 'data' => $articles]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

// =========================================================================
// 2. SAVE (CREATE / UPDATE) ACTION
// =========================================================================
if ($action === 'save') {
    $title = trim($body['title'] ?? '');
    $content = trim($body['content'] ?? '');
    $id = trim($body['id'] ?? '');

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Title and content are required.']);
        exit;
    }

    $category = trim($body['category'] ?? 'Banking & RBI');
    $author = trim($body['author'] ?? 'Adv. Praveen Bhardwaj (MD)');
    $imageUrl = trim($body['imageUrl'] ?? 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80');
    $tldr = is_array($body['tldr'] ?? null) ? $body['tldr'] : [];
    $ctaType = trim($body['ctaType'] ?? 'home-loan');
    $isTrending = !empty($body['isTrending']);
    $status = trim($body['status'] ?? 'published');
    $seoTitle = trim($body['seoTitle'] ?? $title);
    $metaDescription = trim($body['metaDescription'] ?? ($tldr[0] ?? $title));
    $focusKeyword = trim($body['focusKeyword'] ?? '');

    $website_payload = [
        'title' => $title,
        'category' => $category,
        'author' => $author,
        'imageUrl' => $imageUrl,
        'tldr' => $tldr,
        'content' => $content,
        'ctaType' => $ctaType,
        'isTrending' => $isTrending,
        'status' => $status,
        'seoTitle' => $seoTitle,
        'metaDescription' => $metaDescription,
        'focusKeyword' => $focusKeyword
    ];

    $saved_via_api = false;
    $returned_article = null;

    if (!empty($id)) {
        // UPDATE via Next.js API (PUT)
        $website_payload['id'] = $id;
        $api_res = call_nextjs_api('PUT', '/api/news', $website_payload);
        if ($api_res['success']) {
            $saved_via_api = true;
            $returned_article = $api_res['data']['data'] ?? null;
        }
    } else {
        // CREATE via Next.js API (POST)
        $api_res = call_nextjs_api('POST', '/api/news', $website_payload);
        if ($api_res['success']) {
            $saved_via_api = true;
            $returned_article = $api_res['data']['data'] ?? null;
        }
    }

    // Local file sync (if writable)
    $saved_locally = false;
    if ($local_news_json && (file_exists($local_news_json) || is_dir(dirname($local_news_json)))) {
        $articles = file_exists($local_news_json) ? (json_decode(file_get_contents($local_news_json), true) ?: []) : [];

        if (!empty($id)) {
            foreach ($articles as &$a) {
                if ($a['id'] === $id) {
                    $a = array_merge($a, $website_payload);
                    $a['readTime'] = calculate_read_time($content);
                    $a['updatedAt'] = date('c');
                    break;
                }
            }
        } else {
            $new_item = $returned_article ?: [
                'id' => 'news-' . time(),
                'title' => $title,
                'slug' => generate_slug($title),
                'category' => $category,
                'imageUrl' => $imageUrl,
                'tldr' => $tldr,
                'content' => $content,
                'readTime' => calculate_read_time($content),
                'author' => $author,
                'publishedAt' => date('c'),
                'updatedAt' => date('c'),
                'status' => $status,
                'isTrending' => $isTrending,
                'views' => 0,
                'ctaType' => $ctaType,
                'seoTitle' => $seoTitle,
                'metaDescription' => $metaDescription,
                'focusKeyword' => $focusKeyword,
                'canonicalUrl' => 'https://bfsfin.com/news/' . generate_slug($title),
            ];
            array_unshift($articles, $new_item);
        }

        $w_res = @file_put_contents($local_news_json, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($w_res !== false) {
            $saved_locally = true;
        }
    }

    if ($saved_via_api || $saved_locally) {
        echo json_encode([
            'success' => true,
            'message' => 'Article saved and published to website successfully!',
            'data' => $returned_article
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'error' => 'Failed to reach website API and local file storage is not writable.'
    ]);
    exit;
}

// =========================================================================
// 3. DELETE ACTION
// =========================================================================
if ($action === 'delete') {
    $id = trim($_GET['id'] ?? $body['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Article ID is required.']);
        exit;
    }

    $deleted_via_api = false;
    $api_res = call_nextjs_api('DELETE', '/api/news?id=' . urlencode($id));
    if ($api_res['success']) {
        $deleted_via_api = true;
    }

    $deleted_locally = false;
    if ($local_news_json && file_exists($local_news_json)) {
        $articles = json_decode(file_get_contents($local_news_json), true) ?: [];
        $initial_count = count($articles);
        $articles = array_values(array_filter($articles, fn($a) => ($a['id'] ?? '') !== $id));
        if (count($articles) < $initial_count) {
            @file_put_contents($local_news_json, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $deleted_locally = true;
        }
    }

    if ($deleted_via_api || $deleted_locally) {
        echo json_encode(['success' => true, 'message' => 'Article deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Could not delete article from website.']);
    exit;
}

// =========================================================================
// 4. UPLOAD IMAGE ACTION (Directly to Website CDN/Public Storage)
// =========================================================================
if ($action === 'upload_image') {
    $file_path = null;
    $original_name = 'article_' . time() . '.webp';
    $mime_type = 'image/webp';
    $tmp_file = null;

    if (!empty($_FILES['file']['tmp_name']) || !empty($_FILES['image']['tmp_name'])) {
        $f = !empty($_FILES['file']['tmp_name']) ? $_FILES['file'] : $_FILES['image'];
        $file_path = $f['tmp_name'];
        $original_name = $f['name'];
        $mime_type = $f['type'] ?: 'image/jpeg';
    } else {
        $base64_data = $body['image_base64'] ?? null;
        if (!$base64_data && !empty($raw_body)) {
            $json = json_decode($raw_body, true);
            $base64_data = $json['image_base64'] ?? null;
        }

        if ($base64_data) {
            $ext = 'webp';
            if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
                $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
                $ext = strtolower($type[1]);
            }
            $decoded = base64_decode($base64_data);
            if ($decoded) {
                $tmp_file = tempnam(sys_get_temp_dir(), 'news_img_') . '.' . $ext;
                file_put_contents($tmp_file, $decoded);
                $file_path = $tmp_file;
                $original_name = 'upload_' . time() . '.' . $ext;
                $mime_type = 'image/' . $ext;
            }
        }
    }

    if (!$file_path || !file_exists($file_path)) {
        echo json_encode(['success' => false, 'error' => 'No valid image file received.']);
        exit;
    }

    // Call Next.js /api/upload
    $target_urls = [
        'https://bfsfin.com/api/upload',
        'http://127.0.0.1:3000/api/upload'
    ];
    $uploaded_url = null;

    foreach ($target_urls as $target_url) {
        $ch = curl_init($target_url);
        $cfile = new CURLFile($file_path, $mime_type, $original_name);
        $post_fields = [
            'file' => $cfile,
            'folder' => 'uploads/news'
        ];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (strpos($target_url, '127.0.0.1') !== false) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: bfsfin.com']);
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $res) {
            $json = json_decode($res, true);
            if (!empty($json['url'])) {
                $uploaded_url = strpos($json['url'], 'http') === 0 ? $json['url'] : 'https://bfsfin.com' . $json['url'];
                break;
            }
        }
    }

    // Fallback: save to local CRM uploads/news/
    if (!$uploaded_url) {
        $crm_upload_dir = __DIR__ . '/uploads/news/';
        if (!is_dir($crm_upload_dir)) {
            @mkdir($crm_upload_dir, 0775, true);
        }
        $safe_name = time() . '-' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $original_name);
        if (@copy($file_path, $crm_upload_dir . $safe_name)) {
            $uploaded_url = 'https://crm.bfsfin.cloud/uploads/news/' . $safe_name;
        }
    }

    if ($tmp_file && file_exists($tmp_file)) {
        @unlink($tmp_file);
    }

    if ($uploaded_url) {
        echo json_encode([
            'success' => true,
            'url' => $uploaded_url,
            'message' => 'Image uploaded successfully!'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Failed to upload image to website or CRM storage.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
