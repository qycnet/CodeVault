<?php
/**
 * CodeVault - 分支保护模型
 */

namespace CodeVault\Models;

use CodeVault\Database\Connection;

class BranchProtection
{
    /**
     * 获取仓库的分支保护规则
     */
    public static function findByRepo(int $repoId): array
    {
        return Connection::query(
            "SELECT * FROM branch_protections WHERE repo_id = ? ORDER BY branch_name",
            [$repoId]
        );
    }
    
    /**
     * 获取特定分支的保护规则
     */
    public static function findByRepoAndBranch(int $repoId, string $branchName): ?array
    {
        return Connection::queryOne(
            "SELECT * FROM branch_protections WHERE repo_id = ? AND branch_name = ?",
            [$repoId, $branchName]
        );
    }
    
    /**
     * 创建分支保护规则
     */
    public static function create(array $data): int
    {
        return Connection::insert(
            "INSERT INTO branch_protections (
                repo_id, branch_name, require_pr, required_reviewers,
                dismiss_stale_reviews, require_status_checks, enforce_admins,
                allow_force_pushes, allow_deletions, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['repo_id'],
                $data['branch_name'],
                $data['require_pr'] ?? 1,
                $data['required_reviewers'] ?? 0,
                $data['dismiss_stale_reviews'] ?? 0,
                $data['require_status_checks'] ?? 0,
                $data['enforce_admins'] ?? 0,
                $data['allow_force_pushes'] ?? 0,
                $data['allow_deletions'] ?? 0,
            ]
        );
    }
    
    /**
     * 更新分支保护规则
     */
    public static function update(int $id, array $data): int
    {
        $fields = [];
        $params = [];
        
        if (isset($data['require_pr'])) {
            $fields[] = "require_pr = ?";
            $params[] = (int) $data['require_pr'];
        }
        
        if (isset($data['required_reviewers'])) {
            $fields[] = "required_reviewers = ?";
            $params[] = (int) $data['required_reviewers'];
        }
        
        if (isset($data['dismiss_stale_reviews'])) {
            $fields[] = "dismiss_stale_reviews = ?";
            $params[] = (int) $data['dismiss_stale_reviews'];
        }
        
        if (isset($data['require_status_checks'])) {
            $fields[] = "require_status_checks = ?";
            $params[] = (int) $data['require_status_checks'];
        }
        
        if (isset($data['enforce_admins'])) {
            $fields[] = "enforce_admins = ?";
            $params[] = (int) $data['enforce_admins'];
        }
        
        if (isset($data['allow_force_pushes'])) {
            $fields[] = "allow_force_pushes = ?";
            $params[] = (int) $data['allow_force_pushes'];
        }
        
        if (isset($data['allow_deletions'])) {
            $fields[] = "allow_deletions = ?";
            $params[] = (int) $data['allow_deletions'];
        }
        
        if (empty($fields)) {
            return 0;
        }
        
        $params[] = $id;
        
        return Connection::execute(
            "UPDATE branch_protections SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
    }
    
    /**
     * 删除分支保护规则
     */
    public static function delete(int $id): int
    {
        return Connection::execute(
            "DELETE FROM branch_protections WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * 检查分支是否受保护
     */
    public static function isProtected(int $repoId, string $branchName): bool
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule !== null;
    }
    
    /**
     * 检查是否需要 PR
     */
    public static function requiresPR(int $repoId, string $branchName): bool
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule && ($rule['require_pr'] ?? false);
    }
    
    /**
     * 获取所需审查者数量
     */
    public static function getRequiredReviewers(int $repoId, string $branchName): int
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule ? (int) $rule['required_reviewers'] : 0;
    }
    
    /**
     * 检查是否对管理员强制执行
     */
    public static function enforcesAdmins(int $repoId, string $branchName): bool
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule && ($rule['enforce_admins'] ?? false);
    }
    
    /**
     * 检查是否允许强制推送
     */
    public static function allowsForcePushes(int $repoId, string $branchName): bool
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule ? (bool) $rule['allow_force_pushes'] : true;
    }
    
    /**
     * 检查是否允许删除
     */
    public static function allowsDeletions(int $repoId, string $branchName): bool
    {
        $rule = self::findByRepoAndBranch($repoId, $branchName);
        return $rule ? (bool) $rule['allow_deletions'] : true;
    }
}
