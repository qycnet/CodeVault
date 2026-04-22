<?php
/**
 * CodeVault - WikiService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class WikiServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\WikiService();
    }
    
    public function testPageTitleValidation(): void
    {
        $this->assertTrue($this->service->isValidTitle('Getting Started'));
        $this->assertTrue($this->service->isValidTitle('API-Reference'));
        
        $this->assertFalse($this->service->isValidTitle(''));
        $this->assertFalse($this->service->isValidTitle('ab'));
        $this->assertFalse($this->service->isValidTitle(str_repeat('a', 300)));
    }
    
    public function testSlugGeneration(): void
    {
        $slug = $this->service->generateSlug('Getting Started');
        
        $this->assertEquals('getting-started', $slug);
        
        $slug = $this->service->generateSlug('API Reference v2.0');
        $this->assertEquals('api-reference-v2-0', $slug);
    }
    
    public function testContentValidation(): void
    {
        $this->assertTrue($this->service->isValidContent('# Introduction\n\nThis is the wiki content'));
        $this->assertFalse($this->service->isValidContent(''));
        $this->assertFalse($this->service->isValidContent('ab'));
    }
    
    public function testPageStatus(): void
    {
        $validStatuses = ['active', 'deleted'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status));
        }
    }
    
    public function testHistoryLimit(): void
    {
        $this->assertEquals(100, $this->service->getHistoryLimit());
    }
    
    public function testPageLock(): void
    {
        $page = ['locked' => false, 'locked_by' => null];
        $this->assertFalse($this->service->isLocked($page));
        
        $page['locked'] = true;
        $page['locked_by'] = 1;
        $this->assertTrue($this->service->isLocked($page));
    }
    
    public function testLockExpiry(): void
    {
        $lock = [
            'locked_at' => time() - 3600, // 1 hour ago
            'lock_duration' => 1800, // 30 minutes
        ];
        
        $this->assertTrue($this->service->isLockExpired($lock));
        
        $lock['locked_at'] = time() - 300; // 5 minutes ago
        $this->assertFalse($this->service->isLockExpired($lock));
    }
    
    public function testDiffGeneration(): void
    {
        $oldContent = '# Title\n\nOld content';
        $newContent = '# Title\n\nNew content';
        
        $diff = $this->service->generateDiff($oldContent, $newContent);
        
        $this->assertNotEmpty($diff);
    }
}
