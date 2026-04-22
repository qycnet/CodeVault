<?php
/**
 * CodeVault - SecurityScanner 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class SecurityScannerTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\SecurityScanner();
    }
    
    public function testScanTypeValidation(): void
    {
        $validTypes = ['xss', 'sql_injection', 'csrf', 'path_traversal', 'command_injection'];
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidScanType($type));
        }
        $this->assertFalse($this->service->isValidScanType('invalid'));
    }
    
    public function testSeverityLevels(): void
    {
        $validLevels = ['info', 'low', 'medium', 'high', 'critical'];
        foreach ($validLevels as $level) {
            $this->assertTrue($this->service->isValidSeverity($level));
        }
    }
    
    public function testXssDetection(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $result = $this->service->scanForXss($malicious);
        
        $this->assertTrue($result['detected']);
        $this->assertEquals('high', $result['severity']);
        
        $safe = 'Normal text content';
        $result = $this->service->scanForXss($safe);
        $this->assertFalse($result['detected']);
    }
    
    public function testSqlInjectionDetection(): void
    {
        $malicious = "1' OR '1'='1";
        $result = $this->service->scanForSqlInjection($malicious);
        
        $this->assertTrue($result['detected']);
        
        $safe = 'normal search query';
        $result = $this->service->scanForSqlInjection($safe);
        $this->assertFalse($result['detected']);
    }
    
    public function testPathTraversalDetection(): void
    {
        $malicious = '../../../etc/passwd';
        $result = $this->service->scanForPathTraversal($malicious);
        
        $this->assertTrue($result['detected']);
        
        $safe = 'normal/path/file.txt';
        $result = $this->service->scanForPathTraversal($safe);
        $this->assertFalse($result['detected']);
    }
    
    public function testCommandInjectionDetection(): void
    {
        $malicious = '; rm -rf /';
        $result = $this->service->scanForCommandInjection($malicious);
        
        $this->assertTrue($result['detected']);
        
        $safe = 'normal command argument';
        $result = $this->service->scanForCommandInjection($safe);
        $this->assertFalse($result['detected']);
    }
    
    public function testScanReport(): void
    {
        $report = $this->service->generateReport(['xss', 'sql_injection']);
        
        $this->assertArrayHasKey('scan_time', $report);
        $this->assertArrayHasKey('findings', $report);
        $this->assertArrayHasKey('summary', $report);
    }
    
    public function testWhitelistManagement(): void
    {
        $finding = ['type' => 'xss', 'file' => 'test.php', 'line' => 10];
        
        $this->assertFalse($this->service->isWhitelisted($finding));
        
        $this->service->addToWhitelist($finding);
        $this->assertTrue($this->service->isWhitelisted($finding));
    }
}
