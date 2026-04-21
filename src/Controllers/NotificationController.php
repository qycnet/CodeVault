<?php
/**
 * CodeVault - 通知控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class NotificationController
{
    /**
     * 获取通知列表
     */
    public function list(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $page = (int) ($data['page'] ?? 1);
        $perPage = min((int) ($data['per_page'] ?? 30), 100);
        $offset = ($page - 1) * $perPage;
        $unreadOnly = ($data['unread_only'] ?? 'false') === 'true';
        
        $sql = "SELECT n.*, r.name as repo_name, u.username as actor_name
                FROM notifications n
                LEFT JOIN repositories r ON n.repo_id = r.id
                LEFT JOIN users u ON n.actor_id = u.id
                WHERE n.user_id = ?";
        
        $params = [$user['id']];
        
        if ($unreadOnly) {
            $sql .= " AND n.is_read = 0";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $notifications = Connection::query($sql, $params);
        
        // 获取未读数量
        $unreadCount = Connection::queryOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        )['count'];
        
        return [
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => (int) $unreadCount,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    
    /**
     * 标记为已读
     */
    public function markRead(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        if ($id > 0) {
            Connection::execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
                [$id, $user['id']]
            );
        } else {
            // 标记所有为已读
            Connection::execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
                [$user['id']]
            );
        }
        
        return ['success' => true, 'message' => '已标记为已读'];
    }
    
    /**
     * 删除通知
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $id = (int) ($data['id'] ?? 0);
        
        Connection::execute(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );
        
        return ['success' => true, 'message' => '通知已删除'];
    }
    
    /**
     * 获取未读数量
     */
    public function unreadCount(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $count = Connection::queryOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        );
        
        return [
            'success' => true,
            'count' => (int) $count['count'],
        ];
    }
    
    /**
     * 标记所有为已读
     */
    public function markAllRead(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $affected = Connection::execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        );
        
        return [
            'success' => true,
            'message' => "已标记 {$affected} 条通知为已读",
            'affected' => $affected,
        ];
    }
    
    /**
     * 创建通知（内部方法）
     */
    public static function create(array $data): int
    {
        return Connection::insert(
            "INSERT INTO notifications (
                user_id, type, title, body, repo_id, 
                issue_id, pr_id, actor_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['body'] ?? '',
                $data['repo_id'] ?? null,
                $data['issue_id'] ?? null,
                $data['pr_id'] ?? null,
                $data['actor_id'] ?? null,
            ]
        );
    }
    
    /**
     * Issue 相关通知
     */
    public static function notifyIssue(int $issueId, string $type, int $actorId): void
    {
        $issue = Connection::queryOne(
            "SELECT i.*, r.name as repo_name, r.id as repo_id, u.id as owner_id
             FROM issues i
             JOIN repositories r ON i.repo_id = r.id
             JOIN users u ON i.user_id = u.id
             WHERE i.id = ?",
            [$issueId]
        );
        
        if (!$issue) return;
        
        $titles = [
            'created' => "新 Issue: {$issue['title']}",
            'updated' => "Issue 更新: {$issue['title']}",
            'closed' => "Issue 已关闭: {$issue['title']}",
            'reopened' => "Issue 已重新打开: {$issue['title']}",
            'commented' => "Issue 新评论: {$issue['title']}",
        ];
        
        // 通知 Issue 作者
        if ($issue['owner_id'] !== $actorId) {
            self::create([
                'user_id' => $issue['owner_id'],
                'type' => 'issue',
                'title' => $titles[$type] ?? "Issue 通知",
                'repo_id' => $issue['repo_id'],
                'issue_id' => $issueId,
                'actor_id' => $actorId,
            ]);
        }
    }
    
    /**
     * PR 相关通知
     */
    public static function notifyPR(int $prId, string $type, int $actorId): void
    {
        $pr = Connection::queryOne(
            "SELECT pr.*, r.name as repo_name, r.id as repo_id, u.id as owner_id
             FROM pull_requests pr
             JOIN repositories r ON pr.repo_id = r.id
             JOIN users u ON pr.user_id = u.id
             WHERE pr.id = ?",
            [$prId]
        );
        
        if (!$pr) return;
        
        $titles = [
            'created' => "新 Pull Request: {$pr['title']}",
            'updated' => "Pull Request 更新: {$pr['title']}",
            'merged' => "Pull Request 已合并: {$pr['title']}",
            'closed' => "Pull Request 已关闭: {$pr['title']}",
            'reopened' => "Pull Request 已重新打开: {$pr['title']}",
            'commented' => "Pull Request 新评论: {$pr['title']}",
            'reviewed' => "Pull Request 审查: {$pr['title']}",
        ];
        
        // 通知 PR 作者
        if ($pr['owner_id'] !== $actorId) {
            self::create([
                'user_id' => $pr['owner_id'],
                'type' => 'pull_request',
                'title' => $titles[$type] ?? "Pull Request 通知",
                'repo_id' => $pr['repo_id'],
                'pr_id' => $prId,
                'actor_id' => $actorId,
            ]);
        }
        
        // 如果是合并，通知仓库所有者
        if ($type === 'merged') {
            $repoOwner = Connection::queryOne(
                "SELECT user_id FROM repositories WHERE id = ?",
                [$pr['repo_id']]
            );
            
            if ($repoOwner && $repoOwner['user_id'] !== $actorId && $repoOwner['user_id'] !== $pr['owner_id']) {
                self::create([
                    'user_id' => $repoOwner['user_id'],
                    'type' => 'pull_request',
                    'title' => "PR 已合并: {$pr['title']}",
                    'repo_id' => $pr['repo_id'],
                    'pr_id' => $prId,
                    'actor_id' => $actorId,
                ]);
            }
        }
    }
    
    /**
     * @ 提及通知
     */
    public static function notifyMention(string $username, array $context): void
    {
        $user = Connection::queryOne(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );
        
        if (!$user) return;
        
        self::create([
            'user_id' => $user['id'],
            'type' => 'mention',
            'title' => $context['title'] ?? "您被提及了",
            'body' => $context['body'] ?? '',
            'repo_id' => $context['repo_id'] ?? null,
            'issue_id' => $context['issue_id'] ?? null,
            'pr_id' => $context['pr_id'] ?? null,
            'actor_id' => $context['actor_id'] ?? null,
        ]);
    }
    
    /**
     * 解析 @ 提及
     */
    public static function parseMentions(string $text, array $context): void
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        
        $mentioned = array_unique($matches[1] ?? []);
        
        foreach ($mentioned as $username) {
            self::notifyMention($username, $context);
        }
    }
}
