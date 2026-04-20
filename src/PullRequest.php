<?php
/**
 * PullRequest.php - Pull Request 管理类
 * 
 * 功能：PR 创建、列表、详情、合并、关闭
 */

require_once __DIR__ . '/Database/Connection.php';

class PullRequest 
{
    private $db;
    
    public function __construct() 
    {
        $this->db = Database\Connection::getInstance();
    }
    
    /**
     * 创建 Pull Request
     */
    public function create(int $repoId, int $authorId, string $title, string $description, 
                          string $sourceBranch, string $targetBranch): array 
    {
        // 验证仓库权限
        $repo = $this->getRepoById($repoId);
        if (!$repo) {
            return ['code' => 404, 'message' => '仓库不存在'];
        }
        
        // 验证分支是否存在
        if (!$this->branchExists($repoId, $sourceBranch) || !$this->branchExists($repoId, $targetBranch)) {
            return ['code' => 400, 'message' => '分支不存在'];
        }
        
        // 检查是否已存在相同 PR
        $existingPR = $this->db->fetch(
            "SELECT id FROM pull_requests WHERE repo_id = ? AND source_branch = ? 
             AND target_branch = ? AND status = 'open'",
            [$repoId, $sourceBranch, $targetBranch]
        );
        
        if ($existingPR) {
            return ['code' => 400, 'message' => '已存在相同的 Pull Request'];
        }
        
        $sql = "INSERT INTO pull_requests (repo_id, author_id, title, description, 
                source_branch, target_branch, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())";
        
        $prId = $this->db->insert($sql, [$repoId, $authorId, $title, $description, 
                                         $sourceBranch, $targetBranch]);
        
        return [
            'code' => 200,
            'message' => 'Pull Request 创建成功',
            'data' => [
                'id' => $prId,
                'repo_id' => $repoId,
                'author_id' => $authorId,
                'title' => $title,
                'source_branch' => $sourceBranch,
                'target_branch' => $targetBranch,
                'status' => 'open'
            ]
        ];
    }
    
    /**
     * 获取 PR 列表
     */
    public function list(int $repoId, string $status = null, int $page = 1, int $pageSize = 20): array 
    {
        $offset = ($page - 1) * $pageSize;
        $params = [$repoId];
        
        $sql = "SELECT pr.*, u.username as author_name 
                FROM pull_requests pr 
                JOIN users u ON pr.author_id = u.id 
                WHERE pr.repo_id = ?";
        
        if ($status) {
            $sql .= " AND pr.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY pr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $pageSize;
        $params[] = $offset;
        
        $prs = $this->db->fetchAll($sql, $params);
        
        // 获取总数
        $countSql = "SELECT COUNT(*) as total FROM pull_requests WHERE repo_id = ?";
        $countParams = [$repoId];
        if ($status) {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }
        $total = $this->db->fetch($countSql, $countParams)['total'];
        
        return [
            'code' => 200,
            'data' => [
                'items' => $prs,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize
            ]
        ];
    }
    
    /**
     * 获取 PR 详情
     */
    public function detail(int $prId): array 
    {
        $sql = "SELECT pr.*, u.username as author_name, r.name as repo_name, 
                       r.owner_id, u2.username as owner_name
                FROM pull_requests pr 
                JOIN users u ON pr.author_id = u.id 
                JOIN repositories r ON pr.repo_id = r.id
                JOIN users u2 ON r.owner_id = u2.id
                WHERE pr.id = ?";
        
        $pr = $this->db->fetch($sql, [$prId]);
        
        if (!$pr) {
            return ['code' => 404, 'message' => 'Pull Request 不存在'];
        }
        
        // 获取文件变更
        $changes = $this->getPRChanges($pr);
        $pr['changes'] = $changes;
        
        return [
            'code' => 200,
            'data' => $pr
        ];
    }
    
    /**
     * 合并 PR
     */
    public function merge(int $prId, int $userId): array 
    {
        $pr = $this->db->fetch("SELECT * FROM pull_requests WHERE id = ?", [$prId]);
        
        if (!$pr) {
            return ['code' => 404, 'message' => 'Pull Request 不存在'];
        }
        
        if ($pr['status'] !== 'open') {
            return ['code' => 400, 'message' => 'Pull Request 已关闭或已合并'];
        }
        
        // 验证权限（仓库所有者或协作者）
        $repo = $this->getRepoById($pr['repo_id']);
        if (!$this->canMerge($repo, $userId)) {
            return ['code' => 403, 'message' => '无权限合并此 Pull Request'];
        }
        
        // 执行 Git 合并
        $mergeResult = $this->executeMerge($pr);
        if (!$mergeResult['success']) {
            return ['code' => 400, 'message' => '合并失败: ' . $mergeResult['error']];
        }
        
        // 更新 PR 状态
        $this->db->execute(
            "UPDATE pull_requests SET status = 'merged', merged_at = NOW(), 
             merged_by = ? WHERE id = ?",
            [$userId, $prId]
        );
        
        return [
            'code' => 200,
            'message' => 'Pull Request 合并成功'
        ];
    }
    
    /**
     * 关闭 PR
     */
    public function close(int $prId, int $userId): array 
    {
        $pr = $this->db->fetch("SELECT * FROM pull_requests WHERE id = ?", [$prId]);
        
        if (!$pr) {
            return ['code' => 404, 'message' => 'Pull Request 不存在'];
        }
        
        if ($pr['status'] !== 'open') {
            return ['code' => 400, 'message' => 'Pull Request 已关闭或已合并'];
        }
        
        // 验证权限
        if ($pr['author_id'] !== $userId) {
            $repo = $this->getRepoById($pr['repo_id']);
            if (!$this->canMerge($repo, $userId)) {
                return ['code' => 403, 'message' => '无权限关闭此 Pull Request'];
            }
        }
        
        $this->db->execute(
            "UPDATE pull_requests SET status = 'closed' WHERE id = ?",
            [$prId]
        );
        
        return [
            'code' => 200,
            'message' => 'Pull Request 已关闭'
        ];
    }
    
    /**
     * 重新打开 PR
     */
    public function reopen(int $prId, int $userId): array 
    {
        $pr = $this->db->fetch("SELECT * FROM pull_requests WHERE id = ?", [$prId]);
        
        if (!$pr) {
            return ['code' => 404, 'message' => 'Pull Request 不存在'];
        }
        
        if ($pr['status'] !== 'closed') {
            return ['code' => 400, 'message' => '只能重新打开已关闭的 Pull Request'];
        }
        
        // 验证权限
        if ($pr['author_id'] !== $userId) {
            return ['code' => 403, 'message' => '无权限重新打开此 Pull Request'];
        }
        
        $this->db->execute(
            "UPDATE pull_requests SET status = 'open' WHERE id = ?",
            [$prId]
        );
        
        return [
            'code' => 200,
            'message' => 'Pull Request 已重新打开'
        ];
    }
    
    // 私有辅助方法
    
    private function getRepoById(int $repoId): ?array 
    {
        return $this->db->fetch("SELECT * FROM repositories WHERE id = ?", [$repoId]);
    }
    
    private function branchExists(int $repoId, string $branch): bool 
    {
        // TODO: 实际检查 Git 分支是否存在
        return true;
    }
    
    private function canMerge(array $repo, int $userId): bool 
    {
        // 仓库所有者可以合并
        if ($repo['owner_id'] === $userId) {
            return true;
        }
        
        // TODO: 检查协作者权限
        
        return false;
    }
    
    private function getPRChanges(array $pr): array 
    {
        // TODO: 获取实际的文件变更
        return [
            'additions' => 0,
            'deletions' => 0,
            'files' => []
        ];
    }
    
    private function executeMerge(array $pr): array 
    {
        // TODO: 执行实际的 Git 合并操作
        return ['success' => true];
    }
}
