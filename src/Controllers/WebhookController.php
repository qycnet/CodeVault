<?php
/**
 * CodeVault - Webhook 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class WebhookController
{
    /**
     * 创建 Webhook
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $url = trim($data['url'] ?? '');
        $events = $data['events'] ?? [];
        $secret = trim($data['secret'] ?? '');
        
        if ($repoId <= 0 || empty($url) || empty($events)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $webhookId = Connection::insert(
            "INSERT INTO webhooks (repo_id, url, events, secret, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
            [$repoId, $url, json_encode($events), $secret]
        );
        
        return ['success' => true, 'webhook_id' => $webhookId];
    }
    
    /**
     * 获取 Webhook 列表
     */
    public function list(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $webhooks = Connection::query(
            "SELECT * FROM webhooks WHERE repo_id = ? ORDER BY created_at DESC",
            [$repoId]
        );
        
        return ['success' => true, 'webhooks' => $webhooks];
    }
    
    /**
     * 更新 Webhook
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        $webhook = Connection::queryOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
        if (!$webhook) {
            return ['success' => false, 'message' => 'Webhook 不存在'];
        }
        
        // 检查权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$webhook['repo_id']]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['url'])) {
            $fields[] = "url = ?";
            $params[] = trim($data['url']);
        }
        
        if (isset($data['events'])) {
            $fields[] = "events = ?";
            $params[] = json_encode($data['events']);
        }
        
        if (isset($data['secret'])) {
            $fields[] = "secret = ?";
            $params[] = trim($data['secret']);
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = (int) $data['is_active'];
        }
        
        if (empty($fields)) {
            return ['success' => true, 'message' => '无更新'];
        }
        
        $params[] = $id;
        Connection::execute("UPDATE webhooks SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        
        return ['success' => true, 'message' => 'Webhook 已更新'];
    }
    
    /**
     * 删除 Webhook
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        $webhook = Connection::queryOne("SELECT * FROM webhooks WHERE id = ?", [$id]);
        if (!$webhook) {
            return ['success' => true, 'message' => '已删除'];
        }
        
        // 检查权限
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$webhook['repo_id']]);
        if (!$repo || $repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        Connection::execute("DELETE FROM webhooks WHERE id = ?", [$id]);
        
        return ['success' => true, 'message' => 'Webhook 已删除'];
    }
    
    /**
     * 触发 Webhook（内部方法）
     */
    public static function trigger(int $repoId, string $event, array $payload): void
    {
        $webhooks = Connection::query(
            "SELECT * FROM webhooks WHERE repo_id = ? AND is_active = 1",
            [$repoId]
        );
        
        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?? [];
            
            if (!in_array($event, $events) && !in_array('*', $events)) {
                continue;
            }
            
            $payload['event'] = $event;
            $payload['timestamp'] = date('c');
            
            $jsonPayload = json_encode($payload);
            
            // 计算签名
            $signature = '';
            if ($webhook['secret']) {
                $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $webhook['secret']);
            }
            
            // 发送请求
            $ch = curl_init($webhook['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-CodeVault-Event: ' . $event,
                    'X-CodeVault-Signature: ' . $signature,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 记录日志
            Connection::insert(
                "INSERT INTO webhook_logs (webhook_id, event, status, response, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$webhook['id'], $event, $httpCode >= 200 && $httpCode < 300 ? 'success' : 'failed', $response]
            );
        }
    }
}
