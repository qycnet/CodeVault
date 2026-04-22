<?php
/**
 * CodeVault - NotificationService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\NotificationService;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    
    protected function setUp(): void
    {
        $this->service = new NotificationService();
    }
    
    /**
     * 测试通知类型验证
     */
    public function testValidNotificationTypes(): void
    {
        $validTypes = ['email', 'web', 'push', 'sms', 'slack', 'dingtalk'];
        
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidType($type), "Type '$type' should be valid");
        }
        
        $this->assertFalse($this->service->isValidType('invalid'));
        $this->assertFalse($this->service->isValidType(''));
    }
    
    /**
     * 测试优先级验证
     */
    public function testValidPriorities(): void
    {
        $this->assertTrue($this->service->isValidPriority('low'));
        $this->assertTrue($this->service->isValidPriority('normal'));
        $this->assertTrue($this->service->isValidPriority('high'));
        $this->assertTrue($this->service->isValidPriority('urgent'));
        
        $this->assertFalse($this->service->isValidPriority('invalid'));
    }
    
    /**
     * 测试标题验证
     */
    public function testTitleValidation(): void
    {
        $this->assertTrue($this->service->isValidTitle('New notification'));
        $this->assertTrue($this->service->isValidTitle('Issue #123 has been updated'));
        
        $this->assertFalse($this->service->isValidTitle(''));
        $this->assertFalse($this->service->isValidTitle(str_repeat('a', 300)));
    }
    
    /**
     * 测试内容验证
     */
    public function testContentValidation(): void
    {
        $this->assertTrue($this->service->isValidContent('This is a notification message'));
        $this->assertTrue($this->service->isValidContent('Short'));
        
        $this->assertFalse($this->service->isValidContent(''));
        $this->assertFalse($this->service->isValidContent(str_repeat('a', 10001)));
    }
    
    /**
     * 测试邮件地址验证
     */
    public function testEmailValidation(): void
    {
        $this->assertTrue($this->service->isValidEmail('user@example.com'));
        $this->assertTrue($this->service->isValidEmail('test.user@domain.org'));
        
        $this->assertFalse($this->service->isValidEmail('invalid'));
        $this->assertFalse($this->service->isValidEmail('test@'));
    }
    
    /**
     * 测试通知模板
     */
    public function testNotificationTemplate(): void
    {
        $template = $this->service->getTemplate('issue_created');
        
        $this->assertArrayHasKey('title', $template);
        $this->assertArrayHasKey('body', $template);
    }
    
    /**
     * 测试模板变量替换
     */
    public function testTemplateVariableReplacement(): void
    {
        $template = 'Hello {username}, your issue #{issue_id} has been created.';
        $variables = [
            'username' => 'testuser',
            'issue_id' => '123',
        ];
        
        $result = $this->service->renderTemplate($template, $variables);
        
        $this->assertEquals('Hello testuser, your issue #123 has been created.', $result);
    }
    
    /**
     * 测试通知分组
     */
    public function testNotificationGrouping(): void
    {
        $notifications = [
            ['type' => 'issue', 'created_at' => '2024-01-01 10:00:00'],
            ['type' => 'issue', 'created_at' => '2024-01-01 10:05:00'],
            ['type' => 'pr', 'created_at' => '2024-01-01 11:00:00'],
        ];
        
        $grouped = $this->service->groupByType($notifications);
        
        $this->assertCount(2, $grouped['issue']);
        $this->assertCount(1, $grouped['pr']);
    }
    
    /**
     * 测试通知去重
     */
    public function testNotificationDeduplication(): void
    {
        $notifications = [
            ['id' => 1, 'type' => 'issue'],
            ['id' => 2, 'type' => 'pr'],
            ['id' => 1, 'type' => 'issue'], // 重复
        ];
        
        $deduplicated = $this->service->deduplicate($notifications);
        
        $this->assertCount(2, $deduplicated);
    }
    
    /**
     * 测试通知过期检查
     */
    public function testNotificationExpiry(): void
    {
        $notification = [
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'expires_in' => 3600, // 1 小时
        ];
        
        $this->assertFalse($this->service->isExpired($notification));
        
        $notification['created_at'] = date('Y-m-d H:i:s', strtotime('-2 hours'));
        $this->assertTrue($this->service->isExpired($notification));
    }
    
    /**
     * 测试通知频率限制
     */
    public function testNotificationRateLimit(): void
    {
        $userId = 999999;
        
        $this->assertTrue($this->service->canSendNotification($userId));
        
        // 模拟发送多次通知
        for ($i = 0; $i < 100; $i++) {
            $this->service->recordNotificationSent($userId);
        }
        
        $this->assertFalse($this->service->canSendNotification($userId));
    }
    
    /**
     * 测试通知渠道选择
     */
    public function testChannelSelection(): void
    {
        $userPreferences = ['email', 'web'];
        
        $channels = $this->service->getEnabledChannels($userPreferences);
        
        $this->assertContains('email', $channels);
        $this->assertContains('web', $channels);
        $this->assertNotContains('sms', $channels);
    }
}
