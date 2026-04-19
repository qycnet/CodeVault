<?php
/**
 * CodeVault - Issue 模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class Issue
{
    /**
     * 根据ID查找 Issue
     */
    public static function findById(int $id): ?array
    {
        return Connection::queryOne(
            "SELECT i.*, u.username as author_name, r.name as repo_name 
             FROM issues i 
             JOIN users u ON i.user_id = u.id 
             JOIN repositories r ON i.repo_id = r.id 
             WHERE i.id = ?",
            [$id]
        );
    }
    
    /**
     * 获取仓库的 Issue 列表
     */
    public static function findByRepoId(int $repoId, ?string $status = null): array
    {
        $sql = "SELECT i.*, u.username as author_name 
                FROM issues i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.repo_id = ?";
        $params = [$repoId];
        
        if ($status && in_array($status, ['open', 'closed'])) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.updated_at DESC";
        
        return Connection::query($sql, $params);
    }
    
    /**
     * 创建 Issue
     */
    public static function create(array $data): int
    {
        return Connection::insert(
            "INSERT INTO issues (repo_id, user_id, title, content, status) VALUES (?, ?, ?, ?, ?)",
            [
                $data['repo_id'],
                $data['user_id'],
                $data['title'],
                $data['content'] ?? '',
                $data['status'] ?? 'open',
            ]
        );
    }
    
    /**
     * 更新 Issue
     */
    public static function update(int $id, int $userId, array $data): int
    {
        $fields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = $data['title'];
        }
        
        if (isset($data['content'])) {
            $fields[] = "content = ?";
            $params[] = $data['content'];
        }
        
        if (isset($data['status']) && in_array($data['status'], ['open', 'closed'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (empty($fields)) {
            return 0;
        }
        
        $params[] = $id;
        $params[] = $userId;
        
        return Connection::execute(
            "UPDATE issues SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?",
            $params
        );
    }
    
    /**
     * 关闭 Issue
     */
    public static function close(int $id, int $userId): int
    {
        return Connection::execute(
            "UPDATE issues SET status = 'closed' WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
    
    /**
     * 重新打开 Issue
     */
    public static function reopen(int $id, int $userId): int
    {
        return Connection::execute(
            "UPDATE issues SET status = 'open' WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
    
    /**
     * 删除 Issue
     */
    public static function delete(int $id, int $userId): int
    {
        return Connection::execute(
            "DELETE FROM issues WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
    
    /**
     * 统计仓库的 Issue 数量
     */
    public static function countByRepo(int $repoId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) as count FROM issues WHERE repo_id = ?";
        $params = [$repoId];
        
        if ($status && in_array($status, ['open', 'closed'])) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $result = Connection::queryOne($sql, $params);
        return (int) $result['count'];
    }
}
