<?php
/**
 * CodeVault - 分支保护控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Models\Repository;
use CodeVault\Models\BranchProtection;
use CodeVault\Services\Session;

class BranchProtectionController
{
    /**
     * 获取仓库的分支保护规则列表
     */
    public function list(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canAccess($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $rules = BranchProtection::findByRepo($repoId);
        
        return [
            'success' => true,
            'rules' => array_map(function ($rule) {
                return [
                    'id' => (int) $rule['id'],
                    'branch_name' => $rule['branch_name'],
                    'require_pr' => (bool) $rule['require_pr'],
                    'required_reviewers' => (int) $rule['required_reviewers'],
                    'dismiss_stale_reviews' => (bool) $rule['dismiss_stale_reviews'],
                    'require_status_checks' => (bool) $rule['require_status_checks'],
                    'enforce_admins' => (bool) $rule['enforce_admins'],
                    'allow_force_pushes' => (bool) $rule['allow_force_pushes'],
                    'allow_deletions' => (bool) $rule['allow_deletions'],
                    'created_at' => $rule['created_at'],
                ];
            }, $rules),
        ];
    }
    
    /**
     * 获取特定分支的保护规则
     */
    public function detail(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branchName = trim($data['branch_name'] ?? '');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        if (empty($branchName)) {
            return ['success' => false, 'message' => '分支名称不能为空'];
        }
        
        // 检查仓库权限
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        if (!Repository::canAccess($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权访问此仓库'];
        }
        
        $rule = BranchProtection::findByRepoAndBranch($repoId, $branchName);
        
        if (!$rule) {
            return [
                'success' => true,
                'rule' => null,
                'message' => '该分支未设置保护规则',
            ];
        }
        
        return [
            'success' => true,
            'rule' => [
                'id' => (int) $rule['id'],
                'branch_name' => $rule['branch_name'],
                'require_pr' => (bool) $rule['require_pr'],
                'required_reviewers' => (int) $rule['required_reviewers'],
                'dismiss_stale_reviews' => (bool) $rule['dismiss_stale_reviews'],
                'require_status_checks' => (bool) $rule['require_status_checks'],
                'enforce_admins' => (bool) $rule['enforce_admins'],
                'allow_force_pushes' => (bool) $rule['allow_force_pushes'],
                'allow_deletions' => (bool) $rule['allow_deletions'],
                'created_at' => $rule['created_at'],
            ],
        ];
    }
    
    /**
     * 创建分支保护规则
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $branchName = trim($data['branch_name'] ?? '');
        
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        if (empty($branchName)) {
            return ['success' => false, 'message' => '分支名称不能为空'];
        }
        
        // 检查是否是仓库所有者
        if (!Repository::isOwner($repoId, $user['id'])) {
            return ['success' => false, 'message' => '只有仓库所有者可以设置分支保护'];
        }
        
        // 检查是否已存在
        $existing = BranchProtection::findByRepoAndBranch($repoId, $branchName);
        if ($existing) {
            return ['success' => false, 'message' => '该分支已存在保护规则'];
        }
        
        // 创建规则
        $ruleId = BranchProtection::create([
            'repo_id' => $repoId,
            'branch_name' => $branchName,
            'require_pr' => (int) ($data['require_pr'] ?? 1),
            'required_reviewers' => (int) ($data['required_reviewers'] ?? 0),
            'dismiss_stale_reviews' => (int) ($data['dismiss_stale_reviews'] ?? 0),
            'require_status_checks' => (int) ($data['require_status_checks'] ?? 0),
            'enforce_admins' => (int) ($data['enforce_admins'] ?? 0),
            'allow_force_pushes' => (int) ($data['allow_force_pushes'] ?? 0),
            'allow_deletions' => (int) ($data['allow_deletions'] ?? 0),
        ]);
        
        return [
            'success' => true,
            'message' => '分支保护规则创建成功',
            'rule_id' => $ruleId,
        ];
    }
    
    /**
     * 更新分支保护规则
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $ruleId = (int) ($data['rule_id'] ?? 0);
        if ($ruleId <= 0) {
            return ['success' => false, 'message' => '无效的规则ID'];
        }
        
        // 获取规则信息
        $rule = Connection::queryOne(
            "SELECT bp.*, r.user_id FROM branch_protections bp JOIN repositories r ON bp.repo_id = r.id WHERE bp.id = ?",
            [$ruleId]
        );
        
        if (!$rule) {
            return ['success' => false, 'message' => '规则不存在'];
        }
        
        // 检查是否是仓库所有者
        if ($rule['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '只有仓库所有者可以修改分支保护'];
        }
        
        // 更新规则
        $affected = BranchProtection::update($ruleId, $data);
        
        return $affected > 0
            ? ['success' => true, 'message' => '分支保护规则更新成功']
            : ['success' => false, 'message' => '更新失败'];
    }
    
    /**
     * 删除分支保护规则
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $ruleId = (int) ($data['rule_id'] ?? 0);
        if ($ruleId <= 0) {
            return ['success' => false, 'message' => '无效的规则ID'];
        }
        
        // 获取规则信息
        $rule = Connection::queryOne(
            "SELECT bp.*, r.user_id FROM branch_protections bp JOIN repositories r ON bp.repo_id = r.id WHERE bp.id = ?",
            [$ruleId]
        );
        
        if (!$rule) {
            return ['success' => false, 'message' => '规则不存在'];
        }
        
        // 检查是否是仓库所有者
        if ($rule['user_id'] !== $user['id']) {
            return ['success' => false, 'message' => '只有仓库所有者可以删除分支保护'];
        }
        
        $affected = BranchProtection::delete($ruleId);
        
        return $affected > 0
            ? ['success' => true, 'message' => '分支保护规则已删除']
            : ['success' => false, 'message' => '删除失败'];
    }
}

use CodeVault\Database\Connection;
