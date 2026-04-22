<?php
/**
 * CodeVault - CodeReviewService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class CodeReviewServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\CodeReviewService();
    }
    
    public function testReviewStatus(): void
    {
        $validStatuses = ['pending', 'approved', 'changes_requested', 'commented'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status));
        }
        $this->assertFalse($this->service->isValidStatus('invalid'));
    }
    
    public function testCommentTypeValidation(): void
    {
        $validTypes = ['line', 'file', 'commit'];
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidCommentType($type));
        }
    }
    
    public function testDiffPositionValidation(): void
    {
        $validPosition = [
            'file' => 'test.php',
            'line' => 10,
            'side' => 'RIGHT',
        ];
        
        $this->assertTrue($this->service->isValidPosition($validPosition));
        
        $invalidPosition = ['file' => ''];
        $this->assertFalse($this->service->isValidPosition($invalidPosition));
    }
    
    public function testReviewRequirement(): void
    {
        $config = [
            'required_reviewers' => 2,
            'dismiss_stale_reviews' => true,
            'require_code_owner_review' => true,
        ];
        
        $this->assertTrue($this->service->validateReviewConfig($config));
    }
    
    public function testAutoAssignment(): void
    {
        $reviewers = ['user1', 'user2', 'user3'];
        $exclude = ['user1'];
        
        $assigned = $this->service->autoAssignReviewers($reviewers, 2, $exclude);
        
        $this->assertCount(2, $assigned);
        $this->assertNotContains('user1', $assigned);
    }
    
    public function testReviewThread(): void
    {
        $thread = [
            'comments' => [
                ['body' => 'First comment'],
                ['body' => 'Reply'],
            ],
            'resolved' => false,
        ];
        
        $this->assertFalse($this->service->isThreadResolved($thread));
        
        $thread['resolved'] = true;
        $this->assertTrue($this->service->isThreadResolved($thread));
    }
    
    public function testCodeSuggestion(): void
    {
        $suggestion = [
            'start_line' => 10,
            'end_line' => 12,
            'suggestion' => 'new code here',
        ];
        
        $this->assertTrue($this->service->isValidSuggestion($suggestion));
    }
    
    public function testReviewSummary(): void
    {
        $reviews = [
            ['status' => 'approved', 'user_id' => 1],
            ['status' => 'changes_requested', 'user_id' => 2],
        ];
        
        $summary = $this->service->generateSummary($reviews);
        
        $this->assertArrayHasKey('approved', $summary);
        $this->assertArrayHasKey('changes_requested', $summary);
    }
}
