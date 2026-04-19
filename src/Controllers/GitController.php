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
}
