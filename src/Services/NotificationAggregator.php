<?php
/**
 * CodeVault - 通知聚合服务
 * 统一通知管理和实时推送
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class NotificationAggregator
{
    private array $notificationTypes = [
        'issue_created' => 'Issue 创建',
        'issue_closed' => 'Issue 关闭',
        'issue_assigned' => 'Issue 分配',
        'issue_mentioned' => 'Issue 提及',
        'pr_created' => 'PR 创建',
        'pr_merged' => 'PR 合并',
        'pr_closed' => 'PR 关闭',
        'pr_reviewed' => 'PR 审查',
        'pr_commented' => 'PR 评论',
        'push' => '代码推送',
        'release' => 'Release 发布',
        'workflow_success' => '工作流成功',
        'workflow_failed' => '工作流失败',
        'security_alert' => '安全告警',
    ];
    
    /**
     * 创建通知
     */
    public function create(array $data): int
    {
        $notificationId = Connection::insert(
            "INSERT INTO notifications (user_id, type, title, content, repo_id, entity_type, entity_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['type'],
                $data['title'],
                json_encode($data['content'] ?? []),
                $data['repo_id'] ?? null,
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
            ]
        );
        
        // 推送实时通知
        $this->pushNotification($data['user_id'], [
            'id' => $notificationId,
            'type' => $data['type'],
            'title' => $data['title'],
            'content' => $data['content'],
            'repo_id' => $data['repo_id'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        return $notificationId;
    }
    
    /**
     * 批量创建通知
     */
    public function createBatch(array $notifications): array
    {
        $ids = [];
        
        foreach ($notifications as $notification) {
            $ids[] = $this->create($notification);
        }
        
        return $ids;
    }
    
    /**
     * 获取用户通知列表
     */
    public function getUserNotifications(int $userId, array $filters = []): array
    {
        $sql = "SELECT n.*, r.name as repo_name, r.owner as repo_owner
                FROM notifications n
                LEFT JOIN repositories r ON n.repo_id = r.id
                WHERE n.user_id = ?";
        
        $params = [$userId];
        
        // 未读过滤
        if (isset($filters['unread']) && $filters['unread']) {
            $sql .= " AND n.read_at IS NULL";
        }
        
        // 类型过滤
        if (!empty($filters['type'])) {
            $sql .= " AND n.type = ?";
            $params[] = $filters['type'];
        }
        
        // 仓库过滤
        if (!empty($filters['repo_id'])) {
            $sql .= " AND n.repo_id = ?";
            $params[] = $filters['repo_id'];
        }
        
        // 时间范围
        if (!empty($filters['since'])) {
            $sql .= " AND n.created_at >= ?";
            $params[] = $filters['since'];
        }
        
        $sql .= " ORDER BY n.created_at DESC";
        
        // 分页
        $limit = (int) ($filters['limit'] ?? 50);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $notifications = Connection::query($sql, $params);
        
        foreach ($notifications as &$notification) {
            $notification['content'] = json_decode($notification['content'], true);
            $notification['type_label'] = $this->notificationTypes[$notification['type']] ?? $notification['type'];
        }
        
        return $notifications;
    }
    
    /**
     * 获取未读数量
     */
    public function getUnreadCount(int $userId): int
    {
        $result = Connection::queryOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );
        
        return (int) $result['count'];
    }
    
    /**
     * 获取按类型分组的未读数量
     */
    public function getUnreadCountByType(int $userId): array
    {
        $results = Connection::query(
            "SELECT type, COUNT(*) as count 
             FROM notifications 
             WHERE user_id = ? AND read_at IS NULL 
             GROUP BY type",
            [$userId]
        );
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }
        
        return $counts;
    }
    
    /**
     * 标记为已读
     */
    public function markAsRead(int $userId, int $notificationId): bool
    {
        $affected = Connection::execute(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
        
        return $affected > 0;
    }
    
    /**
     * 批量标记为已读
     */
    public function markAllAsRead(int $userId, ?string $type = null): int
    {
        $sql = "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL";
        $params = [$userId];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        return Connection::execute($sql, $params);
    }
    
    /**
     * 删除通知
     */
    public function delete(int $userId, int $notificationId): bool
    {
        $affected = Connection::execute(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
        
        return $affected > 0;
    }
    
    /**
     * 清理旧通知
     */
    public function cleanup(int $daysOld = 30): int
    {
        return Connection::execute(
            "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOld]
        );
    }
    
    /**
     * 获取通知摘要
     */
    public function getSummary(int $userId): array
    {
        $summary = [
            'total_unread' => 0,
            'by_type' => [],
            'recent' => [],
        ];
        
        // 总未读数
        $summary['total_unread'] = $this->getUnreadCount($userId);
        
        // 按类型分组
        $summary['by_type'] = $this->getUnreadCountByType($userId);
        
        // 最近通知
        $summary['recent'] = $this->getUserNotifications($userId, [
            'limit' => 10,
            'unread' => true,
        ]);
        
        return $summary;
    }
    
    /**
     * 推送实时通知（通过 WebSocket）
     */
    private function pushNotification(int $userId, array $notification): void
    {
        // 调用 WebSocket 服务推送
        $wsUrl = getenv('WEBSOCKET_URL') ?: 'http://localhost:8081';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $wsUrl . '/broadcast',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'user_id' => $userId,
                'notification' => $notification,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * 从 Issue 创建通知
     */
    public function notifyIssueCreated(int $issueId, int $repoId, int $authorId): void
    {
        $issue = Connection::queryOne(
            "SELECT i.*, r.name as repo_name, r.owner as repo_owner, u.username as author_name
             FROM issues i
             JOIN repositories r ON i.repo_id = r.id
             JOIN users u ON i.user_id = u.id
             WHERE i.id = ?",
            [$issueId]
        );
        
        if (!$issue) return;
        
        // 通知仓库所有者
        $repo = Connection::queryOne("SELECT user_id FROM repositories WHERE id = ?", [$repoId]);
        
        if ($repo && $repo['user_id'] !== $authorId) {
            $this->create([
                'user_id' => $repo['user_id'],
                'type' => 'issue_created',
                'title' => "新 Issue: {$issue['title']}",
                'content' => [
                    'repo' => "{$issue['repo_owner']}/{$issue['repo_name']}",
                    'issue_number' => $issue['number'],
                    'author' => $issue['author_name'],
                ],
                'repo_id' => $repoId,
                'entity_type' => 'issue',
                'entity_id' => $issueId,
            ]);
        }
        
        // 通知被提及的用户
        $this->notifyMentioned($issue['body'], 'issue', $issueId, $repoId, $authorId);
    }
    
    /**
     * 从 PR 创建通知
     */
    public function notifyPRCreated(int $prId, int $repoId, int $authorId): void
    {
        $pr = Connection::queryOne(
            "SELECT pr.*, r.name as repo_name, r.owner as repo_owner, u.username as author_name
             FROM pull_requests pr
             JOIN repositories r ON pr.repo_id = r.id
             JOIN users u ON pr.user_id = u.id
             WHERE pr.id = ?",
            [$prId]
        );
        
        if (!$pr) return;
        
        // 通知仓库所有者
        $repo = Connection::queryOne("SELECT user_id FROM repositories WHERE id = ?", [$repoId]);
        
        if ($repo && $repo['user_id'] !== $authorId) {
            $this->create([
                'user_id' => $repo['user_id'],
                'type' => 'pr_created',
                'title' => "新 PR: {$pr['title']}",
                'content' => [
                    'repo' => "{$pr['repo_owner']}/{$pr['repo_name']}",
                    'pr_number' => $pr['number'],
                    'author' => $pr['author_name'],
                    'branch' => $pr['head_branch'],
                ],
                'repo_id' => $repoId,
                'entity_type' => 'pull_request',
                'entity_id' => $prId,
            ]);
        }
        
        // 通知被提及的用户
        $this->notifyMentioned($pr['body'], 'pull_request', $prId, $repoId, $authorId);
    }
    
    /**
     * 通知被提及的用户
     */
    private function notifyMentioned(string $text, string $entityType, int $entityId, int $repoId, int $authorId): void
    {
        // 提取 @username
        preg_match_all('/@(\w+)/', $text, $matches);
        
        $usernames = array_unique($matches[1] ?? []);
        
        foreach ($usernames as $username) {
            $user = Connection::queryOne(
                "SELECT id FROM users WHERE username = ? AND deleted_at IS NULL",
                [$username]
            );
            
            if ($user && $user['id'] !== $authorId) {
                $this->create([
                    'user_id' => $user['id'],
                    'type' => 'issue_mentioned',
                    'title' => "你在 {$entityType} 中被提及",
                    'content' => [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ],
                    'repo_id' => $repoId,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ]);
            }
        }
    }
    
    /**
     * 工作流状态通知
     */
    public function notifyWorkflowStatus(int $runId, string $status): void
    {
        $run = Connection::queryOne(
            "SELECT wr.*, w.name as workflow_name, r.name as repo_name, r.owner as repo_owner, r.user_id as repo_owner_id
             FROM workflow_runs wr
             JOIN workflows w ON wr.workflow_id = w.id
             JOIN repositories r ON w.repo_id = r.id
             WHERE wr.id = ?",
            [$runId]
        );
        
        if (!$run) return;
        
        $type = $status === 'success' ? 'workflow_success' : 'workflow_failed';
        $emoji = $status === 'success' ? '✅' : '❌';
        
        $this->create([
            'user_id' => $run['triggered_by'] ?: $run['repo_owner_id'],
            'type' => $type,
            'title' => "{$emoji} 工作流 {$status}: {$run['workflow_name']}",
            'content' => [
                'repo' => "{$run['repo_owner']}/{$run['repo_name']}",
                'workflow' => $run['workflow_name'],
                'run_id' => $runId,
                'status' => $status,
            ],
            'repo_id' => $run['repo_id'],
            'entity_type' => 'workflow_run',
            'entity_id' => $runId,
        ]);
    }
}
