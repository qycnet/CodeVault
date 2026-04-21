<?php
/**
 * CodeVault - 项目管理服务
 * 
 * 提供项目管理（Kanban看板）的核心业务逻辑
 */

namespace CodeVault\Services;

use PDO;
use Exception;

class ProjectService
{
    private PDO $pdo;
    private CacheService $cache;
    
    public function __construct(PDO $pdo, ?CacheService $cache = null)
    {
        $this->pdo = $pdo;
        $this->cache = $cache ?? new CacheService();
    }
    
    /**
     * 创建项目
     */
    public function createProject(array $data): array
    {
        $this->validateProjectData($data);
        
        $this->pdo->beginTransaction();
        
        try {
            // 创建项目
            $stmt = $this->pdo->prepare(
                "INSERT INTO projects (repository_id, user_id, team_id, name, description, body, visibility, view_type, start_date, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([
                $data['repository_id'] ?? null,
                $data['user_id'] ?? null,
                $data['team_id'] ?? null,
                $data['name'],
                $data['description'] ?? null,
                $data['body'] ?? null,
                $data['visibility'] ?? 'public',
                $data['view_type'] ?? 'kanban',
                $data['start_date'] ?? null,
                $data['due_date'] ?? null
            ]);
            
            $projectId = (int)$this->pdo->lastInsertId();
            
            // 创建默认列
            $this->createDefaultColumns($projectId);
            
            // 添加创建者为管理员
            if (!empty($data['user_id'])) {
                $this->addMember($projectId, $data['user_id'], 'admin');
            }
            
            $this->pdo->commit();
            
            return $this->getProject($projectId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * 创建默认列
     */
    private function createDefaultColumns(int $projectId): void
    {
        $defaultColumns = [
            ['name' => '📋 待办', 'color' => '#6b7280', 'position' => 0],
            ['name' => '🔄 进行中', 'color' => '#3b82f6', 'position' => 1],
            ['name' => '👀 待审核', 'color' => '#f59e0b', 'position' => 2],
            ['name' => '✅ 完成', 'color' => '#10b981', 'position' => 3],
        ];
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_columns (project_id, name, color, position, is_default) VALUES (?, ?, ?, ?, 1)"
        );
        
        foreach ($defaultColumns as $column) {
            $stmt->execute([
                $projectId,
                $column['name'],
                $column['color'],
                $column['position']
            ]);
        }
    }
    
    /**
     * 获取项目详情
     */
    public function getProject(int $id): ?array
    {
        $cacheKey = "project:{$id}";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT p.*, 
                    r.name as repo_name, r.owner_id,
                    u.username as creator_name
             FROM projects p
             LEFT JOIN repositories r ON p.repository_id = r.id
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            return null;
        }
        
        // 获取列统计
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.color, c.position,
                    COUNT(pc.id) as card_count
             FROM project_columns c
             LEFT JOIN project_cards pc ON c.id = pc.column_id AND pc.is_archived = 0
             WHERE c.project_id = ?
             GROUP BY c.id
             ORDER BY c.position"
        );
        $stmt->execute([$id]);
        $project['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 计算进度
        $totalCards = 0;
        $completedCards = 0;
        foreach ($project['columns'] as $column) {
            $totalCards += $column['card_count'];
            if (strpos($column['name'], '完成') !== false || strpos($column['name'], '✅') !== false) {
                $completedCards = $column['card_count'];
            }
        }
        $project['progress'] = $totalCards > 0 ? round(($completedCards / $totalCards) * 100) : 0;
        
        $this->cache->set($cacheKey, $project, 300);
        
        return $project;
    }
    
    /**
     * 获取项目列表
     */
    public function getProjects(array $filters = [], array $pagination = []): array
    {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['repository_id'])) {
            $where[] = "p.repository_id = ?";
            $params[] = $filters['repository_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "p.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['state'])) {
            $where[] = "p.state = ?";
            $params[] = $filters['state'];
        }
        
        // 分页
        $page = $pagination['page'] ?? 1;
        $perPage = $pagination['per_page'] ?? 20;
        $offset = ($page - 1) * $perPage;
        
        // 查询总数
        $countSql = "SELECT COUNT(*) FROM projects p WHERE " . implode(" AND ", $where);
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // 查询列表
        $sql = "SELECT p.*, 
                       r.name as repo_name,
                       u.username as creator_name,
                       (SELECT COUNT(*) FROM project_cards pc WHERE pc.column_id IN 
                        (SELECT id FROM project_columns WHERE project_id = p.id) AND pc.is_archived = 0
                       ) as card_count
                FROM projects p
                LEFT JOIN repositories r ON p.repository_id = r.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY p.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'items' => $projects,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * 更新项目
     */
    public function updateProject(int $id, int $userId, array $data): array
    {
        $project = $this->getProject($id);
        if (!$project) {
            throw new Exception("Project not found", 404);
        }
        
        if (!$this->canEdit($project, $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        $updates = [];
        $params = [];
        
        foreach (['name', 'description', 'body', 'visibility', 'view_type', 'start_date', 'due_date'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['state'])) {
            $updates[] = "state = ?";
            $params[] = $data['state'];
            
            if ($data['state'] === 'closed') {
                $updates[] = "closed_at = NOW()";
            } else {
                $updates[] = "closed_at = NULL";
            }
        }
        
        if (empty($updates)) {
            return $project;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE projects SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $this->cache->delete("project:{$id}");
        
        return $this->getProject($id);
    }
    
    /**
     * 删除项目
     */
    public function deleteProject(int $id, int $userId): bool
    {
        $project = $this->getProject($id);
        if (!$project) {
            throw new Exception("Project not found", 404);
        }
        
        if (!$this->canDelete($project, $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->cache->delete("project:{$id}");
        
        return true;
    }
    
    // ==================== 列管理 ====================
    
    /**
     * 创建列
     */
    public function createColumn(int $projectId, array $data): array
    {
        // 获取最大位置
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(position), -1) FROM project_columns WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $maxPosition = (int)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_columns (project_id, name, color, position, wip_limit) VALUES (?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $projectId,
            $data['name'],
            $data['color'] ?? '#0366d6',
            $maxPosition + 1,
            $data['wip_limit'] ?? null
        ]);
        
        $columnId = (int)$this->pdo->lastInsertId();
        
        $this->cache->delete("project:{$projectId}");
        
        return $this->getColumn($columnId);
    }
    
    /**
     * 获取列详情
     */
    public function getColumn(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM project_columns WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 更新列
     */
    public function updateColumn(int $id, array $data): array
    {
        $column = $this->getColumn($id);
        if (!$column) {
            throw new Exception("Column not found", 404);
        }
        
        $updates = [];
        $params = [];
        
        foreach (['name', 'color', 'position', 'wip_limit'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $column;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE project_columns SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $this->cache->delete("project:{$column['project_id']}");
        
        return $this->getColumn($id);
    }
    
    /**
     * 删除列
     */
    public function deleteColumn(int $id): bool
    {
        $column = $this->getColumn($id);
        if (!$column) {
            throw new Exception("Column not found", 404);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM project_columns WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->cache->delete("project:{$column['project_id']}");
        
        return true;
    }
    
    // ==================== 卡片管理 ====================
    
    /**
     * 创建卡片
     */
    public function createCard(int $columnId, int $userId, array $data): array
    {
        $column = $this->getColumn($columnId);
        if (!$column) {
            throw new Exception("Column not found", 404);
        }
        
        // 获取最大位置
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(position), -1) FROM project_cards WHERE column_id = ?");
        $stmt->execute([$columnId]);
        $maxPosition = (int)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_cards (column_id, issue_id, pull_request_id, title, body, position, priority, estimated_hours, due_date, created_by, assigned_to)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $columnId,
            $data['issue_id'] ?? null,
            $data['pull_request_id'] ?? null,
            $data['title'] ?? null,
            $data['body'] ?? null,
            $maxPosition + 1,
            $data['priority'] ?? 'medium',
            $data['estimated_hours'] ?? null,
            $data['due_date'] ?? null,
            $userId,
            $data['assigned_to'] ?? null
        ]);
        
        $cardId = (int)$this->pdo->lastInsertId();
        
        // 记录活动
        $this->logActivity($column['project_id'], $cardId, $userId, 'created', $data);
        
        $this->cache->delete("project:{$column['project_id']}");
        
        return $this->getCard($cardId);
    }
    
    /**
     * 获取卡片详情
     */
    public function getCard(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pc.*, 
                    c.name as column_name, c.color as column_color, c.project_id,
                    i.title as issue_title, i.number as issue_number,
                    pr.title as pr_title, pr.number as pr_number,
                    u.username as creator_name, u.avatar_url as creator_avatar,
                    a.username as assignee_name, a.avatar_url as assignee_avatar
             FROM project_cards pc
             JOIN project_columns c ON pc.column_id = c.id
             LEFT JOIN issues i ON pc.issue_id = i.id
             LEFT JOIN pull_requests pr ON pc.pull_request_id = pr.id
             LEFT JOIN users u ON pc.created_by = u.id
             LEFT JOIN users a ON pc.assigned_to = a.id
             WHERE pc.id = ?"
        );
        $stmt->execute([$id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$card) {
            return null;
        }
        
        // 获取标签
        $stmt = $this->pdo->prepare(
            "SELECT l.* FROM labels l
             JOIN project_card_labels pcl ON l.id = pcl.label_id
             WHERE pcl.card_id = ?"
        );
        $stmt->execute([$id]);
        $card['labels'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取检查项
        $stmt = $this->pdo->prepare(
            "SELECT cl.*, 
                    (SELECT COUNT(*) FROM project_card_checklist_items cli WHERE cli.checklist_id = cl.id) as total_items,
                    (SELECT COUNT(*) FROM project_card_checklist_items cli WHERE cli.checklist_id = cl.id AND cli.is_completed = 1) as completed_items
             FROM project_card_checklists cl
             WHERE cl.card_id = ?
             ORDER BY cl.position"
        );
        $stmt->execute([$id]);
        $card['checklists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $card;
    }
    
    /**
     * 获取列的所有卡片
     */
    public function getColumnCards(int $columnId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pc.*, 
                    u.username as creator_name, u.avatar_url as creator_avatar,
                    a.username as assignee_name, a.avatar_url as assignee_avatar
             FROM project_cards pc
             LEFT JOIN users u ON pc.created_by = u.id
             LEFT JOIN users a ON pc.assigned_to = a.id
             WHERE pc.column_id = ? AND pc.is_archived = 0
             ORDER BY pc.position"
        );
        $stmt->execute([$columnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新卡片
     */
    public function updateCard(int $id, int $userId, array $data): array
    {
        $card = $this->getCard($id);
        if (!$card) {
            throw new Exception("Card not found", 404);
        }
        
        $updates = [];
        $params = [];
        
        foreach (['title', 'body', 'priority', 'estimated_hours', 'actual_hours', 'due_date', 'assigned_to'] as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $card;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE project_cards SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // 记录活动
        $this->logActivity($card['project_id'], $id, $userId, 'updated', $data);
        
        $this->cache->delete("project:{$card['project_id']}");
        
        return $this->getCard($id);
    }
    
    /**
     * 移动卡片
     */
    public function moveCard(int $id, int $userId, int $targetColumnId, int $position): array
    {
        $card = $this->getCard($id);
        if (!$card) {
            throw new Exception("Card not found", 404);
        }
        
        $targetColumn = $this->getColumn($targetColumnId);
        if (!$targetColumn) {
            throw new Exception("Target column not found", 404);
        }
        
        // 更新卡片位置
        $stmt = $this->pdo->prepare("UPDATE project_cards SET column_id = ?, position = ? WHERE id = ?");
        $stmt->execute([$targetColumnId, $position]);
        
        // 记录活动
        $this->logActivity($card['project_id'], $id, $userId, 'moved', [
            'from_column' => $card['column_name'],
            'to_column' => $targetColumn['name']
        ]);
        
        $this->cache->delete("project:{$card['project_id']}");
        
        return $this->getCard($id);
    }
    
    /**
     * 归档卡片
     */
    public function archiveCard(int $id, int $userId): bool
    {
        $card = $this->getCard($id);
        if (!$card) {
            throw new Exception("Card not found", 404);
        }
        
        $stmt = $this->pdo->prepare("UPDATE project_cards SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->logActivity($card['project_id'], $id, $userId, 'archived', []);
        
        $this->cache->delete("project:{$card['project_id']}");
        
        return true;
    }
    
    /**
     * 删除卡片
     */
    public function deleteCard(int $id, int $userId): bool
    {
        $card = $this->getCard($id);
        if (!$card) {
            throw new Exception("Card not found", 404);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM project_cards WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->logActivity($card['project_id'], null, $userId, 'deleted', ['card_title' => $card['title']]);
        
        $this->cache->delete("project:{$card['project_id']}");
        
        return true;
    }
    
    // ==================== 成员管理 ====================
    
    /**
     * 添加成员
     */
    public function addMember(int $projectId, int $userId, string $role = 'write'): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)"
        );
        $stmt->execute([$projectId, $userId, $role]);
        
        $stmt = $this->pdo->prepare(
            "SELECT pm.*, u.username, u.avatar_url FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = ? AND pm.user_id = ?"
        );
        $stmt->execute([$projectId, $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 移除成员
     */
    public function removeMember(int $projectId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?");
        return $stmt->execute([$projectId, $userId]);
    }
    
    /**
     * 获取项目成员
     */
    public function getMembers(int $projectId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pm.*, u.username, u.avatar_url FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = ?"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ==================== 辅助方法 ====================
    
    /**
     * 记录活动
     */
    private function logActivity(int $projectId, ?int $cardId, int $userId, string $action, array $details): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_activities (project_id, card_id, user_id, action, details) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$projectId, $cardId, $userId, $action, json_encode($details)]);
    }
    
    /**
     * 验证项目数据
     */
    private function validateProjectData(array $data): void
    {
        if (empty($data['name'])) {
            throw new Exception("Project name is required", 400);
        }
        
        if (strlen($data['name']) > 100) {
            throw new Exception("Project name must be less than 100 characters", 400);
        }
    }
    
    /**
     * 检查编辑权限
     */
    private function canEdit(array $project, int $userId): bool
    {
        // 项目创建者
        if ($project['user_id'] == $userId) {
            return true;
        }
        
        // 仓库所有者
        if ($project['owner_id'] == $userId) {
            return true;
        }
        
        // 项目管理员
        $stmt = $this->pdo->prepare(
            "SELECT role FROM project_members WHERE project_id = ? AND user_id = ? AND role IN ('admin', 'write')"
        );
        $stmt->execute([$project['id'], $userId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * 检查删除权限
     */
    private function canDelete(array $project, int $userId): bool
    {
        // 项目创建者
        if ($project['user_id'] == $userId) {
            return true;
        }
        
        // 仓库所有者
        if ($project['owner_id'] == $userId) {
            return true;
        }
        
        // 项目管理员
        $stmt = $this->pdo->prepare(
            "SELECT role FROM project_members WHERE project_id = ? AND user_id = ? AND role = 'admin'"
        );
        $stmt->execute([$project['id'], $userId]);
        return $stmt->fetch() !== false;
    }
}
