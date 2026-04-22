<?php
/**
 * CodeVault - IntegrationService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class IntegrationServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\IntegrationService();
    }
    
    public function testIntegrationTypes(): void
    {
        $validTypes = ['slack', 'discord', 'dingtalk', 'wechat', 'email', 'jira', 'trello'];
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidType($type));
        }
        $this->assertFalse($this->service->isValidType('invalid'));
    }
    
    public function testWebhookUrlValidation(): void
    {
        $this->assertTrue($this->service->isValidWebhookUrl('https://hooks.slack.com/services/xxx'));
        $this->assertTrue($this->service->isValidWebhookUrl('https://discord.com/api/webhooks/xxx'));
        
        $this->assertFalse($this->service->isValidWebhookUrl('not-a-url'));
        $this->assertFalse($this->service->isValidWebhookUrl(''));
    }
    
    public function testEventSubscription(): void
    {
        $events = ['push', 'pull_request', 'issue', 'release'];
        
        $this->assertTrue($this->service->areValidEvents($events));
        
        $invalidEvents = ['push', 'invalid_event'];
        $this->assertFalse($this->service->areValidEvents($invalidEvents));
    }
    
    public function testConnectionTest(): void
    {
        $config = [
            'type' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/services/test',
        ];
        
        $result = $this->service->testConnection($config);
        $this->assertArrayHasKey('success', $result);
    }
    
    public function testRateLimiting(): void
    {
        $integrationId = 1;
        
        $this->assertTrue($this->service->canSend($integrationId));
        
        // 模拟达到限制
        for ($i = 0; $i < 100; $i++) {
            $this->service->recordSend($integrationId);
        }
        
        $this->assertFalse($this->service->canSend($integrationId));
    }
    
    public function testPayloadTransformation(): void
    {
        $event = 'push';
        $data = ['ref' => 'refs/heads/main', 'commits' => []];
        
        $payload = $this->service->transformPayload('slack', $event, $data);
        
        $this->assertArrayHasKey('text', $payload);
    }
    
    public function testIntegrationStatus(): void
    {
        $integration = ['active' => true, 'last_error' => null];
        $this->assertTrue($this->service->isHealthy($integration));
        
        $integration['last_error'] = 'Connection failed';
        $this->assertFalse($this->service->isHealthy($integration));
    }
}
