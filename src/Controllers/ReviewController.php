<?php
/**
 * CodeVault - 代码审查控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class ReviewController
{
    /**
     * 获取 PR 审查列表
     */
    public function listReviews(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        if ($prId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $reviews = Connection::query(
            "SELECT r.*, u.username, u.email 
             FROM reviews r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.pr_id = ? 
             ORDER BY r.created_at DESC",
            [$prId]
        );
        
        return [
            'success' => true,
            'reviews' => $reviews,
        ];
    }
    
    /**
     * 创建审查
     */
    public function createReview(array $data): array
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
        $pr = Connection::queryOne(
            "SELECT * FROM pull_requests WHERE id = ?",
            [$prId]
        );
        
        if (!$pr) {
            return ['success' => false, 'message' => 'PR 不存在'];
        }
        
        $reviewId = Connection::insert(
            "INSERT INTO reviews (pr_id, user_id, status, body, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$prId, $user['id'], $status, $body]
        );
        
        // 更新 PR 状态（如果批准）
        if ($status === 'approved') {
            // 检查是否所有必需的审查都已批准
            $this->updatePRApprovalStatus($prId);
        }
        
        return [
            'success' => true,
            'review_id' => $reviewId,
        ];
    }
    
    /**
     * 提交审查（批准/请求更改）
     */
    public function submitReview(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $reviewId = (int) ($data['review_id'] ?? 0);
        $status = $data['status'] ?? 'commented';
        $body = trim($data['body'] ?? '');
        
        if ($reviewId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $review = Connection::queryOne(
            "SELECT * FROM reviews WHERE id = ? AND user_id = ?",
            [$reviewId, $user['id']]
        );
        
        if (!$review) {
            return ['success' => false, 'message' => '审查不存在'];
        }
        
        Connection::execute(
            "UPDATE reviews SET status = ?, body = ?, submitted_at = NOW() WHERE id = ?",
            [$status, $body, $reviewId]
        );
        
        if ($status === 'approved') {
            $this->updatePRApprovalStatus($review['pr_id']);
        }
        
        return ['success' => true, 'message' => '审查已提交'];
    }
    
    /**
     * 获取行内评论
     */
    public function listLineComments(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        $file = trim($data['file'] ?? '');
        
        if ($prId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $sql = "SELECT lc.*, u.username, u.email 
                FROM line_comments lc 
                JOIN users u ON lc.user_id = u.id 
                WHERE lc.pr_id = ?";
        $params = [$prId];
        
        if (!empty($file)) {
            $sql .= " AND lc.file = ?";
            $params[] = $file;
        }
        
        $sql .= " ORDER BY lc.file, lc.line";
        
        $comments = Connection::query($sql, $params);
        
        return [
            'success' => true,
            'comments' => $comments,
        ];
    }
    
    /**
     * 创建行内评论
     */
    public function createLineComment(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $prId = (int) ($data['pr_id'] ?? 0);
        $file = trim($data['file'] ?? '');
        $line = (int) ($data['line'] ?? 0);
        $side = $data['side'] ?? 'RIGHT'; // LEFT | RIGHT
        $body = trim($data['body'] ?? '');
        $startLine = (int) ($data['start_line'] ?? $line);
        
        if ($prId <= 0 || empty($file) || $line <= 0 || empty($body)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $commentId = Connection::insert(
            "INSERT INTO line_comments (pr_id, user_id, file, line, start_line, side, body, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$prId, $user['id'], $file, $line, $startLine, $side, $body]
        );
        
        return [
            'success' => true,
            'comment_id' => $commentId,
        ];
    }
    
    /**
     * 更新行内评论
     */
    public function updateLineComment(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $commentId = (int) ($data['id'] ?? 0);
        $body = trim($data['body'] ?? '');
        
        if ($commentId <= 0 || empty($body)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $comment = Connection::queryOne(
            "SELECT * FROM line_comments WHERE id = ? AND user_id = ?",
            [$commentId, $user['id']]
        );
        
        if (!$comment) {
            return ['success' => false, 'message' => '评论不存在'];
        }
        
        Connection::execute(
            "UPDATE line_comments SET body = ?, updated_at = NOW() WHERE id = ?",
            [$body, $commentId]
        );
        
        return ['success' => true, 'message' => '评论已更新'];
    }
    
    /**
     * 删除行内评论
     */
    public function deleteLineComment(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $commentId = (int) ($data['id'] ?? 0);
        
        Connection::execute(
            "DELETE FROM line_comments WHERE id = ? AND user_id = ?",
            [$commentId, $user['id']]
        );
        
        return ['success' => true, 'message' => '评论已删除'];
    }
    
    /**
     * 更新 PR 批准状态
     */
    private function updatePRApprovalStatus(int $prId): void
    {
        // 获取所有审查
        $reviews = Connection::query(
            "SELECT status FROM reviews WHERE pr_id = ? AND status != 'commented'",
            [$prId]
        );
        
        if (empty($reviews)) {
            return;
        }
        
        // 检查是否有拒绝
        foreach ($reviews as $review) {
            if ($review['status'] === 'changes_requested') {
                return; // 有请求更改，不更新
            }
        }
        
        // 所有审查都是批准，更新 PR 状态
        $allApproved = true;
        foreach ($reviews as $review) {
            if ($review['status'] !== 'approved') {
                $allApproved = false;
                break;
            }
        }
        
        if ($allApproved && count($reviews) > 0) {
            Connection::execute(
                "UPDATE pull_requests SET review_status = 'approved' WHERE id = ?",
                [$prId]
            );
        }
    }
}
