<?php
/**
 * CodeVault 统计分析服务
 * 
 * 功能：
 * - 仓库统计
 * - 用户统计
 * - 活跃度分析
 * - 贡献者统计
 * - 代码变更统计
 * - 趋势分析
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class StatsService
{
    private $db;
    private $logger;
    private $cache;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('stats');
        $this->cache = Cache::getInstance();
    }
    
    /**
     * 获取仓库统计
     */
    public function getRepoStats(int $repoId): array
    {
        $cacheKey = "stats:repo:{$repoId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $stats = [
            'overview' => $this->getRepoOverview($repoId),
            'commits' => $this->getCommitStats($repoId),
            'contributors' => $this->getContributorStats($repoId),
            'issues' => $this->getIssueStats($repoId),
            'prs' => $this->getPullRequestStats($repoId),
            'activity' => $this->getActivityStats($repoId),
            'languages' => $this->getLanguageStats($repoId),
        ];
        
        $this->cache->set($cacheKey, $stats, 300); // 5分钟缓存
        
        return $stats;
    }
    
    /**
     * 仓库概览
     */
    private function getRepoOverview(int $repoId): array
    {
        $repo = $this->db->fetchOne(
            "SELECT r.*, 
                    (SELECT COUNT(*) FROM stars WHERE repo_id = r.id) as stars_count,
                    (SELECT COUNT(*) FROM forks WHERE repo_id = r.id) as forks_count,
                    (SELECT COUNT(*) FROM watchers WHERE repo_id = r.id) as watchers_count
             FROM repositories r WHERE r.id = ?",
            [$repoId]
        );
        
        if (!$repo) {
            return [];
        }
        
        return [
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'stars' => (int)$repo['stars_count'],
            'forks' => (int)$repo['forks_count'],
            'watchers' => (int)$repo['watchers_count'],
            'open_issues' => (int)$repo['open_issues_count'],
            'size' => (int)$repo['size'],
            'created_at' => $repo['created_at'],
            'updated_at' => $repo['updated_at'],
            'pushed_at' => $repo['pushed_at'],
        ];
    }
    
    /**
     * 提交统计
     */
    private function getCommitStats(int $repoId): array
    {
        // 总提交数
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM commits WHERE repo_id = ?",
            [$repoId]
        )['count'];
        
        // 最近 30 天提交数
        $last30Days = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM commits 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$repoId]
        )['count'];
        
        // 最近 7 天提交数
        $last7Days = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM commits 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$repoId]
        )['count'];
        
        // 今日提交数
        $today = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM commits 
             WHERE repo_id = ? AND DATE(created_at) = CURDATE()",
            [$repoId]
        )['count'];
        
        // 按周统计（最近 12 周）
        $weekly = $this->db->fetchAll(
            "SELECT YEARWEEK(created_at, 1) as week, COUNT(*) as count
             FROM commits 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
             GROUP BY YEARWEEK(created_at, 1)
             ORDER BY week DESC",
            [$repoId]
        );
        
        // 按月统计（最近 12 个月）
        $monthly = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM commits 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month DESC",
            [$repoId]
        );
        
        return [
            'total' => (int)$total,
            'last_30_days' => (int)$last30Days,
            'last_7_days' => (int)$last7Days,
            'today' => (int)$today,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }
    
    /**
     * 贡献者统计
     */
    private function getContributorStats(int $repoId): array
    {
        // 总贡献者数
        $total = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT author_email) as count FROM commits WHERE repo_id = ?",
            [$repoId]
        )['count'];
        
        // Top 贡献者
        $topContributors = $this->db->fetchAll(
            "SELECT author_name, author_email, COUNT(*) as commits,
                    SUM(additions) as additions, SUM(deletions) as deletions
             FROM commits 
             WHERE repo_id = ?
             GROUP BY author_email
             ORDER BY commits DESC
             LIMIT 20",
            [$repoId]
        );
        
        // 最近活跃贡献者（30 天内）
        $activeContributors = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT author_email) as count 
             FROM commits 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$repoId]
        )['count'];
        
        return [
            'total' => (int)$total,
            'active_30_days' => (int)$activeContributors,
            'top_contributors' => $topContributors,
        ];
    }
    
    /**
     * Issue 统计
     */
    private function getIssueStats(int $repoId): array
    {
        // 状态分布
        $byStatus = $this->db->fetchAll(
            "SELECT state, COUNT(*) as count FROM issues WHERE repo_id = ? GROUP BY state",
            [$repoId]
        );
        
        // 标签分布
        $byLabel = $this->db->fetchAll(
            "SELECT l.name, l.color, COUNT(il.issue_id) as count
             FROM labels l
             LEFT JOIN issue_labels il ON l.id = il.label_id
             LEFT JOIN issues i ON il.issue_id = i.id
             WHERE i.repo_id = ?
             GROUP BY l.id
             ORDER BY count DESC
             LIMIT 10",
            [$repoId]
        );
        
        // 平均关闭时间
        $avgCloseTime = $this->db->fetchOne(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as hours
             FROM issues 
             WHERE repo_id = ? AND state = 'closed' AND closed_at IS NOT NULL",
            [$repoId]
        );
        
        // 最近 30 天创建/关闭趋势
        $trend = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN closed_at IS NOT NULL AND DATE(closed_at) = DATE(created_at) THEN 1 ELSE 0 END) as closed
             FROM issues 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$repoId]
        );
        
        return [
            'by_status' => $byStatus,
            'by_label' => $byLabel,
            'avg_close_time_hours' => round((float)$avgCloseTime['hours'], 1),
            'trend_30_days' => $trend,
        ];
    }
    
    /**
     * Pull Request 统计
     */
    private function getPullRequestStats(int $repoId): array
    {
        // 状态分布
        $byStatus = $this->db->fetchAll(
            "SELECT state, COUNT(*) as count FROM pull_requests WHERE repo_id = ? GROUP BY state",
            [$repoId]
        );
        
        // 平均合并时间
        $avgMergeTime = $this->db->fetchOne(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, merged_at)) as hours
             FROM pull_requests 
             WHERE repo_id = ? AND state = 'merged' AND merged_at IS NOT NULL",
            [$repoId]
        );
        
        // 合并率
        $mergeRate = $this->db->fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM pull_requests WHERE repo_id = ? AND state = 'merged') as merged,
                (SELECT COUNT(*) FROM pull_requests WHERE repo_id = ?) as total",
            [$repoId, $repoId]
        );
        
        $mergeRatePercent = $mergeRate['total'] > 0 
            ? round(($mergeRate['merged'] / $mergeRate['total']) * 100, 1) 
            : 0;
        
        // 代码变更统计
        $codeChanges = $this->db->fetchOne(
            "SELECT SUM(additions) as additions, SUM(deletions) as deletions, SUM(changed_files) as files
             FROM pull_requests WHERE repo_id = ? AND state = 'merged'",
            [$repoId]
        );
        
        return [
            'by_status' => $byStatus,
            'avg_merge_time_hours' => round((float)$avgMergeTime['hours'], 1),
            'merge_rate' => $mergeRatePercent,
            'code_changes' => [
                'additions' => (int)($codeChanges['additions'] ?? 0),
                'deletions' => (int)($codeChanges['deletions'] ?? 0),
                'files' => (int)($codeChanges['files'] ?? 0),
            ],
        ];
    }
    
    /**
     * 活跃度统计
     */
    private function getActivityStats(int $repoId): array
    {
        // 最近 30 天活动
        $activity = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, 
                    SUM(CASE WHEN type = 'commit' THEN 1 ELSE 0 END) as commits,
                    SUM(CASE WHEN type = 'issue_opened' THEN 1 ELSE 0 END) as issues_opened,
                    SUM(CASE WHEN type = 'issue_closed' THEN 1 ELSE 0 END) as issues_closed,
                    SUM(CASE WHEN type = 'pr_opened' THEN 1 ELSE 0 END) as prs_opened,
                    SUM(CASE WHEN type = 'pr_merged' THEN 1 ELSE 0 END) as prs_merged
             FROM activity_log 
             WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$repoId]
        );
        
        // 活跃度评分（0-100）
        $score = $this->calculateActivityScore($repoId);
        
        return [
            'activity_30_days' => $activity,
            'activity_score' => $score,
        ];
    }
    
    /**
     * 计算活跃度评分
     */
    private function calculateActivityScore(int $repoId): int
    {
        // 基于最近 30 天的活动计算
        $stats = $this->db->fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM commits WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as commits,
                (SELECT COUNT(*) FROM issues WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as issues,
                (SELECT COUNT(*) FROM pull_requests WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as prs,
                (SELECT COUNT(*) FROM comments WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as comments,
                (SELECT COUNT(DISTINCT user_id) FROM stars WHERE repo_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_stars",
            [$repoId, $repoId, $repoId, $repoId, $repoId]
        );
        
        // 加权计算
        $score = 0;
        $score += min($stats['commits'] * 2, 40); // 提交最多 40 分
        $score += min($stats['issues'] * 3, 20);  // Issue 最多 20 分
        $score += min($stats['prs'] * 5, 25);     // PR 最多 25 分
        $score += min($stats['comments'] * 1, 10); // 评论最多 10 分
        $score += min($stats['new_stars'] * 0.5, 5); // 新星标最多 5 分
        
        return min(100, (int)$score);
    }
    
    /**
     * 语言统计
     */
    private function getLanguageStats(int $repoId): array
    {
        return $this->db->fetchAll(
            "SELECT language, bytes, 
                    ROUND(bytes / (SELECT SUM(bytes) FROM repo_languages WHERE repo_id = ?) * 100, 1) as percentage
             FROM repo_languages 
             WHERE repo_id = ?
             ORDER BY bytes DESC",
            [$repoId, $repoId]
        );
    }
    
    /**
     * 获取用户统计
     */
    public function getUserStats(int $userId): array
    {
        $cacheKey = "stats:user:{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $stats = [
            'overview' => $this->getUserOverview($userId),
            'contributions' => $this->getUserContributions($userId),
            'repos' => $this->getUserRepoStats($userId),
            'activity' => $this->getUserActivity($userId),
        ];
        
        $this->cache->set($cacheKey, $stats, 300);
        
        return $stats;
    }
    
    /**
     * 用户概览
     */
    private function getUserOverview(int $userId): array
    {
        $user = $this->db->fetchOne(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM repositories WHERE user_id = u.id) as repos_count,
                    (SELECT COUNT(*) FROM stars s JOIN repositories r ON s.repo_id = r.id WHERE r.user_id = u.id) as total_stars,
                    (SELECT COUNT(*) FROM forks f JOIN repositories r ON f.repo_id = r.id WHERE r.user_id = u.id) as total_forks
             FROM users u WHERE u.id = ?",
            [$userId]
        );
        
        return [
            'repos' => (int)$user['repos_count'],
            'total_stars' => (int)$user['total_stars'],
            'total_forks' => (int)$user['total_forks'],
            'followers' => (int)$user['followers_count'],
            'following' => (int)$user['following_count'],
        ];
    }
    
    /**
     * 用户贡献统计
     */
    private function getUserContributions(int $userId): array
    {
        // 最近一年贡献日历
        $calendar = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM commits 
             WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$userId]
        );
        
        // 总贡献
        $total = $this->db->fetchOne(
            "SELECT 
                (SELECT COUNT(*) FROM commits WHERE author_id = ?) as commits,
                (SELECT COUNT(*) FROM issues WHERE author_id = ?) as issues,
                (SELECT COUNT(*) FROM pull_requests WHERE author_id = ?) as prs,
                (SELECT COUNT(*) FROM comments WHERE author_id = ?) as comments,
                (SELECT COUNT(*) FROM reviews WHERE reviewer_id = ?) as reviews",
            [$userId, $userId, $userId, $userId, $userId]
        );
        
        return [
            'calendar' => $calendar,
            'total' => $total,
        ];
    }
    
    /**
     * 用户仓库统计
     */
    private function getUserRepoStats(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT r.name, r.full_name, r.language,
                    (SELECT COUNT(*) FROM stars WHERE repo_id = r.id) as stars,
                    (SELECT COUNT(*) FROM forks WHERE repo_id = r.id) as forks,
                    (SELECT COUNT(*) FROM watchers WHERE repo_id = r.id) as watchers
             FROM repositories r
             WHERE r.user_id = ?
             ORDER BY stars DESC
             LIMIT 10",
            [$userId]
        );
    }
    
    /**
     * 用户活动统计
     */
    private function getUserActivity(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT type, COUNT(*) as count
             FROM activity_log
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY type
             ORDER BY count DESC",
            [$userId]
        );
    }
    
    /**
     * 获取全局统计
     */
    public function getGlobalStats(): array
    {
        $cacheKey = "stats:global";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $stats = [
            'users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
            'repos' => $this->db->fetchOne("SELECT COUNT(*) as count FROM repositories")['count'],
            'commits' => $this->db->fetchOne("SELECT COUNT(*) as count FROM commits")['count'],
            'issues' => $this->db->fetchOne("SELECT COUNT(*) as count FROM issues")['count'],
            'prs' => $this->db->fetchOne("SELECT COUNT(*) as count FROM pull_requests")['count'],
            'gists' => $this->db->fetchOne("SELECT COUNT(*) as count FROM gists")['count'],
            'stars' => $this->db->fetchOne("SELECT COUNT(*) as count FROM stars")['count'],
            'forks' => $this->db->fetchOne("SELECT COUNT(*) as count FROM forks")['count'],
        ];
        
        // 最近 24 小时活动
        $stats['activity_24h'] = [
            'new_users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'],
            'new_repos' => $this->db->fetchOne("SELECT COUNT(*) as count FROM repositories WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'],
            'commits' => $this->db->fetchOne("SELECT COUNT(*) as count FROM commits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'],
        ];
        
        // 热门仓库
        $stats['trending_repos'] = $this->db->fetchAll(
            "SELECT r.name, r.full_name, r.description, r.language,
                    (SELECT COUNT(*) FROM stars WHERE repo_id = r.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as stars_week
             FROM repositories r
             WHERE r.private = 0
             ORDER BY stars_week DESC
             LIMIT 10"
        );
        
        // 热门语言
        $stats['popular_languages'] = $this->db->fetchAll(
            "SELECT language, COUNT(*) as count
             FROM repositories
             WHERE language IS NOT NULL
             GROUP BY language
             ORDER BY count DESC
             LIMIT 10"
        );
        
        $this->cache->set($cacheKey, $stats, 60); // 1分钟缓存
        
        return $stats;
    }
    
    /**
     * 获取趋势数据
     */
    public function getTrends(string $period = 'month'): array
    {
        $cacheKey = "stats:trends:{$period}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $interval = match ($period) {
            'week' => '1 WEEK',
            'month' => '1 MONTH',
            'year' => '1 YEAR',
            default => '1 MONTH',
        };
        
        $trends = [
            'users' => $this->db->fetchAll(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM users
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY DATE(created_at)
                 ORDER BY date"
            ),
            'repos' => $this->db->fetchAll(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM repositories
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY DATE(created_at)
                 ORDER BY date"
            ),
            'commits' => $this->db->fetchAll(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                 FROM commits
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                 GROUP BY DATE(created_at)
                 ORDER BY date"
            ),
        ];
        
        $this->cache->set($cacheKey, $trends, 300);
        
        return $trends;
    }
}
