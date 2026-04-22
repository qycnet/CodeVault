<?php
/**
 * CodeVault - IssueService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\IssueService;

class IssueServiceTest extends TestCase
{
    private IssueService $service;
    
    protected function setUp(): void
    {
        $this->service = new IssueService();
    }
    
    /**
     * 测试状态验证
     */
    public function testValidStatuses(): void
    {
        $this->assertTrue($this->service->isValidStatus('open'));
        $this->assertTrue($this->service->isValidStatus('closed'));
        $this->assertTrue($this->service->isValidStatus('in_progress'));
        
        $this->assertFalse($this->service->isValidStatus('invalid'));
        $this->assertFalse($this->service->isValidStatus(''));
        $this->assertFalse($this->service->isValidStatus('OPEN'));
    }
    
    /**
     * 测试优先级验证
     */
    public function testValidPriorities(): void
    {
        $this->assertTrue($this->service->isValidPriority('low'));
        $this->assertTrue($this->service->isValidPriority('medium'));
        $this->assertTrue($this->service->isValidPriority('high'));
        $this->assertTrue($this->service->isValidPriority('critical'));
        
        $this->assertFalse($this->service->isValidPriority('invalid'));
        $this->assertFalse($this->service->isValidPriority(''));
    }
    
    /**
     * 测试标题验证
     */
    public function testTitleValidation(): void
    {
        $this->assertTrue($this->service->isValidTitle('这是一个有效的标题'));
        $this->assertTrue($this->service->isValidTitle('Valid title'));
        $this->assertTrue($this->service->isValidTitle('Bug: Something is broken'));
        
        $this->assertFalse($this->service->isValidTitle('')); // 空
        $this->assertFalse($this->service->isValidTitle('ab')); // 太短
        $this->assertFalse($this->service->isValidTitle(str_repeat('a', 300))); // 太长
    }
    
    /**
     * 测试内容验证
     */
    public function testContentValidation(): void
    {
        $this->assertTrue($this->service->isValidContent('这是一个有效的内容描述'));
        $this->assertTrue($this->service->isValidContent('Valid content'));
        
        $this->assertFalse($this->service->isValidContent('')); // 空
        $this->assertFalse($this->service->isValidContent('ab')); // 太短
    }
    
    /**
     * 测试标签验证
     */
    public function testLabelValidation(): void
    {
        $this->assertTrue($this->service->isValidLabel('bug'));
        $this->assertTrue($this->service->isValidLabel('enhancement'));
        $this->assertTrue($this->service->isValidLabel('documentation'));
        $this->assertTrue($this->service->isValidLabel('help wanted'));
        
        $this->assertFalse($this->service->isValidLabel('')); // 空
        $this->assertFalse($this->service->isValidLabel(str_repeat('a', 60))); // 太长
    }
    
    /**
     * 测试状态转换
     */
    public function testStatusTransitions(): void
    {
        $this->assertTrue($this->service->canTransition('open', 'closed'));
        $this->assertTrue($this->service->canTransition('open', 'in_progress'));
        $this->assertTrue($this->service->canTransition('in_progress', 'closed'));
        $this->assertTrue($this->service->canTransition('closed', 'open')); // 重新打开
        
        $this->assertFalse($this->service->canTransition('closed', 'in_progress'));
    }
    
    /**
     * 测试排序选项
     */
    public function testValidSortOptions(): void
    {
        $this->assertTrue($this->service->isValidSortOption('created'));
        $this->assertTrue($this->service->isValidSortOption('updated'));
        $this->assertTrue($this->service->isValidSortOption('comments'));
        $this->assertTrue($this->service->isValidSortOption('priority'));
        
        $this->assertFalse($this->service->isValidSortOption('invalid'));
    }
    
    /**
     * 测试过滤条件
     */
    public function testFilterValidation(): void
    {
        $filters = [
            'status' => 'open',
            'priority' => 'high',
            'label' => 'bug',
        ];
        
        $this->assertTrue($this->service->areValidFilters($filters));
        
        $invalidFilters = [
            'status' => 'invalid_status',
        ];
        
        $this->assertFalse($this->service->areValidFilters($invalidFilters));
    }
    
    /**
     * 测试分页参数
     */
    public function testPaginationValidation(): void
    {
        $this->assertEquals(1, $this->service->validatePage(1));
        $this->assertEquals(10, $this->service->validatePage(10));
        $this->assertEquals(1, $this->service->validatePage(0)); // 最小为 1
        $this->assertEquals(1, $this->service->validatePage(-1)); // 负数转为 1
        
        $this->assertEquals(20, $this->service->validatePerPage(20));
        $this->assertEquals(100, $this->service->validatePerPage(200)); // 最大 100
        $this->assertEquals(10, $this->service->validatePerPage(0)); // 默认 10
    }
}
