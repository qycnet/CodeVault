<?php
/**
 * CodeVault - AuthService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\AuthService;

class AuthServiceTest extends TestCase
{
    private AuthService $service;
    
    protected function setUp(): void
    {
        $this->service = new AuthService();
    }
    
    /**
     * 测试邮箱格式验证
     */
    public function testEmailValidation(): void
    {
        $this->assertTrue($this->service->isValidEmail('test@example.com'));
        $this->assertTrue($this->service->isValidEmail('user.name@domain.org'));
        $this->assertTrue($this->service->isValidEmail('user+tag@example.co.uk'));
        
        $this->assertFalse($this->service->isValidEmail('invalid'));
        $this->assertFalse($this->service->isValidEmail('test@'));
        $this->assertFalse($this->service->isValidEmail('@domain.com'));
        $this->assertFalse($this->service->isValidEmail(''));
    }
    
    /**
     * 测试密码强度验证
     */
    public function testPasswordStrength(): void
    {
        // 强密码
        $this->assertTrue($this->service->isStrongPassword('Password123!'));
        $this->assertTrue($this->service->isStrongPassword('Str0ng@Pass'));
        
        // 弱密码
        $this->assertFalse($this->service->isStrongPassword('password')); // 无大写、数字、特殊字符
        $this->assertFalse($this->service->isStrongPassword('PASSWORD123')); // 无小写、特殊字符
        $this->assertFalse($this->service->isStrongPassword('Pass1!')); // 太短
        $this->assertFalse($this->service->isStrongPassword('')); // 空
    }
    
    /**
     * 测试用户名验证
     */
    public function testUsernameValidation(): void
    {
        $this->assertTrue($this->service->isValidUsername('user123'));
        $this->assertTrue($this->service->isValidUsername('test_user'));
        $this->assertTrue($this->service->isValidUsername('user-name'));
        $this->assertTrue($this->service->isValidUsername('username'));
        
        $this->assertFalse($this->service->isValidUsername('ab')); // 太短
        $this->assertFalse($this->service->isValidUsername('user name')); // 包含空格
        $this->assertFalse($this->service->isValidUsername('user@name')); // 包含特殊字符
        $this->assertFalse($this->service->isValidUsername(''));
    }
    
    /**
     * 测试验证码生成
     */
    public function testGenerateVerificationCode(): void
    {
        $code1 = $this->service->generateVerificationCode();
        $code2 = $this->service->generateVerificationCode();
        
        $this->assertEquals(6, strlen($code1));
        $this->assertTrue(ctype_digit($code1));
        $this->assertNotEquals($code1, $code2);
    }
    
    /**
     * 测试生成不同长度验证码
     */
    public function testGenerateVerificationCodeWithLength(): void
    {
        $code = $this->service->generateVerificationCode(4);
        $this->assertEquals(4, strlen($code));
        
        $code = $this->service->generateVerificationCode(8);
        $this->assertEquals(8, strlen($code));
    }
    
    /**
     * 测试 Session Token 生成
     */
    public function testGenerateSessionToken(): void
    {
        $token1 = $this->service->generateSessionToken();
        $token2 = $this->service->generateSessionToken();
        
        $this->assertNotEmpty($token1);
        $this->assertEquals(64, strlen($token1));
        $this->assertNotEquals($token1, $token2);
    }
    
    /**
     * 测试密码重置 Token 生成
     */
    public function testGenerateResetToken(): void
    {
        $token = $this->service->generateResetToken();
        
        $this->assertNotEmpty($token);
        $this->assertGreaterThanOrEqual(32, strlen($token));
    }
    
    /**
     * 测试登录失败计数
     */
    public function testLoginAttemptTracking(): void
    {
        $identifier = 'test_user_' . time();
        
        // 初始应该允许登录
        $this->assertTrue($this->service->canAttemptLogin($identifier));
        
        // 记录失败尝试
        $this->service->recordFailedAttempt($identifier);
        $this->assertTrue($this->service->canAttemptLogin($identifier));
        
        // 多次失败后应该锁定
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordFailedAttempt($identifier);
        }
        
        $this->assertFalse($this->service->canAttemptLogin($identifier));
        
        // 清除尝试记录
        $this->service->clearLoginAttempts($identifier);
        $this->assertTrue($this->service->canAttemptLogin($identifier));
    }
    
    /**
     * 测试账户锁定状态
     */
    public function testAccountLockStatus(): void
    {
        $userId = 999999;
        
        $this->assertFalse($this->service->isAccountLocked($userId));
        
        $this->service->lockAccount($userId, 60);
        $this->assertTrue($this->service->isAccountLocked($userId));
        
        $this->service->unlockAccount($userId);
        $this->assertFalse($this->service->isAccountLocked($userId));
    }
}
