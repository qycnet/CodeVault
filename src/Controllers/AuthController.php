<?php
/**
 * CodeVault - 认证控制器
 * 处理用户注册、登录、登出
 */

namespace CodeVault\Controllers;

use CodeVault\Models\User;
use CodeVault\Models\VerificationCode;
use CodeVault\Services\Session;
use CodeVault\Services\Mailer;

class AuthController
{
    private Mailer $mailer;
    
    public function __construct()
    {
        $this->mailer = new Mailer();
    }
    
    /**
     * 发送注册验证码
     */
    public function sendRegisterCode(array $data): array
    {
        $email = trim($data['email'] ?? '');
        
        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }
        
        // 检查邮箱是否已注册
        if (User::findByEmail($email)) {
            return ['success' => false, 'message' => '该邮箱已被注册'];
        }
        
        // 生成验证码
        $result = VerificationCode::create($email, 'register');
        
        // 发送验证码邮件
        $subject = 'CodeVault 注册验证码';
        $body = $this->getVerificationEmailTemplate($result['code'], '注册');
        
        $sent = $this->mailer->send($email, $subject, $body);
        
        if (!$sent) {
            error_log("Failed to send verification email to: {$email}");
        }
        
        return [
            'success' => true,
            'message' => '验证码已发送',
        ];
    }
    
    /**
     * 获取验证码邮件模板
     */
    private function getVerificationEmailTemplate(string $code, string $action): string
    {
        $appUrl = $_ENV['APP_URL'] ?? 'https://codevault.local';
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .code { font-size: 32px; font-weight: bold; color: #0366d6; letter-spacing: 8px; }
        .footer { margin-top: 30px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>CodeVault {$action}验证码</h2>
        <p>您的验证码是：</p>
        <p class="code">{$code}</p>
        <p>验证码有效期为 10 分钟，请勿泄露给他人。</p>
        <div class="footer">
            <p>此邮件由系统自动发送，请勿回复。</p>
            <p><a href="{$appUrl}">CodeVault</a> - 自托管 Git 仓库管理平台</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * 用户注册
     */
    public function register(array $data): array
    {
        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $code = trim($data['code'] ?? '');
        
        // 参数验证
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => '用户名长度需在3-50字符之间'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return ['success' => false, 'message' => '用户名只能包含字母、数字、下划线和连字符'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => '密码长度至少8位'];
        }
        
        // 验证验证码
        if (!VerificationCode::verify($email, $code, 'register')) {
            return ['success' => false, 'message' => '验证码无效或已过期'];
        }
        
        // 检查邮箱和用户名是否已存在
        if (User::findByEmail($email)) {
            return ['success' => false, 'message' => '该邮箱已被注册'];
        }
        
        if (User::findByUsername($username)) {
            return ['success' => false, 'message' => '该用户名已被使用'];
        }
        
        // 创建用户
        try {
            $userId = User::create([
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'is_verified' => 1, // 已通过验证码验证
            ]);
            
            return [
                'success' => true,
                'message' => '注册成功',
                'user_id' => $userId,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '注册失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 用户登录
     */
    public function login(array $data): array
    {
        $login = trim($data['login'] ?? ''); // 邮箱或用户名
        $password = $data['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            return ['success' => false, 'message' => '请输入账号和密码'];
        }
        
        // 查找用户（支持邮箱或用户名登录）
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::findByEmail($login)
            : User::findByUsername($login);
        
        if (!$user) {
            return ['success' => false, 'message' => '账号不存在'];
        }
        
        // 验证密码
        if (!User::verifyPassword($user, $password)) {
            return ['success' => false, 'message' => '密码错误'];
        }
        
        // 检查是否已验证
        if (!$user['is_verified']) {
            return ['success' => false, 'message' => '账号未验证，请先验证邮箱'];
        }
        
        // 创建Session
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        Session::create($user['id'], $ip, $userAgent);
        
        return [
            'success' => true,
            'message' => '登录成功',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
            ],
        ];
    }
    
    /**
     * 用户登出
     */
    public function logout(): array
    {
        Session::destroy();
        return ['success' => true, 'message' => '已退出登录'];
    }
    
    /**
     * 获取当前登录用户信息
     */
    public function me(): array
    {
        $user = Session::user();
        
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        return [
            'success' => true,
            'user' => $user,
        ];
    }
}
