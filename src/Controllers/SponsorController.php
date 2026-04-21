<?php
/**
 * CodeVault - 赞助系统控制器
 * 
 * 处理赞助相关的 API 请求
 */

namespace CodeVault\Controllers;

use CodeVault\Services\SponsorService;
use CodeVault\Core\ErrorHandler;

class SponsorController
{
    private SponsorService $service;
    
    public function __construct(SponsorService $service)
    {
        $this->service = $service;
    }
    
    // ==================== 赞助等级管理 ====================
    
    /**
     * 获取用户的赞助等级
     * GET /api/sponsors/tiers
     */
    public function getTiers(): void
    {
        $userId = (int)($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            ErrorHandler::sendError(400, 'user_id is required');
        }
        
        $tiers = $this->service->getUserTiers($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $tiers
        ]);
    }
    
    /**
     * 创建赞助等级
     * POST /api/sponsors/tiers
     */
    public function createTier(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $tier = $this->service->createTier($user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $tier
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取赞助等级详情
     * GET /api/sponsors/tiers/{id}
     */
    public function getTier(int $id): void
    {
        $tier = $this->service->getTier($id);
        
        if (!$tier) {
            ErrorHandler::sendError(404, 'Tier not found');
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $tier
        ]);
    }
    
    /**
     * 更新赞助等级
     * PUT /api/sponsors/tiers/{id}
     */
    public function updateTier(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $tier = $this->service->updateTier($id, $user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $tier
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 删除赞助等级
     * DELETE /api/sponsors/tiers/{id}
     */
    public function deleteTier(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->deleteTier($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Tier deleted'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 赞助管理 ====================
    
    /**
     * 创建赞助
     * POST /api/sponsors
     */
    public function create(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $data['sponsor_user_id'] = $user['id'];
        
        try {
            $sponsor = $this->service->createSponsor($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $sponsor
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取赞助详情
     * GET /api/sponsors/{id}
     */
    public function show(int $id): void
    {
        $sponsor = $this->service->getSponsor($id);
        
        if (!$sponsor) {
            ErrorHandler::sendError(404, 'Sponsor not found');
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $sponsor
        ]);
    }
    
    /**
     * 获取我的赞助列表
     * GET /api/sponsors/my-sponsorships
     */
    public function getMySponsorships(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $filters = [
            'status' => $_GET['status'] ?? null
        ];
        
        $sponsorships = $this->service->getMySponsorships($user['id'], $filters);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $sponsorships
        ]);
    }
    
    /**
     * 获取我的赞助者列表
     * GET /api/sponsors/my-sponsors
     */
    public function getMySponsors(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $filters = [
            'status' => $_GET['status'] ?? null,
            'tier_id' => $_GET['tier_id'] ?? null
        ];
        
        $sponsors = $this->service->getMySponsors($user['id'], $filters);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $sponsors
        ]);
    }
    
    /**
     * 取消赞助
     * POST /api/sponsors/{id}/cancel
     */
    public function cancel(int $id): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        try {
            $this->service->cancelSponsor($id, $user['id']);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Sponsorship cancelled'
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 更新赞助状态（支付回调）
     * POST /api/sponsors/{id}/status
     */
    public function updateStatus(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['status'])) {
            ErrorHandler::sendError(400, 'status is required');
        }
        
        try {
            $sponsor = $this->service->updateSponsorStatus($id, $data['status'], $data['payment_id'] ?? null);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $sponsor
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 交易管理 ====================
    
    /**
     * 获取交易历史
     * GET /api/sponsors/{id}/transactions
     */
    public function getTransactions(int $sponsorId): void
    {
        $transactions = $this->service->getTransactions($sponsorId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $transactions
        ]);
    }
    
    // ==================== 徽章管理 ====================
    
    /**
     * 获取用户徽章
     * GET /api/sponsors/badges/{userId}
     */
    public function getBadge(int $userId): void
    {
        $badge = $this->service->getBadge($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $badge
        ]);
    }
    
    // ==================== 目标管理 ====================
    
    /**
     * 获取赞助目标
     * GET /api/sponsors/goals
     */
    public function getGoals(): void
    {
        $userId = (int)($_GET['user_id'] ?? 0);
        
        if (!$userId) {
            ErrorHandler::sendError(400, 'user_id is required');
        }
        
        $goals = $this->service->getUserGoals($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $goals
        ]);
    }
    
    /**
     * 创建赞助目标
     * POST /api/sponsors/goals
     */
    public function createGoal(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $goal = $this->service->createGoal($user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $goal
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    // ==================== 统计 ====================
    
    /**
     * 获取统计
     * GET /api/sponsors/stats/{userId}
     */
    public function getStats(int $userId): void
    {
        $stats = $this->service->getStats($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    // ==================== 私密内容 ====================
    
    /**
     * 获取创作者的私密内容
     * GET /api/sponsors/content
     */
    public function getCreatorContent(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $tierId = isset($_GET['tier_id']) ? (int)$_GET['tier_id'] : null;
        $content = $this->service->getCreatorContent($user['id'], $tierId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $content
        ]);
    }
    
    /**
     * 创建私密内容
     * POST /api/sponsors/content
     */
    public function createContent(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $content = $this->service->createContent($user['id'], $data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $content
            ]);
        } catch (\Exception $e) {
            ErrorHandler::sendError($e->getCode() ?: 400, $e->getMessage());
        }
    }
    
    /**
     * 获取赞助者可访问的内容
     * GET /api/sponsors/content/{creatorId}
     */
    public function getSponsorContent(int $creatorId): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            ErrorHandler::sendError(401, 'Unauthorized');
        }
        
        $content = $this->service->getSponsorContent($user['id'], $creatorId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $content
        ]);
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
