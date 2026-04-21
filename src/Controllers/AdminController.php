<?php
/**
 * CodeVault - 管理后台控制器（增强版）
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class AdminController
{
    /**
     * 检查管理员权限
     */
    private function checkAdmin(): bool
    {
        $user = Session::user();
        if (!$user) {
            return false;
        }
        
        // 检查是否是管理员
        $admin = Connection::queryOne(
            "SELECT * FROM admins WHERE user_id = ?",
            [$user['id']]
        );
        
        return (bool) $admin;
    }
    
    /**
     * 获取系统概览
     */
    public function dashboard(): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $stats = [
            'users' => Connection::queryOne("SELECT COUNT(*) as count FROM users")['count'],
            'repositories' => Connection::queryOne("SELECT COUNT(*) as count FROM repositories WHERE deleted_at IS NULL")['count'],
            'issues' => Connection::queryOne("SELECT COUNT(*) as count FROM issues WHERE deleted_at IS NULL")['count'],
            'pull_requests' => Connection::queryOne("SELECT COUNT(*) as count FROM pull_requests")['count'],
            'organizations' => Connection::queryOne("SELECT COUNT(*) as count FROM organizations")['count'],
            'comments' => Connection::queryOne("SELECT COUNT(*) as count FROM comments WHERE deleted_at IS NULL")['count'],
        ];
        
        // 最近注册用户
        $recentUsers = Connection::query(
            "SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 10"
        );
        
        // 最近仓库
        $recentRepos = Connection::query(
            "SELECT r.id, r.name, u.username as owner, r.created_at 
             FROM repositories r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.deleted_at IS NULL 
             ORDER BY r.created_at DESC LIMIT 10"
        );
        
        // 存储统计
        $storageStats = $this->getStorageStats();
        
        return [
            'success' => true,
            'stats' => $stats,
            'recent_users' => $recentUsers,
            'recent_repos' => $recentRepos,
            'storage' => $storageStats,
        ];
    }
    
    /**
     * 获取用户列表
     */
    public function listUsers(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 50);
        $search = trim($data['search'] ?? '');
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT id, username, email, avatar_url, bio, created_at FROM users WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        
        $users = Connection::query($sql, $params);
        
        // 获取每个用户的统计
        foreach ($users as &$user) {
            $user['repo_count'] = Connection::queryOne(
                "SELECT COUNT(*) as count FROM repositories WHERE user_id = ? AND deleted_at IS NULL",
                [$user['id']]
            )['count'];
            
            $user['is_admin'] = (bool) Connection::queryOne(
                "SELECT * FROM admins WHERE user_id = ?",
                [$user['id']]
            );
        }
        
        return ['success' => true, 'users' => $users];
    }
    
    /**
     * 禁用/启用用户
     */
    public function toggleUser(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $userId = (int) ($data['user_id'] ?? 0);
        $disabled = (int) ($data['disabled'] ?? 0);
        
        if ($userId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        Connection::execute(
            "UPDATE users SET disabled = ? WHERE id = ?",
            [$disabled, $userId]
        );
        
        return ['success' => true, 'message' => $disabled ? '用户已禁用' : '用户已启用'];
    }
    
    /**
     * 设置管理员
     */
    public function setAdmin(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $userId = (int) ($data['user_id'] ?? 0);
        $isAdmin = (int) ($data['is_admin'] ?? 0);
        
        if ($userId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        if ($isAdmin) {
            Connection::insert(
                "INSERT IGNORE INTO admins (user_id, created_at) VALUES (?, NOW())",
                [$userId]
            );
        } else {
            Connection::execute("DELETE FROM admins WHERE user_id = ?", [$userId]);
        }
        
        return ['success' => true, 'message' => $isAdmin ? '已设为管理员' : '已取消管理员'];
    }
    
    /**
     * 获取仓库列表
     */
    public function listRepos(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 50);
        $search = trim($data['search'] ?? '');
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT r.*, u.username as owner_name 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.deleted_at IS NULL";
        $params = [];
        
        if ($search) {
            $sql .= " AND (r.name LIKE ? OR u.username LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        
        $repos = Connection::query($sql, $params);
        
        return ['success' => true, 'repos' => $repos];
    }
    
    /**
     * 删除仓库
     */
    public function deleteRepo(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 软删除
        Connection::execute(
            "UPDATE repositories SET deleted_at = NOW() WHERE id = ?",
            [$repoId]
        );
        
        return ['success' => true, 'message' => '仓库已删除'];
    }
    
    /**
     * 获取系统日志
     */
    public function getLogs(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 100);
        $type = $data['type'] ?? null;
        
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM system_logs WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        
        $logs = Connection::query($sql, $params);
        
        return ['success' => true, 'logs' => $logs];
    }
    
    /**
     * 获取系统配置
     */
    public function getConfig(): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $configs = Connection::query("SELECT * FROM system_config");
        
        $result = [];
        foreach ($configs as $config) {
            $result[$config['key']] = $config['value'];
        }
        
        return ['success' => true, 'config' => $result];
    }
    
    /**
     * 更新系统配置
     */
    public function updateConfig(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        foreach ($data as $key => $value) {
            Connection::execute(
                "INSERT INTO system_config (`key`, `value`, updated_at) VALUES (?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()",
                [$key, $value, $value]
            );
        }
        
        return ['success' => true, 'message' => '配置已更新'];
    }
    
    /**
     * 获取存储统计
     */
    private function getStorageStats(): array
    {
        $gitPath = '/var/git/repositories';
        
        $totalSize = 0;
        $repoCount = 0;
        
        if (is_dir($gitPath)) {
            $cmd = sprintf('du -sb %s 2>/dev/null', escapeshellarg($gitPath));
            exec($cmd, $output);
            
            if (!empty($output)) {
                $totalSize = (int) preg_replace('/\D/', '', $output[0]);
            }
            
            $cmd = sprintf('find %s -name "*.git" -type d | wc -l', escapeshellarg($gitPath));
            exec($cmd, $output);
            $repoCount = (int) ($output[0] ?? 0);
        }
        
        return [
            'git_storage_bytes' => $totalSize,
            'git_storage_human' => $this->formatBytes($totalSize),
            'repo_count' => $repoCount,
        ];
    }
    
    /**
     * 格式化字节
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * 清理过期数据
     */
    public function cleanup(array $data): array
    {
        if (!$this->checkAdmin()) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $results = [];
        
        // 清理过期的 Session
        $expiredSessions = Connection::execute(
            "DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $results['expired_sessions'] = $expiredSessions;
        
        // 清理软删除的数据（超过 90 天）
        $deletedRepos = Connection::execute(
            "DELETE FROM repositories WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
        $results['deleted_repos'] = $deletedRepos;
        
        // 清理旧日志（超过 180 天）
        $oldLogs = Connection::execute(
            "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
        );
        $results['old_logs'] = $oldLogs;
        
        return [
            'success' => true,
            'message' => '清理完成',
            'results' => $results,
        ];
    }
}
