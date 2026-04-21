<?php

declare(strict_types=1);

namespace App\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * WebSocket 日志服务
 * 
 * 实时推送 CI/CD 构建日志到前端
 */
class LogWebSocketService implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private array $subscriptions = [];
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->clients = new \SplObjectStorage;
        $this->logger = $logger;
    }
    
    /**
     * 新客户端连接
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->logger->info("New WebSocket connection: {$conn->resourceId}");
    }
    
    /**
     * 接收消息
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['action'])) {
            return;
        }
        
        switch ($data['action']) {
            case 'subscribe':
                $this->handleSubscribe($from, $data);
                break;
                
            case 'unsubscribe':
                $this->handleUnsubscribe($from, $data);
                break;
        }
    }
    
    /**
     * 客户端断开连接
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        
        // 清理订阅
        foreach ($this->subscriptions as $runId => $clients) {
            unset($this->subscriptions[$runId][$conn->resourceId]);
        }
        
        $this->logger->info("WebSocket connection closed: {$conn->resourceId}");
    }
    
    /**
     * 错误处理
     */
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error("WebSocket error: {$e->getMessage()}");
        $conn->close();
    }
    
    /**
     * 处理订阅请求
     */
    private function handleSubscribe(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['run_id'])) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Missing run_id'
            ]));
            return;
        }
        
        $runId = (int) $data['run_id'];
        
        // 验证权限
        if (!$this->canAccessRun($conn, $runId)) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => 'Access denied'
            ]));
            return;
        }
        
        // 添加订阅
        if (!isset($this->subscriptions[$runId])) {
            $this->subscriptions[$runId] = [];
        }
        
        $this->subscriptions[$runId][$conn->resourceId] = $conn;
        
        $conn->send(json_encode([
            'type' => 'subscribed',
            'run_id' => $runId
        ]));
        
        $this->logger->info("Client {$conn->resourceId} subscribed to run {$runId}");
    }
    
    /**
     * 处理取消订阅
     */
    private function handleUnsubscribe(ConnectionInterface $conn, array $data): void
    {
        if (!isset($data['run_id'])) {
            return;
        }
        
        $runId = (int) $data['run_id'];
        unset($this->subscriptions[$runId][$conn->resourceId]);
        
        $conn->send(json_encode([
            'type' => 'unsubscribed',
            'run_id' => $runId
        ]));
    }
    
    /**
     * 推送日志到订阅者
     */
    public function pushLog(int $runId, string $log, string $level = 'info'): void
    {
        if (!isset($this->subscriptions[$runId])) {
            return;
        }
        
        $message = json_encode([
            'type' => 'log',
            'run_id' => $runId,
            'level' => $level,
            'message' => $log,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        foreach ($this->subscriptions[$runId] as $client) {
            $client->send($message);
        }
    }
    
    /**
     * 推送运行状态更新
     */
    public function pushStatus(int $runId, string $status): void
    {
        if (!isset($this->subscriptions[$runId])) {
            return;
        }
        
        $message = json_encode([
            'type' => 'status',
            'run_id' => $runId,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        foreach ($this->subscriptions[$runId] as $client) {
            $client->send($message);
        }
        
        // 如果运行结束，清理订阅
        if (in_array($status, ['success', 'failed', 'cancelled'])) {
            unset($this->subscriptions[$runId]);
        }
    }
    
    /**
     * 验证用户是否有权限访问运行记录
     */
    private function canAccessRun(ConnectionInterface $conn, int $runId): bool
    {
        // 从连接中获取用户信息（通过 session 或 token）
        $userId = $conn->user_id ?? null;
        
        if (!$userId) {
            return false;
        }
        
        // TODO: 查询数据库验证权限
        // SELECT r.* FROM workflow_runs r
        // JOIN repositories repo ON r.repo_id = repo.id
        // LEFT JOIN repo_users ru ON repo.id = ru.repo_id
        // WHERE r.id = ? AND (repo.owner_id = ? OR ru.user_id = ?)
        
        return true;
    }
    
    /**
     * 获取当前连接数
     */
    public function getConnectionCount(): int
    {
        return count($this->clients);
    }
    
    /**
     * 获取当前订阅数
     */
    public function getSubscriptionCount(): int
    {
        $count = 0;
        foreach ($this->subscriptions as $clients) {
            $count += count($clients);
        }
        return $count;
    }
}
