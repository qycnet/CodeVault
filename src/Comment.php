<?php
/**
 * Comment.php - 评论管理类
 * 
 * 功能：Issue/PR 评论、行内评论
 */

require_once __DIR__ . '/Database/Connection.php';

class Comment 
{
    private $db;
    
    public function __construct() 
    {
        $this->db = Database\Connection::getInstance();
    }
    
    /**
     * 创建评论
     */
    public function create(string $parentType, int $parentId, int $authorId, 
                          string $content, ?int $lineNumber = null, 
                          ?string $filePath = null): array 
    {
        // 验证父对象是否存在
        if (!$this->parentExists($parentType, $parentId)) {
            return ['code' => 404, 'message' => ucfirst($parentType) . ' 不存在'];
        }
        
        // 内容安全过滤
        $content = $this->sanitizeContent($content);
        
        // 验证内容长度
        if (strlen($content) < 1 || strlen($content) > 10000) {
            return ['code' => 400, 'message' => '评论内容长度必须在 1-10000 字符之间'];
        }
        
        $sql = "INSERT INTO comments (parent_type, parent_id, author_id, content, 
                line_number, file_path, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $commentId = $this->db->insert($sql, [$parentType, $parentId, $authorId, 
                                              $content, $lineNumber, $filePath]);
        
        // 发送通知
        $this->sendNotification($parentType, $parentId, $authorId, $content);
        
        return [
            'code' => 200,
            'message' => '评论创建成功',
            'data' => [
                'id' => $commentId,
                'parent_type' => $parentType,
                'parent_id' => $parentId,
                'author_id' => $authorId,
                'content' => $content,
                'line_number' => $lineNumber,
                'file_path' => $filePath
            ]
        ];
    }
    
    /**
     * 获取评论列表
     */
    public function list(string $parentType, int $parentId, int $page = 1, 
                        int $pageSize = 50): array 
    {
        $offset = ($page - 1) * $pageSize;
        
        $sql = "SELECT c.*, u.username as author_name, u.email as author_email
                FROM comments c 
                JOIN users u ON c.author_id = u.id 
                WHERE c.parent_type = ? AND c.parent_id = ? 
                ORDER BY c.created_at ASC 
                LIMIT ? OFFSET ?";
        
        $comments = $this->db->fetchAll($sql, [$parentType, $parentId, $pageSize, $offset]);
        
        // 获取总数
        $total = $this->db->fetch(
            "SELECT COUNT(*) as total FROM comments WHERE parent_type = ? AND parent_id = ?",
            [$parentType, $parentId]
        )['total'];
        
        return [
            'code' => 200,
            'data' => [
                'items' => $comments,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize
            ]
        ];
    }
    
    /**
     * 获取行内评论
     */
    public function getLineComments(string $parentType, int $parentId, 
                                   string $filePath): array 
    {
        $sql = "SELECT c.*, u.username as author_name 
                FROM comments c 
                JOIN users u ON c.author_id = u.id 
                WHERE c.parent_type = ? AND c.parent_id = ? 
                AND c.file_path = ? AND c.line_number IS NOT NULL 
                ORDER BY c.line_number ASC, c.created_at ASC";
        
        $comments = $this->db->fetchAll($sql, [$parentType, $parentId, $filePath]);
        
        return [
            'code' => 200,
            'data' => $comments
        ];
    }
    
    /**
     * 更新评论
     */
    public function update(int $commentId, int $userId, string $content): array 
    {
        $comment = $this->db->fetch("SELECT * FROM comments WHERE id = ?", [$commentId]);
        
        if (!$comment) {
            return ['code' => 404, 'message' => '评论不存在'];
        }
        
        // 验证权限
        if ($comment['author_id'] !== $userId) {
            return ['code' => 403, 'message' => '无权限编辑此评论'];
        }
        
        // 内容安全过滤
        $content = $this->sanitizeContent($content);
        
        $this->db->execute(
            "UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?",
            [$content, $commentId]
        );
        
        return [
            'code' => 200,
            'message' => '评论更新成功'
        ];
    }
    
    /**
     * 删除评论
     */
    public function delete(int $commentId, int $userId): array 
    {
        $comment = $this->db->fetch("SELECT * FROM comments WHERE id = ?", [$commentId]);
        
        if (!$comment) {
            return ['code' => 404, 'message' => '评论不存在'];
        }
        
        // 验证权限（作者或仓库所有者）
        if ($comment['author_id'] !== $userId) {
            // 检查是否是仓库所有者
            if (!$this->isRepoOwner($comment, $userId)) {
                return ['code' => 403, 'message' => '无权限删除此评论'];
            }
        }
        
        $this->db->execute("DELETE FROM comments WHERE id = ?", [$commentId]);
        
        return [
            'code' => 200,
            'message' => '评论已删除'
        ];
    }
    
    /**
     * 获取评论详情
     */
    public function detail(int $commentId): array 
    {
        $sql = "SELECT c.*, u.username as author_name 
                FROM comments c 
                JOIN users u ON c.author_id = u.id 
                WHERE c.id = ?";
        
        $comment = $this->db->fetch($sql, [$commentId]);
        
        if (!$comment) {
            return ['code' => 404, 'message' => '评论不存在'];
        }
        
        return [
            'code' => 200,
            'data' => $comment
        ];
    }
    
    // 私有辅助方法
    
    private function parentExists(string $parentType, int $parentId): bool 
    {
        if ($parentType === 'issue') {
            $result = $this->db->fetch("SELECT id FROM issues WHERE id = ?", [$parentId]);
        } elseif ($parentType === 'pull_request') {
            $result = $this->db->fetch("SELECT id FROM pull_requests WHERE id = ?", [$parentId]);
        } else {
            return false;
        }
        
        return $result !== null;
    }
    
    private function sanitizeContent(string $content): string 
    {
        // XSS 防护
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // 移除危险标签
        $content = strip_tags($content, '<p><br><strong><em><code><pre><a>');
        
        return $content;
    }
    
    private function sendNotification(string $parentType, int $parentId, 
                                     int $authorId, string $content): void 
    {
        // TODO: 实现通知发送
        // 1. 获取父对象的所有者
        // 2. 获取所有参与者
        // 3. 检查 @ 提及
        // 4. 发送通知
    }
    
    private function isRepoOwner(array $comment, int $userId): bool 
    {
        // 根据 parent_type 获取仓库 ID
        if ($comment['parent_type'] === 'issue') {
            $issue = $this->db->fetch("SELECT repo_id FROM issues WHERE id = ?", 
                                      [$comment['parent_id']]);
            if (!$issue) return false;
            
            $repo = $this->db->fetch("SELECT owner_id FROM repositories WHERE id = ?", 
                                     [$issue['repo_id']]);
            return $repo && $repo['owner_id'] === $userId;
            
        } elseif ($comment['parent_type'] === 'pull_request') {
            $pr = $this->db->fetch("SELECT repo_id FROM pull_requests WHERE id = ?", 
                                   [$comment['parent_id']]);
            if (!$pr) return false;
            
            $repo = $this->db->fetch("SELECT owner_id FROM repositories WHERE id = ?", 
                                     [$pr['repo_id']]);
            return $repo && $repo['owner_id'] === $userId;
        }
        
        return false;
    }
}
