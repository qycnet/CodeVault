<?php
/**
 * PRController.php - Pull Request API 控制器
 */

require_once __DIR__ . '/../PullRequest.php';
require_once __DIR__ . '/../Auth.php';

class PRController 
{
    private $pr;
    private $auth;
    
    public function __construct() 
    {
        $this->pr = new PullRequest();
        $this->auth = new Auth();
    }
    
    /**
     * POST /api/pull-requests - 创建 PR
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
        $required = ['repo_id', 'title', 'source_branch', 'target_branch'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->json(400, ['message' => "缺少必填字段: {$field}"]);
                return;
            }
        }
        
        $result = $this->pr->create(
            (int)$data['repo_id'],
            (int)$user['id'],
            $data['title'],
            $data['description'] ?? '',
            $data['source_branch'],
            $data['target_branch']
        );
        
        $this->json($result['code'], $result);
    }
    
    /**
     * GET /api/pull-requests - 获取 PR 列表
     */
    public function list(): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $repoId = (int)($_GET['repo_id'] ?? 0);
        if (!$repoId) {
            $this->json(400, ['message' => '缺少仓库 ID']);
            return;
        }
        
        $status = $_GET['status'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $pageSize = (int)($_GET['page_size'] ?? 20);
        
        $result = $this->pr->list($repoId, $status, $page, $pageSize);
        $this->json($result['code'], $result);
    }
    
    /**
     * GET /api/pull-requests/:id - 获取 PR 详情
     */
    public function detail(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->pr->detail($id);
        $this->json($result['code'], $result);
    }
    
    /**
     * POST /api/pull-requests/:id/merge - 合并 PR
     */
    public function merge(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->pr->merge($id, (int)$user['id']);
        $this->json($result['code'], $result);
    }
    
    /**
     * POST /api/pull-requests/:id/close - 关闭 PR
     */
    public function close(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->pr->close($id, (int)$user['id']);
        $this->json($result['code'], $result);
    }
    
    /**
     * POST /api/pull-requests/:id/reopen - 重新打开 PR
     */
    public function reopen(int $id): void 
    {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            $this->json(401, ['message' => '未授权']);
            return;
        }
        
        $result = $this->pr->reopen($id, (int)$user['id']);
        $this->json($result['code'], $result);
    }
    
    private function json(int $code, array $data): void 
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
