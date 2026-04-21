<?php
/**
 * CodeVault 导入/导出服务
 * 
 * 功能：
 * - 从 GitHub 导入仓库
 * - 从 GitLab 导入仓库
 * - 数据备份导出
 * - 数据恢复导入
 */

namespace Services;

use Core\Logger;

class ImportExportService
{
    private $logger;
    private $gitPath = '/var/git/repositories';
    
    public function __construct()
    {
        $this->logger = new Logger('import-export');
    }
    
    /**
     * 从 GitHub 导入仓库
     */
    public function importFromGitHub(string $repoUrl, string $targetOwner, array $options = []): array
    {
        $this->logger->info('开始从 GitHub 导入仓库', ['url' => $repoUrl]);
        
        // 解析 GitHub URL
        $parsed = $this->parseGitHubUrl($repoUrl);
        if (!$parsed) {
            return ['success' => false, 'error' => '无效的 GitHub URL'];
        }
        
        [$owner, $repo] = $parsed;
        $targetRepo = $options['name'] ?? $repo;
        
        // 创建目标目录
        $targetPath = "{$this->gitPath}/{$targetOwner}/{$targetRepo}.git";
        if (is_dir($targetPath)) {
            return ['success' => false, 'error' => '目标仓库已存在'];
        }
        
        // 克隆仓库
        $cloneUrl = "https://github.com/{$owner}/{$repo}.git";
        $result = $this->cloneRepository($cloneUrl, $targetPath);
        
        if (!$result['success']) {
            return $result;
        }
        
        // 导入 Issues（如果启用）
        if ($options['import_issues'] ?? false) {
            $this->importGitHubIssues($owner, $repo, $targetOwner, $targetRepo, $options['github_token'] ?? null);
        }
        
        // 导入 Wiki（如果启用）
        if ($options['import_wiki'] ?? false) {
            $this->importGitHubWiki($owner, $repo, $targetPath);
        }
        
        $this->logger->info('GitHub 仓库导入完成', ['target' => $targetPath]);
        
        return [
            'success' => true,
            'repo_path' => $targetPath,
            'clone_url' => $cloneUrl,
            'target' => "{$targetOwner}/{$targetRepo}",
        ];
    }
    
    /**
     * 从 GitLab 导入仓库
     */
    public function importFromGitLab(string $repoUrl, string $targetOwner, array $options = []): array
    {
        $this->logger->info('开始从 GitLab 导入仓库', ['url' => $repoUrl]);
        
        // 解析 GitLab URL
        $parsed = $this->parseGitLabUrl($repoUrl);
        if (!$parsed) {
            return ['success' => false, 'error' => '无效的 GitLab URL'];
        }
        
        [$namespace, $project] = $parsed;
        $targetRepo = $options['name'] ?? $project;
        
        // 创建目标目录
        $targetPath = "{$this->gitPath}/{$targetOwner}/{$targetRepo}.git";
        if (is_dir($targetPath)) {
            return ['success' => false, 'error' => '目标仓库已存在'];
        }
        
        // 克隆仓库
        $cloneUrl = $repoUrl;
        if ($options['gitlab_token'] ?? null) {
            $cloneUrl = preg_replace('/^(https?:\/\/)/', '$1oauth2:' . $options['gitlab_token'] . '@', $repoUrl);
        }
        $cloneUrl .= '.git';
        
        $result = $this->cloneRepository($cloneUrl, $targetPath);
        
        if (!$result['success']) {
            return $result;
        }
        
        $this->logger->info('GitLab 仓库导入完成', ['target' => $targetPath]);
        
        return [
            'success' => true,
            'repo_path' => $targetPath,
            'clone_url' => $cloneUrl,
            'target' => "{$targetOwner}/{$targetRepo}",
        ];
    }
    
    /**
     * 解析 GitHub URL
     */
    private function parseGitHubUrl(string $url): ?array
    {
        // 支持多种格式
        // https://github.com/owner/repo
        // git@github.com:owner/repo.git
        // owner/repo
        
        if (preg_match('#github\.com[/:]([^/]+)/([^/\.]+)#', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }
        
        if (preg_match('#^([^/]+)/([^/]+)$#', $url, $matches)) {
            return [$matches[1], $matches[2]];
        }
        
        return null;
    }
    
    /**
     * 解析 GitLab URL
     */
    private function parseGitLabUrl(string $url): ?array
    {
        // https://gitlab.com/namespace/project
        // git@gitlab.com:namespace/project.git
        
        if (preg_match('#gitlab\.com[/:](.+?)(?:\.git)?$#', $url, $matches)) {
            $parts = explode('/', $matches[1]);
            $project = array_pop($parts);
            $namespace = implode('/', $parts);
            return [$namespace, $project];
        }
        
        return null;
    }
    
    /**
     * 克隆仓库
     */
    private function cloneRepository(string $sourceUrl, string $targetPath): array
    {
        // 创建父目录
        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }
        
        // 执行 git clone --mirror
        $cmd = sprintf('git clone --mirror %s %s 2>&1', escapeshellarg($sourceUrl), escapeshellarg($targetPath));
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->logger->error('克隆仓库失败', ['output' => implode("\n", $output)]);
            return [
                'success' => false,
                'error' => '克隆仓库失败: ' . implode("\n", $output),
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * 导入 GitHub Issues
     */
    private function importGitHubIssues(string $owner, string $repo, string $targetOwner, string $targetRepo, ?string $token): void
    {
        $this->logger->info('开始导入 Issues');
        
        $headers = ['Accept: application/vnd.github.v3+json'];
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        
        $page = 1;
        $imported = 0;
        
        while (true) {
            $url = "https://api.github.com/repos/{$owner}/{$repo}/issues?state=all&page={$page}&per_page=100";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                break;
            }
            
            $issues = json_decode($response, true);
            if (empty($issues)) {
                break;
            }
            
            foreach ($issues as $issue) {
                // 跳过 Pull Requests
                if (isset($issue['pull_request'])) {
                    continue;
                }
                
                // TODO: 导入 Issue 到数据库
                $imported++;
            }
            
            $page++;
        }
        
        $this->logger->info('Issues 导入完成', ['count' => $imported]);
    }
    
    /**
     * 导入 GitHub Wiki
     */
    private function importGitHubWiki(string $owner, string $repo, string $targetPath): void
    {
        $wikiUrl = "https://github.com/{$owner}/{$repo}.wiki.git";
        $wikiPath = $targetPath . '-wiki';
        
        $cmd = sprintf('git clone %s %s 2>&1', escapeshellarg($wikiUrl), escapeshellarg($wikiPath));
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->logger->info('Wiki 导入完成');
        } else {
            $this->logger->warning('Wiki 导入失败（可能没有 Wiki）');
        }
    }
    
    /**
     * 导出仓库数据
     */
    public function exportRepository(string $owner, string $repo, array $options = []): array
    {
        $this->logger->info('开始导出仓库数据', ['repo' => "{$owner}/{$repo}"]);
        
        $repoPath = "{$this->gitPath}/{$owner}/{$repo}.git";
        if (!is_dir($repoPath)) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        // 创建临时导出目录
        $exportDir = sys_get_temp_dir() . '/codevault_export_' . uniqid();
        mkdir($exportDir, 0755, true);
        
        // 导出 Git 仓库
        $exportPath = "{$exportDir}/{$repo}.git";
        $cmd = sprintf('git clone --mirror %s %s 2>&1', escapeshellarg($repoPath), escapeshellarg($exportPath));
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => '导出 Git 仓库失败'];
        }
        
        // 导出元数据
        $metadata = [
            'owner' => $owner,
            'repo' => $repo,
            'exported_at' => date('c'),
            'version' => '1.0.0',
        ];
        
        if ($options['include_issues'] ?? true) {
            $metadata['issues'] = $this->exportIssues($owner, $repo);
        }
        
        if ($options['include_pull_requests'] ?? true) {
            $metadata['pull_requests'] = $this->exportPullRequests($owner, $repo);
        }
        
        if ($options['include_wiki'] ?? true) {
            $wikiPath = "{$this->gitPath}/{$owner}/{$repo}.wiki.git";
            if (is_dir($wikiPath)) {
                $cmd = sprintf('cp -r %s %s/wiki.git 2>&1', escapeshellarg($wikiPath), escapeshellarg($exportDir));
                exec($cmd);
                $metadata['has_wiki'] = true;
            }
        }
        
        // 写入元数据
        file_put_contents("{$exportDir}/metadata.json", json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 创建压缩包
        $archivePath = sys_get_temp_dir() . "/{$repo}_" . date('YmdHis') . '.tar.gz';
        $cmd = sprintf('cd %s && tar -czf %s * 2>&1', escapeshellarg($exportDir), escapeshellarg($archivePath));
        exec($cmd);
        
        // 清理临时目录
        $this->removeDirectory($exportDir);
        
        $this->logger->info('仓库数据导出完成', ['archive' => $archivePath]);
        
        return [
            'success' => true,
            'archive_path' => $archivePath,
            'archive_size' => filesize($archivePath),
            'metadata' => $metadata,
        ];
    }
    
    /**
     * 导出 Issues
     */
    private function exportIssues(string $owner, string $repo): array
    {
        // TODO: 从数据库读取 Issues
        return [];
    }
    
    /**
     * 导出 Pull Requests
     */
    private function exportPullRequests(string $owner, string $repo): array
    {
        // TODO: 从数据库读取 Pull Requests
        return [];
    }
    
    /**
     * 导入备份数据
     */
    public function importBackup(string $archivePath, string $targetOwner, array $options = []): array
    {
        $this->logger->info('开始导入备份数据', ['archive' => $archivePath]);
        
        if (!file_exists($archivePath)) {
            return ['success' => false, 'error' => '备份文件不存在'];
        }
        
        // 解压到临时目录
        $extractDir = sys_get_temp_dir() . '/codevault_import_' . uniqid();
        mkdir($extractDir, 0755, true);
        
        $cmd = sprintf('cd %s && tar -xzf %s 2>&1', escapeshellarg($extractDir), escapeshellarg($archivePath));
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => '解压失败'];
        }
        
        // 读取元数据
        $metadataPath = "{$extractDir}/metadata.json";
        if (!file_exists($metadataPath)) {
            return ['success' => false, 'error' => '无效的备份格式'];
        }
        
        $metadata = json_decode(file_get_contents($metadataPath), true);
        $repo = $metadata['repo'] ?? 'imported-repo';
        
        // 导入 Git 仓库
        $gitPath = "{$extractDir}/{$repo}.git";
        if (is_dir($gitPath)) {
            $targetPath = "{$this->gitPath}/{$targetOwner}/{$repo}.git";
            
            if (is_dir($targetPath)) {
                $this->removeDirectory($extractDir);
                return ['success' => false, 'error' => '目标仓库已存在'];
            }
            
            // 创建父目录
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                mkdir($parentDir, 0755, true);
            }
            
            // 移动仓库
            rename($gitPath, $targetPath);
        }
        
        // 导入 Wiki
        $wikiPath = "{$extractDir}/wiki.git";
        if (is_dir($wikiPath) && ($metadata['has_wiki'] ?? false)) {
            $targetWikiPath = "{$this->gitPath}/{$targetOwner}/{$repo}.wiki.git";
            rename($wikiPath, $targetWikiPath);
        }
        
        // 导入 Issues
        if (!empty($metadata['issues'])) {
            $this->importIssues($metadata['issues'], $targetOwner, $repo);
        }
        
        // 导入 Pull Requests
        if (!empty($metadata['pull_requests'])) {
            $this->importPullRequests($metadata['pull_requests'], $targetOwner, $repo);
        }
        
        // 清理临时目录
        $this->removeDirectory($extractDir);
        
        $this->logger->info('备份数据导入完成', ['target' => "{$targetOwner}/{$repo}"]);
        
        return [
            'success' => true,
            'repo' => "{$targetOwner}/{$repo}",
            'metadata' => $metadata,
        ];
    }
    
    /**
     * 导入 Issues
     */
    private function importIssues(array $issues, string $owner, string $repo): void
    {
        // TODO: 导入 Issues 到数据库
    }
    
    /**
     * 导入 Pull Requests
     */
    private function importPullRequests(array $pullRequests, string $owner, string $repo): void
    {
        // TODO: 导入 Pull Requests 到数据库
    }
    
    /**
     * 全量备份
     */
    public function fullBackup(string $outputPath): array
    {
        $this->logger->info('开始全量备份');
        
        $backupDir = sys_get_temp_dir() . '/codevault_full_backup_' . date('YmdHis');
        mkdir($backupDir, 0755, true);
        
        // 备份所有仓库
        $reposDir = "{$backupDir}/repositories";
        mkdir($reposDir, 0755, true);
        
        $cmd = sprintf('cp -r %s/* %s/ 2>&1', escapeshellarg($this->gitPath), escapeshellarg($reposDir));
        exec($cmd);
        
        // 备份数据库
        $dbBackupPath = "{$backupDir}/database.sql";
        // TODO: 执行数据库备份
        
        // 备份配置
        $config = [
            'backup_at' => date('c'),
            'version' => '1.0.0',
        ];
        file_put_contents("{$backupDir}/backup.json", json_encode($config, JSON_PRETTY_PRINT));
        
        // 创建压缩包
        $archivePath = $outputPath ?: sys_get_temp_dir() . '/codevault_backup_' . date('YmdHis') . '.tar.gz';
        $cmd = sprintf('cd %s && tar -czf %s * 2>&1', escapeshellarg($backupDir), escapeshellarg($archivePath));
        exec($cmd);
        
        // 清理临时目录
        $this->removeDirectory($backupDir);
        
        $this->logger->info('全量备份完成', ['archive' => $archivePath]);
        
        return [
            'success' => true,
            'archive_path' => $archivePath,
            'archive_size' => filesize($archivePath),
        ];
    }
    
    /**
     * 删除目录
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
