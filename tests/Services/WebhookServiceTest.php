<?php
/**
 * CodeVault - WebhookService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\WebhookService;

class WebhookServiceTest extends TestCase
{
    private WebhookService $service;
    
    protected function setUp(): void
    {
        $this->service = new WebhookService();
    }
    
    /**
     * 测试事件类型验证
     */
    public function testValidEventTypes(): void
    {
        $validEvents = ['push', 'pull_request', 'issues', 'release', 'create', 'delete', 'fork', 'star'];
        
        foreach ($validEvents as $event) {
            $this->assertTrue($this->service->isValidEventType($event), "Event type '$event' should be valid");
        }
        
        $this->assertFalse($this->service->isValidEventType('invalid'));
        $this->assertFalse($this->service->isValidEventType(''));
    }
    
    /**
     * 测试 URL 验证
     */
    public function testUrlValidation(): void
    {
        $this->assertTrue($this->service->isValidWebhookUrl('https://example.com/webhook'));
        $this->assertTrue($this->service->isValidWebhookUrl('http://localhost:8080/hook'));
        
        $this->assertFalse($this->service->isValidWebhookUrl('not-a-url'));
        $this->assertFalse($this->service->isValidWebhookUrl('ftp://invalid'));
        $this->assertFalse($this->service->isValidWebhookUrl(''));
    }
    
    /**
     * 测试 Secret 验证
     */
    public function testSecretValidation(): void
    {
        $this->assertTrue($this->service->isValidSecret('my-secret-key-123'));
        $this->assertTrue($this->service->isValidSecret('another_secret'));
        
        $this->assertFalse($this->service->isValidSecret('')); // 空
        $this->assertFalse($this->service->isValidSecret('ab')); // 太短
    }
    
    /**
     * 测试签名生成
     */
    public function testSignatureGeneration(): void
    {
        $payload = '{"test": "data"}';
        $secret = 'my-secret';
        
        $signature = $this->service->generateSignature($payload, $secret);
        
        $this->assertNotEmpty($signature);
        $this->assertStringStartsWith('sha256=', $signature);
        $this->assertEquals(71, strlen($signature)); // 'sha256=' + 64 hex chars
    }
    
    /**
     * 测试签名验证
     */
    public function testSignatureVerification(): void
    {
        $payload = '{"test": "data"}';
        $secret = 'my-secret';
        
        $signature = $this->service->generateSignature($payload, $secret);
        
        $this->assertTrue($this->service->verifySignature($payload, $signature, $secret));
        $this->assertFalse($this->service->verifySignature($payload, $signature, 'wrong-secret'));
        $this->assertFalse($this->service->verifySignature('{"different": "payload"}', $signature, $secret));
    }
    
    /**
     * 测试重试逻辑
     */
    public function testRetryLogic(): void
    {
        $this->assertEquals(0, $this->service->calculateRetryDelay(0));
        $this->assertEquals(1, $this->service->calculateRetryDelay(1));
        $this->assertEquals(2, $this->service->calculateRetryDelay(2));
        $this->assertEquals(4, $this->service->calculateRetryDelay(3));
        $this->assertEquals(8, $this->service->calculateRetryDelay(4));
        $this->assertEquals(16, $this->service->calculateRetryDelay(5));
        
        // 最大重试次数
        $this->assertFalse($this->service->shouldRetry(6));
        $this->assertTrue($this->service->shouldRetry(5));
    }
    
    /**
     * 测试 Payload 构建
     */
    public function testPayloadBuilding(): void
    {
        $event = 'push';
        $data = [
            'ref' => 'refs/heads/main',
            'repository' => ['name' => 'test-repo'],
            'sender' => ['login' => 'testuser'],
        ];
        
        $payload = $this->service->buildPayload($event, $data);
        
        $this->assertArrayHasKey('event', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertEquals($event, $payload['event']);
    }
    
    /**
     * 测试事件过滤
     */
    public function testEventFiltering(): void
    {
        $subscribedEvents = ['push', 'pull_request'];
        
        $this->assertTrue($this->service->shouldTrigger('push', $subscribedEvents));
        $this->assertTrue($this->service->shouldTrigger('pull_request', $subscribedEvents));
        $this->assertFalse($this->service->shouldTrigger('issues', $subscribedEvents));
        $this->assertFalse($this->service->shouldTrigger('release', $subscribedEvents));
    }
    
    /**
     * 测试投递状态
     */
    public function testDeliveryStatus(): void
    {
        $this->assertTrue($this->service->isSuccessfulDelivery(200));
        $this->assertTrue($this->service->isSuccessfulDelivery(201));
        $this->assertTrue($this->service->isSuccessfulDelivery(204));
        
        $this->assertFalse($this->service->isSuccessfulDelivery(400));
        $this->assertFalse($this->service->isSuccessfulDelivery(500));
        $this->assertFalse($this->service->isSuccessfulDelivery(404));
    }
    
    /**
     * 测试超时设置
     */
    public function testTimeoutSettings(): void
    {
        $this->assertEquals(30, $this->service->getDefaultTimeout());
        $this->assertEquals(10, $this->service->getConnectTimeout());
    }
    
    /**
     * 测试 Content-Type
     */
    public function testContentType(): void
    {
        $this->assertEquals('application/json', $this->service->getContentType());
    }
    
    /**
     * 测试 User-Agent
     */
    public function testUserAgent(): void
    {
        $userAgent = $this->service->getUserAgent();
        
        $this->assertNotEmpty($userAgent);
        $this->assertStringContainsString('CodeVault', $userAgent);
    }
}
