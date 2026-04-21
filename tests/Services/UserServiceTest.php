<?php
/**
 * CodeVault - 用户服务测试
 */

require_once __DIR__ . '/BaseTestCase.php';

use CodeVault\Services\UserService;
use CodeVault\Services\Session;

class UserServiceTest extends BaseTestCase
{
    private UserService $userService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
    }
    
    /**
     * 测试用户注册
     */
    public function testRegister(): void
    {
        $data = [
            'username' => 'newuser_' . uniqid(),
            'email' => 'newuser_' . uniqid() . '@test.com',
            'password' => 'SecurePass123!',
        ];
        
        $result = $this->userService->register($data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'username', 'email'], $result);
        $this->assertEquals($data['username'], $result['username']);
        $this->assertEquals($data['email'], $result['email']);
    }
    
    /**
     * 测试用户注册 - 用户名已存在
     */
    public function testRegisterDuplicateUsername(): void
    {
        $user = $this->createTestUser();
        
        $this->expectException(Exception::class);
        
        $this->userService->register([
            'username' => $user['username'],
            'email' => 'different@test.com',
            'password' => 'SecurePass123!',
        ]);
    }
    
    /**
     * 测试用户注册 - 邮箱已存在
     */
    public function testRegisterDuplicateEmail(): void
    {
        $user = $this->createTestUser();
        
        $this->expectException(Exception::class);
        
        $this->userService->register([
            'username' => 'differentuser',
            'email' => $user['email'],
            'password' => 'SecurePass123!',
        ]);
    }
    
    /**
     * 测试用户登录
     */
    public function testLogin(): void
    {
        $user = $this->createTestUser(['password' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12])]);
        
        $result = $this->userService->login($user['username'], 'password123');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'username', 'email', 'token'], $result);
        $this->assertEquals($user['id'], $result['id']);
    }
    
    /**
     * 测试用户登录 - 错误密码
     */
    public function testLoginWrongPassword(): void
    {
        $user = $this->createTestUser();
        
        $this->expectException(Exception::class);
        
        $this->userService->login($user['username'], 'wrongpassword');
    }
    
    /**
     * 测试用户登录 - 用户不存在
     */
    public function testLoginUserNotFound(): void
    {
        $this->expectException(Exception::class);
        
        $this->userService->login('nonexistent', 'password123');
    }
    
    /**
     * 测试获取用户信息
     */
    public function testGetUser(): void
    {
        $user = $this->createTestUser();
        
        $result = $this->userService->getUser($user['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($user['id'], $result['id']);
        $this->assertEquals($user['username'], $result['username']);
    }
    
    /**
     * 测试更新用户信息
     */
    public function testUpdateUser(): void
    {
        $user = $this->createTestUser();
        
        $updateData = [
            'bio' => 'Updated bio',
            'location' => 'Beijing',
        ];
        
        $result = $this->userService->updateUser($user['id'], $updateData);
        
        $this->assertTrue($result);
        
        $updated = $this->userService->getUser($user['id']);
        $this->assertEquals($updateData['bio'], $updated['bio']);
        $this->assertEquals($updateData['location'], $updated['location']);
    }
    
    /**
     * 测试修改密码
     */
    public function testChangePassword(): void
    {
        $user = $this->createTestUser(['password' => password_hash('oldpassword', PASSWORD_BCRYPT, ['cost' => 12])]);
        
        $result = $this->userService->changePassword($user['id'], 'oldpassword', 'newpassword123');
        
        $this->assertTrue($result);
        
        // 验证新密码可以登录
        $loginResult = $this->userService->login($user['username'], 'newpassword123');
        $this->assertIsArray($loginResult);
    }
    
    /**
     * 测试修改密码 - 旧密码错误
     */
    public function testChangePasswordWrongOld(): void
    {
        $user = $this->createTestUser();
        
        $this->expectException(Exception::class);
        
        $this->userService->changePassword($user['id'], 'wrongoldpassword', 'newpassword123');
    }
    
    /**
     * 测试用户名验证
     */
    public function testValidateUsername(): void
    {
        // 有效用户名
        $this->assertTrue($this->userService->validateUsername('validuser'));
        $this->assertTrue($this->userService->validateUsername('user123'));
        $this->assertTrue($this->userService->validateUsername('user_name'));
        
        // 无效用户名
        $this->assertFalse($this->userService->validateUsername('ab')); // 太短
        $this->assertFalse($this->userService->validateUsername(str_repeat('a', 40))); // 太长
        $this->assertFalse($this->userService->validateUsername('user@name')); // 特殊字符
        $this->assertFalse($this->userService->validateUsername('123user')); // 数字开头
    }
    
    /**
     * 测试邮箱验证
     */
    public function testValidateEmail(): void
    {
        // 有效邮箱
        $this->assertTrue($this->userService->validateEmail('test@example.com'));
        $this->assertTrue($this->userService->validateEmail('user.name@domain.co.uk'));
        
        // 无效邮箱
        $this->assertFalse($this->userService->validateEmail('invalid'));
        $this->assertFalse($this->userService->validateEmail('user@'));
        $this->assertFalse($this->userService->validateEmail('@domain.com'));
    }
    
    /**
     * 测试密码强度验证
     */
    public function testValidatePassword(): void
    {
        // 有效密码
        $this->assertTrue($this->userService->validatePassword('SecurePass123!'));
        $this->assertTrue($this->userService->validatePassword('MyP@ssw0rd'));
        
        // 无效密码
        $this->assertFalse($this->userService->validatePassword('short')); // 太短
        $this->assertFalse($this->userService->validatePassword('alllowercase')); // 无大写
        $this->assertFalse($this->userService->validatePassword('ALLUPPERCASE')); // 无小写
        $this->assertFalse($this->userService->validatePassword('NoNumbers')); // 无数字
    }
    
    /**
     * 测试删除用户
     */
    public function testDeleteUser(): void
    {
        $user = $this->createTestUser();
        
        $result = $this->userService->deleteUser($user['id']);
        
        $this->assertTrue($result);
        
        // 验证用户已删除
        $deleted = $this->userService->getUser($user['id']);
        $this->assertNull($deleted);
    }
    
    /**
     * 测试用户列表
     */
    public function testListUsers(): void
    {
        // 创建多个测试用户
        $this->createTestUser();
        $this->createTestUser();
        $this->createTestUser();
        
        $result = $this->userService->listUsers(['page' => 1, 'per_page' => 10]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThanOrEqual(3, $result['total']);
    }
    
    /**
     * 测试搜索用户
     */
    public function testSearchUsers(): void
    {
        $user = $this->createTestUser(['username' => 'searchable_user_' . uniqid()]);
        
        $result = $this->userService->searchUsers('searchable', ['page' => 1, 'per_page' => 10]);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result['users']));
    }
}
