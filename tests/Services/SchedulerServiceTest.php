<?php
/**
 * CodeVault - SchedulerService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class SchedulerServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\SchedulerService();
    }
    
    public function testCronExpressionValidation(): void
    {
        $this->assertTrue($this->service->isValidCron('* * * * *'));
        $this->assertTrue($this->service->isValidCron('0 0 * * *'));
        $this->assertTrue($this->service->isValidCron('*/5 * * * *'));
        $this->assertTrue($this->service->isValidCron('0 9-17 * * 1-5'));
        
        $this->assertFalse($this->service->isValidCron(''));
        $this->assertFalse($this->service->isValidCron('invalid'));
    }
    
    public function testTaskNameValidation(): void
    {
        $this->assertTrue($this->service->isValidTaskName('daily_cleanup'));
        $this->assertTrue($this->service->isValidTaskName('send-notifications'));
        
        $this->assertFalse($this->service->isValidTaskName(''));
        $this->assertFalse($this->service->isValidTaskName(str_repeat('a', 300)));
    }
    
    public function testTaskStatus(): void
    {
        $validStatuses = ['pending', 'running', 'completed', 'failed', 'cancelled'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status));
        }
    }
    
    public function testNextRunCalculation(): void
    {
        $cron = '0 0 * * *'; // 每天午夜
        $nextRun = $this->service->calculateNextRun($cron);
        
        $this->assertNotEmpty($nextRun);
        $this->assertGreaterThan(time(), $nextRun);
    }
    
    public function testTaskLocking(): void
    {
        $taskId = 'test_task_' . time();
        
        $this->assertTrue($this->service->acquireLock($taskId, 300));
        $this->assertFalse($this->service->acquireLock($taskId, 300));
        
        $this->service->releaseLock($taskId);
        $this->assertTrue($this->service->acquireLock($taskId, 300));
        
        $this->service->releaseLock($taskId);
    }
    
    public function testTimeoutValidation(): void
    {
        $this->assertTrue($this->service->isValidTimeout(3600));
        $this->assertFalse($this->service->isValidTimeout(0));
        $this->assertFalse($this->service->isValidTimeout(86401));
    }
    
    public function testMaxRetries(): void
    {
        $this->assertEquals(3, $this->service->getDefaultMaxRetries());
        $this->assertTrue($this->service->isValidRetryCount(3));
        $this->assertFalse($this->service->isValidRetryCount(11));
    }
    
    public function testTaskHistory(): void
    {
        $history = $this->service->getTaskHistory('daily_cleanup', 10);
        
        $this->assertIsArray($history);
    }
}
