<?php
/**
 * CodeVault 社交功能服务
 * 
 * 功能：
 * - 用户关注/粉丝系统
 * - 仓库 Star 排行榜
 * - 开发者活跃度榜单
 * - 动态信息流（Following Feed）
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class SocialService
{
    private $db;
    private $logger;
    private $cache;
    
    // 排行榜缓存时间
    private const RANKING_CACHE_TTL = 3600; // 1小时
    
    // 动态类型
    private const ACTIVITY_TYPES = [
        'push',           // 推送代码
        'star',           // Star 仓库
        'fork',           // Fork 仓库
        'issue_create',   // 创建 Issue
        'issue_close',    // 关闭 Issue
        'pr_create',      // 创建 PR
        'pr_merge',       // 合并 PR
        'release',        // 发布版本
        'repo_create',    // 创建仓库
        'follow',         // 关注用户
        'gist_create',    // 创建 Gist
        'discussion',     // 参与讨论
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('social');
        $this->cache = new Cache();
    }
    
    // ==================== 关注系统 ====================
    
    /**
     * 关注用户
     */
    public function follow(int $followerId, int $followingId): array
    {
        // 不能关注自己
        if ($followerId === $followingId) {
            return ['success' => false, 'error' => '不能关注自己'];
        }
        
        // 检查目标用户是否存在
        $target = $this->db->fetchOne(
            "SELECT id FROM users WHERE id = ? AND status = 'active'",
            [$followingId]
        );
        
        if (!$target) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        
        // 检查是否已关注
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );
        
        if ($existing) {
            return ['success' => false, 'error' => '已关注该用户'];
        }
        
        $this->db->beginTransaction();
        
        try {
            // 创建关注关系
            $this->db->execute(
                "INSERT INTO user_follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())",
                [$followerId, $followingId]
            );
            
            // 更新统计
            $this->db->execute(
                "UPDATE users SET following_count = following_count + 1 WHERE id = ?",
                [$followerId]
            );
            
            $this->db->execute(
                "UPDATE users SET followers_count = followers_count + 1 WHERE id = ?",
                [$followingId]
            );
            
            // 记录动态
            $this->recordActivity($followerId, 'follow', [
                'following_id' => $followingId,
            ]);
            
            $this->db->commit();
            
            $this->logger->info('用户关注成功', [
                'follower_id' => $followerId,
                'following_id' => $followingId,
            ]);
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '关注失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 取消关注
     */
    public function unfollow(int $followerId, int $followingId): array
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );
        
        if (!$existing) {
            return ['success' => false, 'error' => '未关注该用户'];
        }
        
        $this->db->beginTransaction();
        
        try {
            $this->db->execute(
                "DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?",
                [$followerId, $followingId]
            );
            
            $this->db->execute(
                "UPDATE users SET following_count = GREATEST(0, following_count - 1) WHERE id = ?",
                [$followerId]
            );
            
            $this->db->execute(
                "UPDATE users SET followers_count = GREATEST(0, followers_count - 1) WHERE id = ?",
                [$followingId]
            );
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => '取消关注失败'];
        }
    }
    
    /**
     * 检查是否关注
     */
    public function isFollowing(int $followerId, int $followingId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );
        
        return (bool)$result;
    }
    
    /**
     * 获取关注列表
     */
    public function getFollowing(int $userId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.name, u.avatar_url, u.bio, uf.created_at as followed_at
             FROM user_follows uf
             JOIN users u ON uf.following_id = u.id
             WHERE uf.follower_id = ?
             ORDER BY uf.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );
    }
    
    /**
     * 获取粉丝列表
     */
    public function getFollowers(int $userId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.name, u.avatar_url, u.bio, uf.created_at as followed_at
             FROM user_follows uf
             JOIN users u ON uf.follower_id = u.id
             WHERE uf.following_id = ?
             ORDER BY uf.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );
    }
    
    // ==================== 排行榜 ====================
    
    /**
     * 获取 Star 排行榜
     */
    public function getStarRanking(string $period = 'all', int $limit = 100): array
    {
        $cacheKey = "star_ranking:{$period}:{$limit}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateCondition = $this->getPeriodCondition($period, 'r.created_at');
        
        $sql = "SELECT r.id, r.name, r.description, r.owner_id, r.stars_count,
                       u.username as owner_name, u.avatar_url as owner_avatar
                FROM repositories r
                JOIN users u ON r.owner_id = u.id
                WHERE r.is_public = 1 {$dateCondition}
                ORDER BY r.stars_count DESC
                LIMIT ?";
        
        $ranking = $this->db->fetchAll($sql, [$limit]);
        
        // 添加排名
        foreach ($ranking as $index => &$repo) {
            $repo['rank'] = $index + 1;
        }
        
        $this->cache->set($cacheKey, $ranking, self::RANKING_CACHE_TTL);
        
        return $ranking;
    }
    
    /**
     * 获取开发者活跃度榜单
     */
    public function getDeveloperRanking(string $period = 'month', int $limit = 100): array
    {
        $cacheKey = "developer_ranking:{$period}:{$limit}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateCondition = $this->getPeriodCondition($period, 'a.created_at');
        
        // 计算活跃度分数
        // 提交权重: 1, PR: 5, Issue: 2, 评论: 1
        $sql = "SELECT u.id, u.username, u.name, u.avatar_url, u.bio,
                       COUNT(DISTINCT CASE WHEN a.type = 'push' THEN a.id END) as commits,
                       COUNT(DISTINCT CASE WHEN a.type = 'pr_create' THEN a.id END) as prs,
                       COUNT(DISTINCT CASE WHEN a.type = 'pr_merge' THEN a.id END) as merged_prs,
                       COUNT(DISTINCT CASE WHEN a.type = 'issue_create' THEN a.id END) as issues,
                       COUNT(DISTINCT CASE WHEN a.type = 'issue_close' THEN a.id END) as closed_issues,
                       (
                           COUNT(DISTINCT CASE WHEN a.type = 'push' THEN a.id END) * 1 +
                           COUNT(DISTINCT CASE WHEN a.type = 'pr_create' THEN a.id END) * 5 +
                           COUNT(DISTINCT CASE WHEN a.type = 'pr_merge' THEN a.id END) * 10 +
                           COUNT(DISTINCT CASE WHEN a.type = 'issue_create' THEN a.id END) * 2 +
                           COUNT(DISTINCT CASE WHEN a.type = 'issue_close' THEN a.id END) * 3
                       ) as activity_score
                FROM users u
                LEFT JOIN user_activities a ON u.id = a.user_id {$dateCondition}
                WHERE u.status = 'active'
                GROUP BY u.id
                HAVING activity_score > 0
                ORDER BY activity_score DESC
                LIMIT ?";
        
        $ranking = $this->db->fetchAll($sql, [$limit]);
        
        // 添加排名
        foreach ($ranking as $index => &$user) {
            $user['rank'] = $index + 1;
        }
        
        $this->cache->set($cacheKey, $ranking, self::RANKING_CACHE_TTL);
        
        return $ranking;
    }
    
    /**
     * 获取 Fork 排行榜
     */
    public function getForkRanking(string $period = 'all', int $limit = 100): array
    {
        $cacheKey = "fork_ranking:{$period}:{$limit}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateCondition = $this->getPeriodCondition($period, 'r.created_at');
        
        $sql = "SELECT r.id, r.name, r.description, r.owner_id, r.forks_count,
                       u.username as owner_name, u.avatar_url as owner_avatar
                FROM repositories r
                JOIN users u ON r.owner_id = u.id
                WHERE r.is_public = 1 {$dateCondition}
                ORDER BY r.forks_count DESC
                LIMIT ?";
        
        $ranking = $this->db->fetchAll($sql, [$limit]);
        
        foreach ($ranking as $index => &$repo) {
            $repo['rank'] = $index + 1;
        }
        
        $this->cache->set($cacheKey, $ranking, self::RANKING_CACHE_TTL);
        
        return $ranking;
    }
    
    // ==================== 动态信息流 ====================
    
    /**
     * 记录用户动态
     */
    public function recordActivity(int $userId, string $type, array $data = []): int
    {
        if (!in_array($type, self::ACTIVITY_TYPES)) {
            return 0;
        }
        
        $sql = "INSERT INTO user_activities (user_id, type, data, created_at) VALUES (?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $userId,
            $type,
            json_encode($data),
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * 获取关注用户的动态流
     */
    public function getFollowingFeed(int $userId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        // 获取关注用户的动态
        $sql = "SELECT a.*, u.username, u.name, u.avatar_url
                FROM user_activities a
                JOIN users u ON a.user_id = u.id
                JOIN user_follows uf ON a.user_id = uf.following_id
                WHERE uf.follower_id = ?
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $activities = $this->db->fetchAll($sql, [$userId, $perPage, $offset]);
        
        // 丰富动态内容
        foreach ($activities as &$activity) {
            $activity['data'] = json_decode($activity['data'], true);
            $activity['formatted'] = $this->formatActivity($activity);
        }
        
        return $activities;
    }
    
    /**
     * 获取用户个人动态
     */
    public function getUserActivities(int $userId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT a.*, u.username, u.name, u.avatar_url
                FROM user_activities a
                JOIN users u ON a.user_id = u.id
                WHERE a.user_id = ?
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $activities = $this->db->fetchAll($sql, [$userId, $perPage, $offset]);
        
        foreach ($activities as &$activity) {
            $activity['data'] = json_decode($activity['data'], true);
            $activity['formatted'] = $this->formatActivity($activity);
        }
        
        return $activities;
    }
    
    /**
     * 获取全站动态
     */
    public function getPublicFeed(int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT a.*, u.username, u.name, u.avatar_url
                FROM user_activities a
                JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC
                LIMIT ? OFFSET ?";
        
        $activities = $this->db->fetchAll($sql, [$perPage, $offset]);
        
        foreach ($activities as &$activity) {
            $activity['data'] = json_decode($activity['data'], true);
            $activity['formatted'] = $this->formatActivity($activity);
        }
        
        return $activities;
    }
    
    /**
     * 格式化动态内容
     */
    private function formatActivity(array $activity): string
    {
        $data = $activity['data'] ?? [];
        $username = $activity['username'];
        
        return match ($activity['type']) {
            'push' => sprintf(
                '%s pushed %d commits to %s',
                $username,
                $data['commit_count'] ?? 1,
                $data['repo_name'] ?? 'repository'
            ),
            'star' => sprintf(
                '%s starred %s',
                $username,
                $data['repo_name'] ?? 'repository'
            ),
            'fork' => sprintf(
                '%s forked %s',
                $username,
                $data['repo_name'] ?? 'repository'
            ),
            'issue_create' => sprintf(
                '%s opened issue #%d in %s',
                $username,
                $data['issue_number'] ?? 0,
                $data['repo_name'] ?? 'repository'
            ),
            'issue_close' => sprintf(
                '%s closed issue #%d in %s',
                $username,
                $data['issue_number'] ?? 0,
                $data['repo_name'] ?? 'repository'
            ),
            'pr_create' => sprintf(
                '%s opened PR #%d in %s',
                $username,
                $data['pr_number'] ?? 0,
                $data['repo_name'] ?? 'repository'
            ),
            'pr_merge' => sprintf(
                '%s merged PR #%d in %s',
                $username,
                $data['pr_number'] ?? 0,
                $data['repo_name'] ?? 'repository'
            ),
            'release' => sprintf(
                '%s released %s in %s',
                $username,
                $data['tag_name'] ?? 'v1.0.0',
                $data['repo_name'] ?? 'repository'
            ),
            'repo_create' => sprintf(
                '%s created repository %s',
                $username,
                $data['repo_name'] ?? 'new repository'
            ),
            'follow' => sprintf(
                '%s started following a user',
                $username
            ),
            'gist_create' => sprintf(
                '%s created a gist: %s',
                $username,
                $data['gist_description'] ?? 'untitled'
            ),
            'discussion' => sprintf(
                '%s participated in a discussion in %s',
                $username,
                $data['repo_name'] ?? 'repository'
            ),
            default => sprintf('%s performed an action', $username),
        };
    }
    
    // ==================== 辅助方法 ====================
    
    /**
     * 获取时间周期条件
     */
    private function getPeriodCondition(string $period, string $column = 'created_at'): string
    {
        return match ($period) {
            'day' => "AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'week' => "AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "AND {$column} >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => '',
        };
    }
    
    /**
     * 获取用户社交统计
     */
    public function getUserSocialStats(int $userId): array
    {
        return $this->db->fetchOne(
            "SELECT 
                followers_count,
                following_count,
                (SELECT COUNT(*) FROM user_activities WHERE user_id = ?) as total_activities,
                (SELECT COUNT(*) FROM user_activities WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as monthly_activities
            FROM users WHERE id = ?",
            [$userId, $userId, $userId]
        ) ?: [];
    }
}
