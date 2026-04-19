<?php
/**
 * CodeVault - SSH Key 模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class SshKey
{
    /**
     * 根据ID查找SSH Key
     */
    public static function findById(int $id): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM ssh_keys WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 获取用户的所有SSH Key
     */
    public static function findByUserId(int $userId): array
    {
        return Connection::query(
            "SELECT * FROM ssh_keys WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }
    
    /**
     * 根据fingerprint查找SSH Key
     */
    public static function findByFingerprint(string $fingerprint): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM ssh_keys WHERE fingerprint = ?",
            [$fingerprint]
        );
    }
    
    /**
     * 创建SSH Key
     */
    public static function create(int $userId, string $keyName, string $publicKey): int
    {
        $fingerprint = self::generateFingerprint($publicKey);
        
        // 检查fingerprint是否已存在
        if (self::findByFingerprint($fingerprint)) {
            throw new \Exception('该SSH Key已存在');
        }
        
        return Connection::insert(
            "INSERT INTO ssh_keys (user_id, key_name, public_key, fingerprint) VALUES (?, ?, ?, ?)",
            [$userId, $keyName, $publicKey, $fingerprint]
        );
    }
    
    /**
     * 删除SSH Key
     */
    public static function delete(int $id, int $userId): int
    {
        return Connection::execute(
            "DELETE FROM ssh_keys WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
    
    /**
     * 生成SSH Key指纹
     */
    public static function generateFingerprint(string $publicKey): string
    {
        // 移除多余的空白和换行
        $key = trim($publicKey);
        
        // 提取base64部分
        $parts = preg_split('/\s+/', $key);
        if (count($parts) < 2) {
            throw new \Exception('无效的SSH公钥格式');
        }
        
        $keyData = base64_decode($parts[1]);
        if ($keyData === false) {
            throw new \Exception('无效的SSH公钥格式');
        }
        
        return hash('sha256', $keyData);
    }
    
    /**
     * 验证SSH Key格式
     */
    public static function validateKey(string $publicKey): bool
    {
        $key = trim($publicKey);
        
        // 支持的SSH Key类型
        $validTypes = [
            'ssh-rsa',
            'ssh-dss',
            'ssh-ed25519',
            'ecdsa-sha2-nistp256',
            'ecdsa-sha2-nistp384',
            'ecdsa-sha2-nistp521',
        ];
        
        foreach ($validTypes as $type) {
            if (strpos($key, $type) === 0) {
                return true;
            }
        }
        
        return false;
    }
}
