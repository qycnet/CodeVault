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
        $repo = $this->getRepoById($repoId);
        if (!$repo || empty($repo['git_path'])) {
            return false;
        }
        
        $gitPath = realpath($repo['git_path']);
        if ($gitPath === false || !str_starts_with($gitPath, '/var/git/repositories/')) {
            return false;
        }
        
        // 使用 git show-ref 检查分支是否存在
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        $process = proc_open(
            ['git', 'show-ref', '--verify', '--quiet', 'refs/heads/' . $branch],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            return $exitCode === 0;
        }
        
        return false;
    }
    
    private function canMerge(array $repo, int $userId): bool 
    {
        // 仓库所有者可以合并
        if ($repo['owner_id'] === $userId) {
            return true;
        }
        
        // 检查协作者权限
        $collaborator = $this->db->fetch(
            "SELECT permission FROM collaborators WHERE repo_id = ? AND user_id = ?",
            [$repo['id'], $userId]
        );
        
        if ($collaborator && in_array($collaborator['permission'], ['write', 'admin'])) {
            return true;
        }
        
        return false;
    }
    
    private function getPRChanges(array $pr): array 
    {
        $repo = $this->getRepoById($pr['repo_id']);
        if (!$repo || empty($repo['git_path'])) {
            return ['additions' => 0, 'deletions' => 0, 'files' => []];
        }
        
        $gitPath = realpath($repo['git_path']);
        if ($gitPath === false || !str_starts_with($gitPath, '/var/git/repositories/')) {
            return ['additions' => 0, 'deletions' => 0, 'files' => []];
        }
        
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        // 获取两个分支之间的差异统计
        $process = proc_open(
            ['git', 'diff', '--numstat', $pr['target_branch'], $pr['source_branch']],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        $additions = 0;
        $deletions = 0;
        $files = [];
        
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            foreach (explode("\n", trim($output)) as $line) {
                if (empty($line)) continue;
                
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 3) {
                    $additions += (int)($parts[0] === '-' ? 0 : $parts[0]);
                    $deletions += (int)($parts[1] === '-' ? 0 : $parts[1]);
                    $files[] = $parts[2];
                }
            }
        }
        
        return [
            'additions' => $additions,
            'deletions' => $deletions,
            'files' => $files
        ];
    }
    
    private function executeMerge(array $pr): array 
    {
        $repo = $this->getRepoById($pr['repo_id']);
        if (!$repo || empty($repo['git_path'])) {
            return ['success' => false, 'error' => '仓库不存在'];
        }
        
        $gitPath = realpath($repo['git_path']);
        if ($gitPath === false || !str_starts_with($gitPath, '/var/git/repositories/')) {
            return ['success' => false, 'error' => '无效的 Git 路径'];
        }
        
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        // 切换到目标分支
        $process = proc_open(
            ['git', 'checkout', $pr['target_branch']],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        
        // 执行合并
        $process = proc_open(
            ['git', 'merge', '--no-ff', $pr['source_branch'], '-m', 'Merge branch \'' . $pr['source_branch'] . '\''],
            $descriptorspec,
            $pipes,
            $gitPath
        );
        
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            
            if ($exitCode === 0) {
                return ['success' => true, 'output' => $output];
            } else {
                // 合并失败，中止
                $abortProcess = proc_open(
                    ['git', 'merge', '--abort'],
                    $descriptorspec,
                    $pipes,
                    $gitPath
                );
                if (is_resource($abortProcess)) {
                    fclose($pipes[0]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($abortProcess);
                }
                
                return ['success' => false, 'error' => '合并冲突: ' . $error];
            }
        }
        
        return ['success' => false, 'error' => '无法执行合并操作'];
    }
}
