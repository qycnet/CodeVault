<?php
/**
 * CodeVault - 讨论区控制器
 * 
 * 处理讨论区相关的 API 请求
 */

namespace CodeVault\Controllers;

use CodeVault\Services\DiscussionService;
use CodeVault\Core\ErrorHandler;

class DiscussionController
{
    private DiscussionService $service;
    
    public function __construct(DiscussionService $service)
    {
        $this->service = $service;
    }
    
    /**
     * 获取分类列表
     * GET /api/discussions/categories
     */
    public function getCategories(): void
    {
        $repoId = (int)($_GET['repository_id'] ?? 0);
        
        if (!$repoId) {
            ErrorHandler::sendError(400, 'Repository ID is required');
        }
        
        $categories = $this->service->getOrCreateDefaultCategories($repoId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
    }
    
    /**
     * 获取讨论列表
     * GET /api/discussions
     */
    public function index(): void
    {
        $repoId = (int)($_GET['repository_id'] ?? 0);
        
        if (!$repoId) {
            ErrorHandler::sendError(400, 'Repository ID is required');
        }
        
        $filters = [
            'category' => $_GET['category'] ?? null,
            'status' => $_GET['status'] ?? null,
            'q' => $_GET['q'] ?? null,
            'sort' => $_GET['sort'] ?? null,
        ];
        
        $pagination = [
            'page' => (int)($_GET['page'] ?? 1),
            'per_page' => min((int)($_GET['per_page'] ?? 20), 100),
        ];
        
        $result = $this->service->getDiscussions($repoId, $filters, $pagination);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }
    
    /**
     * 创建讨论
     * POST /api/discussions
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
            $discussion = $this->service->createDiscussion($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $discussion
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取讨论详情
     * GET /api/discussions/{id}
     */
    public function show(int $id): void
    {
        $discussion = $this->service->getDiscussion($id);
        
        if (!$discussion) {
            ErrorHandler::sendError(404, 'Discussion not found');
        }
        
        // 增加浏览量
        $this->service->incrementViewCount($id);
        $discussion['view_count']++;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $discussion
        ]);
    }
    
    /**
     * 更新讨论
     * PUT /api/discussions/{id}
     */
    public function update(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $discussion = $this->service->updateDiscussion($id, $user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $discussion
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 删除讨论
     * DELETE /api/discussions/{id}
     */
    public function delete(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->deleteDiscussion($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Discussion deleted'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取回复列表
     * GET /api/discussions/{id}/replies
     */
    public function getReplies(int $id): void
    {
        $pagination = [
            'page' => (int)($_GET['page'] ?? 1),
            'per_page' => min((int)($_GET['per_page'] ?? 30), 100),
        ];
        
        $result = $this->service->getReplies($id, $pagination);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }
    
    /**
     * 创建回复
     * POST /api/discussions/{id}/replies
     */
    public function createReply(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['body'])) {
            ErrorHandler::sendError(400, 'Body is required');
        }
        
        try {
            $reply = $this->service->createReply(
                $id,
                $user['id'],
                $data['body'],
                $data['parent_id'] ?? null
            );
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $reply
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 标记为答案
     * POST /api/discussions/{discussionId}/replies/{replyId}/answer
     */
    public function markAsAnswer(int $discussionId, int $replyId): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->markAsAnswer($discussionId, $replyId, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Marked as answer'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 投票
     * POST /api/discussions/{type}/{id}/vote
     */
    public function vote(string $type, int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['vote'])) {
            ErrorHandler::sendError(400, 'Vote type is required');
        }
        
        try {
            $result = $this->service->vote($user['id'], $type, $id, $data['vote']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 置顶/取消置顶
     * POST /api/discussions/{id}/pin
     */
    public function togglePin(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $isPinned = $this->service->togglePin($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_pinned' => $isPinned
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 锁定/解锁
     * POST /api/discussions/{id}/lock
     */
    public function toggleLock(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $isLocked = $this->service->toggleLock($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'is_locked' => $isLocked
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
