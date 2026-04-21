<?php
/**
 * CodeVault - 讨论区服务
 * 
 * 提供讨论区的核心业务逻辑
 */

namespace CodeVault\Services;

use PDO;
use Exception;

class DiscussionService
{
    private PDO $pdo;
    private CacheService $cache;
    
    public function __construct(PDO $pdo, ?CacheService $cache = null)
    {
        $this->pdo = $pdo;
        $this->cache = $cache ?? new CacheService();
    }
    
    /**
     * 获取或创建默认分类
     */
    public function getOrCreateDefaultCategories(int $repositoryId): array
    {
        // 检查是否已有分类
        $stmt = $this->pdo->prepare(
            "SELECT * FROM discussion_categories WHERE repository_id = ? ORDER BY sort_order"
        );
        $stmt->execute([$repositoryId]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($categories)) {
            return $categories;
        }
        
        // 创建默认分类
        $defaultCategories = [
            ['name' => '📢 Announcements', 'slug' => 'announcements', 'description' => '重要公告和更新', 'emoji' => '📢', 'color' => '#0366d6', 'sort_order' => 0],
            ['name' => '💡 Ideas', 'slug' => 'ideas', 'description' => '新功能建议和想法', 'emoji' => '💡', 'color' => '#f9826c', 'sort_order' => 1],
            ['name' => '❓ Q&A', 'slug' => 'q-a', 'description' => '问题和解答', 'emoji' => '❓', 'color' => '#28a745', 'sort_order' => 2],
            ['name' => '🎉 Show and tell', 'slug' => 'show-and-tell', 'description' => '展示你的项目和使用案例', 'emoji' => '🎉', 'color' => '#a371f7', 'sort_order' => 3],
            ['name' => '💬 General', 'slug' => 'general', 'description' => '一般性讨论', 'emoji' => '💬', 'color' => '#0969da', 'sort_order' => 4],
        ];
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO discussion_categories (repository_id, name, slug, description, emoji, color, sort_order, is_default) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        
        foreach ($defaultCategories as $cat) {
            $stmt->execute([
                $repositoryId,
                $cat['name'],
                $cat['slug'],
                $cat['description'],
                $cat['emoji'],
                $cat['color'],
                $cat['sort_order']
            ]);
        }
        
        // 返回新创建的分类
        $stmt = $this->pdo->prepare(
            "SELECT * FROM discussion_categories WHERE repository_id = ? ORDER BY sort_order"
        );
        $stmt->execute([$repositoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 创建讨论
     */
    public function createDiscussion(array $data): array
    {
        $this->validateDiscussionData($data);
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO discussions (repository_id, category_id, user_id, title, body, body_html, labels, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'open')"
        );
        
        $bodyHtml = $this->renderMarkdown($data['body']);
        $labels = isset($data['labels']) ? json_encode($data['labels']) : null;
        
        $stmt->execute([
            $data['repository_id'],
            $data['category_id'],
            $data['user_id'],
            $data['title'],
            $data['body'],
            $bodyHtml,
            $labels
        ]);
        
        $discussionId = (int)$this->pdo->lastInsertId();
        
        // 清除缓存
        $this->cache->delete("repo:{$data['repository_id']}:discussions");
        
        return $this->getDiscussion($discussionId);
    }
    
    /**
     * 获取讨论详情
     */
    public function getDiscussion(int $id): ?array
    {
        $cacheKey = "discussion:{$id}";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT d.*, 
                    c.name as category_name, c.slug as category_slug, c.emoji as category_emoji, c.color as category_color,
                    u.username, u.avatar_url,
                    r.name as repo_name, r.owner_id,
                    (SELECT COUNT(*) FROM discussion_replies WHERE discussion_id = d.id) as reply_count
             FROM discussions d
             JOIN discussion_categories c ON d.category_id = c.id
             JOIN users u ON d.user_id = u.id
             JOIN repositories r ON d.repository_id = r.id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        $discussion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$discussion) {
            return null;
        }
        
        // 解析 labels
        $discussion['labels'] = $discussion['labels'] ? json_decode($discussion['labels'], true) : [];
        
        // 缓存 5 分钟
        $this->cache->set($cacheKey, $discussion, 300);
        
        return $discussion;
    }
    
    /**
     * 获取讨论列表
     */
    public function getDiscussions(int $repositoryId, array $filters = [], array $pagination = []): array
    {
        $where = ["d.repository_id = ?"];
        $params = [$repositoryId];
        
        // 分类过滤
        if (!empty($filters['category'])) {
            $where[] = "c.slug = ?";
            $params[] = $filters['category'];
        }
        
        // 状态过滤
        if (!empty($filters['status'])) {
            $where[] = "d.status = ?";
            $params[] = $filters['status'];
        }
        
        // 搜索
        if (!empty($filters['q'])) {
            $where[] = "MATCH(d.title, d.body) AGAINST(? IN BOOLEAN MODE)";
            $params[] = $filters['q'];
        }
        
        // 排序
        $orderBy = "d.is_pinned DESC, d.created_at DESC";
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'votes':
                    $orderBy = "d.is_pinned DESC, d.vote_count DESC, d.created_at DESC";
                    break;
                case 'replies':
                    $orderBy = "d.is_pinned DESC, d.reply_count DESC, d.created_at DESC";
                    break;
                case 'updated':
                    $orderBy = "d.is_pinned DESC, d.updated_at DESC";
                    break;
            }
        }
        
        // 分页
        $page = $pagination['page'] ?? 1;
        $perPage = $pagination['per_page'] ?? 20;
        $offset = ($page - 1) * $perPage;
        
        // 查询总数
        $countSql = "SELECT COUNT(*) FROM discussions d 
                     JOIN discussion_categories c ON d.category_id = c.id 
                     WHERE " . implode(" AND ", $where);
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // 查询列表
        $sql = "SELECT d.*, 
                       c.name as category_name, c.slug as category_slug, c.emoji as category_emoji, c.color as category_color,
                       u.username, u.avatar_url
                FROM discussions d
                JOIN discussion_categories c ON d.category_id = c.id
                JOIN users u ON d.user_id = u.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY {$orderBy}
                LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 解析 labels
        foreach ($discussions as &$discussion) {
            $discussion['labels'] = $discussion['labels'] ? json_decode($discussion['labels'], true) : [];
        }
        
        return [
            'items' => $discussions,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * 更新讨论
     */
    public function updateDiscussion(int $id, int $userId, array $data): array
    {
        $discussion = $this->getDiscussion($id);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        // 权限检查
        if ($discussion['user_id'] != $userId) {
            throw new Exception("Permission denied", 403);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
        }
        
        if (isset($data['body'])) {
            $updates[] = "body = ?";
            $updates[] = "body_html = ?";
            $params[] = $data['body'];
            $params[] = $this->renderMarkdown($data['body']);
        }
        
        if (isset($data['category_id'])) {
            $updates[] = "category_id = ?";
            $params[] = $data['category_id'];
        }
        
        if (isset($data['labels'])) {
            $updates[] = "labels = ?";
            $params[] = json_encode($data['labels']);
        }
        
        if (empty($updates)) {
            return $discussion;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE discussions SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // 清除缓存
        $this->cache->delete("discussion:{$id}");
        $this->cache->delete("repo:{$discussion['repository_id']}:discussions");
        
        return $this->getDiscussion($id);
    }
    
    /**
     * 删除讨论
     */
    public function deleteDiscussion(int $id, int $userId): bool
    {
        $discussion = $this->getDiscussion($id);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        // 权限检查（作者或仓库管理员）
        if ($discussion['user_id'] != $userId && !$this->isRepoAdmin($discussion['repository_id'], $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM discussions WHERE id = ?");
        $stmt->execute([$id]);
        
        // 清除缓存
        $this->cache->delete("discussion:{$id}");
        $this->cache->delete("repo:{$discussion['repository_id']}:discussions");
        
        return true;
    }
    
    /**
     * 创建回复
     */
    public function createReply(int $discussionId, int $userId, string $body, ?int $parentId = null): array
    {
        $discussion = $this->getDiscussion($discussionId);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        if ($discussion['is_locked']) {
            throw new Exception("Discussion is locked", 403);
        }
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO discussion_replies (discussion_id, user_id, parent_id, body, body_html)
             VALUES (?, ?, ?, ?, ?)"
        );
        
        $bodyHtml = $this->renderMarkdown($body);
        $stmt->execute([$discussionId, $userId, $parentId, $body, $bodyHtml]);
        
        $replyId = (int)$this->pdo->lastInsertId();
        
        // 更新回复计数
        $this->pdo->prepare("UPDATE discussions SET reply_count = reply_count + 1, updated_at = NOW() WHERE id = ?")
            ->execute([$discussionId]);
        
        // 清除缓存
        $this->cache->delete("discussion:{$discussionId}");
        
        return $this->getReply($replyId);
    }
    
    /**
     * 获取回复详情
     */
    public function getReply(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username, u.avatar_url
             FROM discussion_replies r
             JOIN users u ON r.user_id = u.id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 获取讨论的所有回复
     */
    public function getReplies(int $discussionId, array $pagination = []): array
    {
        $page = $pagination['page'] ?? 1;
        $perPage = $pagination['per_page'] ?? 30;
        $offset = ($page - 1) * $perPage;
        
        // 获取总数
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM discussion_replies WHERE discussion_id = ?");
        $stmt->execute([$discussionId]);
        $total = (int)$stmt->fetchColumn();
        
        // 获取回复（树形结构）
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.username, u.avatar_url
             FROM discussion_replies r
             JOIN users u ON r.user_id = u.id
             WHERE r.discussion_id = ?
             ORDER BY r.is_answer DESC, r.vote_count DESC, r.created_at ASC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$discussionId, $perPage, $offset]);
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'items' => $replies,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * 标记为答案
     */
    public function markAsAnswer(int $discussionId, int $replyId, int $userId): bool
    {
        $discussion = $this->getDiscussion($discussionId);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        // 权限检查（讨论作者或仓库管理员）
        if ($discussion['user_id'] != $userId && !$this->isRepoAdmin($discussion['repository_id'], $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        // 清除之前的答案
        $this->pdo->prepare("UPDATE discussion_replies SET is_answer = 0 WHERE discussion_id = ?")
            ->execute([$discussionId]);
        
        // 设置新答案
        $this->pdo->prepare("UPDATE discussion_replies SET is_answer = 1 WHERE id = ?")
            ->execute([$replyId]);
        
        // 更新讨论状态
        $this->pdo->prepare("UPDATE discussions SET status = 'answered', accepted_reply_id = ? WHERE id = ?")
            ->execute([$replyId, $discussionId]);
        
        // 清除缓存
        $this->cache->delete("discussion:{$discussionId}");
        
        return true;
    }
    
    /**
     * 投票
     */
    public function vote(int $userId, string $type, int $targetId, string $voteType): array
    {
        if (!in_array($type, ['discussion', 'reply'])) {
            throw new Exception("Invalid vote type", 400);
        }
        
        if (!in_array($voteType, ['up', 'down'])) {
            throw new Exception("Invalid vote", 400);
        }
        
        $discussionId = $type === 'discussion' ? $targetId : null;
        $replyId = $type === 'reply' ? $targetId : null;
        
        // 检查是否已投票
        $stmt = $this->pdo->prepare(
            "SELECT * FROM discussion_votes WHERE user_id = ? AND (discussion_id = ? OR reply_id = ?)"
        );
        $stmt->execute([$userId, $discussionId, $replyId]);
        $existingVote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingVote) {
            // 取消投票
            $this->pdo->prepare("DELETE FROM discussion_votes WHERE id = ?")
                ->execute([$existingVote['id']]);
            
            // 更新计数
            $countField = $type === 'discussion' ? 'vote_count' : 'vote_count';
            $table = $type === 'discussion' ? 'discussions' : 'discussion_replies';
            $change = $existingVote['vote_type'] === 'up' ? -1 : 1;
            
            $this->pdo->prepare("UPDATE {$table} SET {$countField} = {$countField} + ? WHERE id = ?")
                ->execute([$change, $targetId]);
            
            return ['action' => 'removed', 'vote_count' => $this->getVoteCount($type, $targetId)];
        }
        
        // 新投票
        $stmt = $this->pdo->prepare(
            "INSERT INTO discussion_votes (discussion_id, reply_id, user_id, vote_type) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$discussionId, $replyId, $userId, $voteType]);
        
        // 更新计数
        $countField = 'vote_count';
        $table = $type === 'discussion' ? 'discussions' : 'discussion_replies';
        $change = $voteType === 'up' ? 1 : -1;
        
        $this->pdo->prepare("UPDATE {$table} SET {$countField} = {$countField} + ? WHERE id = ?")
            ->execute([$change, $targetId]);
        
        return ['action' => 'added', 'vote_type' => $voteType, 'vote_count' => $this->getVoteCount($type, $targetId)];
    }
    
    /**
     * 获取投票数
     */
    private function getVoteCount(string $type, int $id): int
    {
        $table = $type === 'discussion' ? 'discussions' : 'discussion_replies';
        $stmt = $this->pdo->prepare("SELECT vote_count FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * 置顶/取消置顶
     */
    public function togglePin(int $id, int $userId): bool
    {
        $discussion = $this->getDiscussion($id);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        if (!$this->isRepoAdmin($discussion['repository_id'], $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        $newState = $discussion['is_pinned'] ? 0 : 1;
        $this->pdo->prepare("UPDATE discussions SET is_pinned = ? WHERE id = ?")
            ->execute([$newState, $id]);
        
        $this->cache->delete("discussion:{$id}");
        
        return (bool)$newState;
    }
    
    /**
     * 锁定/解锁
     */
    public function toggleLock(int $id, int $userId): bool
    {
        $discussion = $this->getDiscussion($id);
        if (!$discussion) {
            throw new Exception("Discussion not found", 404);
        }
        
        if (!$this->isRepoAdmin($discussion['repository_id'], $userId)) {
            throw new Exception("Permission denied", 403);
        }
        
        $newState = $discussion['is_locked'] ? 0 : 1;
        $this->pdo->prepare("UPDATE discussions SET is_locked = ? WHERE id = ?")
            ->execute([$newState, $id]);
        
        $this->cache->delete("discussion:{$id}");
        
        return (bool)$newState;
    }
    
    /**
     * 增加浏览量
     */
    public function incrementViewCount(int $id): void
    {
        $this->pdo->prepare("UPDATE discussions SET view_count = view_count + 1 WHERE id = ?")
            ->execute([$id]);
    }
    
    /**
     * 验证讨论数据
     */
    private function validateDiscussionData(array $data): void
    {
        if (empty($data['title']) || strlen($data['title']) < 5) {
            throw new Exception("Title must be at least 5 characters", 400);
        }
        
        if (strlen($data['title']) > 255) {
            throw new Exception("Title must be less than 255 characters", 400);
        }
        
        if (empty($data['body']) || strlen($data['body']) < 10) {
            throw new Exception("Body must be at least 10 characters", 400);
        }
        
        if (empty($data['category_id'])) {
            throw new Exception("Category is required", 400);
        }
    }
    
    /**
     * 渲染 Markdown
     */
    private function renderMarkdown(string $text): string
    {
        // 简单的 Markdown 渲染（实际项目中应使用 Parsedown 等库）
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // 代码块
        $html = preg_replace('/```(\w*)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
        $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);
        
        // 标题
        $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
        
        // 粗体和斜体
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        
        // 链接
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html);
        
        // 段落
        $html = '<p>' . preg_replace('/\n\n/', '</p><p>', $html) . '</p>';
        
        return $html;
    }
    
    /**
     * 检查是否为仓库管理员
     */
    private function isRepoAdmin(int $repoId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT owner_id FROM repositories WHERE id = ?"
        );
        $stmt->execute([$repoId]);
        $ownerId = $stmt->fetchColumn();
        
        return $ownerId == $userId;
    }
}
