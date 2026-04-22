<?php
/**
 * CodeVault - 安全功能测试
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CodeVault\Core\SecurityHelper;
use CodeVault\Services\SecurityService;
use CodeVault\Services\FileUploadService;

class SecurityTest
{
    private int $passed = 0;
    private int $failed = 0;
    
    public function run(): void
    {
        echo "=== CodeVault 安全功能测试 ===\n\n";
        
        $this->testCsrfProtection();
        $this->testXssProtection();
        $this->testPathTraversal();
        $this->testFileUpload();
        $this->testPasswordHashing();
        $this->testInputValidation();
        
        echo "\n=== 测试结果 ===\n";
        echo "通过: {$this->passed}\n";
        echo "失败: {$this->failed}\n";
        echo "总计: " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n✅ 所有安全测试通过！\n";
        } else {
            echo "\n❌ 部分测试失败，请检查！\n";
        }
    }
    
    /**
     * 测试 CSRF 防护
     */
    private function testCsrfProtection(): void
    {
        echo "1. CSRF 防护测试\n";
        
        // 模拟 session
        $_SESSION = [];
        
        // 测试 Token 生成
        $token1 = SecurityHelper::generateCsrfToken();
        $token2 = SecurityHelper::generateCsrfToken();
        
        $this->assert('Token 生成成功', !empty($token1));
        $this->assert('Token 长度正确', strlen($token1) === 64);
        $this->assert('Token 相同（同一会话）', $token1 === $token2);
        
        // 测试 Token 验证
        $this->assert('Token 验证成功', SecurityHelper::verifyCsrfToken($token1));
        $this->assert('错误 Token 验证失败', !SecurityHelper::verifyCsrfToken('invalid_token'));
        $this->assert('空 Token 验证失败', !SecurityHelper::verifyCsrfToken(null));
        
        echo "\n";
    }
    
    /**
     * 测试 XSS 防护
     */
    private function testXssProtection(): void
    {
        echo "2. XSS 防护测试\n";
        
        $input = '<script>alert("xss")</script>';
        $escaped = SecurityHelper::htmlEscape($input);
        
        $this->assert('HTML 转义正确', strpos($escaped, '<script>') === false);
        $this->assert('转义后包含实体', strpos($escaped, '&lt;') !== false);
        
        // 测试 JS 转义
        $jsInput = 'alert("xss")';
        $jsEscaped = SecurityHelper::jsEscape($jsInput);
        $this->assert('JS 转义成功', !empty($jsEscaped));
        
        echo "\n";
    }
    
    /**
     * 测试路径遍历防护
     */
    private function testPathTraversal(): void
    {
        echo "3. 路径遍历防护测试\n";
        
        $baseDir = '/var/git/repositories';
        
        // 正常路径
        $validPath = SecurityHelper::validatePath($baseDir . '/user/repo.git', $baseDir);
        $this->assert('正常路径验证通过', $validPath !== false);
        
        // 路径遍历攻击
        $attackPath = SecurityHelper::validatePath($baseDir . '/../../../etc/passwd', $baseDir);
        $this->assert('路径遍历攻击被阻止', $attackPath === false);
        
        // 另一种攻击
        $attackPath2 = SecurityHelper::validatePath($baseDir . '/..%2F..%2F..%2Fetc%2Fpasswd', $baseDir);
        $this->assert('URL 编码攻击被阻止', $attackPath2 === false);
        
        echo "\n";
    }
    
    /**
     * 测试文件上传安全
     */
    private function testFileUpload(): void
    {
        echo "4. 文件上传安全测试\n";
        
        // 测试允许的扩展名
        $this->assert('允许 .txt 文件', FileUploadService::isAllowedExtension('test.txt'));
        $this->assert('允许 .md 文件', FileUploadService::isAllowedExtension('README.md'));
        $this->assert('禁止 .php 文件', !FileUploadService::isAllowedExtension('shell.php'));
        $this->assert('禁止 .exe 文件', !FileUploadService::isAllowedExtension('virus.exe'));
        
        echo "\n";
    }
    
    /**
     * 测试密码哈希
     */
    private function testPasswordHashing(): void
    {
        echo "5. 密码安全测试\n";
        
        $security = new SecurityService();
        $password = 'TestPassword123!';
        
        $hash = $security->hashPassword($password);
        
        $this->assert('密码哈希生成成功', !empty($hash));
        $this->assert('哈希长度正确', strlen($hash) === 60);
        $this->assert('哈希以 $2y$ 开头', strpos($hash, '$2y$') === 0);
        $this->assert('密码验证成功', $security->verifyPassword($password, $hash));
        $this->assert('错误密码验证失败', !$security->verifyPassword('WrongPassword', $hash));
        
        echo "\n";
    }
    
    /**
     * 测试输入验证
     */
    private function testInputValidation(): void
    {
        echo "6. 输入验证测试\n";
        
        $security = new SecurityService();
        
        // 整数验证
        $this->assert('整数验证成功', $security->validateInt('123') === 123);
        $this->assert('整数范围验证', $security->validateInt('50', 1, 100) === 50);
        $this->assert('超出范围返回 null', $security->validateInt('200', 1, 100) === null);
        
        // 邮箱验证
        $this->assert('邮箱验证成功', $security->validateEmail('test@example.com') === 'test@example.com');
        $this->assert('无效邮箱返回 false', $security->validateEmail('invalid') === false);
        
        // 用户名验证
        $this->assert('用户名验证成功', $security->validateUsername('user_123'));
        $this->assert('无效用户名验证失败', !$security->validateUsername('a'));
        
        echo "\n";
    }
    
    private function assert(string $name, bool $condition): void
    {
        if ($condition) {
            echo "  ✅ {$name}\n";
            $this->passed++;
        } else {
            echo "  ❌ {$name}\n";
            $this->failed++;
        }
    }
}

// 运行测试
$test = new SecurityTest();
$test->run();
