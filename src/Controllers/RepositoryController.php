<?php
/**
 * CodeVault - 仓库控制器
 * 处理仓库的 CRUD 操作
 */

namespace CodeVault\Controllers;

use CodeVault\Models\Repository;
use CodeVault\Services\Session;

class RepositoryController
{
    /**
     * 获取当前用户的仓库列表
     */
    public function list(): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repos = Repository::findByUserId($user['id']);
        
        return [
            'success' => true,
            'repos' => array_map(function ($repo) {
                return [
                    'id' => $repo['id'],
                    'name' => $repo['name'],
                    'description' => $repo['description'],
                    'is_private' => (bool) $repo['is_private'],
                    'created_at' => $repo['created_at'],
                    'updated_at' => $repo['updated_at'],
                ];
            }, $repos),
        ];
    }
    
    /**
     * 创建新仓库
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $isPrivate = isset($data['is_private']) ? (int) $data['is_private'] : 0;
        
        // 参数验证
        if (empty($name)) {
            return ['success' => false, 'message' => '仓库名称不能为空'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            return ['success' => false, 'message' => '仓库名称只能包含字母、数字、下划线和连字符'];
        }
        
        if (strlen($name) > 100) {
            return ['success' => false, 'message' => '仓库名称不能超过100个字符'];
        }
        
        // 检查是否已存在同名仓库
        if (Repository::findByUserAndName($user['id'], $name)) {
            return ['success' => false, 'message' => '仓库名称已存在'];
        }
        
        // 生成 Git 路径
        $config = require __DIR__ . '/../../config/app.php';
        $basePath = $config['git']['repositories_path'];
        $gitPath = rtrim($basePath, '/') . '/' . $user['id'] . '/' . $name . '.git';
        
        // 创建数据库记录
        try {
            $repoId = Repository::create([
                'user_id' => $user['id'],
                'name' => $name,
                'description' => $description,
                'is_private' => $isPrivate,
                'git_path' => $gitPath,
            ]);
            
            // 初始化 Git 裸仓库
            $this->initGitRepository($gitPath);
            
            return [
                'success' => true,
                'message' => '仓库创建成功',
                'repo' => [
                    'id' => $repoId,
                    'name' => $name,
                    'description' => $description,
                    'is_private' => (bool) $isPrivate,
                    'git_path' => $gitPath,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => '创建失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 获取仓库详情
     */
    public function detail(array $data): array
    {
        $user = Session::user();
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 检查访问权限
        if (!$user || !Repository::canAccess($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        return [
            'success' => true,
            'repo' => [
                'id' => $repo['id'],
                'name' => $repo['name'],
                'description' => $repo['description'],
                'is_private' => (bool) $repo['is_private'],
                'git_path' => $repo['git_path'],
                'owner_name' => $repo['owner_name'],
                'created_at' => $repo['created_at'],
                'updated_at' => $repo['updated_at'],
            ],
        ];
    }
    
    /**
     * 更新仓库设置
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查是否是所有者
        if (!Repository::isOwner($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权修改此仓库'];
        }
        
        $updateData = [];
        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }
        if (isset($data['is_private'])) {
            $updateData['is_private'] = (int) $data['is_private'];
        }
        
        if (empty($updateData)) {
            return ['success' => false, 'message' => '没有需要更新的内容'];
        }
        
        $affected = Repository::update($repoId, $user['id'], $updateData);
        
        return $affected > 0
            ? ['success' => true, 'message' => '仓库更新成功']
            : ['success' => false, 'message' => '更新失败'];
    }
    
    /**
     * 删除仓库
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 获取仓库信息
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 检查是否是所有者
        if ($repo['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '无权删除此仓库'];
        }
        
        // 删除 Git 目录
        $this->deleteGitRepository($repo['git_path']);
        
        // 删除数据库记录
        $affected = Repository::delete($repoId, $user['id']);
        
        return $affected > 0
            ? ['success' => true, 'message' => '仓库已删除']
            : ['success' => false, 'message' => '删除失败'];
    }
    
    /**
     * 初始化 Git 裸仓库
     */
    private function initGitRepository(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 创建裸仓库
        exec(sprintf('git init --bare %s 2>&1', escapeshellarg($path)), $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \RuntimeException('Git 仓库初始化失败: ' . implode("\n", $output));
        }
    }
    
    /**
     * 删除 Git 仓库目录
     */
    private function deleteGitRepository(string $path): void
    {
        if (is_dir($path)) {
            exec(sprintf('rm -rf %s', escapeshellarg($path)));
        }
    }
    
    /**
     * 获取文件树
     */
    public function tree(array $data): array
    {
        $user = Session::user();
        $owner = trim($data['owner'] ?? '');
        $repoName = trim($data['repo'] ?? '');
        $branch = trim($data['branch'] ?? 'main');
        $path = trim($data['path'] ?? '');
        
        if (empty($owner) || empty($repoName)) {
            return ['code' => 400, 'message' => '参数错误'];
        }
        
        // 获取仓库
        $repo = \CodeVault\Models\Repository::findByOwnerAndName($owner, $repoName);
        if (!$repo) {
            return ['code' => 404, 'message' => '仓库不存在'];
        }
        
        // 检查访问权限
        if ($repo['is_private'] && (!$user || !\CodeVault\Models\Repository::canAccess($repo['id'], $user['id']))) {
            return ['code' => 403, 'message' => '无权访问'];
        }
        
        $gitPath = $repo['git_path'];
        if (!is_dir($gitPath)) {
            return ['code' => 404, 'message' => 'Git 仓库不存在'];
        }
        
        // 获取文件树
        $result = $this->getGitTree($gitPath, $branch, $path);
        
        return [
            'code' => 200,
            'data' => $result
        ];
    }
    
    /**
     * 获取 Git 文件树
     */
    private function getGitTree(string $gitPath, string $branch, string $path): array
    {
        // 获取指定分支的最新提交
        $cmd = sprintf(
            'cd %s && git rev-parse %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($branch)
        );
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['type' => 'dir', 'files' => []];
        }
        
        $commitHash = trim($output[0]);
        $treePath = empty($path) ? $commitHash : "$commitHash:$path";
        
        // 检查是文件还是目录
        $cmd = sprintf(
            'cd %s && git cat-file -t %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($treePath)
        );
        exec($cmd, $typeOutput, $typeReturn);
        
        $type = trim($typeOutput[0] ?? '');
        
        if ($type === 'blob') {
            // 是文件，返回文件内容
            $cmd = sprintf(
                'cd %s && git cat-file -p %s 2>&1',
                escapeshellarg($gitPath),
                escapeshellarg($treePath)
            );
            exec($cmd, $contentOutput);
            
            $content = implode("\n", $contentOutput);
            $lines = count($contentOutput);
            $size = strlen($content);
            
            return [
                'type' => 'file',
                'name' => basename($path),
                'content' => $content,
                'lines' => $lines,
                'size' => $size,
                'encoding' => 'UTF-8'
            ];
        }
        
        // 是目录，返回文件列表
        $cmd = sprintf(
            'cd %s && git ls-tree %s %s 2>&1',
            escapeshellarg($gitPath),
            escapeshellarg($commitHash),
            escapeshellarg($path)
        );
        exec($cmd, $lsOutput);
        
        $files = [];
        foreach ($lsOutput as $line) {
            if (preg_match('/^(\d+)\s+(\w+)\s+([a-f0-9]+)\s+(.+)$/', $line, $matches)) {
                $fileMode = $matches[1];
                $fileType = $matches[2];
                $fileHash = $matches[3];
                $fileName = $matches[4];
                
                $isDir = ($fileType === 'tree');
                
                // 获取文件大小和最后提交信息
                $fileSize = 0;
                $lastMessage = '';
                $lastTime = '';
                
                if (!$isDir) {
                    // 获取文件大小
                    $cmd = sprintf(
                        'cd %s && git cat-file -s %s 2>&1',
                        escapeshellarg($gitPath),
                        escapeshellarg($fileHash)
                    );
                    exec($cmd, $sizeOutput);
                    $fileSize = (int) ($sizeOutput[0] ?? 0);
                }
                
                // 获取最后提交信息
                $filePath = empty($path) ? $fileName : "$path/$fileName";
                $cmd = sprintf(
                    'cd %s && git log -1 --format="%%s|%%ci" %s -- %s 2>&1',
                    escapeshellarg($gitPath),
                    escapeshellarg($commitHash),
                    escapeshellarg($filePath)
                );
                exec($cmd, $logOutput);
                
                if (!empty($logOutput[0])) {
                    $parts = explode('|', $logOutput[0]);
                    $lastMessage = $parts[0] ?? '';
                    $lastTime = $parts[1] ?? '';
                }
                
                $files[] = [
                    'name' => $fileName,
                    'type' => $isDir ? 'dir' : 'file',
                    'size' => $fileSize,
                    'message' => $lastMessage,
                    'time' => $lastTime
                ];
            }
        }
        
        return [
            'type' => 'dir',
            'files' => $files
        ];
    }
}
