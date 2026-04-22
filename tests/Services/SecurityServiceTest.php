<?php
/**
 * CodeVault - SecurityService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\SecurityService;

class SecurityServiceTest extends TestCase
{
    private SecurityService $service;
    
    protected function setUp(): void
    {
        $this->service = new SecurityService();
    }
    
    /**
     * 测试密码哈希
     */
    public function testPasswordHashing(): void
    {
        $password = 'TestPassword123!';
        $hash = $this->service->hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertEquals(60, strlen($hash));
        $this->assertStringStartsWith('$2y$', $hash);
    }
    
    /**
     * 测试密码验证
     */
    public function testPasswordVerification(): void
    {
        $password = 'TestPassword123!';
        $hash = $this->service->hashPassword($password);
        
        $this->assertTrue($this->service->verifyPassword($password, $hash));
        $this->assertFalse($this->service->verifyPassword('WrongPassword', $hash));
    }
    
    /**
     * 测试不同密码生成不同哈希
     */
    public function testDifferentPasswordsGenerateDifferentHashes(): void
    {
        $hash1 = $this->service->hashPassword('password1');
        $hash2 = $this->service->hashPassword('password2');
        
        $this->assertNotEquals($hash1, $hash2);
    }
    
    /**
     * 测试整数验证
     */
    public function testValidateInt(): void
    {
        $this->assertEquals(123, $this->service->validateInt('123'));
        $this->assertEquals(0, $this->service->validateInt('0'));
        $this->assertEquals(-10, $this->service->validateInt('-10'));
    }
    
    /**
     * 测试整数范围验证
     */
    public function testValidateIntWithRange(): void
    {
        $this->assertEquals(50, $this->service->validateInt('50', 1, 100));
        $this->assertNull($this->service->validateInt('200', 1, 100));
        $this->assertNull($this->service->validateInt('0', 1, 100));
    }
    
    /**
     * 测试无效整数
     */
    public function testValidateInvalidInt(): void
    {
        $this->assertNull($this->service->validateInt('abc'));
        $this->assertNull($this->service->validateInt('12.5'));
        $this->assertNull($this->service->validateInt(''));
    }
    
    /**
     * 测试邮箱验证
     */
    public function testValidateEmail(): void
    {
        $this->assertEquals('test@example.com', $this->service->validateEmail('test@example.com'));
        $this->assertEquals('USER@DOMAIN.COM', $this->service->validateEmail('USER@DOMAIN.COM'));
    }
    
    /**
     * 测试无效邮箱
     */
    public function testValidateInvalidEmail(): void
    {
        $this->assertFalse($this->service->validateEmail('invalid'));
        $this->assertFalse($this->service->validateEmail('test@'));
        $this->assertFalse($this->service->validateEmail('@domain.com'));
        $this->assertFalse($this->service->validateEmail(''));
    }
    
    /**
     * 测试用户名验证
     */
    public function testValidateUsername(): void
    {
        $this->assertTrue($this->service->validateUsername('user123'));
        $this->assertTrue($this->service->validateUsername('test_user'));
        $this->assertTrue($this->service->validateUsername('user-name'));
    }
    
    /**
     * 测试无效用户名
     */
    public function testValidateInvalidUsername(): void
    {
        $this->assertFalse($this->service->validateUsername('a')); // 太短
        $this->assertFalse($this->service->validateUsername('ab')); // 太短
        $this->assertFalse($this->service->validateUsername(''));
        $this->assertFalse($this->service->validateUsername('user name')); // 包含空格
    }
    
    /**
     * 测试 URL 验证
     */
    public function testValidateUrl(): void
    {
        $this->assertEquals('https://example.com', $this->service->validateUrl('https://example.com'));
        $this->assertEquals('http://test.org/path', $this->service->validateUrl('http://test.org/path'));
    }
    
    /**
     * 测试无效 URL
     */
    public function testValidateInvalidUrl(): void
    {
        $this->assertFalse($this->service->validateUrl('not-a-url'));
        $this->assertFalse($this->service->validateUrl('ftp://invalid'));
        $this->assertFalse($this->service->validateUrl(''));
    }
    
    /**
     * 测试字符串长度验证
     */
    public function testValidateStringLength(): void
    {
        $this->assertTrue($this->service->validateStringLength('hello', 1, 10));
        $this->assertTrue($this->service->validateStringLength('test', 4, 4));
        $this->assertFalse($this->service->validateStringLength('hi', 5, 10));
        $this->assertFalse($this->service->validateStringLength('too long string', 1, 5));
    }
    
    /**
     * 测试生成随机 Token
     */
    public function testGenerateToken(): void
    {
        $token1 = $this->service->generateToken();
        $token2 = $this->service->generateToken();
        
        $this->assertNotEmpty($token1);
        $this->assertEquals(64, strlen($token1));
        $this->assertNotEquals($token1, $token2);
    }
    
    /**
     * 测试生成指定长度 Token
     */
    public function testGenerateTokenWithLength(): void
    {
        $token = $this->service->generateToken(32);
        $this->assertEquals(64, strlen($token)); // hex 编码后长度翻倍
    }
    
    /**
     * 测试安全比较
     */
    public function testSecureCompare(): void
    {
        $this->assertTrue($this->service->secureCompare('abc', 'abc'));
        $this->assertFalse($this->service->secureCompare('abc', 'abd'));
        $this->assertFalse($this->service->secureCompare('abc', 'abcd'));
    }
}
