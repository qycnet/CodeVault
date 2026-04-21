<?php
/**
 * CodeVault - 仓库服务测试
 */

require_once __DIR__ . '/BaseTestCase.php';

use CodeVault\Services\RepositoryService;
use CodeVault\Services\GitService;

class RepositoryServiceTest extends BaseTestCase
{
    private RepositoryService $repoService;
    private array $testUser;
    private string $gitBasePath;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repoService = new RepositoryService();
        $this->testUser = $this->createTestUser();
        $this->gitBasePath = getenv('GIT_PATH') ?: '/var/git/repositories';
    }
    
    /**
     * 测试创建仓库
     */
    public function testCreateRepository(): void
    {
        $data = [
            'name' => 'test-repo-' . uniqid(),
            'description' => 'Test repository',
            'is_private' => false,
            'user_id' => $this->testUser['id'],
        ];
        
        $result = $this->repoService->create($data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['id', 'name', 'description'], $result);
        $this->assertEquals($data['name'], $result['name']);
    }
    
    /**
     * 测试创建仓库 - 无效名称
     */
    public function testCreateRepositoryInvalidName(): void
    {
        $this->expectException(Exception::class);
        
        $this->repoService->create([
            'name' => 'invalid repo name!',
            'description' => 'Test',
            'user_id' => $this->testUser['id'],
        ]);
    }
    
    /**
     * 测试获取仓库
     */
    public function testGetRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $result = $this->repoService->get($repo['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($repo['id'], $result['id']);
        $this->assertEquals($repo['name'], $result['name']);
    }
    
    /**
     * 测试获取仓库 - 不存在
     */
    public function testGetRepositoryNotFound(): void
    {
        $result = $this->repoService->get(999999);
        
        $this->assertNull($result);
    }
    
    /**
     * 测试更新仓库
     */
    public function testUpdateRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $updateData = [
            'description' => 'Updated description',
            'is_private' => true,
        ];
        
        $result = $this->repoService->update($repo['id'], $updateData);
        
        $this->assertTrue($result);
        
        $updated = $this->repoService->get($repo['id']);
        $this->assertEquals($updateData['description'], $updated['description']);
        $this->assertEquals($updateData['is_private'], (bool)$updated['is_private']);
    }
    
    /**
     * 测试删除仓库
     */
    public function testDeleteRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $result = $this->repoService->delete($repo['id']);
        
        $this->assertTrue($result);
        
        $deleted = $this->repoService->get($repo['id']);
        $this->assertNull($deleted);
    }
    
    /**
     * 测试用户仓库列表
     */
    public function testListUserRepositories(): void
    {
        $this->createTestRepo($this->testUser['id']);
        $this->createTestRepo($this->testUser['id']);
        $this->createTestRepo($this->testUser['id'], ['is_private' => 1]);
        
        $result = $this->repoService->listByUser($this->testUser['id']);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(3, count($result));
    }
    
    /**
     * 测试公开仓库列表
     */
    public function testListPublicRepositories(): void
    {
        $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $this->createTestRepo($this->testUser['id'], ['is_private' => 1]); // 私有
        
        $result = $this->repoService->listPublic(['page' => 1, 'per_page' => 10]);
        
        $this->assertIsArray($result);
        foreach ($result['repos'] as $repo) {
            $this->assertEquals(0, $repo['is_private']);
        }
    }
    
    /**
     * 测试仓库名称验证
     */
    public function testValidateRepoName(): void
    {
        // 有效名称
        $this->assertTrue($this->repoService->validateName('valid-repo'));
        $this->assertTrue($this->repoService->validateName('repo123'));
        $this->assertTrue($this->repoService->validateName('my_repo'));
        $this->assertTrue($this->repoService->validateName('repo.name'));
        
        // 无效名称
        $this->assertFalse($this->repoService->validateName('ab')); // 太短
        $this->assertFalse($this->repoService->validateName(str_repeat('a', 101))); // 太长
        $this->assertFalse($this->repoService->validateName('.repo')); // 点开头
        $this->assertFalse($this->repoService->validateName('repo.')); // 点结尾
        $this->assertFalse($this->repoService->validateName('repo..name')); // 连续点
        $this->assertFalse($this->repoService->validateName('repo@name')); // 特殊字符
    }
    
    /**
     * 测试检查用户权限
     */
    public function testCheckPermission(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        // 所有者有写权限
        $this->assertTrue($this->repoService->checkPermission($repo['id'], $this->testUser['id'], 'write'));
        
        // 其他用户无权限（私有仓库）
        $otherUser = $this->createTestUser();
        $this->assertFalse($this->repoService->checkPermission($repo['id'], $otherUser['id'], 'read'));
    }
    
    /**
     * 测试公开仓库权限
     */
    public function testPublicRepoPermission(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $otherUser = $this->createTestUser();
        
        // 公开仓库，所有人可读
        $this->assertTrue($this->repoService->checkPermission($repo['id'], $otherUser['id'], 'read'));
        
        // 但不可写
        $this->assertFalse($this->repoService->checkPermission($repo['id'], $otherUser['id'], 'write'));
    }
    
    /**
     * 测试仓库统计
     */
    public function testGetStats(): void
    {
        $repo = $this->createTestRepo($this->testUser['id']);
        
        $result = $this->repoService->getStats($repo['id']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['stars', 'forks', 'issues', 'prs', 'commits'], $result);
    }
    
    /**
     * 测试搜索仓库
     */
    public function testSearchRepositories(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], [
            'name' => 'searchable-repo-' . uniqid(),
            'description' => 'Searchable test repository'
        ]);
        
        $result = $this->repoService->search('searchable', ['page' => 1, 'per_page' => 10]);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, $result['total']);
    }
    
    /**
     * 测试 Fork 仓库
     */
    public function testForkRepository(): void
    {
        $originalRepo = $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $otherUser = $this->createTestUser();
        
        $result = $this->repoService->fork($originalRepo['id'], $otherUser['id']);
        
        $this->assertIsArray($result);
        $this->assertEquals($otherUser['id'], $result['user_id']);
        $this->assertEquals($originalRepo['id'], $result['forked_from']);
    }
    
    /**
     * 测试 Fork 私有仓库 - 无权限
     */
    public function testForkPrivateRepoNoPermission(): void
    {
        $privateRepo = $this->createTestRepo($this->testUser['id'], ['is_private' => 1]);
        $otherUser = $this->createTestUser();
        
        $this->expectException(Exception::class);
        
        $this->repoService->fork($privateRepo['id'], $otherUser['id']);
    }
    
    /**
     * 测试 Star 仓库
     */
    public function testStarRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $otherUser = $this->createTestUser();
        
        $result = $this->repoService->star($repo['id'], $otherUser['id']);
        
        $this->assertTrue($result);
        
        // 验证已 star
        $isStarred = $this->repoService->isStarred($repo['id'], $otherUser['id']);
        $this->assertTrue($isStarred);
    }
    
    /**
     * 测试取消 Star
     */
    public function testUnstarRepository(): void
    {
        $repo = $this->createTestRepo($this->testUser['id'], ['is_private' => 0]);
        $otherUser = $this->createTestUser();
        
        // 先 star
        $this->repoService->star($repo['id'], $otherUser['id']);
        
        // 再取消
        $result = $this->repoService->unstar($repo['id'], $otherUser['id']);
        
        $this->assertTrue($result);
        
        // 验证已取消
        $isStarred = $this->repoService->isStarred($repo['id'], $otherUser['id']);
        $this->assertFalse($isStarred);
    }
}
