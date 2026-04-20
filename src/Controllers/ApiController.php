<?php
/**
 * CodeVault - REST API 控制器
 * 提供完整的 RESTful API 接口
 */

namespace CodeVault\Controllers;

use CodeVault\Models\User;
use CodeVault\Models\Repository;
use CodeVault\Models\Issue;
use CodeVault\Models\BranchProtection;
use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class ApiController
{
    /**
     * API 文档
     */
    public function docs(array $data): array
    {
        return [
            'success' => true,
            'version' => '1.0.0',
            'endpoints' => [
                'auth' => [
                    'POST /api/auth/register' => '用户注册',
                    'POST /api/auth/login' => '用户登录',
                    'POST /api/auth/logout' => '用户登出',
                    'GET /api/auth/me' => '获取当前用户信息',
                ],
                'users' => [
                    'GET /api/users' => '获取用户列表（管理员）',
                    'GET /api/users/:id' => '获取用户详情',
                    'PUT /api/users/:id' => '更新用户信息',
                    'DELETE /api/users/:id' => '删除用户（管理员）',
                ],
                'repositories' => [
                    'GET /api/repos' => '获取仓库列表',
                    'POST /api/repos' => '创建仓库',
                    'GET /api/repos/:id' => '获取仓库详情',
                    'PUT /api/repos/:id' => '更新仓库',
                    'DELETE /api/repos/:id' => '删除仓库',
                    'GET /api/repos/:id/tree' => '获取文件树',
                    'GET /api/repos/:id/branches' => '获取分支列表',
                    'GET /api/repos/:id/commits' => '获取提交历史',
                ],
                'issues' => [
                    'GET /api/repos/:repo_id/issues' => '获取 Issue 列表',
                    'POST /api/repos/:repo_id/issues' => '创建 Issue',
                    'GET /api/repos/:repo_id/issues/:id' => '获取 Issue 详情',
                    'PUT /api/repos/:repo_id/issues/:id' => '更新 Issue',
                    'DELETE /api/repos/:repo_id/issues/:id' => '删除 Issue',
                ],
                'pull_requests' => [
                    'GET /api/repos/:repo_id/pulls' => '获取 PR 列表',
                    'POST /api/repos/:repo_id/pulls' => '创建 PR',
                    'GET /api/repos/:repo_id/pulls/:id' => '获取 PR 详情',
                    'POST /api/repos/:repo_id/pulls/:id/merge' => '合并 PR',
                    'POST /api/repos/:repo_id/pulls/:id/close' => '关闭 PR',
                ],
                'comments' => [
                    'GET /api/issues/:issue_id/comments' => '获取 Issue 评论',
                    'POST /api/issues/:issue_id/comments' => '添加 Issue 评论',
                    'GET /api/pulls/:pr_id/comments' => '获取 PR 评论',
                    'POST /api/pulls/:pr_id/comments' => '添加 PR 评论',
                ],
                'git' => [
                    'POST /api/repos/:id/git/clone' => '克隆仓库',
                    'POST /api/repos/:id/git/push' => '推送代码',
                    'POST /api/repos/:id/git/pull' => '拉取代码',
                    'GET /api/repos/:id/git/status' => '获取状态',
                    'GET /api/repos/:id/git/log' => '获取日志',
                ],
                'branch_protection' => [
                    'GET /api/repos/:id/protection' => '获取分支保护规则',
                    'POST /api/repos/:id/protection' => '创建保护规则',
                    'PUT /api/repos/:id/protection/:branch' => '更新保护规则',
                    'DELETE /api/repos/:id/protection/:branch' => '删除保护规则',
                ],
                'notifications' => [
                    'GET /api/notifications' => '获取通知列表',
                    'PUT /api/notifications/settings' => '更新通知设置',
                    'POST /api/notifications/test' => '发送测试邮件',
                ],
                'webhooks' => [
                    'GET /api/repos/:id/webhooks' => '获取 Webhook 列表',
                    'POST /api/repos/:id/webhooks' => '创建 Webhook',
                    'DELETE /api/repos/:id/webhooks/:id' => '删除 Webhook',
                ],
                'stars' => [
                    'GET /api/repos/:id/stargazers' => '获取 Star 列表',
                    'POST /api/repos/:id/star' => 'Star 仓库',
                    'DELETE /api/repos/:id/star' => '取消 Star',
                ],
                'forks' => [
                    'GET /api/repos/:id/forks' => '获取 Fork 列表',
                    'POST /api/repos/:id/fork' => 'Fork 仓库',
                ],
                'labels' => [
                    'GET /api/repos/:id/labels' => '获取标签列表',
                    'POST /api/repos/:id/labels' => '创建标签',
                    'PUT /api/repos/:id/labels/:name' => '更新标签',
                    'DELETE /api/repos/:id/labels/:name' => '删除标签',
                ],
                'milestones' => [
                    'GET /api/repos/:id/milestones' => '获取里程碑列表',
                    'POST /api/repos/:id/milestones' => '创建里程碑',
                    'PUT /api/repos/:id/milestones/:number' => '更新里程碑',
                    'DELETE /api/repos/:id/milestones/:number' => '删除里程碑',
                ],
                'releases' => [
                    'GET /api/repos/:id/releases' => '获取 Release 列表',
                    'POST /api/repos/:id/releases' => '创建 Release',
                    'GET /api/repos/:id/releases/:id' => '获取 Release 详情',
                    'DELETE /api/repos/:id/releases/:id' => '删除 Release',
                ],
                'organizations' => [
                    'GET /api/orgs' => '获取组织列表',
                    'POST /api/orgs' => '创建组织',
                    'GET /api/orgs/:org' => '获取组织详情',
                    'PUT /api/orgs/:org' => '更新组织',
                    'DELETE /api/orgs/:org' => '删除组织',
                    'GET /api/orgs/:org/members' => '获取组织成员',
                    'POST /api/orgs/:org/members' => '添加组织成员',
                    'DELETE /api/orgs/:org/members/:username' => '移除组织成员',
                ],
                'search' => [
                    'GET /api/search/repositories' => '搜索仓库',
                    'GET /api/search/issues' => '搜索 Issue',
                    'GET /api/search/users' => '搜索用户',
                    'GET /api/search/code' => '搜索代码',
                ],
            ],
        ];
    }
    
    /**
     * 获取用户列表（管理员）
     */
    public function listUsers(array $data): array
    {
        $user = Session::user();
        if (!$user || !$user['is_admin']) {
            return ['success' => false, 'message' => '无权访问'];
        }
        
        $page = (int) ($data['page'] ?? 1);
        $perPage = min((int) ($data['per_page'] ?? 30), 100);
        $offset = ($page - 1) * $perPage;
        
        $users = Connection::query(
            "SELECT id, username, email, created_at, is_admin FROM users ORDER BY id DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
        
        $total = Connection::queryOne("SELECT COUNT(*) as count FROM users")['count'];
        
        return [
            'success' => true,
            'users' => $users,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    
    /**
     * 获取用户详情
     */
    public function getUser(array $data): array
    {
        $userId = (int) ($data['id'] ?? 0);
        if ($userId <= 0) {
            return ['success' => false, 'message' => '无效的用户ID'];
        }
        
        $user = Connection::queryOne(
            "SELECT id, username, email, created_at, is_admin FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 获取用户的仓库数量
        $repoCount = Connection::queryOne(
            "SELECT COUNT(*) as count FROM repositories WHERE user_id = ?",
            [$userId]
        )['count'];
        
        return [
            'success' => true,
            'user' => $user,
            'stats' => [
                'repos' => (int) $repoCount,
            ],
        ];
    }
    
    /**
     * 搜索仓库
     */
    public function searchRepos(array $data): array
    {
        $query = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        $perPage = min((int) ($data['per_page'] ?? 30), 100);
        $offset = ($page - 1) * $perPage;
        
        $user = Session::user();
        $userId = $user ? $user['id'] : 0;
        
        $sql = "SELECT r.*, u.username as owner_name 
                FROM repositories r 
                JOIN users u ON r.user_id = u.id 
                WHERE (r.is_private = 0 OR r.user_id = ?)";
        
        $params = [$userId];
        
        if (!empty($query)) {
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        $sql .= " ORDER BY r.updated_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $repos = Connection::query($sql, $params);
        
        return [
            'success' => true,
            'items' => $repos,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    
    /**
     * 搜索 Issue
     */
    public function searchIssues(array $data): array
    {
        $query = trim($data['q'] ?? '');
        $repoId = (int) ($data['repo_id'] ?? 0);
        $page = (int) ($data['page'] ?? 1);
        $perPage = min((int) ($data['per_page'] ?? 30), 100);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT i.*, r.name as repo_name, u.username as author_name
                FROM issues i
                JOIN repositories r ON i.repo_id = r.id
                JOIN users u ON i.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if ($repoId > 0) {
            $sql .= " AND i.repo_id = ?";
            $params[] = $repoId;
        }
        
        if (!empty($query)) {
            $sql .= " AND (i.title LIKE ? OR i.content LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        $sql .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $issues = Connection::query($sql, $params);
        
        return [
            'success' => true,
            'items' => $issues,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    
    /**
     * 搜索用户
     */
    public function searchUsers(array $data): array
    {
        $query = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);
        $perPage = min((int) ($data['per_page'] ?? 30), 100);
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT id, username, email, created_at FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (username LIKE ? OR email LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $users = Connection::query($sql, $params);
        
        return [
            'success' => true,
            'items' => $users,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
    
    /**
     * 获取 Star 列表
     */
    public function listStargazers(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $stargazers = Connection::query(
            "SELECT s.*, u.username, u.email 
             FROM stars s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.repo_id = ? 
             ORDER BY s.created_at DESC",
            [$repoId]
        );
        
        return [
            'success' => true,
            'stargazers' => $stargazers,
        ];
    }
    
    /**
     * Star 仓库
     */
    public function starRepo(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        // 检查是否已 star
        $existing = Connection::queryOne(
            "SELECT * FROM stars WHERE repo_id = ? AND user_id = ?",
            [$repoId, $user['id']]
        );
        
        if ($existing) {
            return ['success' => true, 'message' => '已 star'];
        }
        
        Connection::insert(
            "INSERT INTO stars (repo_id, user_id, created_at) VALUES (?, ?, NOW())",
            [$repoId, $user['id']]
        );
        
        return ['success' => true, 'message' => 'Star 成功'];
    }
    
    /**
     * 取消 Star
     */
    public function unstarRepo(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        Connection::execute(
            "DELETE FROM stars WHERE repo_id = ? AND user_id = ?",
            [$repoId, $user['id']]
        );
        
        return ['success' => true, 'message' => '已取消 star'];
    }
    
    /**
     * 获取 Fork 列表
     */
    public function listForks(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $forks = Connection::query(
            "SELECT r.*, u.username as owner_name 
             FROM repositories r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.fork_from = ? 
             ORDER BY r.created_at DESC",
            [$repoId]
        );
        
        return [
            'success' => true,
            'forks' => $forks,
        ];
    }
    
    /**
     * Fork 仓库
     */
    public function forkRepo(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $repo = Repository::findById($repoId);
        if (!$repo) {
            return ['success' => false, 'message' => '仓库不存在'];
        }
        
        // 创建 Fork
        $forkName = $repo['name'];
        $forkPath = "/var/git/repositories/{$user['username']}/{$forkName}.git";
        
        // 检查是否已存在
        $existing = Repository::findByUserAndName($user['id'], $forkName);
        if ($existing) {
            return ['success' => false, 'message' => '您已有一个同名的仓库'];
        }
        
        // Git clone --bare
        $cmd = sprintf(
            'git clone --bare %s %s 2>&1',
            escapeshellarg($repo['git_path']),
            escapeshellarg($forkPath)
        );
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'message' => 'Fork 失败'];
        }
        
        // 创建数据库记录
        $forkId = Repository::create([
            'user_id' => $user['id'],
            'name' => $forkName,
            'description' => $repo['description'],
            'is_private' => 0,
            'git_path' => $forkPath,
        ]);
        
        // 记录 fork 关系
        Connection::execute(
            "UPDATE repositories SET fork_from = ? WHERE id = ?",
            [$repoId, $forkId]
        );
        
        return [
            'success' => true,
            'message' => 'Fork 成功',
            'fork_id' => $forkId,
        ];
    }
    
    /**
     * 获取标签列表
     */
    public function listLabels(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $labels = Connection::query(
            "SELECT * FROM labels WHERE repo_id = ? ORDER BY name",
            [$repoId]
        );
        
        return [
            'success' => true,
            'labels' => $labels,
        ];
    }
    
    /**
     * 创建标签
     */
    public function createLabel(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $color = trim($data['color'] ?? '#999999');
        
        if ($repoId <= 0 || empty($name)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        if (!Repository::isOwner($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $labelId = Connection::insert(
            "INSERT INTO labels (repo_id, name, color, created_at) VALUES (?, ?, ?, NOW())",
            [$repoId, $name, $color]
        );
        
        return [
            'success' => true,
            'label_id' => $labelId,
        ];
    }
    
    /**
     * 获取里程碑列表
     */
    public function listMilestones(array $data): array
    {
        $repoId = (int) ($data['repo_id'] ?? 0);
        if ($repoId <= 0) {
            return ['success' => false, 'message' => '无效的仓库ID'];
        }
        
        $milestones = Connection::query(
            "SELECT * FROM milestones WHERE repo_id = ? ORDER BY due_date",
            [$repoId]
        );
        
        return [
            'success' => true,
            'milestones' => $milestones,
        ];
    }
    
    /**
     * 创建里程碑
     */
    public function createMilestone(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $repoId = (int) ($data['repo_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $dueDate = $data['due_date'] ?? null;
        
        if ($repoId <= 0 || empty($title)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        if (!Repository::isOwner($repoId, $user['id'])) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $milestoneId = Connection::insert(
            "INSERT INTO milestones (repo_id, title, description, due_date, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$repoId, $title, $description, $dueDate]
        );
        
        return [
            'success' => true,
            'milestone_id' => $milestoneId,
        ];
    }
    
    /**
     * 获取组织列表
     */
    public function listOrgs(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $orgs = Connection::query(
            "SELECT o.*, 
                    (SELECT COUNT(*) FROM org_members WHERE org_id = o.id) as member_count
             FROM organizations o
             WHERE o.id IN (SELECT org_id FROM org_members WHERE user_id = ?)
             ORDER BY o.created_at DESC",
            [$user['id']]
        );
        
        return [
            'success' => true,
            'orgs' => $orgs,
        ];
    }
    
    /**
     * 创建组织
     */
    public function createOrg(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $name = trim($data['name'] ?? '');
        $displayName = trim($data['display_name'] ?? $name);
        $description = trim($data['description'] ?? '');
        
        if (empty($name)) {
            return ['success' => false, 'message' => '组织名称不能为空'];
        }
        
        // 检查名称是否已存在
        $existing = Connection::queryOne(
            "SELECT * FROM organizations WHERE name = ?",
            [$name]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => '组织名称已存在'];
        }
        
        $orgId = Connection::insert(
            "INSERT INTO organizations (name, display_name, description, created_at) VALUES (?, ?, ?, NOW())",
            [$name, $displayName, $description]
        );
        
        // 添加创建者为管理员
        Connection::insert(
            "INSERT INTO org_members (org_id, user_id, role, created_at) VALUES (?, ?, 'admin', NOW())",
            [$orgId, $user['id']]
        );
        
        return [
            'success' => true,
            'org_id' => $orgId,
        ];
    }
}
