<?php
// api_website_news.php - Bridge between CRM (PHP port 8000) and Website (bfsfin.com / port 3000)
// Allows CRM admins to publish, edit, and delete financial news on bfsfin.com

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$local_news_json = 'E:/Downloads/2026-08-06-bfsfin/src/data/news.json';
// On VPS, the path would be /var/www/bfsfin/src/data/news.json
if (!file_exists($local_news_json) && file_exists('/var/www/bfsfin/src/data/news.json')) {
    $local_news_json = '/var/www/bfsfin/src/data/news.json';
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

// 1. LIST ACTION
if ($action === 'list') {
    if (file_exists($local_news_json)) {
        $content = file_get_contents($local_news_json);
        $articles = json_decode($content, true) ?: [];
        echo json_encode(['success' => true, 'data' => $articles]);
        exit;
    }

    // Fallback: Fetch via cURL from website
    $ch = curl_init('https://bfsfin.com/api/news?status=all');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $res) {
        echo $res;
        exit;
    }

    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

// 2. SAVE (CREATE / UPDATE) ACTION
if ($action === 'save') {
    $title = trim($body['title'] ?? '');
    $content = trim($body['content'] ?? '');
    $id = trim($body['id'] ?? '');

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Title and content are required.']);
        exit;
    }

    $category = trim($body['category'] ?? 'Banking & RBI');
    $author = trim($body['author'] ?? 'Adv. Praveen Bhardwaj');
    $imageUrl = trim($body['imageUrl'] ?? 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80');
    $tldr = is_array($body['tldr'] ?? null) ? $body['tldr'] : [];
    $ctaType = trim($body['ctaType'] ?? 'home-loan');
    $isTrending = !empty($body['isTrending']);
    $status = trim($body['status'] ?? 'published');
    $seoTitle = trim($body['seoTitle'] ?? $title);
    $metaDescription = trim($body['metaDescription'] ?? ($tldr[0] ?? $title));
    $focusKeyword = trim($body['focusKeyword'] ?? '');

    // Read existing
    $articles = [];
    if (file_exists($local_news_json)) {
        $articles = json_decode(file_get_contents($local_news_json), true) ?: [];
    }

    if (!empty($id)) {
        // UPDATE existing
        $found = false;
        foreach ($articles as &$a) {
            if ($a['id'] === $id) {
                $a['title'] = $title;
                $a['category'] = $category;
                $a['author'] = $author;
                $a['imageUrl'] = $imageUrl;
                $a['tldr'] = $tldr;
                $a['content'] = $content;
                $a['ctaType'] = $ctaType;
                $a['isTrending'] = $isTrending;
                $a['status'] = $status;
                $a['seoTitle'] = $seoTitle;
                $a['metaDescription'] = $metaDescription;
                $a['focusKeyword'] = $focusKeyword;
                $a['readTime'] = calculate_read_time($content);
                $a['updatedAt'] = date('c');
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Article not found.']);
            exit;
        }
    } else {
        // CREATE new
        $base_slug = generate_slug($title);
        $unique_slug = $base_slug;
        $counter = 1;
        while (array_filter($articles, fn($item) => ($item['slug'] ?? '') === $unique_slug)) {
            $unique_slug = $base_slug . '-' . $counter;
            $counter++;
        }

        $new_item = [
            'id' => 'news-' . time(),
            'title' => $title,
            'slug' => $unique_slug,
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
            'canonicalUrl' => 'https://bfsfin.com/news/' . $unique_slug,
        ];
        array_unshift($articles, $new_item);
    }

    if (file_exists($local_news_json) || is_dir(dirname($local_news_json))) {
        file_put_contents($local_news_json, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true, 'message' => 'Article saved successfully to website!']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'news.json path not writable']);
    exit;
}

// 3. DELETE ACTION
if ($action === 'delete') {
    $id = trim($_GET['id'] ?? $body['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'Article ID is required.']);
        exit;
    }

    if (file_exists($local_news_json)) {
        $articles = json_decode(file_get_contents($local_news_json), true) ?: [];
        $initial_count = count($articles);
        $articles = array_values(array_filter($articles, fn($a) => ($a['id'] ?? '') !== $id));
        if (count($articles) < $initial_count) {
            file_put_contents($local_news_json, json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            echo json_encode(['success' => true, 'message' => 'Article deleted successfully.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Could not delete article.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
