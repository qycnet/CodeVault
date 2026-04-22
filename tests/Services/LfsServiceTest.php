<?php
/**
 * CodeVault - LfsService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class LfsServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\LfsService();
    }
    
    public function testOidValidation(): void
    {
        $validOid = str_repeat('a', 64);
        $this->assertTrue($this->service->isValidOid($validOid));
        
        $this->assertFalse($this->service->isValidOid(''));
        $this->assertFalse($this->service->isValidOid('invalid'));
        $this->assertFalse($this->service->isValidOid(str_repeat('a', 63)));
    }
    
    public function testSizeValidation(): void
    {
        $this->assertTrue($this->service->isValidSize(1024));
        $this->assertTrue($this->service->isValidSize(1073741824)); // 1GB
        
        $this->assertFalse($this->service->isValidSize(0));
        $this->assertFalse($this->service->isValidSize(-1));
        $this->assertFalse($this->service->isValidSize(107374182401)); // > 100GB
    }
    
    public function testLockPathValidation(): void
    {
        $this->assertTrue($this->service->isValidLockPath('assets/image.png'));
        $this->assertTrue($this->service->isValidLockPath('data/large-file.bin'));
        
        $this->assertFalse($this->service->isValidLockPath(''));
        $this->assertFalse($this->service->isValidLockPath('../../../etc/passwd'));
    }
    
    public function testOperationValidation(): void
    {
        $validOps = ['upload', 'download', 'verify'];
        foreach ($validOps as $op) {
            $this->assertTrue($this->service->isValidOperation($op));
        }
        $this->assertFalse($this->service->isValidOperation('invalid'));
    }
    
    public function testBatchRequest(): void
    {
        $objects = [
            ['oid' => str_repeat('a', 64), 'size' => 1024],
            ['oid' => str_repeat('b', 64), 'size' => 2048],
        ];
        
        $this->assertTrue($this->service->validateBatchRequest($objects));
        
        $invalidObjects = [
            ['oid' => 'invalid', 'size' => 1024],
        ];
        $this->assertFalse($this->service->validateBatchRequest($invalidObjects));
    }
    
    public function testTransferAdapters(): void
    {
        $adapters = $this->service->getSupportedTransferAdapters();
        
        $this->assertContains('basic', $adapters);
    }
    
    public function testLockOwnership(): void
    {
        $lock = ['user_id' => 1, 'path' => 'test.bin'];
        
        $this->assertTrue($this->service->isOwner($lock, 1));
        $this->assertFalse($this->service->isOwner($lock, 2));
    }
    
    public function testStorageQuota(): void
    {
        $quota = $this->service->getQuota(1);
        
        $this->assertArrayHasKey('used', $quota);
        $this->assertArrayHasKey('limit', $quota);
    }
}
