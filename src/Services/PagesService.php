<?php
/**
 * CodeVault Pages 静态网站托管服务
 * 
 * 功能：
 * - 静态网站托管
 * - 自定义域名绑定
 * - HTTPS 支持
 * - 构建部署
 * - 版本管理
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class PagesService
{
    private $db;
    private $logger;
    private $security;
    
    // Pages 存储路径
    private const STORAGE_PATH = '/var/git/pages';
    
    // 支持的构建类型
    private const BUILD_TYPES = [
        'static' => '静态文件',
        'jekyll' => 'Jekyll',
        'hugo' => 'Hugo',
        'next' => 'Next.js',
        'nuxt' => 'Nuxt.js',
        'vuepress' => 'VuePress',
        'docsify' => 'Docsify',
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('pages');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建 Pages 站点
     */
    public function createSite(int $repoId, int $userId, array $data): array
    {
        // 检查是否已存在
        $existing = $this->db->fetchOne(
            "SELECT id FROM pages_sites WHERE repo_id = ?",
            [$repoId]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '该仓库已启用 Pages'];
        }
        
        // 验证源分支
        $repo = $this->db->fetchOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        $sql = "INSERT INTO pages_sites (repo_id, user_id, source_branch, source_dir, build_type, custom_domain, https_enabled, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $repoId,
            $userId,
            $data['source_branch'] ?? 'main',
            $data['source_dir'] ?? '/',
            $data['build_type'] ?? 'static',
            $data['custom_domain'] ?? null,
            $data['https_enabled'] ?? true,
        ]);
        
        $siteId = (int)$this->db->lastInsertId();
        
        // 生成默认域名
        $defaultDomain = $this->generateDefaultDomain($repo['owner_type'], $repo['owner_id'], $repo['name']);
        $this->db->execute(
            "UPDATE pages_sites SET default_domain = ? WHERE id = ?",
            [$defaultDomain, $siteId]
        );
        
        // 创建存储目录
        $storagePath = self::STORAGE_PATH . '/' . $siteId;
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $this->logger->info('Pages 站点创建成功', ['site_id' => $siteId, 'repo_id' => $repoId]);
        
        return [
            'success' => true,
            'site_id' => $siteId,
            'default_domain' => $defaultDomain,
        ];
    }
    
    /**
     * 获取站点信息
     */
    public function getSite(int $siteId): ?array
    {
        $site = $this->db->fetchOne(
            "SELECT ps.*, r.name as repo_name, r.owner_type, r.owner_id, u.username as owner_name
             FROM pages_sites ps
             JOIN repositories r ON ps.repo_id = r.id
             JOIN users u ON ps.user_id = u.id
             WHERE ps.id = ?",
            [$siteId]
        );
        
        if ($site) {
            $site['build_type_name'] = self::BUILD_TYPES[$site['build_type']] ?? $site['build_type'];
        }
        
        return $site;
    }
    
    /**
     * 获取仓库的 Pages 站点
     */
    public function getSiteByRepo(int $repoId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM pages_sites WHERE repo_id = ?",
            [$repoId]
        );
    }
    
    /**
     * 更新站点配置
     */
    public function updateSite(int $siteId, int $userId, array $data): array
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return ['success' => false, 'error' => '站点不存在'];
        }
        
        if (!$this->canManage($siteId, $userId)) {
            return ['success' => false, 'error' => '无权限修改'];
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['source_branch'])) {
            $updates[] = 'source_branch = ?';
            $params[] = $data['source_branch'];
        }
        
        if (isset($data['source_dir'])) {
            $updates[] = 'source_dir = ?';
            $params[] = $data['source_dir'];
        }
        
        if (isset($data['build_type'])) {
            if (!isset(self::BUILD_TYPES[$data['build_type']])) {
                return ['success' => false, 'error' => '不支持的构建类型'];
            }
            $updates[] = 'build_type = ?';
            $params[] = $data['build_type'];
        }
        
        if (isset($data['custom_domain'])) {
            // 验证域名格式
            if ($data['custom_domain'] && !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*$/', $data['custom_domain'])) {
                return ['success' => false, 'error' => '无效的域名格式'];
            }
            $updates[] = 'custom_domain = ?';
            $params[] = $data['custom_domain'];
        }
        
        if (isset($data['https_enabled'])) {
            $updates[] = 'https_enabled = ?';
            $params[] = $data['https_enabled'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            return ['success' => true];
        }
        
        $params[] = $siteId;
        
        $sql = "UPDATE pages_sites SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $this->db->execute($sql, $params);
        
        return ['success' => true];
    }
    
    /**
     * 删除站点
     */
    public function deleteSite(int $siteId, int $userId): array
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return ['success' => false, 'error' => '站点不存在'];
        }
        
        if (!$this->canManage($siteId, $userId)) {
            return ['success' => false, 'error' => '无权限删除'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 删除部署记录
            $this->db->execute("DELETE FROM pages_deployments WHERE site_id = ?", [$siteId]);
            
            // 删除域名验证
            $this->db->execute("DELETE FROM pages_domains WHERE site_id = ?", [$siteId]);
            
            // 删除站点
            $this->db->execute("DELETE FROM pages_sites WHERE id = ?", [$siteId]);
            
            // 删除存储
            $storagePath = self::STORAGE_PATH . '/' . $siteId;
            if (is_dir($storagePath)) {
                exec("rm -rf " . escapeshellarg($storagePath));
            }
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 触发部署
     */
    public function deploy(int $siteId, int $userId, ?string $commitSha = null): array
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return ['success' => false, 'error' => '站点不存在'];
        }
        
        // 检查是否有正在进行的部署
        $running = $this->db->fetchOne(
            "SELECT id FROM pages_deployments WHERE site_id = ? AND status IN ('pending', 'building')",
            [$siteId]
        );
        
        if ($running) {
            return ['success' => false, 'error' => '已有部署正在进行中'];
        }
        
        // 获取最新提交
        if (!$commitSha) {
            $commitSha = $this->getLatestCommit($site['repo_id'], $site['source_branch']);
        }
        
        $sql = "INSERT INTO pages_deployments (site_id, commit_sha, status, triggered_by, created_at) 
                VALUES (?, ?, 'pending', ?, NOW())";
        
        $this->db->execute($sql, [$siteId, $commitSha, $userId]);
        
        $deploymentId = (int)$this->db->lastInsertId();
        
        // 异步执行构建
        $this->executeBuild($deploymentId, $site);
        
        return [
            'success' => true,
            'deployment_id' => $deploymentId,
        ];
    }
    
    /**
     * 执行构建
     */
    private function executeBuild(int $deploymentId, array $site): void
    {
        // 更新状态为构建中
        $this->db->execute(
            "UPDATE pages_deployments SET status = 'building', started_at = NOW() WHERE id = ?",
            [$deploymentId]
        );
        
        $storagePath = self::STORAGE_PATH . '/' . $site['id'];
        $buildPath = $storagePath . '/builds/' . $deploymentId;
        
        try {
            // 克隆仓库
            $repoPath = "/var/git/repositories/{$site['repo_id']}.git";
            $this->cloneRepository($repoPath, $buildPath, $site['source_branch']);
            
            // 执行构建
            $output = $this->runBuild($buildPath, $site['build_type'], $site['source_dir']);
            
            // 部署到生产目录
            $productionPath = $storagePath . '/public';
            $this->deployToProduction($buildPath, $productionPath, $site['source_dir']);
            
            // 更新部署状态
            $this->db->execute(
                "UPDATE pages_deployments SET status = 'success', completed_at = NOW(), build_log = ? WHERE id = ?",
                [$output, $deploymentId]
            );
            
            // 更新站点状态
            $this->db->execute(
                "UPDATE pages_sites SET last_deployment_id = ?, status = 'active', updated_at = NOW() WHERE id = ?",
                [$deploymentId, $site['id']]
            );
            
        } catch (\Exception $e) {
            // 更新失败状态
            $this->db->execute(
                "UPDATE pages_deployments SET status = 'failed', completed_at = NOW(), error_message = ? WHERE id = ?",
                [$e->getMessage(), $deploymentId]
            );
            
            $this->logger->error('Pages 构建失败', ['deployment_id' => $deploymentId, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * 克隆仓库
     */
    private function cloneRepository(string $repoPath, string $buildPath, string $branch): void
    {
        if (!is_dir($buildPath)) {
            mkdir($buildPath, 0755, true);
        }
        
        $cmd = sprintf(
            'git clone --branch %s --single-branch %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($repoPath),
            escapeshellarg($buildPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('克隆仓库失败: ' . implode("\n", $output));
        }
    }
    
    /**
     * 执行构建
     */
    private function runBuild(string $buildPath, string $buildType, string $sourceDir): string
    {
        // 验证构建路径在允许范围内
        if (!$this->security->validatePath($buildPath, [
            self::STORAGE_PATH,
            '/tmp/',
        ])) {
            throw new \Exception('非法构建路径');
        }
        
        // 验证构建类型白名单
        if (!isset(self::BUILD_TYPES[$buildType])) {
            throw new \Exception('不支持的构建类型');
        }
        
        $output = [];
        $originalDir = getcwd();
        
        try {
            switch ($buildType) {
                case 'static':
                    // 静态文件无需构建
                    $output[] = 'Static site - no build required';
                    break;
                    
                case 'jekyll':
                    chdir($buildPath);
                    exec('bundle install 2>&1 && bundle exec jekyll build 2>&1', $output);
                    break;
                    
                case 'hugo':
                    chdir($buildPath);
                    exec('hugo 2>&1', $output);
                    break;
                    
                case 'next':
                    chdir($buildPath);
                    exec('npm install 2>&1 && npm run build 2>&1 && npm run export 2>&1', $output);
                    break;
                    
                case 'nuxt':
                    chdir($buildPath);
                    exec('npm install 2>&1 && npm run generate 2>&1', $output);
                    break;
                    
                case 'vuepress':
                    chdir($buildPath);
                    exec('npm install 2>&1 && npm run build 2>&1', $output);
                    break;
                    
                case 'docsify':
                    // Docsify 无需构建
                    $output[] = 'Docsify site - no build required';
                    break;
            }
        } finally {
            // 恢复原始目录
            if ($originalDir !== false) {
                chdir($originalDir);
            }
        }
        
        return implode("\n", $output);
    }
    
    /**
     * 部署到生产目录
     */
    private function deployToProduction(string $buildPath, string $productionPath, string $sourceDir): void
    {
        // 备份旧版本
        if (is_dir($productionPath)) {
            $backupPath = $productionPath . '.backup';
            if (is_dir($backupPath)) {
                exec("rm -rf " . escapeshellarg($backupPath));
            }
            rename($productionPath, $backupPath);
        }
        
        // 创建新的生产目录
        mkdir($productionPath, 0755, true);
        
        // 复制文件
        $sourcePath = rtrim($buildPath, '/') . $sourceDir;
        if ($sourceDir !== '/' && is_dir($sourcePath)) {
            $cmd = sprintf('cp -r %s/* %s/', escapeshellarg($sourcePath), escapeshellarg($productionPath));
        } else {
            $cmd = sprintf('cp -r %s/* %s/', escapeshellarg($buildPath), escapeshellarg($productionPath));
        }
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception('部署文件失败');
        }
    }
    
    /**
     * 获取部署列表
     */
    public function listDeployments(int $siteId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT pd.*, u.username as trigger_name
             FROM pages_deployments pd
             JOIN users u ON pd.triggered_by = u.id
             WHERE pd.site_id = ?
             ORDER BY pd.created_at DESC
             LIMIT ?",
            [$siteId, $limit]
        );
    }
    
    /**
     * 获取部署详情
     */
    public function getDeployment(int $deploymentId): ?array
    {
        return $this->db->fetchOne(
            "SELECT pd.*, ps.default_domain, ps.custom_domain
             FROM pages_deployments pd
             JOIN pages_sites ps ON pd.site_id = ps.id
             WHERE pd.id = ?",
            [$deploymentId]
        );
    }
    
    /**
     * 取消部署
     */
    public function cancelDeployment(int $deploymentId, int $userId): array
    {
        $deployment = $this->getDeployment($deploymentId);
        
        if (!$deployment) {
            return ['success' => false, 'error' => '部署不存在'];
        }
        
        if (!in_array($deployment['status'], ['pending', 'building'])) {
            return ['success' => false, 'error' => '无法取消已完成的部署'];
        }
        
        $this->db->execute(
            "UPDATE pages_deployments SET status = 'cancelled', completed_at = NOW() WHERE id = ?",
            [$deploymentId]
        );
        
        return ['success' => true];
    }
    
    /**
     * 添加自定义域名
     */
    public function addDomain(int $siteId, int $userId, string $domain): array
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return ['success' => false, 'error' => '站点不存在'];
        }
        
        if (!$this->canManage($siteId, $userId)) {
            return ['success' => false, 'error' => '无权限操作'];
        }
        
        // 验证域名格式
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*$/', $domain)) {
            return ['success' => false, 'error' => '无效的域名格式'];
        }
        
        // 检查域名是否已被使用
        $existing = $this->db->fetchOne(
            "SELECT id FROM pages_domains WHERE domain = ?",
            [$domain]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '域名已被使用'];
        }
        
        $sql = "INSERT INTO pages_domains (site_id, domain, verification_token, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $verificationToken = bin2hex(random_bytes(32));
        $this->db->execute($sql, [$siteId, $domain, $verificationToken]);
        
        $domainId = (int)$this->db->lastInsertId();
        
        return [
            'success' => true,
            'domain_id' => $domainId,
            'verification_token' => $verificationToken,
            'verification_method' => 'dns',
            'dns_record' => [
                'type' => 'TXT',
                'name' => '_codevault-pages.' . $domain,
                'value' => $verificationToken,
            ],
        ];
    }
    
    /**
     * 验证域名
     */
    public function verifyDomain(int $domainId): array
    {
        $domain = $this->db->fetchOne(
            "SELECT * FROM pages_domains WHERE id = ?",
            [$domainId]
        );
        
        if (!$domain) {
            return ['success' => false, 'error' => '域名不存在'];
        }
        
        // 检查 DNS 记录
        $dnsRecord = '_codevault-pages.' . $domain['domain'];
        $records = @dns_get_record($dnsRecord, DNS_TXT);
        
        if ($records === false || empty($records)) {
            return ['success' => false, 'error' => '未找到验证记录'];
        }
        
        $verified = false;
        foreach ($records as $record) {
            if ($record['txt'] === $domain['verification_token']) {
                $verified = true;
                break;
            }
        }
        
        if (!$verified) {
            return ['success' => false, 'error' => '验证记录不匹配'];
        }
        
        // 更新验证状态
        $this->db->execute(
            "UPDATE pages_domains SET verified = 1, verified_at = NOW() WHERE id = ?",
            [$domainId]
        );
        
        // 更新站点的自定义域名
        $this->db->execute(
            "UPDATE pages_sites SET custom_domain = ? WHERE id = ?",
            [$domain['domain'], $domain['site_id']]
        );
        
        return ['success' => true];
    }
    
    /**
     * 删除域名
     */
    public function removeDomain(int $domainId, int $userId): array
    {
        $domain = $this->db->fetchOne(
            "SELECT pd.*, ps.user_id
             FROM pages_domains pd
             JOIN pages_sites ps ON pd.site_id = ps.id
             WHERE pd.id = ?",
            [$domainId]
        );
        
        if (!$domain) {
            return ['success' => false, 'error' => '域名不存在'];
        }
        
        if ($domain['user_id'] != $userId && !$this->canManage($domain['site_id'], $userId)) {
            return ['success' => false, 'error' => '无权限操作'];
        }
        
        $this->db->execute("DELETE FROM pages_domains WHERE id = ?", [$domainId]);
        
        // 清除站点的自定义域名
        $this->db->execute(
            "UPDATE pages_sites SET custom_domain = NULL WHERE id = ? AND custom_domain = ?",
            [$domain['site_id'], $domain['domain']]
        );
        
        return ['success' => true];
    }
    
    /**
     * 获取站点统计
     */
    public function getStats(int $siteId): array
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return [];
        }
        
        return [
            'total_deployments' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM pages_deployments WHERE site_id = ?",
                [$siteId]
            )['count'],
            'successful_deployments' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM pages_deployments WHERE site_id = ? AND status = 'success'",
                [$siteId]
            )['count'],
            'failed_deployments' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM pages_deployments WHERE site_id = ? AND status = 'failed'",
                [$siteId]
            )['count'],
            'domains_count' => $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM pages_domains WHERE site_id = ? AND verified = 1",
                [$siteId]
            )['count'],
            'storage_used' => $this->calculateStorage($siteId),
        ];
    }
    
    /**
     * 计算存储使用量
     */
    private function calculateStorage(int $siteId): int
    {
        $storagePath = self::STORAGE_PATH . '/' . $siteId;
        
        if (!is_dir($storagePath)) {
            return 0;
        }
        
        $output = [];
        exec("du -sb " . escapeshellarg($storagePath) . " 2>/dev/null", $output);
        
        return isset($output[0]) ? (int)preg_split('/\s+/', $output[0])[0] : 0;
    }
    
    /**
     * 生成默认域名
     */
    private function generateDefaultDomain(string $ownerType, int $ownerId, string $repoName): string
    {
        if ($ownerType === 'organization') {
            $org = $this->db->fetchOne("SELECT slug FROM organizations WHERE id = ?", [$ownerId]);
            $ownerSlug = $org ? $org['slug'] : 'org' . $ownerId;
        } else {
            $user = $this->db->fetchOne("SELECT username FROM users WHERE id = ?", [$ownerId]);
            $ownerSlug = $user ? $user['username'] : 'user' . $ownerId;
        }
        
        return strtolower($ownerSlug . '-' . $repoName . '.pages.codevault.io');
    }
    
    /**
     * 获取最新提交
     */
    private function getLatestCommit(int $repoId, string $branch): ?string
    {
        $repoPath = "/var/git/repositories/{$repoId}.git";
        
        if (!is_dir($repoPath)) {
            return null;
        }
        
        $cmd = sprintf(
            'git --git-dir=%s rev-parse %s 2>/dev/null',
            escapeshellarg($repoPath),
            escapeshellarg('refs/heads/' . $branch)
        );
        
        $output = [];
        exec($cmd, $output);
        
        return $output[0] ?? null;
    }
    
    /**
     * 检查管理权限
     */
    private function canManage(int $siteId, int $userId): bool
    {
        $site = $this->getSite($siteId);
        
        if (!$site) {
            return false;
        }
        
        // 检查仓库所有者
        $repo = $this->db->fetchOne(
            "SELECT user_id FROM repositories WHERE id = ?",
            [$site['repo_id']]
        );
        
        if ($repo && $repo['user_id'] == $userId) {
            return true;
        }
        
        // 检查协作者权限
        $collab = $this->db->fetchOne(
            "SELECT permission FROM repo_collaborators WHERE repo_id = ? AND user_id = ?",
            [$site['repo_id'], $userId]
        );
        
        return $collab && in_array($collab['permission'], ['admin', 'write']);
    }
    
    /**
     * 获取支持的构建类型
     */
    public function getBuildTypes(): array
    {
        return self::BUILD_TYPES;
    }
}
