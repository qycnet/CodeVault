<?php
/**
 * CodeVault 导入/导出服务
 * 
 * 功能：
 * - GitHub 仓库导入
 * - GitLab 仓库导入
 * - 仓库导出
 * - Issue 导入/导出
 * - Wiki 导入/导出
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Services\SecurityService;

class ImportExportService
{
    private $db;
    private $logger;
    private $security;
    
    // GitHub API 基础 URL
    private const GITHUB_API = 'https://api.github.com';
    
    // GitLab API 基础 URL
    private const GITLAB_API = 'https://gitlab.com/api/v4';
    
    // 导入任务状态
    private const STATUS_PENDING = 'pending';
    private const STATUS_RUNNING = 'running';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('import-export');
        $this->security = new SecurityService();
    }
    
    /**
     * 创建导入任务
     */
    public function createImportTask(int $userId, array $data): array
    {
        $source = $data['source'] ?? 'github';
        
        if (!in_array($source, ['github', 'gitlab', 'git', 'bundle'])) {
            return ['success' => false, 'error' => '不支持的导入源'];
        }
        
        $taskId = bin2hex(random_bytes(16));
        
        $sql = "INSERT INTO import_tasks (id, user_id, source, config, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $taskId,
            $userId,
            $source,
            json_encode($data),
            self::STATUS_PENDING,
        ]);
        
        $this->logger->info('导入任务创建', ['task_id' => $taskId, 'source' => $source]);
        
        return [
            'success' => true,
            'task_id' => $taskId,
        ];
    }
    
    /**
     * 执行导入任务
     */
    public function executeImportTask(string $taskId): array
    {
        $task = $this->db->fetchOne(
            "SELECT * FROM import_tasks WHERE id = ?",
            [$taskId]
        );
        
        if (!$task) {
            return ['success' => false, 'error' => '任务不存在'];
        }
        
        if ($task['status'] === self::STATUS_RUNNING) {
            return ['success' => false, 'error' => '任务正在执行中'];
        }
        
        // 更新状态
        $this->updateTaskStatus($taskId, self::STATUS_RUNNING);
        
        $config = json_decode($task['config'], true);
        
        try {
            $result = match ($task['source']) {
                'github' => $this->importFromGitHub($task['user_id'], $config, $taskId),
                'gitlab' => $this->importFromGitLab($task['user_id'], $config, $taskId),
                'git' => $this->importFromGit($task['user_id'], $config, $taskId),
                'bundle' => $this->importFromBundle($task['user_id'], $config, $taskId),
                default => ['success' => false, 'error' => '不支持的导入源'],
            };
            
            if ($result['success']) {
                $this->updateTaskStatus($taskId, self::STATUS_COMPLETED, $result);
            } else {
                $this->updateTaskStatus($taskId, self::STATUS_FAILED, $result);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->updateTaskStatus($taskId, self::STATUS_FAILED, ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 从 GitHub 导入
     */
    private function importFromGitHub(int $userId, array $config, string $taskId): array
    {
        $token = $config['token'] ?? '';
        $repoUrl = $config['repo_url'] ?? '';
        
        if (empty($repoUrl)) {
            return ['success' => false, 'error' => '仓库 URL 不能为空'];
        }
        
        // 解析仓库信息
        $repoInfo = $this->parseGitHubUrl($repoUrl);
        
        if (!$repoInfo) {
            return ['success' => false, 'error' => '无效的 GitHub URL'];
        }
        
        // 获取仓库信息
        $apiUrl = self::GITHUB_API . "/repos/{$repoInfo['owner']}/{$repoInfo['repo']}";
        
        $repoData = $this->githubApiRequest($apiUrl, $token);
        
        if (!$repoData) {
            return ['success' => false, 'error' => '无法获取仓库信息'];
        }
        
        $this->updateTaskProgress($taskId, 10, '获取仓库信息...');
        
        // 创建仓库
        $repoName = $config['name'] ?? $repoData['name'];
        $repoName = $this->security->sanitizeFilename($repoName);
        
        $sql = "INSERT INTO repositories (user_id, name, full_name, description, private, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $fullName = $this->getUserUsername($userId) . '/' . $repoName;
        
        $this->db->execute($sql, [
            $userId,
            $repoName,
            $fullName,
            $repoData['description'] ?? '',
            $config['private'] ?? false,
        ]);
        
        $repoId = (int)$this->db->lastInsertId();
        
        $this->updateTaskProgress($taskId, 20, '创建仓库...');
        
        // 克隆仓库
        $cloneUrl = $repoData['clone_url'];
        $repoPath = "/var/git/repositories/{$fullName}.git";
        
        $this->updateTaskProgress($taskId, 30, '克隆仓库...');
        
        $cloneResult = $this->cloneRepository($cloneUrl, $repoPath, $token);
        
        if (!$cloneResult['success']) {
            return ['success' => false, 'error' => '克隆失败: ' . $cloneResult['error']];
        }
        
        $this->updateTaskProgress($taskId, 50, '导入 Issues...');
        
        // 导入 Issues
        if ($config['import_issues'] ?? true) {
            $this->importGitHubIssues($repoId, $repoInfo['owner'], $repoInfo['repo'], $token, $taskId);
        }
        
        $this->updateTaskProgress($taskId, 80, '导入 Wiki...');
        
        // 导入 Wiki
        if (($config['import_wiki'] ?? false) && ($repoData['has_wiki'] ?? false)) {
            $this->importGitHubWiki($repoId, $repoInfo['owner'], $repoInfo['repo'], $token);
        }
        
        $this->updateTaskProgress($taskId, 100, '导入完成');
        
        return [
            'success' => true,
            'repo_id' => $repoId,
            'repo_name' => $fullName,
        ];
    }
    
    /**
     * 从 GitLab 导入
     */
    private function importFromGitLab(int $userId, array $config, string $taskId): array
    {
        $token = $config['token'] ?? '';
        $repoUrl = $config['repo_url'] ?? '';
        
        if (empty($repoUrl)) {
            return ['success' => false, 'error' => '仓库 URL 不能为空'];
        }
        
        // 解析 GitLab URL
        $repoInfo = $this->parseGitLabUrl($repoUrl);
        
        if (!$repoInfo) {
            return ['success' => false, 'error' => '无效的 GitLab URL'];
        }
        
        // 获取项目信息
        $projectPath = urlencode($repoInfo['path']);
        $apiUrl = self::GITLAB_API . "/projects/{$projectPath}";
        
        $projectData = $this->gitlabApiRequest($apiUrl, $token);
        
        if (!$projectData) {
            return ['success' => false, 'error' => '无法获取项目信息'];
        }
        
        $this->updateTaskProgress($taskId, 10, '获取项目信息...');
        
        // 创建仓库
        $repoName = $config['name'] ?? $projectData['path'];
        $repoName = $this->security->sanitizeFilename($repoName);
        
        $sql = "INSERT INTO repositories (user_id, name, full_name, description, private, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $fullName = $this->getUserUsername($userId) . '/' . $repoName;
        
        $this->db->execute($sql, [
            $userId,
            $repoName,
            $fullName,
            $projectData['description'] ?? '',
            $projectData['visibility'] === 'private',
        ]);
        
        $repoId = (int)$this->db->lastInsertId();
        
        $this->updateTaskProgress($taskId, 20, '创建仓库...');
        
        // 克隆仓库
        $cloneUrl = $projectData['http_url_to_repo'];
        $repoPath = "/var/git/repositories/{$fullName}.git";
        
        $this->updateTaskProgress($taskId, 30, '克隆仓库...');
        
        $cloneResult = $this->cloneRepository($cloneUrl, $repoPath, $token);
        
        if (!$cloneResult['success']) {
            return ['success' => false, 'error' => '克隆失败: ' . $cloneResult['error']];
        }
        
        $this->updateTaskProgress($taskId, 100, '导入完成');
        
        return [
            'success' => true,
            'repo_id' => $repoId,
            'repo_name' => $fullName,
        ];
    }
    
    /**
     * 从 Git URL 导入
     */
    private function importFromGit(int $userId, array $config, string $taskId): array
    {
        $cloneUrl = $config['url'] ?? '';
        
        if (empty($cloneUrl)) {
            return ['success' => false, 'error' => 'Git URL 不能为空'];
        }
        
        // 验证 URL
        if (!filter_var($cloneUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => '无效的 Git URL'];
        }
        
        // 只允许 https/git/ssh 协议
        $scheme = parse_url($cloneUrl, PHP_URL_SCHEME);
        if (!in_array($scheme, ['https', 'git', 'ssh'])) {
            return ['success' => false, 'error' => '只支持 https/git/ssh 协议'];
        }
        
        $this->updateTaskProgress($taskId, 10, '准备导入...');
        
        // 创建仓库
        $repoName = $config['name'] ?? $this->extractRepoName($cloneUrl);
        $repoName = $this->security->sanitizeFilename($repoName);
        
        $sql = "INSERT INTO repositories (user_id, name, full_name, description, private, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $fullName = $this->getUserUsername($userId) . '/' . $repoName;
        
        $this->db->execute($sql, [
            $userId,
            $repoName,
            $fullName,
            $config['description'] ?? '',
            $config['private'] ?? false,
        ]);
        
        $repoId = (int)$this->db->lastInsertId();
        
        $this->updateTaskProgress($taskId, 20, '创建仓库...');
        
        // 克隆仓库
        $repoPath = "/var/git/repositories/{$fullName}.git";
        
        $this->updateTaskProgress($taskId, 30, '克隆仓库...');
        
        $cloneResult = $this->cloneRepository($cloneUrl, $repoPath);
        
        if (!$cloneResult['success']) {
            return ['success' => false, 'error' => '克隆失败: ' . $cloneResult['error']];
        }
        
        $this->updateTaskProgress($taskId, 100, '导入完成');
        
        return [
            'success' => true,
            'repo_id' => $repoId,
            'repo_name' => $fullName,
        ];
    }
    
    /**
     * 从 Bundle 文件导入
     */
    private function importFromBundle(int $userId, array $config, string $taskId): array
    {
        $bundlePath = $config['file_path'] ?? '';
        
        if (empty($bundlePath) || !file_exists($bundlePath)) {
            return ['success' => false, 'error' => 'Bundle 文件不存在'];
        }
        
        $this->updateTaskProgress($taskId, 10, '准备导入...');
        
        // 创建仓库
        $repoName = $config['name'] ?? 'imported-repo';
        $repoName = $this->security->sanitizeFilename($repoName);
        
        $sql = "INSERT INTO repositories (user_id, name, full_name, description, private, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $fullName = $this->getUserUsername($userId) . '/' . $repoName;
        
        $this->db->execute($sql, [
            $userId,
            $repoName,
            $fullName,
            $config['description'] ?? '',
            $config['private'] ?? false,
        ]);
        
        $repoId = (int)$this->db->lastInsertId();
        
        $this->updateTaskProgress($taskId, 20, '创建仓库...');
        
        // 从 Bundle 恢复
        $repoPath = "/var/git/repositories/{$fullName}.git";
        
        $this->updateTaskProgress($taskId, 30, '恢复仓库...');
        
        // 初始化空仓库
        exec("git init --bare " . escapeshellarg($repoPath));
        
        // 从 Bundle 恢复
        $cmd = sprintf(
            'cd %s && git bundle unbundle %s',
            escapeshellarg($repoPath),
            escapeshellarg($bundlePath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Bundle 恢复失败'];
        }
        
        $this->updateTaskProgress($taskId, 100, '导入完成');
        
        return [
            'success' => true,
            'repo_id' => $repoId,
            'repo_name' => $fullName,
        ];
    }
    
    /**
     * 导出仓库
     */
    public function exportRepo(int $repoId, int $userId, string $format = 'bundle'): array
    {
        $repo = $this->db->fetchOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        // 检查权限
        if ($repo['user_id'] != $userId && !$this->hasWriteAccess($repoId, $userId)) {
            return ['success' => false, 'error' => '无权限导出'];
        }
        
        $repoPath = "/var/git/repositories/{$repo['full_name']}.git";
        
        if (!is_dir($repoPath)) {
            return ['success' => false, 'error' => '仓库目录不存在'];
        }
        
        $exportDir = "/tmp/exports/{$userId}";
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filename = $repo['name'] . '-' . date('Y-m-d-His');
        
        if ($format === 'bundle') {
            $exportPath = "{$exportDir}/{$filename}.bundle";
            
            $cmd = sprintf(
                'cd %s && git bundle create %s --all',
                escapeshellarg($repoPath),
                escapeshellarg($exportPath)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                return ['success' => false, 'error' => '导出失败'];
            }
            
            return [
                'success' => true,
                'file' => $exportPath,
                'filename' => "{$filename}.bundle",
                'size' => filesize($exportPath),
            ];
            
        } elseif ($format === 'tar') {
            $exportPath = "{$exportDir}/{$filename}.tar.gz";
            
            $cmd = sprintf(
                'tar -czf %s -C %s .',
                escapeshellarg($exportPath),
                escapeshellarg($repoPath)
            );
            
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                return ['success' => false, 'error' => '导出失败'];
            }
            
            return [
                'success' => true,
                'file' => $exportPath,
                'filename' => "{$filename}.tar.gz",
                'size' => filesize($exportPath),
            ];
        }
        
        return ['success' => false, 'error' => '不支持的导出格式'];
    }
    
    /**
     * 导出 Issues
     */
    public function exportIssues(int $repoId, int $userId): array
    {
        $repo = $this->db->fetchOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        // 检查权限
        if ($repo['user_id'] != $userId && !$this->hasReadAccess($repoId, $userId)) {
            return ['success' => false, 'error' => '无权限导出'];
        }
        
        $issues = $this->db->fetchAll(
            "SELECT i.*, u.username as author_name
             FROM issues i
             JOIN users u ON i.author_id = u.id
             WHERE i.repo_id = ?
             ORDER BY i.number",
            [$repoId]
        );
        
        // 获取评论
        foreach ($issues as &$issue) {
            $issue['comments'] = $this->db->fetchAll(
                "SELECT c.*, u.username as author_name
                 FROM comments c
                 JOIN users u ON c.author_id = u.id
                 WHERE c.issue_id = ?
                 ORDER BY c.created_at",
                [$issue['id']]
            );
        }
        
        $exportDir = "/tmp/exports/{$userId}";
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $filename = $repo['name'] . '-issues-' . date('Y-m-d-His') . '.json';
        $exportPath = "{$exportDir}/{$filename}";
        
        file_put_contents($exportPath, json_encode($issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return [
            'success' => true,
            'file' => $exportPath,
            'filename' => $filename,
            'count' => count($issues),
        ];
    }
    
    /**
     * 导入 Issues
     */
    public function importIssues(int $repoId, int $userId, string $filePath): array
    {
        $repo = $this->db->fetchOne(
            "SELECT * FROM repositories WHERE id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        // 检查权限
        if ($repo['user_id'] != $userId && !$this->hasWriteAccess($repoId, $userId)) {
            return ['success' => false, 'error' => '无权限导入'];
        }
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => '文件不存在'];
        }
        
        $content = file_get_contents($filePath);
        $issues = json_decode($content, true);
        
        if (!is_array($issues)) {
            return ['success' => false, 'error' => '无效的 JSON 格式'];
        }
        
        $imported = 0;
        
        foreach ($issues as $issueData) {
            $sql = "INSERT INTO issues (repo_id, title, body, state, author_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $this->db->execute($sql, [
                $repoId,
                $issueData['title'],
                $issueData['body'] ?? '',
                $issueData['state'] ?? 'open',
                $userId,
                $issueData['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
            
            $imported++;
        }
        
        return [
            'success' => true,
            'imported' => $imported,
        ];
    }
    
    /**
     * 获取任务状态
     */
    public function getTaskStatus(string $taskId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM import_tasks WHERE id = ?",
            [$taskId]
        );
    }
    
    /**
     * 更新任务状态
     */
    private function updateTaskStatus(string $taskId, string $status, array $result = null): void
    {
        $sql = "UPDATE import_tasks SET status = ?, result = ?, updated_at = NOW() WHERE id = ?";
        
        $this->db->execute($sql, [
            $status,
            $result ? json_encode($result) : null,
            $taskId,
        ]);
    }
    
    /**
     * 更新任务进度
     */
    private function updateTaskProgress(string $taskId, int $progress, string $message = ''): void
    {
        $sql = "UPDATE import_tasks SET progress = ?, message = ? WHERE id = ?";
        
        $this->db->execute($sql, [$progress, $message, $taskId]);
    }
    
    /**
     * 克隆仓库
     */
    private function cloneRepository(string $url, string $path, string $token = ''): array
    {
        // 如果有 token，添加到 URL
        if ($token && strpos($url, 'https://') === 0) {
            $url = str_replace('https://', "https://oauth2:{$token}@", $url);
        }
        
        $cmd = sprintf(
            'git clone --bare %s %s 2>&1',
            escapeshellarg($url),
            escapeshellarg($path)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => implode("\n", $output),
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * GitHub API 请求
     */
    private function githubApiRequest(string $url, string $token = ''): ?array
    {
        $ch = curl_init();
        
        $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: CodeVault'];
        
        if ($token) {
            $headers[] = "Authorization: token {$token}";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * GitLab API 请求
     */
    private function gitlabApiRequest(string $url, string $token = ''): ?array
    {
        $ch = curl_init();
        
        $headers = ['User-Agent: CodeVault'];
        
        if ($token) {
            $headers[] = "PRIVATE-TOKEN: {$token}";
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * 解析 GitHub URL
     */
    private function parseGitHubUrl(string $url): ?array
    {
        if (preg_match('#github\.com/([^/]+)/([^/]+)/?#', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
            ];
        }
        
        return null;
    }
    
    /**
     * 解析 GitLab URL
     */
    private function parseGitLabUrl(string $url): ?array
    {
        if (preg_match('#gitlab\.com/(.+)$#', $url, $matches)) {
            return [
                'path' => rtrim($matches[1], '/'),
            ];
        }
        
        return null;
    }
    
    /**
     * 从 URL 提取仓库名
     */
    private function extractRepoName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $name = basename($path, '.git');
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?: 'imported-repo';
    }
    
    /**
     * 获取用户名
     */
    private function getUserUsername(int $userId): string
    {
        $user = $this->db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]);
        return $user['username'] ?? 'unknown';
    }
    
    /**
     * 检查写权限
     */
    private function hasWriteAccess(int $repoId, int $userId): bool
    {
        $access = $this->db->fetchOne(
            "SELECT permission FROM repo_collaborators WHERE repo_id = ? AND user_id = ?",
            [$repoId, $userId]
        );
        
        return $access && in_array($access['permission'], ['admin', 'write']);
    }
    
    /**
     * 检查读权限
     */
    private function hasReadAccess(int $repoId, int $userId): bool
    {
        $repo = $this->db->fetchOne("SELECT private FROM repositories WHERE id = ?", [$repoId]);
        
        if (!$repo['private']) {
            return true;
        }
        
        return (bool)$this->db->fetchOne(
            "SELECT 1 FROM repo_collaborators WHERE repo_id = ? AND user_id = ?",
            [$repoId, $userId]
        );
    }
    
    /**
     * 导入 GitHub Issues
     */
    private function importGitHubIssues(int $repoId, string $owner, string $repo, string $token, string $taskId): int
    {
        $page = 1;
        $imported = 0;
        
        while (true) {
            $url = self::GITHUB_API . "/repos/{$owner}/{$repo}/issues?state=all&page={$page}&per_page=100";
            
            $issues = $this->githubApiRequest($url, $token);
            
            if (empty($issues)) {
                break;
            }
            
            foreach ($issues as $issue) {
                // 跳过 PR
                if (isset($issue['pull_request'])) {
                    continue;
                }
                
                $sql = "INSERT INTO issues (repo_id, number, title, body, state, author_id, created_at, closed_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $this->db->execute($sql, [
                    $repoId,
                    $issue['number'],
                    $issue['title'],
                    $issue['body'] ?? '',
                    $issue['state'],
                    0, // 系统用户
                    $issue['created_at'],
                    $issue['closed_at'],
                ]);
                
                $imported++;
            }
            
            $page++;
        }
        
        return $imported;
    }
    
    /**
     * 导入 GitHub Wiki
     */
    private function importGitHubWiki(int $repoId, string $owner, string $repo, string $token): bool
    {
        $wikiUrl = "https://github.com/{$owner}/{$repo}.wiki.git";
        $wikiPath = "/tmp/wiki-{$repoId}";
        
        $result = $this->cloneRepository($wikiUrl, $wikiPath, $token);
        
        if (!$result['success']) {
            return false;
        }
        
        // 导入 Markdown 文件
        $files = glob("{$wikiPath}/*.md");
        
        foreach ($files as $file) {
            $filename = basename($file, '.md');
            $content = file_get_contents($file);
            
            $sql = "INSERT INTO wiki_pages (repo_id, title, slug, content, format, author_id, created_at) 
                    VALUES (?, ?, ?, ?, 'markdown', 0, NOW())";
            
            $this->db->execute($sql, [
                $repoId,
                $filename,
                strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $filename)),
                $content,
            ]);
        }
        
        // 清理临时目录
        exec("rm -rf " . escapeshellarg($wikiPath));
        
        return true;
    }
}
