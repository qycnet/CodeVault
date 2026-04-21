<?php
/**
 * CodeVault - 队列服务
 * 基于 Redis Stream 的轻量级消息队列
 */

namespace CodeVault\Services;

class QueueService
{
    private $redis;
    private $prefix = 'codevault:queue:';
    private $enabled = false;
    
    // 队列名称
    const QUEUE_DEFAULT = 'default';
    const QUEUE_GIT = 'git';
    const QUEUE_EMAIL = 'email';
    const QUEUE_NOTIFICATION = 'notification';
    const QUEUE_WEBHOOK = 'webhook';
    
    public function __construct()
    {
        $this->connect();
    }
    
    /**
     * 连接 Redis
     */
    private function connect(): void
    {
        try {
            $this->redis = new \Redis();
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            
            if ($this->redis->connect($host, $port, 2)) {
                $this->enabled = true;
            }
        } catch (\Exception $e) {
            $this->enabled = false;
        }
    }
    
    /**
     * 推送任务到队列
     */
    public function push(string $queue, string $job, array $data = [], int $delay = 0): bool
    {
        if (!$this->enabled) {
            return $this->fallback($queue, $job, $data);
        }
        
        try {
            $payload = json_encode([
                'job' => $job,
                'data' => $data,
                'attempts' => 0,
                'created_at' => time(),
                'available_at' => time() + $delay,
            ]);
            
            $streamKey = $this->prefix . $queue;
            
            if ($delay > 0) {
                // 延迟任务
                $delayedKey = $this->prefix . 'delayed:' . $queue;
                return $this->redis->zAdd($delayedKey, time() + $delay, $payload) !== false;
            }
            
            // 即时任务
            return $this->redis->xAdd($streamKey, '*', ['payload' => $payload]) !== false;
        } catch (\Exception $e) {
            return $this->fallback($queue, $job, $data);
        }
    }
    
    /**
     * 从队列获取任务
     */
    public function pop(string $queue, int $timeout = 5): ?array
    {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $streamKey = $this->prefix . $queue;
            
            // 检查延迟任务
            $this->processDelayedJobs($queue);
            
            // 从 Stream 读取
            $result = $this->redis->xRead([$streamKey => '0'], 1, $timeout * 1000);
            
            if (empty($result)) {
                return null;
            }
            
            foreach ($result as $messages) {
                foreach ($messages as $id => $message) {
                    // 删除已读取的消息
                    $this->redis->xDel($streamKey, [$id]);
                    
                    $payload = json_decode($message['payload'], true);
                    $payload['id'] = $id;
                    
                    return $payload;
                }
            }
        } catch (\Exception $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * 处理延迟任务
     */
    private function processDelayedJobs(string $queue): void
    {
        try {
            $delayedKey = $this->prefix . 'delayed:' . $queue;
            $streamKey = $this->prefix . $queue;
            
            $now = time();
            $jobs = $this->redis->zRangeByScore($delayedKey, 0, $now);
            
            foreach ($jobs as $job) {
                $this->redis->zRem($delayedKey, $job);
                $this->redis->xAdd($streamKey, '*', ['payload' => $job]);
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
    
    /**
     * 获取队列长度
     */
    public function size(string $queue): int
    {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $streamKey = $this->prefix . $queue;
            $info = $this->redis->xInfo('STREAM', $streamKey);
            
            return $info['length'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 清空队列
     */
    public function clear(string $queue): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            $this->redis->del($this->prefix . $queue);
            $this->redis->del($this->prefix . 'delayed:' . $queue);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 后备方案（直接执行）
     */
    private function fallback(string $queue, string $job, array $data): bool
    {
        // 如果 Redis 不可用，直接执行任务
        $this->executeJob($job, $data);
        return true;
    }
    
    /**
     * 执行任务
     */
    private function executeJob(string $job, array $data): void
    {
        $jobClass = "CodeVault\\Jobs\\{$job}";
        
        if (class_exists($jobClass)) {
            $instance = new $jobClass();
            $instance->handle($data);
        }
    }
    
    /**
     * 推送 Git 操作任务
     */
    public function pushGitOperation(string $operation, int $repoId, array $params = []): bool
    {
        return $this->push(self::QUEUE_GIT, 'GitJob', [
            'operation' => $operation,
            'repo_id' => $repoId,
            'params' => $params,
        ]);
    }
    
    /**
     * 推送邮件任务
     */
    public function pushEmail(string $to, string $subject, string $body, array $options = []): bool
    {
        return $this->push(self::QUEUE_EMAIL, 'EmailJob', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'options' => $options,
        ]);
    }
    
    /**
     * 推送通知任务
     */
    public function pushNotification(int $userId, string $type, string $title, string $content): bool
    {
        return $this->push(self::QUEUE_NOTIFICATION, 'NotificationJob', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
        ]);
    }
    
    /**
     * 推送 Webhook 任务
     */
    public function pushWebhook(int $webhookId, string $event, array $payload): bool
    {
        return $this->push(self::QUEUE_WEBHOOK, 'WebhookJob', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => $payload,
        ]);
    }
    
    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
