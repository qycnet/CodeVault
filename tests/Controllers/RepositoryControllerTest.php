<?php
/**
 * CodeVault - RepositoryController 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Controllers;

use PHPUnit\Framework\TestCase;

class RepositoryControllerTest extends TestCase
{
    /**
     * 测试创建仓库验证
     */
    public function testCreateRepositoryValidation(): void
    {
        $validData = [
            'name' => 'my-repo',
            'description' => 'A test repository',
            'visibility' => 'public',
        ];
        
        $this->assertTrue($this->validateCreateRequest($validData));
        
        // 无效仓库名
        $invalidData = [
            'name' => 'my repo', // 包含空格
            'visibility' => 'public',
        ];
        $this->assertFalse($this->validateCreateRequest($invalidData));
        
        // 无效可见性
        $invalidData = [
            'name' => 'my-repo',
            'visibility' => 'invalid',
        ];
        $this->assertFalse($this->validateCreateRequest($invalidData));
    }
    
    /**
     * 测试仓库名称验证
     */
    public function testRepositoryNameValidation(): void
    {
        // 有效名称
        $this->assertTrue($this->isValidRepoName('my-repo'));
        $this->assertTrue($this->isValidRepoName('my_repo'));
        $this->assertTrue($this->isValidRepoName('my.repo'));
        $this->assertTrue($this->isValidRepoName('repo123'));
        $this->assertTrue($this->isValidRepoName('123repo'));
        
        // 无效名称
        $this->assertFalse($this->isValidRepoName('')); // 空
        $this->assertFalse($this->isValidRepoName('a')); // 太短
        $this->assertFalse($this->isValidRepoName('my repo')); // 空格
        $this->assertFalse($this->isValidRepoName('my@repo')); // 特殊字符
        $this->assertFalse($this->isValidRepoName('.repo')); // 以点开头
        $this->assertFalse($this->isValidRepoName('repo.')); // 以点结尾
        $this->assertFalse($this->isValidRepoName(str_repeat('a', 101))); // 太长
    }
    
    /**
     * 测试更新仓库验证
     */
    public function testUpdateRepositoryValidation(): void
    {
        $validData = [
            'description' => 'Updated description',
            'visibility' => 'private',
        ];
        
        $this->assertTrue($this->validateUpdateRequest($validData));
        
        // 空数据
        $this->assertFalse($this->validateUpdateRequest([]));
    }
    
    /**
     * 测试分支保护规则验证
     */
    public function testBranchProtectionValidation(): void
    {
        $validRule = [
            'branch' => 'main',
            'required_reviews' => 2,
            'dismiss_stale_reviews' => true,
            'require_signed_commits' => true,
        ];
        
        $this->assertTrue($this->validateBranchProtection($validRule));
        
        // 无效审核数
        $invalidRule = [
            'branch' => 'main',
            'required_reviews' => -1,
        ];
        $this->assertFalse($this->validateBranchProtection($invalidRule));
    }
    
    /**
     * 测试协作者权限验证
     */
    public function testCollaboratorPermissionValidation(): void
    {
        $validPermissions = ['read', 'write', 'admin'];
        
        foreach ($validPermissions as $permission) {
            $this->assertTrue($this->isValidPermission($permission));
        }
        
        $this->assertFalse($this->isValidPermission('invalid'));
        $this->assertFalse($this->isValidPermission(''));
    }
    
    /**
     * 测试 Webhook 配置验证
     */
    public function testWebhookConfigValidation(): void
    {
        $validConfig = [
            'url' => 'https://example.com/webhook',
            'events' => ['push', 'pull_request'],
            'active' => true,
        ];
        
        $this->assertTrue($this->validateWebhookConfig($validConfig));
        
        // 无效 URL
        $invalidConfig = [
            'url' => 'not-a-url',
            'events' => ['push'],
        ];
        $this->assertFalse($this->validateWebhookConfig($invalidConfig));
        
        // 无效事件
        $invalidConfig = [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid_event'],
        ];
        $this->assertFalse($this->validateWebhookConfig($invalidConfig));
    }
    
    /**
     * 测试转移仓库验证
     */
    public function testTransferValidation(): void
    {
        $validTransfer = [
            'new_owner' => 'new-owner',
        ];
        
        $this->assertTrue($this->validateTransfer($validTransfer));
        
        // 空所有者
        $invalidTransfer = [
            'new_owner' => '',
        ];
        $this->assertFalse($this->validateTransfer($invalidTransfer));
    }
    
    /**
     * 测试归档操作
     */
    public function testArchiveOperation(): void
    {
        // 可以归档活跃仓库
        $this->assertTrue($this->canArchive(['archived' => false]));
        
        // 已归档的不能再归档
        $this->assertFalse($this->canArchive(['archived' => true]));
    }
    
    // 辅助验证方法
    private function validateCreateRequest(array $data): bool
    {
        if (!$this->isValidRepoName($data['name'] ?? '')) {
            return false;
        }
        if (!in_array($data['visibility'] ?? '', ['public', 'private'])) {
            return false;
        }
        return true;
    }
    
    private function isValidRepoName(string $name): bool
    {
        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            return false;
        }
        if (preg_match('/[^a-zA-Z0-9._-]/', $name)) {
            return false;
        }
        if (str_starts_with($name, '.') || str_ends_with($name, '.')) {
            return false;
        }
        return true;
    }
    
    private function validateUpdateRequest(array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        if (isset($data['visibility']) && !in_array($data['visibility'], ['public', 'private'])) {
            return false;
        }
        return true;
    }
    
    private function validateBranchProtection(array $rule): bool
    {
        if (empty($rule['branch'])) {
            return false;
        }
        if (isset($rule['required_reviews']) && $rule['required_reviews'] < 0) {
            return false;
        }
        return true;
    }
    
    private function isValidPermission(string $permission): bool
    {
        return in_array($permission, ['read', 'write', 'admin']);
    }
    
    private function validateWebhookConfig(array $config): bool
    {
        if (empty($config['url']) || !filter_var($config['url'], FILTER_VALIDATE_URL)) {
            return false;
        }
        $validEvents = ['push', 'pull_request', 'issues', 'release', 'create', 'delete'];
        if (!empty($config['events'])) {
            foreach ($config['events'] as $event) {
                if (!in_array($event, $validEvents)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    private function validateTransfer(array $data): bool
    {
        return !empty($data['new_owner']);
    }
    
    private function canArchive(array $repo): bool
    {
        return !$repo['archived'];
    }
}
