<?php
/**
 * CodeVault - 组织团队管理控制器
 */

namespace CodeVault\Controllers;

use CodeVault\Services\Session;
use CodeVault\Database\Connection;

class TeamController
{
    /**
     * 创建团队
     */
    public function create(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $orgId = (int) ($data['org_id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $permission = $data['permission'] ?? 'read'; // read, write, admin
        
        if ($orgId <= 0 || empty($name)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$orgId, $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权创建团队'];
        }
        
        $teamId = Connection::insert(
            "INSERT INTO teams (org_id, name, description, permission, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$orgId, $name, $description, $permission]
        );
        
        return ['success' => true, 'team_id' => $teamId];
    }
    
    /**
     * 获取组织团队列表
     */
    public function list(array $data): array
    {
        $orgId = (int) ($data['org_id'] ?? 0);
        
        if ($orgId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $teams = Connection::query(
            "SELECT t.*, 
                    (SELECT COUNT(*) FROM team_members WHERE team_id = t.id) as member_count,
                    (SELECT COUNT(*) FROM team_repos WHERE team_id = t.id) as repo_count
             FROM teams t
             WHERE t.org_id = ?
             ORDER BY t.created_at DESC",
            [$orgId]
        );
        
        return ['success' => true, 'teams' => $teams];
    }
    
    /**
     * 更新团队
     */
    public function update(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => false, 'message' => '团队不存在'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权修改团队'];
        }
        
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = trim($data['name']);
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = trim($data['description']);
        }
        
        if (isset($data['permission'])) {
            $fields[] = "permission = ?";
            $params[] = $data['permission'];
        }
        
        if (empty($fields)) {
            return ['success' => true, 'message' => '无更新'];
        }
        
        $params[] = $teamId;
        Connection::execute("UPDATE teams SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        
        return ['success' => true, 'message' => '团队已更新'];
    }
    
    /**
     * 删除团队
     */
    public function delete(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => true, 'message' => '已删除'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权删除团队'];
        }
        
        // 删除团队成员和仓库关联
        Connection::execute("DELETE FROM team_members WHERE team_id = ?", [$teamId]);
        Connection::execute("DELETE FROM team_repos WHERE team_id = ?", [$teamId]);
        Connection::execute("DELETE FROM teams WHERE id = ?", [$teamId]);
        
        return ['success' => true, 'message' => '团队已删除'];
    }
    
    /**
     * 添加团队成员
     */
    public function addMember(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        $role = $data['role'] ?? 'member'; // member, maintainer
        
        if ($teamId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => false, 'message' => '团队不存在'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权添加成员'];
        }
        
        // 检查用户是否是组织成员
        $orgMember = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ?",
            [$team['org_id'], $userId]
        );
        
        if (!$orgMember) {
            return ['success' => false, 'message' => '用户不是组织成员'];
        }
        
        // 检查是否已在团队
        $existing = Connection::queryOne(
            "SELECT * FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        if ($existing) {
            return ['success' => true, 'message' => '用户已在团队中'];
        }
        
        Connection::insert(
            "INSERT INTO team_members (team_id, user_id, role, added_at) VALUES (?, ?, ?, NOW())",
            [$teamId, $userId, $role]
        );
        
        return ['success' => true, 'message' => '成员已添加'];
    }
    
    /**
     * 移除团队成员
     */
    public function removeMember(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => true, 'message' => '已移除'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权移除成员'];
        }
        
        Connection::execute(
            "DELETE FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        return ['success' => true, 'message' => '成员已移除'];
    }
    
    /**
     * 获取团队成员列表
     */
    public function listMembers(array $data): array
    {
        $teamId = (int) ($data['team_id'] ?? 0);
        
        if ($teamId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $members = Connection::query(
            "SELECT tm.*, u.username, u.avatar_url, u.email
             FROM team_members tm
             JOIN users u ON tm.user_id = u.id
             WHERE tm.team_id = ?
             ORDER BY tm.added_at DESC",
            [$teamId]
        );
        
        return ['success' => true, 'members' => $members];
    }
    
    /**
     * 添加仓库到团队
     */
    public function addRepo(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        $repoId = (int) ($data['repo_id'] ?? 0);
        $permission = $data['permission'] ?? null; // 覆盖团队默认权限
        
        if ($teamId <= 0 || $repoId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => false, 'message' => '团队不存在'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        // 检查仓库是否属于组织
        $repo = Connection::queryOne("SELECT * FROM repositories WHERE id = ?", [$repoId]);
        if (!$repo || $repo['user_id'] !== $team['org_id']) {
            return ['success' => false, 'message' => '仓库不属于该组织'];
        }
        
        // 检查是否已关联
        $existing = Connection::queryOne(
            "SELECT * FROM team_repos WHERE team_id = ? AND repo_id = ?",
            [$teamId, $repoId]
        );
        
        if ($existing) {
            return ['success' => true, 'message' => '仓库已在团队中'];
        }
        
        Connection::insert(
            "INSERT INTO team_repos (team_id, repo_id, permission, added_at) VALUES (?, ?, ?, NOW())",
            [$teamId, $repoId, $permission ?? $team['permission']]
        );
        
        return ['success' => true, 'message' => '仓库已添加'];
    }
    
    /**
     * 移除仓库
     */
    public function removeRepo(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        $repoId = (int) ($data['repo_id'] ?? 0);
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => true, 'message' => '已移除'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        Connection::execute(
            "DELETE FROM team_repos WHERE team_id = ? AND repo_id = ?",
            [$teamId, $repoId]
        );
        
        return ['success' => true, 'message' => '仓库已移除'];
    }
    
    /**
     * 获取团队仓库列表
     */
    public function listRepos(array $data): array
    {
        $teamId = (int) ($data['team_id'] ?? 0);
        
        if ($teamId <= 0) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $repos = Connection::query(
            "SELECT tr.*, r.name, r.description, r.is_private
             FROM team_repos tr
             JOIN repositories r ON tr.repo_id = r.id
             WHERE tr.team_id = ?
             ORDER BY tr.added_at DESC",
            [$teamId]
        );
        
        return ['success' => true, 'repos' => $repos];
    }
    
    /**
     * 批量分配权限
     */
    public function batchAssignPermission(array $data): array
    {
        $user = Session::user();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        $teamId = (int) ($data['team_id'] ?? 0);
        $repoIds = $data['repo_ids'] ?? [];
        $permission = $data['permission'] ?? 'read';
        
        if ($teamId <= 0 || empty($repoIds)) {
            return ['success' => false, 'message' => '参数错误'];
        }
        
        $team = Connection::queryOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
        if (!$team) {
            return ['success' => false, 'message' => '团队不存在'];
        }
        
        // 检查权限
        $membership = Connection::queryOne(
            "SELECT * FROM org_members WHERE org_id = ? AND user_id = ? AND role = 'owner'",
            [$team['org_id'], $user['id']]
        );
        
        if (!$membership) {
            return ['success' => false, 'message' => '无权操作'];
        }
        
        $successCount = 0;
        
        foreach ($repoIds as $repoId) {
            $repoId = (int) $repoId;
            
            $existing = Connection::queryOne(
                "SELECT * FROM team_repos WHERE team_id = ? AND repo_id = ?",
                [$teamId, $repoId]
            );
            
            if ($existing) {
                Connection::execute(
                    "UPDATE team_repos SET permission = ? WHERE team_id = ? AND repo_id = ?",
                    [$permission, $teamId, $repoId]
                );
            } else {
                Connection::insert(
                    "INSERT INTO team_repos (team_id, repo_id, permission, added_at) VALUES (?, ?, ?, NOW())",
                    [$teamId, $repoId, $permission]
                );
            }
            
            $successCount++;
        }
        
        return [
            'success' => true,
            'message' => "成功分配 {$successCount} 个仓库权限",
        ];
    }
}
