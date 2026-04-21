<?php
/**
 * CodeVault - 赞助系统服务
 * 
 * 提供赞助计划、赞助者管理、支付处理的核心业务逻辑
 */

namespace CodeVault\Services;

use PDO;
use Exception;

class SponsorService
{
    private PDO $pdo;
    private CacheService $cache;
    
    public function __construct(PDO $pdo, ?CacheService $cache = null)
    {
        $this->pdo = $pdo;
        $this->cache = $cache ?? new CacheService();
    }
    
    // ==================== 赞助计划管理 ====================
    
    /**
     * 创建赞助等级
     */
    public function createTier(int $userId, array $data): array
    {
        $this->validateTierData($data);
        
        // 获取最大位置
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(position), -1) FROM sponsor_tiers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $maxPosition = (int)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_tiers (user_id, name, description, monthly_amount, yearly_amount, one_time_amount, color, icon, benefits, position)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $userId,
            $data['name'],
            $data['description'] ?? null,
            $data['monthly_amount'],
            $data['yearly_amount'] ?? null,
            $data['one_time_amount'] ?? null,
            $data['color'] ?? '#0366d6',
            $data['icon'] ?? 'heart',
            json_encode($data['benefits'] ?? []),
            $maxPosition + 1
        ]);
        
        $tierId = (int)$this->pdo->lastInsertId();
        
        $this->cache->delete("user_tiers:{$userId}");
        
        return $this->getTier($tierId);
    }
    
    /**
     * 获取赞助等级详情
     */
    public function getTier(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_tiers WHERE id = ?");
        $stmt->execute([$id]);
        $tier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tier) {
            return null;
        }
        
        $tier['benefits'] = json_decode($tier['benefits'], true) ?: [];
        
        // 获取赞助者数量
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sponsors WHERE tier_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $tier['sponsor_count'] = (int)$stmt->fetchColumn();
        
        return $tier;
    }
    
    /**
     * 获取用户的赞助等级列表
     */
    public function getUserTiers(int $userId): array
    {
        $cacheKey = "user_tiers:{$userId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sponsor_tiers WHERE user_id = ? AND is_active = 1 ORDER BY monthly_amount ASC"
        );
        $stmt->execute([$userId]);
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tiers as &$tier) {
            $tier['benefits'] = json_decode($tier['benefits'], true) ?: [];
            
            // 获取赞助者数量
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sponsors WHERE tier_id = ? AND status = 'active'");
            $stmt->execute([$tier['id']]);
            $tier['sponsor_count'] = (int)$stmt->fetchColumn();
        }
        
        $this->cache->set($cacheKey, $tiers, 300);
        
        return $tiers;
    }
    
    /**
     * 更新赞助等级
     */
    public function updateTier(int $id, int $userId, array $data): array
    {
        $tier = $this->getTier($id);
        if (!$tier) {
            throw new Exception("Tier not found", 404);
        }
        
        if ($tier['user_id'] != $userId) {
            throw new Exception("Permission denied", 403);
        }
        
        $updates = [];
        $params = [];
        
        foreach (['name', 'description', 'monthly_amount', 'yearly_amount', 'one_time_amount', 'color', 'icon', 'benefits', 'is_active', 'position'] as $field) {
            if (isset($data[$field])) {
                if ($field === 'benefits') {
                    $updates[] = "benefits = ?";
                    $params[] = json_encode($data[$field]);
                } else {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            return $tier;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE sponsor_tiers SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $this->cache->delete("user_tiers:{$userId}");
        
        return $this->getTier($id);
    }
    
    /**
     * 删除赞助等级
     */
    public function deleteTier(int $id, int $userId): bool
    {
        $tier = $this->getTier($id);
        if (!$tier) {
            throw new Exception("Tier not found", 404);
        }
        
        if ($tier['user_id'] != $userId) {
            throw new Exception("Permission denied", 403);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM sponsor_tiers WHERE id = ?");
        $stmt->execute([$id]);
        
        $this->cache->delete("user_tiers:{$userId}");
        
        return true;
    }
    
    // ==================== 赞助管理 ====================
    
    /**
     * 创建赞助
     */
    public function createSponsor(array $data): array
    {
        $this->validateSponsorData($data);
        
        $this->pdo->beginTransaction();
        
        try {
            // 计算日期
            $startDate = new \DateTime();
            $endDate = null;
            $nextBillingDate = null;
            
            if ($data['frequency'] !== 'one_time') {
                $endDate = clone $startDate;
                $nextBillingDate = clone $startDate;
                
                if ($data['frequency'] === 'monthly') {
                    $endDate->modify('+1 month');
                    $nextBillingDate->modify('+1 month');
                } else {
                    $endDate->modify('+1 year');
                    $nextBillingDate->modify('+1 year');
                }
            }
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO sponsors (sponsor_user_id, creator_user_id, tier_id, amount, currency, payment_method, payment_id, status, frequency, is_anonymous, message, start_date, end_date, next_billing_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([
                $data['sponsor_user_id'],
                $data['creator_user_id'],
                $data['tier_id'] ?? null,
                $data['amount'],
                $data['currency'] ?? 'CNY',
                $data['payment_method'] ?? 'alipay',
                $data['payment_id'] ?? null,
                $data['status'] ?? 'pending',
                $data['frequency'] ?? 'monthly',
                $data['is_anonymous'] ?? 0,
                $data['message'] ?? null,
                $startDate->format('Y-m-d'),
                $endDate ? $endDate->format('Y-m-d') : null,
                $nextBillingDate ? $nextBillingDate->format('Y-m-d') : null
            ]);
            
            $sponsorId = (int)$this->pdo->lastInsertId();
            
            // 创建交易记录
            $this->createTransaction($sponsorId, [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'CNY',
                'payment_method' => $data['payment_method'] ?? 'alipay',
                'payment_id' => $data['payment_id'] ?? null,
                'status' => 'pending',
                'transaction_type' => 'payment'
            ]);
            
            // 更新统计
            $this->updateStats($data['creator_user_id']);
            
            // 更新徽章
            $this->updateBadge($data['sponsor_user_id']);
            
            $this->pdo->commit();
            
            return $this->getSponsor($sponsorId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * 获取赞助详情
     */
    public function getSponsor(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, 
                    t.name as tier_name, t.color as tier_color,
                    su.username as sponsor_name, su.avatar_url as sponsor_avatar,
                    cu.username as creator_name, cu.avatar_url as creator_avatar
             FROM sponsors s
             LEFT JOIN sponsor_tiers t ON s.tier_id = t.id
             LEFT JOIN users su ON s.sponsor_user_id = su.id
             LEFT JOIN users cu ON s.creator_user_id = cu.id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 获取用户的赞助列表（作为赞助者）
     */
    public function getMySponsorships(int $userId, array $filters = []): array
    {
        $where = ["s.sponsor_user_id = ?"];
        $params = [$userId];
        
        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql = "SELECT s.*, 
                       t.name as tier_name, t.color as tier_color,
                       cu.username as creator_name, cu.avatar_url as creator_avatar
                FROM sponsors s
                LEFT JOIN sponsor_tiers t ON s.tier_id = t.id
                LEFT JOIN users cu ON s.creator_user_id = cu.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY s.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取创作者的赞助者列表
     */
    public function getMySponsors(int $userId, array $filters = []): array
    {
        $where = ["s.creator_user_id = ?"];
        $params = [$userId];
        
        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['tier_id'])) {
            $where[] = "s.tier_id = ?";
            $params[] = $filters['tier_id'];
        }
        
        $sql = "SELECT s.*, 
                       t.name as tier_name, t.color as tier_color,
                       CASE WHEN s.is_anonymous = 1 THEN 'Anonymous' ELSE su.username END as sponsor_name,
                       CASE WHEN s.is_anonymous = 1 THEN NULL ELSE su.avatar_url END as sponsor_avatar
                FROM sponsors s
                LEFT JOIN sponsor_tiers t ON s.tier_id = t.id
                LEFT JOIN users su ON s.sponsor_user_id = su.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY s.amount DESC, s.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新赞助状态
     */
    public function updateSponsorStatus(int $id, string $status, ?string $paymentId = null): array
    {
        $sponsor = $this->getSponsor($id);
        if (!$sponsor) {
            throw new Exception("Sponsor not found", 404);
        }
        
        $updates = ["status = ?"];
        $params = [$status];
        
        if ($paymentId) {
            $updates[] = "payment_id = ?";
            $params[] = $paymentId;
        }
        
        if ($status === 'cancelled') {
            $updates[] = "end_date = CURDATE()";
            $updates[] = "next_billing_date = NULL";
        }
        
        $params[] = $id;
        
        $sql = "UPDATE sponsors SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        // 更新交易状态
        $stmt = $this->pdo->prepare(
            "UPDATE sponsor_transactions SET status = ? WHERE sponsor_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$status === 'active' ? 'success' : $status, $id]);
        
        // 更新统计
        $this->updateStats($sponsor['creator_user_id']);
        $this->updateBadge($sponsor['sponsor_user_id']);
        
        return $this->getSponsor($id);
    }
    
    /**
     * 取消赞助
     */
    public function cancelSponsor(int $id, int $userId): bool
    {
        $sponsor = $this->getSponsor($id);
        if (!$sponsor) {
            throw new Exception("Sponsor not found", 404);
        }
        
        if ($sponsor['sponsor_user_id'] != $userId) {
            throw new Exception("Permission denied", 403);
        }
        
        $this->updateSponsorStatus($id, 'cancelled');
        
        return true;
    }
    
    // ==================== 交易管理 ====================
    
    /**
     * 创建交易记录
     */
    private function createTransaction(int $sponsorId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_transactions (sponsor_id, amount, currency, payment_method, payment_id, status, transaction_type, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $sponsorId,
            $data['amount'],
            $data['currency'] ?? 'CNY',
            $data['payment_method'],
            $data['payment_id'] ?? null,
            $data['status'] ?? 'pending',
            $data['transaction_type'] ?? 'payment',
            $data['notes'] ?? null
        ]);
        
        $transactionId = (int)$this->pdo->lastInsertId();
        
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取交易历史
     */
    public function getTransactions(int $sponsorId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sponsor_transactions WHERE sponsor_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$sponsorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ==================== 徽章管理 ====================
    
    /**
     * 更新赞助者徽章
     */
    private function updateBadge(int $userId): void
    {
        // 计算总赞助金额
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
             FROM sponsors 
             WHERE sponsor_user_id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalSponsored = (float)$result['total'];
        $monthsSponsored = (int)$result['count'];
        
        // 确定徽章等级
        $badgeType = 'bronze';
        if ($totalSponsored >= 10000) {
            $badgeType = 'diamond';
        } elseif ($totalSponsored >= 5000) {
            $badgeType = 'platinum';
        } elseif ($totalSponsored >= 1000) {
            $badgeType = 'gold';
        } elseif ($totalSponsored >= 500) {
            $badgeType = 'silver';
        }
        
        // 更新或创建徽章
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_badges (user_id, badge_type, total_sponsored, months_sponsored)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE badge_type = VALUES(badge_type), total_sponsored = VALUES(total_sponsored), months_sponsored = VALUES(months_sponsored)"
        );
        $stmt->execute([$userId, $badgeType, $totalSponsored, $monthsSponsored]);
    }
    
    /**
     * 获取用户徽章
     */
    public function getBadge(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_badges WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // ==================== 目标管理 ====================
    
    /**
     * 创建赞助目标
     */
    public function createGoal(int $userId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_goals (user_id, title, description, target_amount, currency, deadline)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description'] ?? null,
            $data['target_amount'],
            $data['currency'] ?? 'CNY',
            $data['deadline'] ?? null
        ]);
        
        $goalId = (int)$this->pdo->lastInsertId();
        
        return $this->getGoal($goalId);
    }
    
    /**
     * 获取目标详情
     */
    public function getGoal(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_goals WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 获取用户的赞助目标
     */
    public function getUserGoals(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sponsor_goals WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新目标进度
     */
    public function updateGoalProgress(int $userId): void
    {
        // 获取当前总收入
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM sponsors WHERE creator_user_id = ? AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $totalEarnings = (float)$stmt->fetchColumn();
        
        // 更新所有活跃目标
        $stmt = $this->pdo->prepare(
            "UPDATE sponsor_goals SET current_amount = ?, is_achieved = CASE WHEN current_amount >= target_amount THEN 1 ELSE 0 END, achieved_at = CASE WHEN is_achieved = 0 AND ? >= target_amount THEN NOW() ELSE achieved_at END WHERE user_id = ? AND is_active = 1"
        );
        $stmt->execute([$totalEarnings, $totalEarnings, $userId]);
    }
    
    // ==================== 统计管理 ====================
    
    /**
     * 更新统计
     */
    private function updateStats(int $userId): void
    {
        // 计算各项统计
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, COALESCE(SUM(amount), 0) as total_earnings
             FROM sponsors WHERE creator_user_id = ?"
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM sponsors WHERE creator_user_id = ? AND status = 'active' AND frequency = 'monthly'"
        );
        $stmt->execute([$userId]);
        $monthlyEarnings = (float)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM sponsors WHERE creator_user_id = ? AND status = 'active' AND frequency = 'yearly'"
        );
        $stmt->execute([$userId]);
        $yearlyEarnings = (float)$stmt->fetchColumn();
        
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sponsor_transactions WHERE sponsor_id IN (SELECT id FROM sponsors WHERE creator_user_id = ?) AND status = 'success'"
        );
        $stmt->execute([$userId]);
        $totalTransactions = (int)$stmt->fetchColumn();
        
        $avgSponsorship = $result['active'] > 0 ? $result['total_earnings'] / $result['active'] : 0;
        
        // 更新统计表
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_stats (user_id, total_sponsors, active_sponsors, total_earnings, monthly_earnings, yearly_earnings, total_transactions, avg_sponsorship)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE total_sponsors = VALUES(total_sponsors), active_sponsors = VALUES(active_sponsors), total_earnings = VALUES(total_earnings), monthly_earnings = VALUES(monthly_earnings), yearly_earnings = VALUES(yearly_earnings), total_transactions = VALUES(total_transactions), avg_sponsorship = VALUES(avg_sponsorship)"
        );
        
        $stmt->execute([
            $userId,
            (int)$result['total'],
            (int)$result['active'],
            (float)$result['total_earnings'],
            $monthlyEarnings,
            $yearlyEarnings,
            $totalTransactions,
            $avgSponsorship
        ]);
        
        // 更新目标进度
        $this->updateGoalProgress($userId);
    }
    
    /**
     * 获取统计
     */
    public function getStats(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    // ==================== 私密内容管理 ====================
    
    /**
     * 创建私密内容
     */
    public function createContent(int $userId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sponsor_content (user_id, tier_id, title, content, content_type, file_path, is_public)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $userId,
            $data['tier_id'] ?? null,
            $data['title'],
            $data['content'],
            $data['content_type'] ?? 'post',
            $data['file_path'] ?? null,
            $data['is_public'] ?? 0
        ]);
        
        $contentId = (int)$this->pdo->lastInsertId();
        
        return $this->getContent($contentId);
    }
    
    /**
     * 获取私密内容
     */
    public function getContent(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sponsor_content WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 获取创作者的私密内容列表
     */
    public function getCreatorContent(int $userId, ?int $tierId = null): array
    {
        $where = ["user_id = ?"];
        $params = [$userId];
        
        if ($tierId) {
            $where[] = "(tier_id = ? OR tier_id IS NULL)";
            $params[] = $tierId;
        }
        
        $sql = "SELECT * FROM sponsor_content WHERE " . implode(" AND ", $where) . " ORDER BY published_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取赞助者可访问的内容
     */
    public function getSponsorContent(int $sponsorUserId, int $creatorUserId): array
    {
        // 获取赞助者订阅的等级
        $stmt = $this->pdo->prepare(
            "SELECT tier_id FROM sponsors WHERE sponsor_user_id = ? AND creator_user_id = ? AND status = 'active'"
        );
        $stmt->execute([$sponsorUserId, $creatorUserId]);
        $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sponsor) {
            // 未赞助，只返回公开内容
            $stmt = $this->pdo->prepare(
                "SELECT * FROM sponsor_content WHERE user_id = ? AND is_public = 1 ORDER BY published_at DESC"
            );
            $stmt->execute([$creatorUserId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // 返回赞助等级对应的内容和公开内容
        $sql = "SELECT * FROM sponsor_content WHERE user_id = ? AND (is_public = 1 OR tier_id = ? OR tier_id IS NULL) ORDER BY published_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$creatorUserId, $sponsor['tier_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ==================== 验证方法 ====================
    
    /**
     * 验证赞助等级数据
     */
    private function validateTierData(array $data): void
    {
        if (empty($data['name'])) {
            throw new Exception("Tier name is required", 400);
        }
        
        if (!isset($data['monthly_amount']) || $data['monthly_amount'] < 0) {
            throw new Exception("Valid monthly amount is required", 400);
        }
    }
    
    /**
     * 验证赞助数据
     */
    private function validateSponsorData(array $data): void
    {
        if (empty($data['sponsor_user_id'])) {
            throw new Exception("Sponsor user ID is required", 400);
        }
        
        if (empty($data['creator_user_id'])) {
            throw new Exception("Creator user ID is required", 400);
        }
        
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw new Exception("Valid amount is required", 400);
        }
    }
}
