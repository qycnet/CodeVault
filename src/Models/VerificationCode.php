<?php
/**
 * CodeVault - 验证码模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class VerificationCode
{
    /**
     * 创建验证码
     */
    public static function create(string $email, string $type = 'register'): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $codeLength = $config['verification']['code_length'];
        $expireTime = $config['verification']['expire_time'];
        
        // 生成随机验证码
        $code = str_pad(random_int(0, pow(10, $codeLength) - 1), $codeLength, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + $expireTime);
        
        $id = Connection::insert(
            "INSERT INTO verification_codes (email, code, type, expires_at) VALUES (?, ?, ?, ?)",
            [$email, $code, $type, $expiresAt]
        );
        
        return [
            'id' => $id,
            'code' => $code,
            'expires_at' => $expiresAt,
        ];
    }
    
    /**
     * 验证验证码
     */
    public static function verify(string $email, string $code, string $type = 'register'): bool
    {
        $record = Connection::queryOne(
            "SELECT * FROM verification_codes 
             WHERE email = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            [$email, $code, $type]
        );
        
        if (!$record) {
            return false;
        }
        
        // 标记为已使用
        Connection::execute(
            "UPDATE verification_codes SET used = 1 WHERE id = ?",
            [$record['id']]
        );
        
        return true;
    }
    
    /**
     * 清理过期验证码
     */
    public static function cleanExpired(): int
    {
        return Connection::execute(
            "DELETE FROM verification_codes WHERE expires_at < NOW() OR used = 1"
        );
    }
    
    /**
     * 获取最新的未使用验证码（用于测试或重发）
     */
    public static function getLatestUnused(string $email, string $type = 'register'): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM verification_codes 
             WHERE email = ? AND type = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            [$email, $type]
        );
    }
}
