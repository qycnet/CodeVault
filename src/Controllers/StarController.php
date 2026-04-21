<?php
/**
 * CodeVault - Star 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class StarController
{
    /**
     * Star 仓库
     */
    public function star(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查仓库是否存在
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 检查是否已 star
        $existing = Connection::queryOne(
            "SELECT * FROM stars WHERE user_id = ? AND repo_id = ?",
            [$user['id'], $repoId]
        );
        
        if ($existing) {
            return ['success' => true, 'message' => '已 Star'];
        }
        
        Connection::insert(
            "INSERT INTO stars (user_id, repo_id, created_at) VALUES (?, ?, NOW())",
            [$user['id'], $repoId]
        );
        
        // 更新仓库 star 数
        Connection::execute(
            "UPDATE repositories SET star_count = star_count + 1 WHERE id = ?",
            [$repoId]
        );
        
        return ['success' => true, 'message' => 'Star 成功'];
    }
    
    /**
     * 取消 Star
     */
    public function unstar(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        $existing = Connection::queryOne(
            "SELECT * FROM stars WHERE user_id = ? AND repo_id = ?",
            [$user['id'], $repoId]
        );
        
        if (!$existing) {
            return ['success' => true, 'message' => '未 Star'];
        }
        
        Connection::execute(
            "DELETE FROM stars WHERE user_id = ? AND repo_id = ?",
            [$user['id'], $repoId]
        );
        
        // 更新仓库 star 数
        Connection::execute(
            "UPDATE repositories SET star_count = GREATEST(0, star_count - 1) WHERE id = ?",
            [$repoId]
        );
        
        return ['success' => true, 'message' => '已取消 Star'];
    }
    
    /**
     * 检查是否已 Star
     */
    public function checkStar(array $data): array
    {
        $user = Session::user();
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if (!$user) {
            return ['success' => true, 'starred' => false];
        }
        
        $existing = Connection::queryOne(
            "SELECT * FROM stars WHERE user_id = ? AND repo_id = ?",
            [$user['id'], $repoId]
        );
        
        return ['success' => true, 'starred' => (bool) $existing];
    }
    
    /**
     * 获取用户 Star 列表
     */
    public function listUserStars(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $stars = Connection::query(
            "SELECT s.*, r.name, r.description, u.username as owner
             FROM stars s
             JOIN repositories r ON s.repo_id = r.id
             JOIN users u ON r.user_id = u.id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC",
            [$user['id']]
        );
        
        return ['success' => true, 'stars' => $stars];
    }
}
