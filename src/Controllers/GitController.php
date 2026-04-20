<?php
/**
 * CodeVault - Git 操作控制器
 * 处理 Git clone/push/pull 请求
 */

namespace CodeVault\Controllers;

use CodeVault\Services\GitService;
use CodeVault\Services\Session;

class GitController
{
    /**
     * Clone 仓库
     */
    public function clone(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoUrl = trim($data['repo_url'] ?? '');
        $localPath = trim($data['local_path'] ?? '');
        $sshKeyId = isset($data['ssh_key_id']) ? (int) $data['ssh_key_id'] : null;
        
        if (empty($repoUrl)) {
            return ['success' => false, 'message' => '仓库地址不能为空'];
        }
        
        if (empty($localPath)) {
            return ['success' => false, 'message' => '本地路径不能为空'];
        }
        
        // 验证 URL 格式
        if (!filter_var($repoUrl, FILTER_VALIDATE_URL) && !preg_match('/^git@.+\.git$/', $repoUrl)) {
            return ['success' => false, 'message' => '无效的仓库地址'];
        }
        
        return GitService::clone($repoUrl, $localPath, $sshKeyId);
    }
    
    /**
     * Push 到远程仓库
     */
    public function push(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $sshKeyId = isset($data['ssh_key_id']) ? (int) $data['ssh_key_id'] : null;
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        return GitService::push($repoId, $user['id'], $branch, $sshKeyId);
    }
    
    /**
     * Pull 从远程仓库
     */
    public function pull(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branch = trim($data['branch'] ?? 'main');
        $sshKeyId = isset($data['ssh_key_id']) ? (int) $data['ssh_key_id'] : null;
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        return GitService::pull($repoId, $user['id'], $branch, $sshKeyId);
    }
    
    /**
     * 获取仓库状态
     */
    public function status(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        return GitService::status($repoId, $user['id']);
    }
    
    /**
     * 获取提交历史
     */
    public function log(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $limit = (int) ($data['limit'] ?? 20);
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        return GitService::log($repoId, $user['id'], $limit);
    }
    
    /**
     * 获取分支列表
     */
    public function branches(array $data): array
    {
        $user = Session::user();
        $owner = trim($data['owner'] ?? '');
        $repoName = trim($data['repo'] ?? '');
        
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
        
        return GitService::branches($repo['git_path']);
    }
    
    /**
     * 获取提交历史
     */
    public function commits(array $data): array
    {
        $user = Session::user();
        $owner = trim($data['owner'] ?? '');
        $repoName = trim($data['repo'] ?? '');
        $branch = trim($data['branch'] ?? 'main');
        $page = (int) ($data['page'] ?? 0);
        $perPage = (int) ($data['per_page'] ?? 30);
        
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
        
        return GitService::commits($repo['git_path'], $branch, $page, $perPage);
    }
    
    /**
     * 获取提交详情
     */
    public function commit(array $data): array
    {
        $user = Session::user();
        $owner = trim($data['owner'] ?? '');
        $repoName = trim($data['repo'] ?? '');
        $hash = trim($data['hash'] ?? '');
        
        if (empty($owner) || empty($repoName) || empty($hash)) {
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
        
        return GitService::commit($repo['git_path'], $hash);
    }
}
