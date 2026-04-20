<?php
/**
 * CommentController.php - 评论 API 控制器
 */

require_once __DIR__ . '/../Comment.php';
require_once __DIR__ . '/../Auth.php';

class CommentController 
{
    private $comment;
    private $auth;
    
    public function __construct() 
    {
        $this->comment = new Comment();
        $this->auth = new Auth();
    }
    
    /**
     * POST /api/comments - 创建评论
     */
    public function create(): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 验证必填字段
        if (empty($data['parent_type']) || empty($data['parent_id']) || empty($data['content'])) {
            $this->json(400, ['message' => '缺少必填字段']);
            return;
        }
        
        // 验证 parent_type
        if (!in_array($data['parent_type'], ['issue', 'pull_request'])) {
            $this->json(400, ['message' => '无效的 parent_type']);
            return;
        }
        
        $result = $this->comment->create(
            $data['parent_type'],
            (int)$data['parent_id'],
            (int)$user['id'],
            $data['content'],
            isset($data['line_number']) ? (int)$data['line_number'] : null,
            $data['file_path'] ?? null
        );
        
        $this->json($result['code'], $result);
    }
    
    /**
     * GET /api/comments - 获取评论列表
     */
    public function list(): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $parentType = $_GET['parent_type'] ?? null;
        $parentId = (int)($_GET['parent_id'] ?? 0);
        
        if (!$parentType || !$parentId) {
            $this->json(400, ['message' => '缺少必填参数']);
            return;
        }
        
        $page = (int)($_GET['page'] ?? 1);
        $pageSize = (int)($_GET['page_size'] ?? 50);
        
        $result = $this->comment->list($parentType, $parentId, $page, $pageSize);
        $this->json($result['code'], $result);
    }
    
    /**
     * GET /api/comments/:id - 获取评论详情
     */
    public function detail(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->comment->detail($id);
        $this->json($result['code'], $result);
    }
    
    /**
     * PUT /api/comments/:id - 更新评论
     */
    public function update(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['content'])) {
            $this->json(400, ['message' => '缺少评论内容']);
            return;
        }
        
        $result = $this->comment->update($id, (int)$user['id'], $data['content']);
        $this->json($result['code'], $result);
    }
    
    /**
     * DELETE /api/comments/:id - 删除评论
     */
    public function delete(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->comment->delete($id, (int)$user['id']);
        $this->json($result['code'], $result);
    }
    
    /**
     * GET /api/comments/line - 获取行内评论
     */
    public function lineComments(): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $parentType = $_GET['parent_type'] ?? null;
        $parentId = (int)($_GET['parent_id'] ?? 0);
        $filePath = $_GET['file_path'] ?? null;
        
        if (!$parentType || !$parentId || !$filePath) {
            $this->json(400, ['message' => '缺少必填参数']);
            return;
        }
        
        $result = $this->comment->getLineComments($parentType, $parentId, $filePath);
        $this->json($result['code'], $result);
    }
    
    private function json(int $code, array $data): void 
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
