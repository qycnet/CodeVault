<?php
/**
 * CodeVault - DiscussionService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class DiscussionServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\DiscussionService();
    }
    
    public function testCategoryValidation(): void
    {
        $validCategories = ['general', 'ideas', 'q&a', 'announcements', 'showcase'];
        foreach ($validCategories as $category) {
            $this->assertTrue($this->service->isValidCategory($category));
        }
        $this->assertFalse($this->service->isValidCategory('invalid'));
    }
    
    public function testDiscussionStatus(): void
    {
        $validStatuses = ['open', 'closed', 'answered', 'solved'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status));
        }
    }
    
    public function testTitleValidation(): void
    {
        $this->assertTrue($this->service->isValidTitle('How to fix this bug?'));
        $this->assertTrue($this->service->isValidTitle('Feature request'));
        
        $this->assertFalse($this->service->isValidTitle(''));
        $this->assertFalse($this->service->isValidTitle('ab'));
        $this->assertFalse($this->service->isValidTitle(str_repeat('a', 300)));
    }
    
    public function testContentValidation(): void
    {
        $this->assertTrue($this->service->isValidContent('This is a discussion content'));
        $this->assertFalse($this->service->isValidContent(''));
        $this->assertFalse($this->service->isValidContent('ab'));
    }
    
    public function testAnswerMarking(): void
    {
        $discussion = [
            'id' => 1,
            'status' => 'open',
            'answers' => [],
        ];
        
        $this->assertFalse($this->service->hasAnswer($discussion));
        
        $discussion['answers'][] = ['id' => 1, 'is_answer' => true];
        $this->assertTrue($this->service->hasAnswer($discussion));
    }
    
    public function testReactionValidation(): void
    {
        $validReactions = ['+1', '-1', 'laugh', 'hooray', 'confused', 'heart', 'rocket', 'eyes'];
        foreach ($validReactions as $reaction) {
            $this->assertTrue($this->service->isValidReaction($reaction));
        }
    }
    
    public function testPinStatus(): void
    {
        $discussion = ['pinned' => false];
        $this->assertFalse($this->service->isPinned($discussion));
        
        $discussion['pinned'] = true;
        $this->assertTrue($this->service->isPinned($discussion));
    }
    
    public function testLockStatus(): void
    {
        $discussion = ['locked' => false];
        $this->assertFalse($this->service->isLocked($discussion));
        
        $discussion['locked'] = true;
        $this->assertTrue($this->service->isLocked($discussion));
    }
}
