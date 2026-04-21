<?php
/**
 * CodeVault - API 控制器测试
 */

require_once __DIR__ . '/BaseTestCase.php';

use CodeVault\Controllers\UserController;
use CodeVault\Controllers\RepositoryController;
use CodeVault\Controllers\IssueController;

class ApiControllerTest extends BaseTestCase
{
    private array $testUser;
    private string $authToken;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testUser = $this->createTestUser();
        $this->authToken = $this->generateAuthToken($this->testUser['id']);
    }
    
    /**
     * 测试用户 API - 获取当前用户
     */
    public function testGetCurrentUser(): void
    {
        $controller = new UserController();
        $result = $controller->current($this->authToken);
        
        $this->assertIsArray($result);
        $this->assertEquals($this->testUser['id'], $result['id']);
        $this->assertEquals($this->testUser['username'], $result['username']);
    }
    
    /**
     * 测试用户 API - 无效 Token
     */
    public function testGetCurrentUserInvalidToken(): void
    {
        $controller = new UserController();
        
        $this->expectException(Exception::class);
        
        $controller->current('invalid_token');
    }
    
    /**
     * 测试仓库 API - 创建仓库
     */
    public function testCreateRepository(): void
    {
        $controller = new RepositoryController();
        
        $data = [
            'name' => 'api-test-repo-' . uniqid(),
            'description' => 'API test repository',
            'is_private' => false,
        ];
        
        $result = $controller->create($data, $this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'name', 'description'], $result);
        $this->assertEquals($data['name'], $result['name']);
    }
    
    /**
     * 测试仓库 API - 获取仓库列表
     */
    public function testListRepositories(): void
    {
        $this->createTestRepo($this->testUser['id']);
        $this->createTestRepo($this->testUser['id']);
        
        $controller = new RepositoryController();
        $result = $controller->list($this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }
    
    /**
     * 测试仓库 API - 更新仓库
     */
    public function testUpdateRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $controller = new RepositoryController();
        
        $updateData = [
            'description' => 'Updated via API',
        ];
        
        $result = $controller->update($repo['id'], $updateData, $this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($updateData['description'], $result['description']);
    }
    
    /**
     * 测试仓库 API - 删除仓库
     */
    public function testDeleteRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $controller = new RepositoryController();
        $result = $controller->delete($repo['id'], $this->testUser['id']);
        
        $this->assertTrue($result);
    }
    
    /**
     * 测试 Issue API - 创建 Issue
     */
    public function testCreateIssue(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $controller = new IssueController();
        
        $data = [
            'title' => 'Test Issue',
            'content' => 'This is a test issue',
            'repo_id' => $repo['id'],
        ];
        
        $result = $controller->create($data, $this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'title', 'content'], $result);
        $this->assertEquals($data['title'], $result['title']);
    }
    
    /**
     * 测试 Issue API - 获取 Issue 列表
     */
    public function testListIssues(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        $this->createTestIssue($repo['id'], $this->testUser['id']);
        $this->createTestIssue($repo['id'], $this->testUser['id']);
        
        $controller = new IssueController();
        $result = $controller->list($repo['id']);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }
    
    /**
     * 测试 Issue API - 更新 Issue
     */
    public function testUpdateIssue(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        $issue = $this->createTestIssue($repo['id'], $this->testUser['id']);
        
        $controller = new IssueController();
        
        $updateData = [
            'title' => 'Updated Issue Title',
            'status' => 'closed',
        ];
        
        $result = $controller->update($issue['id'], $updateData, $this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($updateData['title'], $result['title']);
        $this->assertEquals($updateData['status'], $result['status']);
    }
    
    /**
     * 测试 Issue API - 添加评论
     */
    public function testAddComment(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        $issue = $this->createTestIssue($repo['id'], $this->testUser['id']);
        
        $controller = new IssueController();
        
        $commentData = [
            'content' => 'This is a test comment',
        ];
        
        $result = $controller->addComment($issue['id'], $commentData, $this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'content', 'user_id'], $result);
        $this->assertEquals($commentData['content'], $result['content']);
    }
    
    /**
     * 测试权限检查 - 无权限用户
     */
    public function testPermissionDenied(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], ['is_private' => 1]);
        $otherUser = $this->createTestUser();
        
        $controller = new RepositoryController();
        
        $this->expectException(Exception::class);
        
        $controller->update($repo['id'], ['description' => 'Hacked'], $otherUser['id']);
    }
    
    /**
     * 测试分页
     */
    public function testPagination(): void
    {
        // 创建多个仓库
        for ($i = 0; $i < 15; $i++) {
            $this->createTestRepo($this->testUser['id']);
        }
        
        $controller = new RepositoryController();
        
        // 第一页
        $result1 = $controller->list($this->testUser['id'], ['page' => 1, 'per_page' => 10]);
        $this->assertCount(10, $result1['repos']);
        
        // 第二页
        $result2 = $controller->list($this->testUser['id'], ['page' => 2, 'per_page' => 10]);
        $this->assertGreaterThanOrEqual(5, count($result2['repos']));
    }
    
    /**
     * 测试搜索
     */
    public function testSearch(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], [
            'name' => 'searchable-repo-' . uniqid(),
            'description' => 'Searchable repository'
        ]);
        
        $controller = new RepositoryController();
        $result = $controller->search('searchable');
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, $result['total']);
    }
    
    /**
     * 测试排序
     */
    public function testSorting(): void
    {
        $repo1 = $this->createTestRepo($this->testUser['id'], ['name' => 'repo-a']);
        sleep(1);
        $repo2 = $this->createTestRepo($this->testUser['id'], ['name' => 'repo-b']);
        
        $controller = new RepositoryController();
        
        // 按名称升序
        $result = $controller->list($this->testUser['id'], ['sort' => 'name', 'order' => 'asc']);
        $names = array_column($result['repos'], 'name');
        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names);
    }
    
    /**
     * 测试过滤
     */
    public function testFiltering(): void
    {
        $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $this->createTestRepo($this->testUser['id'], ['is_private' => 1]);
        
        $controller = new RepositoryController();
        
        // 只获取公开仓库
        $result = $controller->list($this->testUser['id'], ['visibility' => 'public']);
        
        foreach ($result['repos'] as $repo) {
            $this->assertEquals(0, $repo['is_private']);
        }
    }
    
    /**
     * 生成认证 Token
     */
    private function generateAuthToken(int $userId): string
    {
        return 'test_token_' . $userId . '_' . time();
    }
}
