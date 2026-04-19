<?php
/**
 * CodeVault - 用户认证类
 * PHP 原生开发，密码使用 bcrypt 哈希
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * 用户注册
     * @param string $username 用户名
     * @param string $email 邮箱
     * @param string $password 明文密码
     * @return array 用户信息
     * @throws Exception
     */
    public function register(string $username, string $email, string $password): array
    {
        // 输入验证
        $username = trim($username);
        $email = trim(strtolower($email));

        if (!$this->validateUsername($username)) {
            throw new Exception('用户名格式无效（3-50字符，仅字母数字下划线）');
        }

        if (!$this->validateEmail($email)) {
            throw new Exception('邮箱格式无效');
        }

        if (!$this->validatePassword($password)) {
            throw new Exception('密码强度不足（至少8位，包含字母和数字）');
        }

        // 检查用户名/邮箱是否已存在
        if ($this->usernameExists($username)) {
            throw new Exception('用户名已被使用');
        }

        if ($this->emailExists($email)) {
            throw new Exception('邮箱已被注册');
        }

        // 密码哈希（bcrypt，cost=12）
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // 插入用户
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash]);

        $userId = (int)$this->db->lastInsertId();

        return [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
        ];
    }

    /**
     * 用户登录
     * @param string $username 用户名或邮箱
     * @param string $password 明文密码
     * @return array 用户信息
     * @throws Exception
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);

        // 支持用户名或邮箱登录
        $stmt = $this->db->prepare(
            'SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ?'
        );
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('用户名或密码错误');
        }

        // 验证密码
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('用户名或密码错误');
        }

        // 设置 Session
        initSession();
        session_regenerate_id(true); // 防止 Session Fixation
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];

        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ];
    }

    /**
     * 用户登出
     */
    public function logout(): void
    {
        initSession();
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }

    /**
     * 获取当前登录用户
     * @return array|null 用户信息或 null
     */
    public function getCurrentUser(): ?array
    {
        initSession();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, username, email, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * 检查是否已登录
     */
    public function isLoggedIn(): bool
    {
        initSession();
        return isset($_SESSION['user_id']);
    }

    /**
     * 要求登录（中间件）
     * @throws Exception
     */
    public function requireLogin(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new Exception('请先登录', 401);
        }
        return $user;
    }

    // ==================== 私有方法 ====================

    private function validateUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username) === 1;
    }

    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validatePassword(string $password): bool
    {
        // 至少8位，包含字母和数字
        return strlen($password) >= 8
            && preg_match('/[a-zA-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    private function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }

    private function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
}
