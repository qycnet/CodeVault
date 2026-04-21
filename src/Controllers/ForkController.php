<?php
/**
 * CodeVault - Fork 控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Services\GitService;
use CodeVault\Database\Connection;

class ForkController
{
    /**
     * Fork 仓库
     */
    public function fork(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $sourceRepoId = (int) ($data['repo_id'] ?? 0);
        $newName = trim($data['name'] ?? '');
        
        if ($sourceRepoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 获取源仓库
        $sourceRepo = Connection::queryOne(
            "SELECT r.*, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.id = ?",
            [$sourceRepoId]
        );
        
        if (!$sourceRepo) {
            return ['success' => false, 'message' => '源仓库不存在'];
        }
        
        // 检查权限（私有仓库不能 fork）
        if ($sourceRepo['is_private'] && $sourceRepo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权 Fork 私有仓库'];
        }
        
        // 确定新仓库名
        if (empty($newName)) {
            $newName = $sourceRepo['name'];
        }
        
        // 检查是否已存在同名仓库
        $existing = Connection::queryOne(
            "SELECT * FROM repositories WHERE user_id = ? AND name = ?",
            [$user['id'], $newName]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => '已存在同名仓库'];
        }
        
        // 创建新仓库记录
        $forkRepoId = Connection::insert(
            "INSERT INTO repositories (user_id, name, description, is_private, fork_from, created_at) VALUES (?, ?, ?, 0, ?, NOW())",
            [$user['id'], $newName, $sourceRepo['description'], $sourceRepoId]
        );
        
        // 创建 Git 仓库（clone 源仓库）
        $gitPath = "/var/git/repositories/{$user['id']}/{$forkRepoId}.git";
        $sourceGitPath = $sourceRepo['git_path'];
        
        // 确保目录存在
        $dir = dirname($gitPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Clone 源仓库
        $cmd = sprintf(
            'git clone --bare %s %s 2>&1',
            escapeshellarg($sourceGitPath),
            escapeshellarg($gitPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // 回滚数据库
            Connection::execute("DELETE FROM repositories WHERE id = ?", [$forkRepoId]);
            return ['success' => false, 'message' => 'Git Clone 失败'];
        }
        
        // 更新 git_path
        Connection::execute("UPDATE repositories SET git_path = ? WHERE id = ?", [$gitPath, $forkRepoId]);
        
        return [
            'success' => true,
            'repo_id' => $forkRepoId,
            'message' => 'Fork 成功',
        ];
    }
    
    /**
     * 获取 Fork 列表
     */
    public function listForks(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $forks = Connection::query(
            "SELECT r.id, r.name, r.created_at, u.username as owner_name
             FROM repositories r
             JOIN users u ON r.user_id = u.id
             WHERE r.fork_from = ?
             ORDER BY r.created_at DESC",
            [$repoId]
        );
        
        return ['success' => true, 'forks' => $forks];
    }
    
    /**
     * 获取 Fork 网络（上游/下游关系）
     */
    public function getForkNetwork(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        $network = [
            'upstream' => null,
            'current' => null,
            'forks' => [],
        ];
        
        // 获取当前仓库
        $repo = Connection::queryOne(
            "SELECT r.*, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        $network['current'] = [
            'id' => $repo['id'],
            'name' => $repo['name'],
            'owner' => $repo['owner_name'],
        ];
        
        // 获取上游仓库
        if ($repo['fork_from']) {
            $upstream = Connection::queryOne(
                "SELECT r.id, r.name, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.id = ?",
                [$repo['fork_from']]
            );
            
            if ($upstream) {
                $network['upstream'] = [
                    'id' => $upstream['id'],
                    'name' => $upstream['name'],
                    'owner' => $upstream['owner_name'],
                ];
            }
        }
        
        // 获取下游 Fork
        $forks = Connection::query(
            "SELECT r.id, r.name, u.username as owner_name FROM repositories r JOIN users u ON r.user_id = u.id WHERE r.fork_from = ?",
            [$repoId]
        );
        
        $network['forks'] = array_map(function($f) {
            return [
                'id' => $f['id'],
                'name' => $f['name'],
                'owner' => $f['owner_name'],
            ];
        }, $forks);
        
        return ['success' => true, 'network' => $network];
    }
}
