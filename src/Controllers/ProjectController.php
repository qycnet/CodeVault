<?php
/**
 * CodeVault - 项目管理控制器
 * 
 * 处理项目管理相关的 API 请求
 */

namespace CodeVault\Controllers;

use CodeVault\Services\ProjectService;
use CodeVault\Core\ErrorHandler;

class ProjectController
{
    private ProjectService $service;
    
    public function __construct(ProjectService $service)
    {
        $this->service = $service;
    }
    
    /**
     * 获取项目列表
     * GET /api/projects
     */
    public function index(): void
    {
        $filters = [
            'repository_id' => $_GET['repository_id'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'state' => $_GET['state'] ?? null,
        ];
        
        $pagination = [
            'page' => (int)($_GET['page'] ?? 1),
            'per_page' => min((int)($_GET['per_page'] ?? 20), 100),
        ];
        
        $result = $this->service->getProjects($filters, $pagination);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }
    
    /**
     * 创建项目
     * POST /api/projects
     */
    public function create(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $data['user_id'] = $user['id'];
        
        try {
            $project = $this->service->createProject($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $project
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取项目详情
     * GET /api/projects/{id}
     */
    public function show(int $id): void
    {
        $project = $this->service->getProject($id);
        
        if (!$project) {
            ErrorHandler::sendError(404, 'Project not found');
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $project
        ]);
    }
    
    /**
     * 更新项目
     * PUT /api/projects/{id}
     */
    public function update(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $project = $this->service->updateProject($id, $user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $project
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 删除项目
     * DELETE /api/projects/{id}
     */
    public function delete(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->deleteProject($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Project deleted'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 列管理 ====================
    
    /**
     * 获取项目列
     * GET /api/projects/{id}/columns
     */
    public function getColumns(int $projectId): void
    {
        $project = $this->service->getProject($projectId);
        
        if (!$project) {
            ErrorHandler::sendError(404, 'Project not found');
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $project['columns']
        ]);
    }
    
    /**
     * 创建列
     * POST /api/projects/{id}/columns
     */
    public function createColumn(int $projectId): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $column = $this->service->createColumn($projectId, $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $column
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 更新列
     * PUT /api/projects/columns/{id}
     */
    public function updateColumn(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $column = $this->service->updateColumn($id, $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $column
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 删除列
     * DELETE /api/projects/columns/{id}
     */
    public function deleteColumn(int $id): void
    {
        try {
            $this->service->deleteColumn($id);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Column deleted'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 卡片管理 ====================
    
    /**
     * 获取列的卡片
     * GET /api/projects/columns/{id}/cards
     */
    public function getColumnCards(int $columnId): void
    {
        $cards = $this->service->getColumnCards($columnId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $cards
        ]);
    }
    
    /**
     * 创建卡片
     * POST /api/projects/columns/{id}/cards
     */
    public function createCard(int $columnId): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $card = $this->service->createCard($columnId, $user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $card
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取卡片详情
     * GET /api/projects/cards/{id}
     */
    public function getCard(int $id): void
    {
        $card = $this->service->getCard($id);
        
        if (!$card) {
            ErrorHandler::sendError(404, 'Card not found');
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $card
        ]);
    }
    
    /**
     * 更新卡片
     * PUT /api/projects/cards/{id}
     */
    public function updateCard(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $card = $this->service->updateCard($id, $user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $card
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 移动卡片
     * POST /api/projects/cards/{id}/move
     */
    public function moveCard(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['column_id']) || !isset($data['position'])) {
            ErrorHandler::sendError(400, 'column_id and position are required');
        }
        
        try {
            $card = $this->service->moveCard($id, $user['id'], $data['column_id'], $data['position']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $card
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 归档卡片
     * POST /api/projects/cards/{id}/archive
     */
    public function archiveCard(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->archiveCard($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Card archived'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 删除卡片
     * DELETE /api/projects/cards/{id}
     */
    public function deleteCard(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->deleteCard($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Card deleted'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 成员管理 ====================
    
    /**
     * 获取项目成员
     * GET /api/projects/{id}/members
     */
    public function getMembers(int $projectId): void
    {
        $members = $this->service->getMembers($projectId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $members
        ]);
    }
    
    /**
     * 添加成员
     * POST /api/projects/{id}/members
     */
    public function addMember(int $projectId): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['user_id'])) {
            ErrorHandler::sendError(400, 'user_id is required');
        }
        
        try {
            $member = $this->service->addMember($projectId, $data['user_id'], $data['role'] ?? 'write');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $member
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 移除成员
     * DELETE /api/projects/{projectId}/members/{userId}
     */
    public function removeMember(int $projectId, int $userId): void
    {
        try {
            $this->service->removeMember($projectId, $userId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Member removed'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取当前用户
     */
    private function getCurrentUser(): ?array
    {
        session_start();
        return $_SESSION['user'] ?? null;
    }
}
