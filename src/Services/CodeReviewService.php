<?php
/**
 * CodeVault - 代码审查增强服务
 * 审批流程、变更请求、批量评论
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;
use CodeVault\Services\Session;

class CodeReviewService
{
    /**
     * 提交审查
     */
    public function submitReview(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        $status = $data['status'] ?? 'commented'; // approved, changes_requested, commented
        $body = trim($data['body'] ?? '');
        
        if ($prId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        if (!in_array($status, ['approved', 'changes_requested', 'commented'])) {
            return ['success' => false, 'message' => '无效的审查状态'];
        }
        
        // 检查 PR 是否存在
        $pr = Connection::queryOne("SELECT * FROM pull_requests WHERE id = ?", [$prId]);
        if (!$pr) {
            return ['success' => false, 'message' => 'PR 不存在'];
        }
        
        // 检查是否已审查
        $existing = Connection::queryOne(
            "SELECT * FROM reviews WHERE pr_id = ? AND reviewer_id = ?",
            [$prId, $user['id']]
        );
        
        if ($existing) {
            // 更新审查
            Connection::execute(
                "UPDATE reviews SET status = ?, body = ?, updated_at = NOW() WHERE id = ?",
                [$status, $body, $existing['id']]
            );
            $reviewId = $existing['id'];
        } else {
            // 创建审查
            $reviewId = Connection::insert(
                "INSERT INTO reviews (pr_id, reviewer_id, status, body, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$prId, $user['id'], $status, $body]
            );
        }
        
        // 更新 PR 状态
        $this->updatePRReviewStatus($prId);
        
        // 发送通知
        $this->sendReviewNotification($prId, $user['id'], $status);
        
        return [
            'success' => true,
            'review_id' => $reviewId,
            'message' => '审查已提交',
        ];
    }
    
    /**
     * 批量评论
     */
    public function submitBatchComments(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        $comments = $data['comments'] ?? [];
        
        if ($prId <= 0 || empty($comments)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $successCount = 0;
        $errors = [];
        
        foreach ($comments as $comment) {
            $file = trim($comment['file'] ?? '');
            $line = (int) ($comment['line'] ?? 0);
            $body = trim($comment['body'] ?? '');
            
            if (empty($file) || empty($body)) {
                $errors[] = "评论缺少必要字段";
                continue;
            }
            
            try {
                Connection::insert(
                    "INSERT INTO review_comments (pr_id, file, line, body, author_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$prId, $file, $line, $body, $user['id']]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "评论保存失败: {$file}:{$line}";
            }
        }
        
        return [
            'success' => true,
            'message' => "成功创建 {$successCount} 条评论",
            'success_count' => $successCount,
            'errors' => $errors,
        ];
    }
    
    /**
     * 请求变更
     */
    public function requestChanges(array $data): array
    {
        return $this->submitReview(array_merge($data, ['status' => 'changes_requested']));
    }
    
    /**
     * 批准审查
     */
    public function approve(array $data): array
    {
        return $this->submitReview(array_merge($data, ['status' => 'approved']));
    }
    
    /**
     * 获取审查列表
     */
    public function getReviews(int $prId): array
    {
        return Connection::query(
            "SELECT r.*, u.username as reviewer_name, u.avatar_url 
             FROM reviews r 
             JOIN users u ON r.reviewer_id = u.id 
             WHERE r.pr_id = ? 
             ORDER BY r.created_at DESC",
            [$prId]
        );
    }
    
    /**
     * 获取审查统计
     */
    public function getReviewStats(int $prId): array
    {
        $stats = [
            'total' => 0,
            'approved' => 0,
            'changes_requested' => 0,
            'commented' => 0,
        ];
        
        $reviews = $this->getReviews($prId);
        $stats['total'] = count($reviews);
        
        foreach ($reviews as $review) {
            $stats[$review['status']]++;
        }
        
        return $stats;
    }
    
    /**
     * 检查是否可以合并
     */
    public function canMerge(int $prId): array
    {
        $pr = Connection::queryOne("SELECT * FROM pull_requests WHERE id = ?", [$prId]);
        if (!$pr) {
            return ['can_merge' => false, 'reason' => 'PR 不存在'];
        }
        
        // 检查分支保护规则
        $rules = $this->getBranchProtectionRules($pr['repo_id'], $pr['target_branch']);
        
        // 检查是否需要审查
        if ($rules['required_approving_review_count'] > 0) {
            $stats = $this->getReviewStats($prId);
            
            if ($stats['approved'] < $rules['required_approving_review_count']) {
                return [
                    'can_merge' => false,
                    'reason' => "需要至少 {$rules['required_approving_review_count']} 个批准，当前 {$stats['approved']} 个",
                ];
            }
        }
        
        // 检查是否有变更请求
        $stats = $this->getReviewStats($prId);
        if ($stats['changes_requested'] > 0) {
            return [
                'can_merge' => false,
                'reason' => '存在变更请求，需要解决后才能合并',
            ];
        }
        
        // 检查状态检查
        if ($rules['required_status_checks']) {
            $checks = $this->getStatusChecks($prId);
            $failedChecks = array_filter($checks, fn($c) => $c['status'] !== 'success');
            
            if (count($failedChecks) > 0) {
                return [
                    'can_merge' => false,
                    'reason' => '状态检查未通过',
                ];
            }
        }
        
        return ['can_merge' => true];
    }
    
    /**
     * 获取分支保护规则
     */
    private function getBranchProtectionRules(int $repoId, string $branch): array
    {
        $rule = Connection::queryOne(
            "SELECT * FROM branch_protection_rules WHERE repo_id = ? AND branch = ?",
            [$repoId, $branch]
        );
        
        if (!$rule) {
            return [
                'required_approving_review_count' => 0,
                'required_status_checks' => false,
                'enforce_admins' => false,
            ];
        }
        
        return [
            'required_approving_review_count' => (int) $rule['required_approving_review_count'],
            'required_status_checks' => (bool) $rule['required_status_checks'],
            'enforce_admins' => (bool) $rule['enforce_admins'],
        ];
    }
    
    /**
     * 获取状态检查
     */
    private function getStatusChecks(int $prId): array
    {
        return Connection::query(
            "SELECT * FROM status_checks WHERE pr_id = ? ORDER BY created_at DESC",
            [$prId]
        );
    }
    
    /**
     * 更新 PR 审查状态
     */
    private function updatePRReviewStatus(int $prId): void
    {
        $stats = $this->getReviewStats($prId);
        
        $status = 'pending';
        if ($stats['changes_requested'] > 0) {
            $status = 'changes_requested';
        } elseif ($stats['approved'] > 0) {
            $status = 'approved';
        }
        
        Connection::execute(
            "UPDATE pull_requests SET review_status = ? WHERE id = ?",
            [$status, $prId]
        );
    }
    
    /**
     * 发送审查通知
     */
    private function sendReviewNotification(int $prId, int $reviewerId, string $status): void
    {
        $pr = Connection::queryOne(
            "SELECT pr.*, r.user_id as repo_owner_id 
             FROM pull_requests pr 
             JOIN repositories r ON pr.repo_id = r.id 
             WHERE pr.id = ?",
            [$prId]
        );
        
        if (!$pr) return;
        
        $statusText = [
            'approved' => '批准了',
            'changes_requested' => '请求变更',
            'commented' => '评论了',
        ];
        
        $message = "有人{$statusText[$status]}你的 Pull Request #{$prId}";
        
        // 通知 PR 作者
        if ($pr['author_id'] !== $reviewerId) {
            Connection::insert(
                "INSERT INTO notifications (user_id, type, title, content, reference_id, created_at) VALUES (?, 'review', ?, ?, ?, NOW())",
                [$pr['author_id'], '代码审查', $message, $prId]
            );
        }
    }
}
