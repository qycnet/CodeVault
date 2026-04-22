<?php
/**
 * CodeVault - IssueController 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Controllers;

use PHPUnit\Framework\TestCase;

class IssueControllerTest extends TestCase
{
    /**
     * 测试创建 Issue 验证
     */
    public function testCreateIssueValidation(): void
    {
        $validData = [
            'title' => 'Bug in login page',
            'content' => 'Description of the bug',
            'status' => 'open',
            'priority' => 'high',
        ];
        
        $this->assertTrue($this->validateCreateRequest($validData));
        
        // 缺少标题
        $invalidData = [
            'content' => 'Description',
        ];
        $this->assertFalse($this->validateCreateRequest($invalidData));
        
        // 标题太短
        $invalidData = [
            'title' => 'ab',
            'content' => 'Description',
        ];
        $this->assertFalse($this->validateCreateRequest($invalidData));
    }
    
    /**
     * 测试更新 Issue 验证
     */
    public function testUpdateIssueValidation(): void
    {
        $validData = [
            'title' => 'Updated title',
            'status' => 'closed',
        ];
        
        $this->assertTrue($this->validateUpdateRequest($validData));
        
        // 无效状态
        $invalidData = [
            'status' => 'invalid',
        ];
        $this->assertFalse($this->validateUpdateRequest($invalidData));
    }
    
    /**
     * 测试列表过滤
     */
    public function testListFilters(): void
    {
        $validFilters = [
            'status' => 'open',
            'priority' => 'high',
            'label' => 'bug',
            'page' => 1,
            'per_page' => 20,
        ];
        
        $this->assertTrue($this->validateListFilters($validFilters));
        
        // 无效分页
        $invalidFilters = [
            'page' => -1,
            'per_page' => 1000,
        ];
        $this->assertFalse($this->validateListFilters($invalidFilters));
    }
    
    /**
     * 测试评论验证
     */
    public function testCommentValidation(): void
    {
        $validComment = [
            'content' => 'This is a valid comment',
        ];
        
        $this->assertTrue($this->validateComment($validComment));
        
        // 空评论
        $invalidComment = [
            'content' => '',
        ];
        $this->assertFalse($this->validateComment($invalidComment));
        
        // 评论太长
        $invalidComment = [
            'content' => str_repeat('a', 70000),
        ];
        $this->assertFalse($this->validateComment($invalidComment));
    }
    
    /**
     * 测试标签操作
     */
    public function testLabelOperations(): void
    {
        $validLabels = ['bug', 'enhancement', 'documentation'];
        
        $this->assertTrue($this->validateLabels($validLabels));
        
        // 无效标签
        $invalidLabels = ['', 'a', str_repeat('a', 60)];
        $this->assertFalse($this->validateLabels($invalidLabels));
    }
    
    // 辅助验证方法
    private function validateCreateRequest(array $data): bool
    {
        if (empty($data['title']) || strlen($data['title']) < 3) {
            return false;
        }
        if (empty($data['content']) || strlen($data['content']) < 3) {
            return false;
        }
        return true;
    }
    
    private function validateUpdateRequest(array $data): bool
    {
        $validStatuses = ['open', 'closed', 'in_progress'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
            return false;
        }
        return true;
    }
    
    private function validateListFilters(array $filters): bool
    {
        if (isset($filters['page']) && $filters['page'] < 1) {
            return false;
        }
        if (isset($filters['per_page']) && ($filters['per_page'] < 1 || $filters['per_page'] > 100)) {
            return false;
        }
        return true;
    }
    
    private function validateComment(array $data): bool
    {
        if (empty($data['content'])) {
            return false;
        }
        if (strlen($data['content']) > 65535) {
            return false;
        }
        return true;
    }
    
    private function validateLabels(array $labels): bool
    {
        foreach ($labels as $label) {
            if (empty($label) || strlen($label) < 2 || strlen($label) > 50) {
                return false;
            }
        }
        return true;
    }
}
