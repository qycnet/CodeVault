<?php
/**
 * CodeVault - API Token 控制器
 * 个人访问令牌管理
 */

namespace CodeVault\Controllers;

use CodeVault\Database\Connection;
use CodeVault\Services\Session;

class ApiTokenController
{
    /**
     * 获取用户的 API Token 列表
     */
    public function list(): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $tokens = Connection::query(
            "SELECT id, name, scopes, last_used_at, expires_at, created_at 
             FROM api_tokens 
             WHERE user_id = ? AND deleted_at IS NULL 
             ORDER BY created_at DESC",
            [$user['id']]
        );
        
        // 隐藏敏感信息
        foreach ($tokens as &$token) {
            $token['scopes'] = json_decode($token['scopes'], true) ?? [];
            $token['is_expired'] = $token['expires_at'] && strtotime($token['expires_at']) < time();
        }
        
        return [
            'success' => true,
            'tokens' => $tokens,
        ];
    }
    
    /**
     * 创建新的 API Token
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $name = trim($data['name'] ?? '');
        $scopes = $data['scopes'] ?? ['repo', 'user'];
        $expiresAt = $data['expires_at'] ?? null;
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Token 名称不能为空'];
        }
        
        // 检查名称是否重复
        $existing = Connection::queryOne(
            "SELECT id FROM api_tokens WHERE user_id = ? AND name = ? AND deleted_at IS NULL",
            [$user['id'], $name]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'Token 名称已存在'];
        }
        
        // 生成 Token
        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);
        $tokenPrefix = substr($token, 0, 8);
        
        // 存储到数据库
        $tokenId = Connection::insert(
            "INSERT INTO api_tokens (user_id, name, token_hash, token_prefix, scopes, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $user['id'],
                $name,
                $tokenHash,
                $tokenPrefix,
                json_encode($scopes),
                $expiresAt,
            ]
        );
        
        return [
            'success' => true,
            'token' => $token, // 只返回一次
            'token_id' => $tokenId,
            'name' => $name,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'message' => '请妥善保管 Token，此 Token 只会显示一次',
        ];
    }
    
    /**
     * 删除 API Token
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $tokenId = (int) ($data['id'] ?? 0);
        
        if ($tokenId <= 0) {
            return ['success' => false, 'message' => '无效的 Token ID'];
        }
        
        // 验证所有权
        $token = Connection::queryOne(
            "SELECT id FROM api_tokens WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$tokenId, $user['id']]
        );
        
        if (!$token) {
            return ['success' => false, 'message' => 'Token 不存在或无权删除'];
        }
        
        // 软删除
        Connection::execute(
            "UPDATE api_tokens SET deleted_at = NOW() WHERE id = ?",
            [$tokenId]
        );
        
        return [
            'success' => true,
            'message' => 'Token 已删除',
        ];
    }
    
    /**
     * 更新 Token（修改名称/范围）
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $tokenId = (int) ($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $scopes = $data['scopes'] ?? null;
        
        if ($tokenId <= 0) {
            return ['success' => false, 'message' => '无效的 Token ID'];
        }
        
        // 验证所有权
        $token = Connection::queryOne(
            "SELECT * FROM api_tokens WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$tokenId, $user['id']]
        );
        
        if (!$token) {
            return ['success' => false, 'message' => 'Token 不存在或无权修改'];
        }
        
        $updates = [];
        $params = [];
        
        if (!empty($name) && $name !== $token['name']) {
            // 检查名称是否重复
            $existing = Connection::queryOne(
                "SELECT id FROM api_tokens WHERE user_id = ? AND name = ? AND id != ? AND deleted_at IS NULL",
                [$user['id'], $name, $tokenId]
            );
            
            if ($existing) {
                return ['success' => false, 'message' => 'Token 名称已存在'];
            }
            
            $updates[] = 'name = ?';
            $params[] = $name;
        }
        
        if ($scopes !== null) {
            $updates[] = 'scopes = ?';
            $params[] = json_encode($scopes);
        }
        
        if (empty($updates)) {
            return ['success' => true, 'message' => '无更新'];
        }
        
        $params[] = $tokenId;
        
        Connection::execute(
            "UPDATE api_tokens SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );
        
        return [
            'success' => true,
            'message' => 'Token 已更新',
        ];
    }
    
    /**
     * 验证 API Token
     */
    public static function verify(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }
        
        $tokenHash = hash('sha256', $token);
        
        $tokenData = Connection::queryOne(
            "SELECT t.*, u.username, u.email, u.is_admin 
             FROM api_tokens t 
             JOIN users u ON t.user_id = u.id 
             WHERE t.token_hash = ? AND t.deleted_at IS NULL AND u.deleted_at IS NULL",
            [$tokenHash]
        );
        
        if (!$tokenData) {
            return null;
        }
        
        // 检查是否过期
        if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
            return null;
        }
        
        // 更新最后使用时间
        Connection::execute(
            "UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?",
            [$tokenData['id']]
        );
        
        return [
            'user_id' => $tokenData['user_id'],
            'username' => $tokenData['username'],
            'email' => $tokenData['email'],
            'is_admin' => $tokenData['is_admin'],
            'scopes' => json_decode($tokenData['scopes'], true) ?? [],
            'token_id' => $tokenData['id'],
        ];
    }
    
    /**
     * 检查 Token 权限范围
     */
    public static function hasScope(array $tokenData, string $scope): bool
    {
        $scopes = $tokenData['scopes'] ?? [];
        
        // admin 拥有所有权限
        if (in_array('admin', $scopes)) {
            return true;
        }
        
        return in_array($scope, $scopes) || in_array('*', $scopes);
    }
    
    /**
     * 生成 Token
     * 格式: cv_<random_string>
     */
    private function generateToken(): string
    {
        $random = bin2hex(random_bytes(32));
        return 'cv_' . $random;
    }
    
    /**
     * 获取可用的权限范围
     */
    public function getScopes(): array
    {
        return [
            ['name' => 'repo', 'description' => '仓库完全访问权限'],
            ['name' => 'repo:read', 'description' => '仓库只读权限'],
            ['name' => 'repo:write', 'description' => '仓库写入权限'],
            ['name' => 'user', 'description' => '用户信息读取权限'],
            ['name' => 'user:email', 'description' => '用户邮箱读取权限'],
            ['name' => 'admin', 'description' => '管理员权限'],
            ['name' => 'workflow', 'description' => '工作流管理权限'],
            ['name' => 'delete_repo', 'description' => '删除仓库权限'],
        ];
    }
}
