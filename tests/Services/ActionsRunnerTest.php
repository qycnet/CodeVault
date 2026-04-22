<?php
/**
 * CodeVault - ActionsRunner 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class ActionsRunnerTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\ActionsRunner();
    }
    
    public function testValidWorkflowTrigger(): void
    {
        $validTriggers = ['push', 'pull_request', 'schedule', 'workflow_dispatch', 'release'];
        foreach ($validTriggers as $trigger) {
            $this->assertTrue($this->service->isValidTrigger($trigger));
        }
        $this->assertFalse($this->service->isValidTrigger('invalid'));
    }
    
    public function testWorkflowValidation(): void
    {
        $validWorkflow = [
            'name' => 'CI',
            'on' => ['push'],
            'jobs' => [
                'build' => [
                    'runs-on' => 'ubuntu-latest',
                    'steps' => [['run' => 'echo test']],
                ],
            ],
        ];
        
        $this->assertTrue($this->service->validateWorkflow($validWorkflow));
        
        $invalidWorkflow = ['name' => ''];
        $this->assertFalse($this->service->validateWorkflow($invalidWorkflow));
    }
    
    public function testRunnerLabels(): void
    {
        $labels = ['ubuntu-latest', 'windows-latest', 'macos-latest', 'self-hosted'];
        foreach ($labels as $label) {
            $this->assertTrue($this->service->isValidRunnerLabel($label));
        }
        $this->assertFalse($this->service->isValidRunnerLabel('invalid-runner'));
    }
    
    public function testJobStatus(): void
    {
        $validStatuses = ['queued', 'in_progress', 'completed', 'failed', 'cancelled'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidJobStatus($status));
        }
    }
    
    public function testStepOutputValidation(): void
    {
        $output = ['key' => 'value', 'number' => '123'];
        $this->assertTrue($this->service->validateStepOutput($output));
        
        $invalidOutput = [str_repeat('a', 100) => 'value'];
        $this->assertFalse($this->service->validateStepOutput($invalidOutput));
    }
    
    public function testConcurrencyControl(): void
    {
        $this->assertTrue($this->service->canRunConcurrently('test-group', 5));
        
        for ($i = 0; $i < 10; $i++) {
            $this->service->registerRun('test-group');
        }
        
        $this->assertFalse($this->service->canRunConcurrently('test-group', 5));
    }
    
    public function testSecretMasking(): void
    {
        $secrets = ['SECRET_TOKEN' => 'super-secret-value'];
        $output = 'The token is super-secret-value and should be masked';
        
        $masked = $this->service->maskSecrets($output, $secrets);
        $this->assertStringNotContainsString('super-secret-value', $masked);
        $this->assertStringContainsString('***', $masked);
    }
    
    public function testTimeoutValidation(): void
    {
        $this->assertEquals(3600, $this->service->validateTimeout(3600));
        $this->assertEquals(360, $this->service->validateTimeout(0)); // 默认 6 小时
        $this->assertEquals(360, $this->service->validateTimeout(-1)); // 负数使用默认
        $this->assertEquals(360, $this->service->validateTimeout(100000)); // 最大 6 小时
    }
}
