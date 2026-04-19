<?php
/**
 * CodeVault - Issue 管理类
 * PHP 原生开发
 */

require_once __DIR__ . '/../config/database.php';

class Issue
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * 创建 Issue
     * @param int $repoId 仓库ID
     * @param int $authorId 作者ID
     * @param string $title 标题
     * @param string $content 内容
     * @return array Issue 信息
     * @throws Exception
     */
    public function create(int $repoId, int $authorId, string $title, string $content = ''): array
    {
        $title = trim($title);
        $content = trim($content);

        if (empty($title) || strlen($title) > 255) {
            throw new Exception('标题长度无效（1-255字符）');
        }

        // 验证仓库存在
        $stmt = $this->db->prepare('SELECT 1 FROM repositories WHERE id = ?');
        $stmt->execute([$repoId]);
        if (!$stmt->fetch()) {
            throw new Exception('仓库不存在');
        }

        // 插入 Issue
        $stmt = $this->db->prepare(
            'INSERT INTO issues (repo_id, author_id, title, content) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$repoId, $authorId, $title, $content]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'repo_id' => $repoId,
            'author_id' => $authorId,
            'title' => $title,
            'content' => $content,
            'status' => 'open',
        ];
    }

    /**
     * 获取 Issue 详情
     * @param int $issueId Issue ID
     * @return array|null
     */
    public function get(int $issueId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, u.username as author_name FROM issues i 
             JOIN users u ON i.author_id = u.id 
             WHERE i.id = ?'
        );
        $stmt->execute([$issueId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 获取仓库的 Issue 列表
     * @param int $repoId 仓库ID
     * @param string|null $status 状态过滤 (open/closed/null=全部)
     * @param int $limit 分页限制
     * @param int $offset 偏移量
     * @return array
     */
    public function getRepoIssues(int $repoId, ?string $status = null, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT i.*, u.username as author_name FROM issues i 
                JOIN users u ON i.author_id = u.id 
                WHERE i.repo_id = ?';
        $params = [$repoId];

        if ($status !== null && in_array($status, ['open', 'closed'], true)) {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY i.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 更新 Issue 状态
     * @param int $issueId Issue ID
     * @param string $status 新状态 (open/closed)
     * @return array
     * @throws Exception
     */
    public function updateStatus(int $issueId, string $status): array
    {
        if (!in_array($status, ['open', 'closed'], true)) {
            throw new Exception('无效的状态');
        }

        $stmt = $this->db->prepare('UPDATE issues SET status = ? WHERE id = ?');
        $stmt->execute([$status, $issueId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Issue 不存在');
        }

        return $this->get($issueId);
    }

    /**
     * 更新 Issue 内容
     * @param int $issueId Issue ID
     * @param int $userId 当前用户ID
     * @param array $data 更新数据
     * @return array
     * @throws Exception
     */
    public function update(int $issueId, int $userId, array $data): array
    {
        $issue = $this->get($issueId);
        if (!$issue) {
            throw new Exception('Issue 不存在');
        }

        // 只有作者可以修改
        if ($issue['author_id'] !== $userId) {
            throw new Exception('无权修改此 Issue');
        }

        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title) || strlen($title) > 255) {
                throw new Exception('标题长度无效');
            }
            $updates[] = 'title = ?';
            $params[] = $title;
        }

        if (isset($data['content'])) {
            $updates[] = 'content = ?';
            $params[] = trim($data['content']);
        }

        if (empty($updates)) {
            return $issue;
        }

        $params[] = $issueId;
        $stmt = $this->db->prepare('UPDATE issues SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        return $this->get($issueId);
    }

    /**
     * 删除 Issue
     * @param int $issueId Issue ID
     * @param int $userId 当前用户ID
     * @throws Exception
     */
    public function delete(int $issueId, int $userId): void
    {
        $issue = $this->get($issueId);
        if (!$issue) {
            throw new Exception('Issue 不存在');
        }

        if ($issue['author_id'] !== $userId) {
            throw new Exception('无权删除此 Issue');
        }

        $stmt = $this->db->prepare('DELETE FROM issues WHERE id = ?');
        $stmt->execute([$issueId]);
    }

    /**
     * 统计仓库 Issue 数量
     * @param int $repoId 仓库ID
     * @return array ['open' => x, 'closed' => y]
     */
    public function countByRepo(int $repoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) as count FROM issues WHERE repo_id = ? GROUP BY status'
        );
        $stmt->execute([$repoId]);
        $result = ['open' => 0, 'closed' => 0];
        
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        
        return $result;
    }
}
