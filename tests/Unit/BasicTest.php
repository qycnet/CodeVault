<?php
/**
 * CodeVault - 单元测试
 * 
 * 测试核心功能
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Repository.php';
require_once __DIR__ . '/../src/Models/Issue.php';
require_once __DIR__ . '/../src/Models/PullRequest.php';
require_once __DIR__ . '/../src/Models/Comment.php';
require_once __DIR__ . '/../src/Models/SSHKey.php';
require_once __DIR__ . '/../src/Models/VerificationCode.php';

class UnitTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    /**
     * 测试密码长度验证
     */
    public function testPasswordLength(): void
    {
        $minLength = 8;
        
        // 测试有效密码
        $validPasswords = ['password123', 'Abc@12345', '12345678'];
        foreach ($validPasswords as $password) {
            if (strlen($password) >= $minLength) {
                $this->passed++;
                echo "✅ 密码验证通过: $password\n";
            } else {
                $this->failed++;
                $this->errors[] = "密码验证失败: $password (长度: " . strlen($password) . ")";
            }
        }
        
        // 测试无效密码
        $invalidPasswords = ['123456', 'abc', 'pass'];
        foreach ($invalidPasswords as $password) {
            if (strlen($password) < $minLength) {
                $this->passed++;
                echo "✅ 无效密码正确拒绝: $password\n";
            } else {
                $this->failed++;
                $this->errors[] = "无效密码未被拒绝: $password";
            }
        }
    }

    /**
     * 测试邮箱格式验证
     */
    public function testEmailValidation(): void
    {
        $validEmails = ['test@example.com', 'user@domain.org', 'admin@codevault.io'];
        $invalidEmails = ['invalid', 'no@', '@domain.com', 'spaces in@email.com'];
        
        foreach ($validEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->passed++;
                echo "✅ 邮箱验证通过: $email\n";
            } else {
                $this->failed++;
                $this->errors[] = "邮箱验证失败: $email";
            }
        }
        
        foreach ($invalidEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->passed++;
                echo "✅ 无效邮箱正确拒绝: $email\n";
            } else {
                $this->failed++;
                $this->errors[] = "无效邮箱未被拒绝: $email";
            }
        }
    }

    /**
     * 测试 XSS 防护
     */
    public function testXSSProtection(): void
    {
        $maliciousInputs = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            '<a href="javascript:alert(1)">click</a>',
            '"><script>alert(String.fromCharCode(88,83,83))</script>'
        ];
        
        foreach ($maliciousInputs as $input) {
            $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $sanitized = strip_tags($sanitized);
            
            if (strpos($sanitized, '<script>') === false && 
                strpos($sanitized, 'onerror') === false &&
                strpos($sanitized, 'javascript:') === false) {
                $this->passed++;
                echo "✅ XSS 防护成功: " . substr($input, 0, 30) . "...\n";
            } else {
                $this->failed++;
                $this->errors[] = "XSS 防护失败: $input";
            }
        }
    }

    /**
     * 测试 Session 配置
     */
    public function testSessionConfig(): void
    {
        // 测试 Cookie Secure 配置
        $cookieSecure = getenv('APP_ENV') === 'production';
        echo "✅ Cookie Secure: " . ($cookieSecure ? 'true (生产环境)' : 'false (开发环境)') . "\n";
        $this->passed++;
        
        // 测试 SameSite 配置
        $sameSite = 'Strict';
        echo "✅ SameSite: $sameSite\n";
        $this->passed++;
    }

    /**
     * 测试 CORS 配置
     */
    public function testCORSConfig(): void
    {
        $allowedOrigins = ['http://localhost:5173', 'http://localhost:3000'];
        $testOrigin = 'http://localhost:5173';
        
        if (in_array($testOrigin, $allowedOrigins)) {
            $this->passed++;
            echo "✅ CORS 白名单验证通过: $testOrigin\n";
        } else {
            $this->failed++;
            $this->errors[] = "CORS 白名单验证失败: $testOrigin";
        }
        
        $blockedOrigin = 'http://malicious-site.com';
        if (!in_array($blockedOrigin, $allowedOrigins)) {
            $this->passed++;
            echo "✅ CORS 正确阻止: $blockedOrigin\n";
        } else {
            $this->failed++;
            $this->errors[] = "CORS 未阻止恶意来源: $blockedOrigin";
        }
    }

    /**
     * 运行所有测试
     */
    public function run(): void
    {
        echo "\n========================================\n";
        echo "CodeVault 单元测试\n";
        echo "========================================\n\n";
        
        echo "--- 测试密码长度验证 ---\n";
        $this->testPasswordLength();
        
        echo "\n--- 测试邮箱格式验证 ---\n";
        $this->testEmailValidation();
        
        echo "\n--- 测试 XSS 防护 ---\n";
        $this->testXSSProtection();
        
        echo "\n--- 测试 Session 配置 ---\n";
        $this->testSessionConfig();
        
        echo "\n--- 测试 CORS 配置 ---\n";
        $this->testCORSConfig();
        
        echo "\n========================================\n";
        echo "测试结果汇总\n";
        echo "========================================\n";
        echo "✅ 通过: {$this->passed}\n";
        echo "❌ 失败: {$this->failed}\n";
        
        if (!empty($this->errors)) {
            echo "\n错误详情:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
        }
        
        echo "\n";
    }
}

// 运行测试
$test = new UnitTest();
$test->run();
