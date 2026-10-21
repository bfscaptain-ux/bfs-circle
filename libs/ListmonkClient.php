<?php
/**
 * BFS Circle - Listmonk Marketing & Campaign Engine Client
 * Seamlessly interfaces with Listmonk Go Backend REST API
 */

class ListmonkClient {
    private string $baseUrl;
    private string $apiUser;
    private string $apiToken;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $apiUser = null, ?string $apiToken = null, int $timeout = 15) {
        $this->baseUrl = rtrim($baseUrl ?: (defined('LISTMONK_API_URL') ? LISTMONK_API_URL : 'http://127.0.0.1:9000'), '/');
        $this->apiUser = $apiUser ?: (defined('LISTMONK_API_USER') ? LISTMONK_API_USER : 'bfs_crm_api');
        $this->apiToken = $apiToken ?: (defined('LISTMONK_API_TOKEN') ? LISTMONK_API_TOKEN : 'bfs_listmonk_secret_key_789456');
        $this->timeout = $timeout;
    }

    /**
     * Send HTTP Request to Listmonk REST API
     */
    private function request(string $endpoint, string $method = 'GET', ?array $data = null): array {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $authHeader = 'Basic ' . base64_encode($this->apiUser . ':' . $this->apiToken);
        
        $headers = [
            'Authorization: ' . $authHeader,
            'Accept: application/json',
            'User-Agent: BFSCircle-CRM-Client/1.0'
        ];

        $payload = null;
        if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $payload = json_encode($data);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($payload);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['success' => false, 'code' => 0, 'error' => 'Connection failed: ' . $curlError];
            }

            $decoded = json_decode($response, true);
            $isSuccess = ($httpCode >= 200 && $httpCode < 300);

            return [
                'success' => $isSuccess,
                'code' => $httpCode,
                'data' => $decoded['data'] ?? $decoded,
                'raw' => $response,
                'error' => !$isSuccess ? ($decoded['message'] ?? "HTTP error {$httpCode}") : null
            ];
        }

        // Fallback using stream context
        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ];
        if ($payload !== null) {
            $opts['http']['content'] = $payload;
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'code' => 0, 'error' => 'Stream connection to Listmonk failed.'];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => true,
            'code' => 200,
            'data' => $decoded['data'] ?? $decoded
        ];
    }

    /**
     * Check Listmonk Service Health
     */
    public function isHealthy(): bool {
        $res = $this->request('/api/lists?per_page=1');
        return !empty($res['success']);
    }

    /**
     * Get All Mailing Lists
     */
    public function getLists(int $page = 1, int $perPage = 50): array {
        return $this->request("/api/lists?page={$page}&per_page={$perPage}");
    }

    /**
     * Create a New List
     */
    public function createList(string $name, string $type = 'private', string $optin = 'single', array $tags = []): array {
        return $this->request('/api/lists', 'POST', [
            'name' => $name,
            'type' => $type,
            'optin' => $optin,
            'tags' => $tags
        ]);
    }

    /**
     * Search Subscriber by Email
     */
    public function findSubscriberByEmail(string $email): ?array {
        $escaped = str_replace("'", "''", $email);
        $query = urlencode("subscribers.email = '{$escaped}'");
        $res = $this->request("/api/subscribers?query={$query}&per_page=1");
        if (!empty($res['success']) && !empty($res['data']['results'][0])) {
            return $res['data']['results'][0];
        }
        return null;
    }

    /**
     * Upsert Subscriber (Smart Create or Update)
     * Handles existing emails gracefully and syncs loan attributes & lists
     */
    public function syncSubscriber(string $email, string $name, array $listIds, array $attributes = [], string $status = 'enabled'): array {
        $existing = $this->findSubscriberByEmail($email);

        $payload = [
            'email' => strtolower(trim($email)),
            'name' => trim($name) ?: 'Valued Client',
            'status' => $status,
            'lists' => array_values(array_unique($listIds)),
            'attribs' => $attributes,
            'preconfirm_subscriptions' => true
        ];

        if ($existing) {
            // Update existing subscriber
            $subId = $existing['id'];
            // Merge existing lists with new lists
            $existingLists = array_column($existing['lists'] ?? [], 'id');
            $mergedLists = array_values(array_unique(array_merge($existingLists, $listIds)));
            $payload['lists'] = $mergedLists;
            
            // Merge attribs
            $mergedAttribs = array_merge($existing['attribs'] ?? [], $attributes);
            $payload['attribs'] = $mergedAttribs;

            return $this->request("/api/subscribers/{$subId}", 'PUT', $payload);
        } else {
            // Create fresh subscriber
            return $this->request('/api/subscribers', 'POST', $payload);
        }
    }

    /**
     * Get All Campaigns with Delivery & Open Rates
     */
    public function getCampaigns(int $page = 1, int $perPage = 20, ?string $status = null): array {
        $q = "/api/campaigns?page={$page}&per_page={$perPage}";
        if ($status) {
            $q .= '&status=' . urlencode($status);
        }
        return $this->request($q);
    }

    /**
     * Create a Campaign
     */
    public function createCampaign(string $name, string $subject, array $listIds, string $bodyHtml, ?string $fromEmail = null): array {
        $payload = [
            'name' => $name,
            'subject' => $subject,
            'lists' => array_values($listIds),
            'type' => 'regular',
            'content_type' => 'html',
            'body' => $bodyHtml,
            'send_at' => null
        ];
        if ($fromEmail) {
            $payload['from_email'] = $fromEmail;
        }
        return $this->request('/api/campaigns', 'POST', $payload);
    }

    /**
     * Change Campaign Status: 'running' (send), 'paused', 'cancelled'
     */
    public function changeCampaignStatus(int $campaignId, string $status = 'running'): array {
        return $this->request("/api/campaigns/{$campaignId}/status", 'PUT', [
            'status' => $status
        ]);
    }

    /**
     * Get System Stats Overview
     */
    public function getStats(): array {
        $dashboard = $this->request('/api/dashboard/charts');
        $lists = $this->getLists(1, 100);
        $campaigns = $this->getCampaigns(1, 10);

        $totalSubscribers = 0;
        if (!empty($lists['data']['results'])) {
            foreach ($lists['data']['results'] as $l) {
                $totalSubscribers += (int)($l['subscriber_count'] ?? 0);
            }
        }

        return [
            'healthy' => !empty($lists['success']),
            'total_lists' => count($lists['data']['results'] ?? []),
            'total_campaigns' => $campaigns['data']['total'] ?? 0,
            'total_subscribers' => $totalSubscribers,
            'campaigns' => $campaigns['data']['results'] ?? [],
            'lists' => $lists['data']['results'] ?? []
        ];
    }

    /**
     * Send Transactional Email via Listmonk
     */
    public function sendTransactional(string $subscriberEmail, int $templateId, array $data = []): array {
        return $this->request('/api/tx', 'POST', [
            'subscriber_email' => $subscriberEmail,
            'template_id' => $templateId,
            'data' => $data
        ]);
    }
}
