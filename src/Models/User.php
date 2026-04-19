<?php
/**
 * CodeVault - 用户模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class User
{
    /**
     * 根据ID查找用户
     */
    public static function findById(int $id): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM users WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 根据邮箱查找用户
     */
    public static function findByEmail(string $email): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
    }
    
    /**
     * 根据用户名查找用户
     */
    public static function findByUsername(string $username): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
    }
    
    /**
     * 创建用户
     */
    public static function create(array $data): int
    {
        return Connection::insert(
            "INSERT INTO users (email, password_hash, username, is_verified) VALUES (?, ?, ?, ?)",
            [
                $data['email'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $data['username'],
                $data['is_verified'] ?? 0
            ]
        );
    }
    
    /**
     * 更新用户
     */
    public static function update(int $id, array $data): int
    {
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        return Connection::execute(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
    }
    
    /**
     * 验证密码
     */
    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * 设置邮箱已验证
     */
    public static function setVerified(int $id): int
    {
        return Connection::execute(
            "UPDATE users SET is_verified = 1 WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 删除用户
     */
    public static function delete(int $id): int
    {
        return Connection::execute(
            "DELETE FROM users WHERE id = ?",
            [$id]
        );
    }
}
