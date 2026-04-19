<?php
/**
 * CodeVault - Session 管理类
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class Session
{
    private static ?array $currentUser = null;
    
    /**
     * 启动Session
     */
    public static function start(): void
    {
        $config = require __DIR__ . '/../../config/app.php';
        $sessionConfig = $config['session'];
        
        session_name($sessionConfig['name']);
        session_set_cookie_params([
            'lifetime'  => $sessionConfig['lifetime'],
            'path'      => $sessionConfig['cookie_path'],
            'secure'    => $sessionConfig['cookie_secure'],
            'httponly'  => $sessionConfig['cookie_httponly'],
        ]);
        
        session_start();
    }
    
    /**
     * 创建登录Session
     */
    public static function create(int $userId, string $ip = null, string $userAgent = null): string
    {
        $config = require __DIR__ . '/../../config/app.php';
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $config['session']['lifetime']);
        
        Connection::insert(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$sessionId, $userId, $ip, $userAgent, $expiresAt]
        );
        
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_id'] = $userId;
        
        return $sessionId;
    }
    
    /**
     * 获取当前登录用户
     */
    public static function user(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }
        
        if (!isset($_SESSION['session_id']) || !isset($_SESSION['user_id'])) {
            return null;
        }
        
        // 验证Session是否有效
        $session = Connection::queryOne(
            "SELECT * FROM sessions WHERE id = ? AND user_id = ? AND expires_at > NOW()",
            [$_SESSION['session_id'], $_SESSION['user_id']]
        );
        
        if (!$session) {
            self::destroy();
            return null;
        }
        
        // 获取用户信息
        $user = Connection::queryOne(
            "SELECT id, email, username, is_verified, created_at FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if ($user) {
            self::$currentUser = $user;
        }
        
        return self::$currentUser;
    }
    
    /**
     * 检查是否已登录
     */
    public static function isLoggedIn(): bool
    {
        return self::user() !== null;
    }
    
    /**
     * 销毁Session
     */
    public static function destroy(): void
    {
        if (isset($_SESSION['session_id'])) {
            Connection::execute(
                "DELETE FROM sessions WHERE id = ?",
                [$_SESSION['session_id']]
            );
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"]);
        }
        
        session_destroy();
        self::$currentUser = null;
    }
    
    /**
     * 清理过期Session
     */
    public static function cleanExpired(): int
    {
        return Connection::execute(
            "DELETE FROM sessions WHERE expires_at < NOW()"
        );
    }
}
