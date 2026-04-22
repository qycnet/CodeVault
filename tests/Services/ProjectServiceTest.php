<?php
/**
 * CodeVault - ProjectService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class ProjectServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\ProjectService();
    }
    
    public function testProjectNameValidation(): void
    {
        $this->assertTrue($this->service->isValidName('My Project'));
        $this->assertTrue($this->service->isValidName('Project-2024'));
        
        $this->assertFalse($this->service->isValidName(''));
        $this->assertFalse($this->service->isValidName('ab'));
        $this->assertFalse($this->service->isValidName(str_repeat('a', 300)));
    }
    
    public function testProjectStatus(): void
    {
        $validStatuses = ['open', 'closed', 'archived'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status));
        }
        $this->assertFalse($this->service->isValidStatus('invalid'));
    }
    
    public function testColumnValidation(): void
    {
        $this->assertTrue($this->service->isValidColumnName('To Do'));
        $this->assertTrue($this->service->isValidColumnName('In Progress'));
        
        $this->assertFalse($this->service->isValidColumnName(''));
        $this->assertFalse($this->service->isValidColumnName(str_repeat('a', 300)));
    }
    
    public function testCardValidation(): void
    {
        $card = [
            'content' => 'Task description',
            'type' => 'issue',
        ];
        
        $this->assertTrue($this->service->isValidCard($card));
        
        $invalidCard = ['content' => ''];
        $this->assertFalse($this->service->isValidCard($invalidCard));
    }
    
    public function testCardLimit(): void
    {
        $this->assertTrue($this->service->isValidCardCount(100));
        $this->assertFalse($this->service->isValidCardCount(10000));
    }
    
    public function testColumnLimit(): void
    {
        $this->assertTrue($this->service->isValidColumnCount(10));
        $this->assertFalse($this->service->isValidColumnCount(100));
    }
    
    public function testProgressCalculation(): void
    {
        $columns = [
            ['name' => 'To Do', 'cards' => 5],
            ['name' => 'Done', 'cards' => 3],
        ];
        
        $progress = $this->service->calculateProgress($columns);
        
        $this->assertEquals(37.5, $progress);
    }
    
    public function testAutomationRules(): void
    {
        $rule = [
            'trigger' => 'card_moved',
            'action' => 'set_due_date',
            'conditions' => ['column' => 'In Progress'],
        ];
        
        $this->assertTrue($this->service->isValidAutomationRule($rule));
    }
}
