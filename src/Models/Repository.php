<?php
/**
 * CodeVault - 仓库模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class Repository
{
    /**
     * 根据ID查找仓库
     */
    public static function findById(int $id): ?array
    {
        return Connection::queryOne(
            "SELECT r.*, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.id = ?",
            [$id]
        );
    }
    
    /**
     * 根据用户ID和仓库名查找
     */
    public static function findByUserAndName(int $userId, string $name): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM repositories WHERE user_id = ? AND name = ?",
            [$userId, $name]
        );
    }
    
    /**
     * 获取用户的所有仓库
     */
    public static function findByUserId(int $userId): array
    {
        return Connection::query(
            "SELECT * FROM repositories WHERE user_id = ? ORDER BY updated_at DESC",
            [$userId]
        );
    }
    
    /**
     * 获取公开仓库列表
     */
    public static function getPublic(int $limit = 20, int $offset = 0): array
    {
        return Connection::query(
            "SELECT r.*, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.is_private = 0 ORDER BY r.updated_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
    
    /**
     * 创建仓库
     */
    public static function create(array $data): int
    {
        return Connection::insert(
            "INSERT INTO repositories (user_id, name, description, is_private, git_path) VALUES (?, ?, ?, ?, ?)",
            [
                $data['user_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['is_private'] ?? 0,
                $data['git_path'],
            ]
        );
    }
    
    /**
     * 更新仓库
     */
    public static function update(int $id, int $userId, array $data): int
    {
        $fields = [];
        $params = [];
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['is_private'])) {
            $fields[] = "is_private = ?";
            $params[] = $data['is_private'];
        }
        
        if (empty($fields)) {
            return 0;
        }
        
        $params[] = $id;
        $params[] = $userId;
        
        return Connection::execute(
            "UPDATE repositories SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?",
            $params
        );
    }
    
    /**
     * 删除仓库
     */
    public static function delete(int $id, int $userId): int
    {
        return Connection::execute(
            "DELETE FROM repositories WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
    
    /**
     * 检查用户是否有权限访问仓库
     */
    public static function canAccess(int $repoId, int $userId): bool
    {
        $repo = self::findById($repoId);
        if (!$repo) {
            return false;
        }
        
        // 公开仓库任何人可访问
        if (!$repo['is_private']) {
            return true;
        }
        
        // 私有仓库只有所有者可访问
        return $repo['user_id'] === $userId;
    }
    
    /**
     * 检查用户是否是仓库所有者
     */
    public static function isOwner(int $repoId, int $userId): bool
    {
        $repo = self::findById($repoId);
        return $repo && $repo['user_id'] === $userId;
    }
}
