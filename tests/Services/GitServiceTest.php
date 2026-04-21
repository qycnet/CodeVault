<?php
/**
 * CodeVault - Git 服务测试
 */

require_once __DIR__ . '/BaseTestCase.php';

use CodeVault\Services\GitService;

class GitServiceTest extends BaseTestCase
{
    private GitService $gitService;
    private array $testUser;
    private array $testRepo;
    private string $repoPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->gitService = new GitService();
        $this->testUser = $this->createTestUser();
        $this->testRepo = $this->createTestRepo($this->testUser['id']);
        
        // 创建测试 Git 仓库
        $this->repoPath = getenv('GIT_PATH') ?: '/var/git/repositories';
        $this->repoPath .= '/' . $this->testUser['username'] . '/' . $this->testRepo['name'] . '.git';
        
        if (!is_dir($this->repoPath)) {
            mkdir(dirname($this->repoPath), 0755, true);
            exec('git init --bare ' . escapeshellarg($this->repoPath));
        }
    }
    
    /**
     * 测试获取分支列表
     */
    public function testGetBranches(): void
    {
        // 创建初始提交
        $this->createInitialCommit();
        
        $result = $this->gitService->getBranches($this->repoPath);
        
        $this->assertIsArray($result);
        $this->assertContains('main', $result);
    }
    
    /**
     * 测试创建分支
     */
    public function testCreateBranch(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->createBranch($this->repoPath, 'feature/test-branch', 'main');
        
        $this->assertTrue($result);
        
        $branches = $this->gitService->getBranches($this->repoPath);
        $this->assertContains('feature/test-branch', $branches);
    }
    
    /**
     * 测试删除分支
     */
    public function testDeleteBranch(): void
    {
        $this->createInitialCommit();
        $this->gitService->createBranch($this->repoPath, 'to-delete', 'main');
        
        $result = $this->gitService->deleteBranch($this->repoPath, 'to-delete');
        
        $this->assertTrue($result);
        
        $branches = $this->gitService->getBranches($this->repoPath);
        $this->assertNotContains('to-delete', $branches);
    }
    
    /**
     * 测试获取提交历史
     */
    public function testGetCommits(): void
    {
        $this->createInitialCommit();
        $this->createAdditionalCommit();
        
        $result = $this->gitService->getCommits($this->repoPath, 'main', 10, 0);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
        
        // 验证提交结构
        $commit = $result[0];
        $this->assertArrayHasKeys(['hash', 'message', 'author', 'date'], $commit);
    }
    
    /**
     * 测试获取单个提交
     */
    public function testGetCommit(): void
    {
        $this->createInitialCommit();
        
        $commits = $this->gitService->getCommits($this->repoPath, 'main', 1, 0);
        $hash = $commits[0]['hash'];
        
        $result = $this->gitService->getCommit($this->repoPath, $hash);
        
        $this->assertIsArray($result);
        $this->assertEquals($hash, $result['hash']);
        $this->assertArrayHasKeys(['hash', 'message', 'author', 'date', 'files'], $result);
    }
    
    /**
     * 测试获取文件内容
     */
    public function testGetFileContent(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->getFileContent($this->repoPath, 'README.md', 'main');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('# Test Repository', $result['content']);
    }
    
    /**
     * 测试获取文件内容 - 文件不存在
     */
    public function testGetFileContentNotFound(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->getFileContent($this->repoPath, 'nonexistent.txt', 'main');
        
        $this->assertNull($result);
    }
    
    /**
     * 测试获取目录列表
     */
    public function testGetTree(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->getTree($this->repoPath, '', 'main');
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
    }
    
    /**
     * 测试获取 Diff
     */
    public function testGetDiff(): void
    {
        $this->createInitialCommit();
        $this->createAdditionalCommit();
        
        $commits = $this->gitService->getCommits($this->repoPath, 'main', 2, 0);
        $hash1 = $commits[1]['hash'];
        $hash2 = $commits[0]['hash'];
        
        $result = $this->gitService->getDiff($this->repoPath, $hash1, $hash2);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
    }
    
    /**
     * 测试获取标签列表
     */
    public function testGetTags(): void
    {
        $this->createInitialCommit();
        
        // 创建标签
        exec('cd ' . escapeshellarg($this->repoPath) . ' && git tag v1.0.0');
        
        $result = $this->gitService->getTags($this->repoPath);
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertEquals('v1.0.0', $result[0]['name']);
    }
    
    /**
     * 测试创建标签
     */
    public function testCreateTag(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->createTag($this->repoPath, 'v1.0.1', 'main', 'Release v1.0.1');
        
        $this->assertTrue($result);
        
        $tags = $this->gitService->getTags($this->repoPath);
        $tagNames = array_column($tags, 'name');
        $this->assertContains('v1.0.1', $tagNames);
    }
    
    /**
     * 测试删除标签
     */
    public function testDeleteTag(): void
    {
        $this->createInitialCommit();
        $this->gitService->createTag($this->repoPath, 'v1.0.2', 'main');
        
        $result = $this->gitService->deleteTag($this->repoPath, 'v1.0.2');
        
        $this->assertTrue($result);
        
        $tags = $this->gitService->getTags($this->repoPath);
        $tagNames = array_column($tags, 'name');
        $this->assertNotContains('v1.0.2', $tagNames);
    }
    
    /**
     * 测试搜索代码
     */
    public function testSearchCode(): void
    {
        $this->createInitialCommit();
        
        $result = $this->gitService->searchCode($this->repoPath, 'Test Repository');
        
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
    }
    
    /**
     * 测试获取仓库统计
     */
    public function testGetStats(): void
    {
        $this->createInitialCommit();
        $this->createAdditionalCommit();
        
        $result = $this->gitService->getStats($this->repoPath);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['commits', 'branches', 'tags', 'contributors'], $result);
        $this->assertGreaterThanOrEqual(2, $result['commits']);
    }
    
    /**
     * 测试分支保护检查
     */
    public function testCheckBranchProtection(): void
    {
        $this->createInitialCommit();
        
        // 模拟分支保护规则
        $rules = [
            'required_reviews' => 1,
            'require_status_checks' => true,
        ];
        
        $result = $this->gitService->checkBranchProtection($this->repoPath, 'main', $rules);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('can_push', $result);
    }
    
    /**
     * 创建初始提交
     */
    private function createInitialCommit(): void
    {
        $workTree = sys_get_temp_dir() . '/git-test-' . uniqid();
        mkdir($workTree);
        
        // 初始化工作目录
        exec('cd ' . escapeshellarg($workTree) . ' && git init');
        exec('cd ' . escapeshellarg($workTree) . ' && git config user.email "test@example.com"');
        exec('cd ' . escapeshellarg($workTree) . ' && git config user.name "Test User"');
        
        // 创建文件
        file_put_contents($workTree . '/README.md', "# Test Repository\n\nThis is a test.");
        exec('cd ' . escapeshellarg($workTree) . ' && git add .');
        exec('cd ' . escapeshellarg($workTree) . ' && git commit -m "Initial commit"');
        
        // 推送到裸仓库
        exec('cd ' . escapeshellarg($workTree) . ' && git remote add origin ' . escapeshellarg($this->repoPath));
        exec('cd ' . escapeshellarg($workTree) . ' && git push -u origin main');
        
        // 清理
        exec('rm -rf ' . escapeshellarg($workTree));
    }
    
    /**
     * 创建额外提交
     */
    private function createAdditionalCommit(): void
    {
        $workTree = sys_get_temp_dir() . '/git-test-' . uniqid();
        mkdir($workTree);
        
        // 克隆仓库
        exec('git clone ' . escapeshellarg($this->repoPath) . ' ' . escapeshellarg($workTree));
        exec('cd ' . escapeshellarg($workTree) . ' && git config user.email "test@example.com"');
        exec('cd ' . escapeshellarg($workTree) . ' && git config user.name "Test User"');
        
        // 修改文件
        file_put_contents($workTree . '/README.md', "# Test Repository\n\nUpdated content.");
        exec('cd ' . escapeshellarg($workTree) . ' && git add .');
        exec('cd ' . escapeshellarg($workTree) . ' && git commit -m "Update README"');
        exec('cd ' . escapeshellarg($workTree) . ' && git push');
        
        // 清理
        exec('rm -rf ' . escapeshellarg($workTree));
    }
    
    protected function tearDown(): void
    {
        // 清理测试仓库
        if (is_dir($this->repoPath)) {
            exec('rm -rf ' . escapeshellarg($this->repoPath));
        }
        
        parent::tearDown();
    }
}
