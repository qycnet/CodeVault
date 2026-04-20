<?php
/**
 * CodeVault - 邮件通知控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Models\User;
use CodeVault\Models\Repository;
use CodeVault\Services\Mailer;
use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class NotificationController
{
    private Mailer $mailer;
    
    public function __construct()
    {
        $this->mailer = new Mailer();
    }
    
    /**
     * 获取用户通知设置
     */
    public function getSettings(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $settings = Connection::queryOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$user['id']]
        );
        
        if (!$settings) {
            // 返回默认设置
            return [
                'success' => true,
                'settings' => [
                    'email_enabled' => true,
                    'notify_issue' => true,
                    'notify_pr' => true,
                    'notify_comment' => true,
                    'notify_mention' => true,
                    'notify_watch' => true,
                    'digest_enabled' => false,
                    'digest_frequency' => 'daily',
                ],
            ];
        }
        
        return [
            'success' => true,
            'settings' => [
                'email_enabled' => (bool) $settings['email_enabled'],
                'notify_issue' => (bool) $settings['notify_issue'],
                'notify_pr' => (bool) $settings['notify_pr'],
                'notify_comment' => (bool) $settings['notify_comment'],
                'notify_mention' => (bool) $settings['notify_mention'],
                'notify_watch' => (bool) $settings['notify_watch'],
                'digest_enabled' => (bool) $settings['digest_enabled'],
                'digest_frequency' => $settings['digest_frequency'],
            ],
        ];
    }
    
    /**
     * 更新通知设置
     */
    public function updateSettings(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $existing = Connection::queryOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$user['id']]
        );
        
        if ($existing) {
            Connection::execute(
                "UPDATE notification_settings SET
                    email_enabled = ?,
                    notify_issue = ?,
                    notify_pr = ?,
                    notify_comment = ?,
                    notify_mention = ?,
                    notify_watch = ?,
                    digest_enabled = ?,
                    digest_frequency = ?
                WHERE user_id = ?",
                [
                    (int) ($data['email_enabled'] ?? 1),
                    (int) ($data['notify_issue'] ?? 1),
                    (int) ($data['notify_pr'] ?? 1),
                    (int) ($data['notify_comment'] ?? 1),
                    (int) ($data['notify_mention'] ?? 1),
                    (int) ($data['notify_watch'] ?? 1),
                    (int) ($data['digest_enabled'] ?? 0),
                    $data['digest_frequency'] ?? 'daily',
                    $user['id'],
                ]
            );
        } else {
            Connection::insert(
                "INSERT INTO notification_settings (
                    user_id, email_enabled, notify_issue, notify_pr,
                    notify_comment, notify_mention, notify_watch,
                    digest_enabled, digest_frequency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $user['id'],
                    (int) ($data['email_enabled'] ?? 1),
                    (int) ($data['notify_issue'] ?? 1),
                    (int) ($data['notify_pr'] ?? 1),
                    (int) ($data['notify_comment'] ?? 1),
                    (int) ($data['notify_mention'] ?? 1),
                    (int) ($data['notify_watch'] ?? 1),
                    (int) ($data['digest_enabled'] ?? 0),
                    $data['digest_frequency'] ?? 'daily',
                ]
            );
        }
        
        return ['success' => true, 'message' => '通知设置已更新'];
    }
    
    /**
     * 发送测试邮件
     */
    public function sendTest(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        if (empty($user['email'])) {
            return ['success' => false, 'message' => '请先设置邮箱地址'];
        }
        
        $sent = $this->mailer->sendNotification(
            $user['email'],
            'CodeVault 测试邮件',
            '这是一封测试邮件，如果您收到此邮件，说明邮件配置正确。'
        );
        
        return $sent
            ? ['success' => true, 'message' => '测试邮件已发送']
            : ['success' => false, 'message' => '邮件发送失败'];
    }
    
    /**
     * 发送 Issue 通知
     */
    public function sendIssueNotify(int $issueId, string $type, int $triggerUserId): void
    {
        // 获取 Issue 信息
        $issue = Connection::queryOne(
            "SELECT i.*, r.name as repo_name, r.id as repo_id, u.email, u.username
             FROM issues i
             JOIN repositories r ON i.repo_id = r.id
             JOIN users u ON i.user_id = u.id
             WHERE i.id = ?",
            [$issueId]
        );
        
        if (!$issue || empty($issue['email'])) {
            return;
        }
        
        // 检查用户通知设置
        if (!$this->shouldNotify($issue['user_id'], 'issue')) {
            return;
        }
        
        // 不通知触发者自己
        if ($issue['user_id'] === $triggerUserId) {
            return;
        }
        
        $this->mailer->sendIssueNotification(
            $issue['email'],
            $type,
            [
                'id' => $issue['id'],
                'title' => $issue['title'],
                'status' => $issue['status'],
            ],
            [
                'id' => $issue['repo_id'],
                'name' => $issue['repo_name'],
            ]
        );
    }
    
    /**
     * 发送 PR 通知
     */
    public function sendPRNotify(int $prId, string $type, int $triggerUserId): void
    {
        // 获取 PR 信息
        $pr = Connection::queryOne(
            "SELECT pr.*, r.name as repo_name, r.id as repo_id, u.email, u.username
             FROM pull_requests pr
             JOIN repositories r ON pr.repo_id = r.id
             JOIN users u ON pr.user_id = u.id
             WHERE pr.id = ?",
            [$prId]
        );
        
        if (!$pr || empty($pr['email'])) {
            return;
        }
        
        // 检查用户通知设置
        if (!$this->shouldNotify($pr['user_id'], 'pr')) {
            return;
        }
        
        // 不通知触发者自己
        if ($pr['user_id'] === $triggerUserId) {
            return;
        }
        
        $this->mailer->sendPRNotification(
            $pr['email'],
            $type,
            [
                'id' => $pr['id'],
                'title' => $pr['title'],
                'status' => $pr['status'],
            ],
            [
                'id' => $pr['repo_id'],
                'name' => $pr['repo_name'],
            ]
        );
    }
    
    /**
     * 发送评论通知
     */
    public function sendCommentNotify(int $commentId, int $triggerUserId): void
    {
        // 获取评论信息
        $comment = Connection::queryOne(
            "SELECT c.*, u.email as commenter_email
             FROM comments c
             JOIN users u ON c.user_id = u.id
             WHERE c.id = ?",
            [$commentId]
        );
        
        if (!$comment) {
            return;
        }
        
        // 获取被评论对象的所有者
        $targetUser = null;
        
        if ($comment['issue_id']) {
            $targetUser = Connection::queryOne(
                "SELECT u.id, u.email FROM issues i JOIN users u ON i.user_id = u.id WHERE i.id = ?",
                [$comment['issue_id']]
            );
        } elseif ($comment['pr_id']) {
            $targetUser = Connection::queryOne(
                "SELECT u.id, u.email FROM pull_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.id = ?",
                [$comment['pr_id']]
            );
        }
        
        if (!$targetUser || empty($targetUser['email'])) {
            return;
        }
        
        // 检查通知设置
        if (!$this->shouldNotify($targetUser['id'], 'comment')) {
            return;
        }
        
        // 不通知自己
        if ($targetUser['id'] === $triggerUserId) {
            return;
        }
        
        $this->mailer->sendNotification(
            $targetUser['email'],
            '您收到了新的评论',
            $comment['content'],
            ['comment_id' => $commentId]
        );
    }
    
    /**
     * 检查是否应该发送通知
     */
    private function shouldNotify(int $userId, string $type): bool
    {
        $settings = Connection::queryOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        if (!$settings) {
            return true; // 默认发送
        }
        
        if (!$settings['email_enabled']) {
            return false;
        }
        
        $field = "notify_{$type}";
        return isset($settings[$field]) ? (bool) $settings[$field] : true;
    }
}
