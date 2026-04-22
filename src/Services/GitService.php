<?php
/**
 * CodeVault - Git 操作服务
 * 处理 Git clone/push/pull 操作
 */

namespace CodeVault\Services;

use CodeVault\Models\Repository;
use CodeVault\Models\SshKey;

class GitService
{
    /**
     * 安全执行命令（使用 proc_open）
     */
    private static function executeCommand(array $command, ?string $cwd = null, ?array $env = null): array
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env);
        
        if (!is_resource($process)) {
            return ['success' => false, 'output' => '', 'error' => '无法启动进程'];
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnCode = proc_close($process);
        
        return [
            'success' => $returnCode === 0,
            'output' => trim($output),
            'error' => trim($error),
            'returnCode' => $returnCode,
        ];
    }
    
    /**
     * 获取仓库的 Git 命令前缀
     */
    private static function getGitCommand(string $repoPath, ?string $sshKey = null): string
    {
        $cmd = 'git';
        
        if ($sshKey) {
            // 使用 SSH Key
            $sshCmd = sprintf(
                'ssh -o StrictHostKeyChecking=no -i %s',
                escapeshellarg($sshKey)
            );
            $cmd = sprintf('GIT_SSH_COMMAND=%s git', escapeshellarg($sshCmd));
        }
        
        return $cmd;
    }
    
    /**
     * Clone 仓库到本地
     */
    public static function clone(string $repoUrl, string $localPath, ?int $sshKeyId = null): array
    {
        // 检查目标目录
        if (is_dir($localPath)) {
            return ['success' => false, 'message' => '目标目录已存在'];
        }
        
        // 获取 SSH Key（如果提供）
        $sshKeyPath = null;
        if ($sshKeyId) {
            $sshKey = SshKey::findById($sshKeyId);
            if (!$sshKey) {
                return ['success' => false, 'message' => 'SSH Key 不存在'];
            }
            // 写入临时私钥文件
            $sshKeyPath = sys_get_temp_dir() . '/cv_ssh_' . uniqid();
            file_put_contents($sshKeyPath, $sshKey['private_key']);
            chmod($sshKeyPath, 0600);
        }
        
        $gitCmd = self::getGitCommand($localPath, $sshKeyPath);
        
        // 使用 proc_open 安全执行
        $env = $sshKeyPath ? ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no -i ' . $sshKeyPath] : null;
        $result = self::executeCommand(['git', 'clone', $repoUrl, $localPath], null, $env);
        
        // 清理临时 SSH Key
        if ($sshKeyPath && file_exists($sshKeyPath)) {
            unlink($sshKeyPath);
        }
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Clone 失败: ' . $result['error'] . ' ' . $result['output'],
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Clone 成功',
            'path' => $localPath,
        ];
    }
    
    /**
     * Push 到远程仓库
     */
    public static function push(int $repoId, int $userId, string $branch = 'main', ?int $sshKeyId = null): array
    {
        // 检查仓库权限
        if (!Repository::isOwner($repoId, $userId)) {
            return ['success' => false, 'message' => '无权操作此仓库'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $repoPath = $repo['git_path'];
        if (!is_dir($repoPath)) {
            return ['success' => false, 'message' => 'Git 仓库目录不存在'];
        }
        
        // 获取 SSH Key
        $sshKeyPath = null;
        if ($sshKeyId) {
            $sshKey = SshKey::findById($sshKeyId);
            if (!$sshKey) {
                return ['success' => false, 'message' => 'SSH Key 不存在'];
            }
            $sshKeyPath = sys_get_temp_dir() . '/cv_ssh_' . uniqid();
            file_put_contents($sshKeyPath, $sshKey['private_key']);
            chmod($sshKeyPath, 0600);
        }
        
        $gitCmd = self::getGitCommand($repoPath, $sshKeyPath);
        
        // 使用 proc_open 安全执行
        $env = $sshKeyPath ? ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no -i ' . $sshKeyPath] : null;
        $result = self::executeCommand(['git', 'push', 'origin', $branch], $repoPath, $env);
        
        // 清理
        if ($sshKeyPath && file_exists($sshKeyPath)) {
            unlink($sshKeyPath);
        }
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Push 失败: ' . $result['error'] . ' ' . $result['output'],
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Push 成功',
            'branch' => $branch,
        ];
    }
    
    /**
     * Pull 从远程仓库
     */
    public static function pull(int $repoId, int $userId, string $branch = 'main', ?int $sshKeyId = null): array
    {
        // 检查仓库权限
        if (!Repository::canAccess($repoId, $userId)) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $repoPath = $repo['git_path'];
        if (!is_dir($repoPath)) {
            return ['success' => false, 'message' => 'Git 仓库目录不存在'];
        }
        
        // 获取 SSH Key
        $sshKeyPath = null;
        if ($sshKeyId) {
            $sshKey = SshKey::findById($sshKeyId);
            if (!$sshKey) {
                return ['success' => false, 'message' => 'SSH Key 不存在'];
            }
            $sshKeyPath = sys_get_temp_dir() . '/cv_ssh_' . uniqid();
            file_put_contents($sshKeyPath, $sshKey['private_key']);
            chmod($sshKeyPath, 0600);
        }
        
        $gitCmd = self::getGitCommand($repoPath, $sshKeyPath);
        
        // 使用 proc_open 安全执行
        $env = $sshKeyPath ? ['GIT_SSH_COMMAND' => 'ssh -o StrictHostKeyChecking=no -i ' . $sshKeyPath] : null;
        $result = self::executeCommand(['git', 'pull', 'origin', $branch], $repoPath, $env);
        
        // 清理
        if ($sshKeyPath && file_exists($sshKeyPath)) {
            unlink($sshKeyPath);
        }
        
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Pull 失败: ' . $result['error'] . ' ' . $result['output'],
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Pull 成功',
            'branch' => $branch,
        ];
    }
    
    /**
     * 获取仓库状态
     */
    public static function status(int $repoId, int $userId): array
    {
        if (!Repository::canAccess($repoId, $userId)) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $repoPath = $repo['git_path'];
        if (!is_dir($repoPath)) {
            return ['success' => false, 'message' => 'Git 仓库目录不存在'];
        }
        
        $result = self::executeCommand(['git', 'status', '--porcelain'], $repoPath);
        
        $output = $result['success'] ? $result['output'] : $result['error'];
        $lines = $result['success'] ? explode("\n", trim($result['output'])) : [];
        
        return [
            'success' => true,
            'status' => $output,
            'clean' => empty($lines) || ($lines === ['']),
        ];
    }
    
    /**
     * 获取提交历史
     */
    public static function log(int $repoId, int $userId, int $limit = 20): array
    {
        if (!Repository::canAccess($repoId, $userId)) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $repoPath = $repo['git_path'];
        if (!is_dir($repoPath)) {
            return ['success' => false, 'message' => 'Git 仓库目录不存在'];
        }
        
        $result = self::executeCommand(['git', 'log', '--oneline', '-n', (string) $limit], $repoPath);
        
        $commits = [];
        if ($result['success']) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                if (preg_match('/^([a-f0-9]+)\s+(.+)$/', $line, $matches)) {
                    $commits[] = [
                        'hash' => $matches[1],
                        'message' => $matches[2],
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'commits' => $commits,
        ];
    }
    
    /**
     * 获取分支列表
     */
    public static function branches(string $gitPath): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        $result = self::executeCommand(['git', 'branch', '-a'], $gitPath);
        
        $branches = [];
        if ($result['success']) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                $line = trim($line);
                // 跳过远程分支和当前分支标记
                if (empty($line) || strpos($line, 'remotes/') !== false) {
                    continue;
                }
                
                // 移除当前分支标记 (*)
                $branch = ltrim($line, '* ');
                
                if (!empty($branch)) {
                    $branches[] = $branch;
                }
            }
        }
        
        return [
            'code' => 200,
            'data' => $branches
        ];
    }
    
    /**
     * 获取提交历史
     */
    public static function commits(string $gitPath, string $branch = 'main', int $page = 0, int $perPage = 30): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        $skip = $page * $perPage;
        
        // 获取提交列表
        $result = self::executeCommand(
            ['git', 'log', $branch, '--format=%H|%h|%s|%an|%ae|%ci', '--skip=' . $skip, '-n', (string) $perPage],
            $gitPath
        );
        
        $commits = [];
        if ($result['success']) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (count($parts) >= 6) {
                    $commits[] = [
                        'full_hash' => $parts[0],
                        'hash' => $parts[1],
                        'message' => $parts[2],
                        'author_name' => $parts[3],
                        'author_email' => $parts[4],
                        'time' => $parts[5],
                        'author_avatar' => null // 可以使用 Gravatar
                    ];
                }
            }
        }
        
        return [
            'code' => 200,
            'data' => $commits
        ];
    }
    
    /**
     * 获取提交详情
     */
    public static function commit(string $gitPath, string $hash): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        // 获取提交信息
        $result = self::executeCommand(
            ['git', 'show', '--format=%H|%h|%s|%b|%an|%ae|%ci', '--no-patch', $hash],
            $gitPath
        );
        
        if (!$result['success'] || empty($result['output'])) {
            return ['code' => 404, 'message' => '提交不存在'];
        }
        
        $parts = explode('|', $output[0]);
        $commit = [
            'full_hash' => $parts[0] ?? '',
            'hash' => $parts[1] ?? '',
            'message' => $parts[2] ?? '',
            'full_message' => $parts[3] ?? '',
            'author_name' => $parts[4] ?? '',
            'author_email' => $parts[5] ?? '',
            'time' => $parts[6] ?? '',
            'files' => []
        ];
        
        // 获取文件变更
        $result = self::executeCommand(['git', 'show', '--name-status', '--format=', $hash], $gitPath);
        
        $files = [];
        if ($result['success']) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                if (preg_match('/^([AMD])\s+(.+)$/', trim($line), $matches)) {
                    $status = $matches[1];
                    $path = $matches[2];
                    
                    // 获取变更统计
                    $statusText = 'modified';
                    if ($status === 'A') $statusText = 'added';
                    if ($status === 'D') $statusText = 'deleted';
                    
                    $files[] = [
                        'status' => $statusText,
                        'path' => $path,
                        'additions' => 0, // 简化处理
                        'deletions' => 0
                    ];
                }
            }
        }
        
        $commit['files'] = $files;
        
        return [
            'code' => 200,
            'data' => $commit
        ];
    }
    
    /**
     * 获取分支最新提交
     */
    public static function branchCommit(string $gitPath, string $branch): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        $result = self::executeCommand(
            ['git', 'log', '-1', '--format=%H|%h|%s|%an|%ci', $branch],
            $gitPath
        );
        
        if (!$result['success'] || empty($result['output'])) {
            return ['code' => 404, 'message' => '分支不存在'];
        }
        
        $parts = explode('|', $result['output']);
        
        return [
            'code' => 200,
            'data' => [
                'full_hash' => $parts[0] ?? '',
                'hash' => $parts[1] ?? '',
                'message' => $parts[2] ?? '',
                'author_name' => $parts[3] ?? '',
                'time' => $parts[4] ?? ''
            ]
        ];
    }
    
    /**
     * 创建分支
     */
    public static function createBranch(string $gitPath, string $name, string $source): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        // 检查分支是否已存在
        $result = self::executeCommand(['git', 'show-ref', '--verify', '--quiet', 'refs/heads/' . $name], $gitPath);
        
        if ($result['returnCode'] === 0) {
            return ['code' => 400, 'message' => '分支已存在'];
        }
        
        // 创建分支
        $cmd = sprintf(
            'cd %s && git branch %s %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($name),
            escapeshellarg($source)
        );
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['code' => 500, 'message' => '创建分支失败: ' . implode("\n", $output)];
        }
        
        return [
            'code' => 200,
            'message' => '分支创建成功',
            'data' => ['name' => $name, 'source' => $source]
        ];
    }
    
    /**
     * 删除分支
     */
    public static function deleteBranch(string $gitPath, string $name): array
    {
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库目录不存在'];
        }
        
        // 禁止删除 main/master
        if ($name === 'main' || $name === 'master') {
            return ['code' => 400, 'message' => '不能删除默认分支'];
        }
        
        // 删除分支
        $result = self::executeCommand(['git', 'branch', '-D', $name], $gitPath);
        
        if (!$result['success']) {
            return ['code' => 500, 'message' => '删除分支失败: ' . $result['error']];
        }
        
        return [
            'code' => 200,
            'message' => '分支已删除'
        ];
    }
}
