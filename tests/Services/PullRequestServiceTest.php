<?php
/**
 * CodeVault - PullRequestService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\PullRequestService;

class PullRequestServiceTest extends TestCase
{
    private PullRequestService $service;
    
    protected function setUp(): void
    {
        $this->service = new PullRequestService();
    }
    
    /**
     * 测试 PR 状态验证
     */
    public function testValidStatuses(): void
    {
        $this->assertTrue($this->service->isValidStatus('open'));
        $this->assertTrue($this->service->isValidStatus('closed'));
        $this->assertTrue($this->service->isValidStatus('merged'));
        $this->assertTrue($this->service->isValidStatus('draft'));
        
        $this->assertFalse($this->service->isValidStatus('invalid'));
        $this->assertFalse($this->service->isValidStatus(''));
    }
    
    /**
     * 测试合并策略验证
     */
    public function testValidMergeStrategies(): void
    {
        $this->assertTrue($this->service->isValidMergeStrategy('merge'));
        $this->assertTrue($this->service->isValidMergeStrategy('squash'));
        $this->assertTrue($this->service->isValidMergeStrategy('rebase'));
        
        $this->assertFalse($this->service->isValidMergeStrategy('invalid'));
        $this->assertFalse($this->service->isValidMergeStrategy(''));
    }
    
    /**
     * 测试标题验证
     */
    public function testTitleValidation(): void
    {
        $this->assertTrue($this->service->isValidTitle('Add new feature'));
        $this->assertTrue($this->service->isValidTitle('Fix: Bug in authentication'));
        $this->assertTrue($this->service->isValidTitle('feat: 新功能'));
        
        $this->assertFalse($this->service->isValidTitle(''));
        $this->assertFalse($this->service->isValidTitle('ab'));
        $this->assertFalse($this->service->isValidTitle(str_repeat('a', 300)));
    }
    
    /**
     * 测试分支名称验证
     */
    public function testBranchNameValidation(): void
    {
        $this->assertTrue($this->service->isValidBranchName('main'));
        $this->assertTrue($this->service->isValidBranchName('feature/new-feature'));
        $this->assertTrue($this->service->isValidBranchName('bugfix/issue-123'));
        $this->assertTrue($this->service->isValidBranchName('release/v1.0.0'));
        
        $this->assertFalse($this->service->isValidBranchName(''));
        $this->assertFalse($this->service->isValidBranchName('branch name')); // 包含空格
        $this->assertFalse($this->service->isValidBranchName('branch..name')); // 双点
    }
    
    /**
     * 测试合并条件检查
     */
    public function testMergeConditions(): void
    {
        // 模拟 PR 数据
        $pr = [
            'status' => 'open',
            'mergeable' => true,
            'merge_conflicts' => false,
            'required_reviews' => 2,
            'approved_reviews' => 2,
            'ci_status' => 'success',
        ];
        
        $this->assertTrue($this->service->canMerge($pr));
        
        // 有冲突
        $pr['merge_conflicts'] = true;
        $this->assertFalse($this->service->canMerge($pr));
        
        // CI 失败
        $pr['merge_conflicts'] = false;
        $pr['ci_status'] = 'failed';
        $this->assertFalse($this->service->canMerge($pr));
        
        // 审核不足
        $pr['ci_status'] = 'success';
        $pr['approved_reviews'] = 1;
        $this->assertFalse($this->service->canMerge($pr));
    }
    
    /**
     * 测试冲突检测
     */
    public function testConflictDetection(): void
    {
        $this->assertFalse($this->service->hasConflicts(false, []));
        $this->assertTrue($this->service->hasConflicts(true, []));
        $this->assertTrue($this->service->hasConflicts(false, ['file1.php', 'file2.php']));
    }
    
    /**
     * 测试审核状态计算
     */
    public function testReviewStatusCalculation(): void
    {
        $reviews = [
            ['status' => 'approved'],
            ['status' => 'approved'],
            ['status' => 'changes_requested'],
        ];
        
        $this->assertEquals('changes_requested', $this->service->calculateReviewStatus($reviews));
        
        $reviews = [
            ['status' => 'approved'],
            ['status' => 'approved'],
        ];
        
        $this->assertEquals('approved', $this->service->calculateReviewStatus($reviews));
        
        $reviews = [
            ['status' => 'commented'],
            ['status' => 'commented'],
        ];
        
        $this->assertEquals('pending', $this->service->calculateReviewStatus($reviews));
    }
    
    /**
     * 测试 Diff 统计
     */
    public function testDiffStats(): void
    {
        $diff = "@@ -1,5 +1,6 @@\n-old line\n+new line\n+another new line";
        
        $stats = $this->service->calculateDiffStats($diff);
        
        $this->assertArrayHasKey('additions', $stats);
        $this->assertArrayHasKey('deletions', $stats);
        $this->assertArrayHasKey('files_changed', $stats);
    }
    
    /**
     * 测试草稿 PR 检查
     */
    public function testDraftStatus(): void
    {
        $this->assertTrue($this->service->isDraft(['status' => 'draft']));
        $this->assertFalse($this->service->isDraft(['status' => 'open']));
        $this->assertFalse($this->service->isDraft(['status' => 'merged']));
    }
    
    /**
     * 测试自动合并检查
     */
    public function testAutoMergeEligibility(): void
    {
        $pr = [
            'status' => 'open',
            'draft' => false,
            'mergeable' => true,
            'auto_merge_enabled' => true,
            'required_reviews' => 1,
            'approved_reviews' => 1,
            'ci_status' => 'success',
        ];
        
        $this->assertTrue($this->service->isEligibleForAutoMerge($pr));
        
        $pr['draft'] = true;
        $this->assertFalse($this->service->isEligibleForAutoMerge($pr));
        
        $pr['draft'] = false;
        $pr['auto_merge_enabled'] = false;
        $this->assertFalse($this->service->isEligibleForAutoMerge($pr));
    }
}
