<?php
/**
 * CodeVault - QueueService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class QueueServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\QueueService();
    }
    
    public function testQueueNameValidation(): void
    {
        $this->assertTrue($this->service->isValidQueueName('default'));
        $this->assertTrue($this->service->isValidQueueName('high-priority'));
        $this->assertTrue($this->service->isValidQueueName('email_queue'));
        
        $this->assertFalse($this->service->isValidQueueName(''));
        $this->assertFalse($this->service->isValidQueueName('queue name'));
    }
    
    public function testJobValidation(): void
    {
        $job = [
            'type' => 'send_email',
            'payload' => ['to' => 'test@example.com'],
            'priority' => 1,
        ];
        
        $this->assertTrue($this->service->isValidJob($job));
        
        $invalidJob = ['type' => ''];
        $this->assertFalse($this->service->isValidJob($invalidJob));
    }
    
    public function testPriorityRange(): void
    {
        $this->assertTrue($this->service->isValidPriority(1));
        $this->assertTrue($this->service->isValidPriority(10));
        
        $this->assertFalse($this->service->isValidPriority(0));
        $this->assertFalse($this->service->isValidPriority(11));
    }
    
    public function testDelayValidation(): void
    {
        $this->assertTrue($this->service->isValidDelay(0));
        $this->assertTrue($this->service->isValidDelay(3600));
        
        $this->assertFalse($this->service->isValidDelay(-1));
        $this->assertFalse($this->service->isValidDelay(86401)); // > 24 hours
    }
    
    public function testRetryLimit(): void
    {
        $this->assertTrue($this->service->canRetry(3));
        $this->assertFalse($this->service->canRetry(6));
    }
    
    public function testQueueStats(): void
    {
        $stats = $this->service->getStats('default');
        
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('failed', $stats);
    }
    
    public function testJobTimeout(): void
    {
        $this->assertEquals(60, $this->service->getDefaultTimeout());
        $this->assertEquals(60, $this->service->validateTimeout(0));
        $this->assertEquals(60, $this->service->validateTimeout(1000));
    }
    
    public function testDeadLetterQueue(): void
    {
        $failedJob = ['id' => 1, 'error' => 'Connection timeout'];
        
        $this->assertTrue($this->service->shouldMoveToDeadLetter($failedJob, 5));
    }
}
