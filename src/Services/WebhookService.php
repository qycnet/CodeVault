<?php
/**
 * CodeVault Webhooks 增强服务
 * 
 * 功能：
 * - 事件筛选
 * - 重试机制
 * - 投递日志
 * - 签名验证
 * - 批量投递
 * - 失败队列
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class WebhookService
{
    private $db;
    private $logger;
    private $security;
    
    // 最大重试次数
    private const MAX_RETRIES = 5;
    
    // 重试延迟（秒）
    private const RETRY_DELAYS = [10, 30, 60, 300, 900];
    
    // 支持的事件类型
    private const EVENT_TYPES = [
        'push',
        'pull_request',
        'pull_request_review',
        'issues',
        'issue_comment',
        'release',
        'fork',
        'star',
        'watch',
        'deployment',
        'deployment_status',
        'repository',
        'member',
        'team',
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('webhook');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建 Webhook
     */
    public function create(int $repoId, int $userId, array $data): array
    {
        // 验证 URL
        if (empty($data['url'])) {
            return ['success' => false, 'error' => 'URL 不能为空'];
        }
        
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => '无效的 URL'];
        }
        
        // 验证事件
        $events = $data['events'] ?? ['push'];
        $events = array_intersect($events, self::EVENT_TYPES);
        
        if (empty($events)) {
            return ['success' => false, 'error' => '至少需要一个有效事件'];
        }
        
        // 生成密钥
        $secret = $data['secret'] ?? bin2hex(random_bytes(32));
        
        $sql = "INSERT INTO webhooks (repo_id, url, secret, events, content_type, active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $repoId,
            $data['url'],
            $secret,
            json_encode($events),
            $data['content_type'] ?? 'json',
            $data['active'] ?? true,
            $userId,
        ]);
        
        $webhookId = (int)$this->db->lastInsertId();
        
        $this->logger->info('Webhook 创建成功', ['webhook_id' => $webhookId, 'repo_id' => $repoId]);
        
        return [
            'success' => true,
            'webhook' => $this->getWebhook($webhookId),
        ];
    }
    
    /**
     * 获取 Webhook
     */
    public function getWebhook(int $webhookId): ?array
    {
        $webhook = $this->db->fetchOne(
            "SELECT * FROM webhooks WHERE id = ?",
            [$webhookId]
        );
        
        if ($webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
        }
        
        return $webhook;
    }
    
    /**
     * 列出 Webhooks
     */
    public function listWebhooks(int $repoId): array
    {
        $webhooks = $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE repo_id = ? ORDER BY created_at DESC",
            [$repoId]
        );
        
        foreach ($webhooks as &$webhook) {
            $webhook['events'] = json_decode($webhook['events'], true);
        }
        
        return $webhooks;
    }
    
    /**
     * 更新 Webhook
     */
    public function update(int $webhookId, int $userId, array $data): array
    {
        $webhook = $this->getWebhook($webhookId);
        
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook 不存在'];
        }
        
        $updates = [];
        $bindings = [];
        
        if (isset($data['url'])) {
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return ['success' => false, 'error' => '无效的 URL'];
            }
            $updates[] = 'url = ?';
            $bindings[] = $data['url'];
        }
        
        if (isset($data['secret'])) {
            $updates[] = 'secret = ?';
            $bindings[] = $data['secret'];
        }
        
        if (isset($data['events'])) {
            $events = array_intersect($data['events'], self::EVENT_TYPES);
            if (empty($events)) {
                return ['success' => false, 'error' => '至少需要一个有效事件'];
            }
            $updates[] = 'events = ?';
            $bindings[] = json_encode($events);
        }
        
        if (isset($data['active'])) {
            $updates[] = 'active = ?';
            $bindings[] = $data['active'] ? 1 : 0;
        }
        
        if (isset($data['content_type'])) {
            $updates[] = 'content_type = ?';
            $bindings[] = $data['content_type'];
        }
        
        if (empty($updates)) {
            return ['success' => true, 'webhook' => $webhook];
        }
        
        $bindings[] = $webhookId;
        
        $sql = "UPDATE webhooks SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->execute($sql, $bindings);
        
        return [
            'success' => true,
            'webhook' => $this->getWebhook($webhookId),
        ];
    }
    
    /**
     * 删除 Webhook
     */
    public function delete(int $webhookId): array
    {
        $webhook = $this->getWebhook($webhookId);
        
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook 不存在'];
        }
        
        // 删除投递日志
        $this->db->execute("DELETE FROM webhook_deliveries WHERE webhook_id = ?", [$webhookId]);
        
        // 删除 Webhook
        $this->db->execute("DELETE FROM webhooks WHERE id = ?", [$webhookId]);
        
        return ['success' => true];
    }
    
    /**
     * 触发 Webhook
     */
    public function trigger(int $repoId, string $event, array $payload): void
    {
        // 获取所有匹配的 Webhooks
        $webhooks = $this->db->fetchAll(
            "SELECT * FROM webhooks WHERE repo_id = ? AND active = 1",
            [$repoId]
        );
        
        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true);
            
            // 检查事件是否匹配
            if (!in_array($event, $events) && !in_array('*', $events)) {
                continue;
            }
            
            // 异步投递
            $this->deliver($webhook, $event, $payload);
        }
    }
    
    /**
     * 投递 Webhook
     */
    private function deliver(array $webhook, string $event, array $payload): void
    {
        $deliveryId = $this->createDelivery($webhook['id'], $event, $payload);
        
        try {
            $startTime = microtime(true);
            
            // 生成签名
            $signature = $this->generateSignature($webhook['secret'], $payload);
            
            // 构建请求
            $headers = [
                'Content-Type: application/' . $webhook['content_type'],
                'X-CodeVault-Event: ' . $event,
                'X-CodeVault-Delivery: ' . $deliveryId,
                'X-CodeVault-Signature: sha256=' . $signature,
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhook['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            // 更新投递状态
            $this->updateDelivery($deliveryId, [
                'status' => $httpCode >= 200 && $httpCode < 300 ? 'success' : 'failed',
                'http_code' => $httpCode,
                'response' => $response,
                'duration' => $duration,
                'error' => $error,
            ]);
            
            // 失败则加入重试队列
            if ($httpCode < 200 || $httpCode >= 300) {
                $this->scheduleRetry($deliveryId, $webhook, $event, $payload);
            }
            
        } catch (\Exception $e) {
            $this->updateDelivery($deliveryId, [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            
            $this->scheduleRetry($deliveryId, $webhook, $event, $payload);
        }
    }
    
    /**
     * 创建投递记录
     */
    private function createDelivery(int $webhookId, string $event, array $payload): string
    {
        $deliveryId = bin2hex(random_bytes(16));
        
        $sql = "INSERT INTO webhook_deliveries (id, webhook_id, event, payload, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $this->db->execute($sql, [
            $deliveryId,
            $webhookId,
            $event,
            json_encode($payload),
        ]);
        
        return $deliveryId;
    }
    
    /**
     * 更新投递状态
     */
    private function updateDelivery(string $deliveryId, array $data): void
    {
        $sql = "UPDATE webhook_deliveries SET 
                status = ?, 
                http_code = ?, 
                response = ?, 
                duration = ?, 
                error = ?, 
                delivered_at = NOW() 
                WHERE id = ?";
        
        $this->db->execute($sql, [
            $data['status'],
            $data['http_code'] ?? null,
            $data['response'] ?? null,
            $data['duration'] ?? null,
            $data['error'] ?? null,
            $deliveryId,
        ]);
    }
    
    /**
     * 安排重试
     */
    private function scheduleRetry(string $deliveryId, array $webhook, string $event, array $payload): void
    {
        // 获取当前重试次数
        $delivery = $this->db->fetchOne(
            "SELECT retry_count FROM webhook_deliveries WHERE id = ?",
            [$deliveryId]
        );
        
        $retryCount = (int)($delivery['retry_count'] ?? 0);
        
        if ($retryCount >= self::MAX_RETRIES) {
            $this->logger->warning('Webhook 重试次数已达上限', ['delivery_id' => $deliveryId]);
            return;
        }
        
        // 计算延迟
        $delay = self::RETRY_DELAYS[$retryCount] ?? 900;
        
        // 更新重试信息
        $this->db->execute(
            "UPDATE webhook_deliveries SET retry_count = ?, next_retry_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?",
            [$retryCount + 1, $delay, $deliveryId]
        );
        
        $this->logger->info('Webhook 已安排重试', [
            'delivery_id' => $deliveryId,
            'retry_count' => $retryCount + 1,
            'delay' => $delay,
        ]);
    }
    
    /**
     * 处理重试队列
     */
    public function processRetries(): int
    {
        $deliveries = $this->db->fetchAll(
            "SELECT wd.*, w.url, w.secret, w.content_type 
             FROM webhook_deliveries wd
             JOIN webhooks w ON wd.webhook_id = w.id
             WHERE wd.status = 'failed' 
             AND wd.retry_count < ? 
             AND wd.next_retry_at <= NOW()
             ORDER BY wd.created_at ASC
             LIMIT 100",
            [self::MAX_RETRIES]
        );
        
        $processed = 0;
        
        foreach ($deliveries as $delivery) {
            $webhook = [
                'id' => $delivery['webhook_id'],
                'url' => $delivery['url'],
                'secret' => $delivery['secret'],
                'content_type' => $delivery['content_type'],
            ];
            
            $payload = json_decode($delivery['payload'], true);
            
            $this->deliver($webhook, $delivery['event'], $payload);
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * 生成签名
     */
    private function generateSignature(string $secret, array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
    
    /**
     * 验证签名
     */
    public function verifySignature(string $secret, array $payload, string $signature): bool
    {
        $expected = $this->generateSignature($secret, $payload);
        
        return hash_equals($expected, $signature);
    }
    
    /**
     * 获取投递日志
     */
    public function getDeliveries(int $webhookId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT id, event, status, http_code, duration, created_at, delivered_at, retry_count
             FROM webhook_deliveries
             WHERE webhook_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$webhookId, $limit]
        );
    }
    
    /**
     * 获取投递详情
     */
    public function getDelivery(string $deliveryId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM webhook_deliveries WHERE id = ?",
            [$deliveryId]
        );
    }
    
    /**
     * 重试投递
     */
    public function retryDelivery(string $deliveryId): array
    {
        $delivery = $this->getDelivery($deliveryId);
        
        if (!$delivery) {
            return ['success' => false, 'error' => '投递记录不存在'];
        }
        
        $webhook = $this->getWebhook($delivery['webhook_id']);
        
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook 不存在'];
        }
        
        $payload = json_decode($delivery['payload'], true);
        
        // 重置状态
        $this->db->execute(
            "UPDATE webhook_deliveries SET status = 'pending', retry_count = 0 WHERE id = ?",
            [$deliveryId]
        );
        
        // 重新投递
        $this->deliver($webhook, $delivery['event'], $payload);
        
        return ['success' => true];
    }
    
    /**
     * 测试 Webhook
     */
    public function test(int $webhookId): array
    {
        $webhook = $this->getWebhook($webhookId);
        
        if (!$webhook) {
            return ['success' => false, 'error' => 'Webhook 不存在'];
        }
        
        $testPayload = [
            'test' => true,
            'timestamp' => time(),
            'webhook_id' => $webhookId,
        ];
        
        $startTime = microtime(true);
        
        try {
            $signature = $this->generateSignature($webhook['secret'], $testPayload);
            
            $headers = [
                'Content-Type: application/' . $webhook['content_type'],
                'X-CodeVault-Event: ping',
                'X-CodeVault-Delivery: test-' . bin2hex(random_bytes(8)),
                'X-CodeVault-Signature: sha256=' . $signature,
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhook['url'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($testPayload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $duration = round((microtime(true) - $startTime) * 1000);
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'response' => $response,
                'duration' => $duration,
                'error' => $error ?: null,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 获取事件类型列表
     */
    public function getEventTypes(): array
    {
        return self::EVENT_TYPES;
    }
}
