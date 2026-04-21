<?php
/**
 * CodeVault 统计分析服务
 * 
 * 功能：
 * - 代码贡献统计
 * - 活跃度分析
 * - 趋势图表
 * - 仓库统计
 */

namespace Services;

use Core\Database;
use Core\Logger;

class AnalyticsService
{
    private $db;
    private $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('analytics');
    }
    
    /**
     * 获取仓库统计概览
     */
    public function getRepoStats(string $owner, string $repo): array
    {
        return [
            'commits' => $this->getCommitStats($owner, $repo),
            'contributors' => $this->getContributorStats($owner, $repo),
            'code_changes' => $this->getCodeChangeStats($owner, $repo),
            'issues' => $this->getIssueStats($owner, $repo),
            'pull_requests' => $this->getPullRequestStats($owner, $repo),
            'activity' => $this->getActivityTimeline($owner, $repo, 30),
        ];
    }
    
    /**
     * 获取提交统计
     */
    public function getCommitStats(string $owner, string $repo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT author_email) as authors,
                    MIN(created_at) as first_commit,
                    MAX(created_at) as last_commit
                FROM commits 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)";
        
        $result = $this->db->fetchOne($sql, [$owner, $repo]);
        
        // 按时间段统计
        $periodStats = $this->db->fetchAll(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM commits 
            WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date",
            [$owner, $repo]
        );
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'authors' => (int)($result['authors'] ?? 0),
            'first_commit' => $result['first_commit'],
            'last_commit' => $result['last_commit'],
            'daily' => $periodStats,
        ];
    }
    
    /**
     * 获取贡献者统计
     */
    public function getContributorStats(string $owner, string $repo): array
    {
        $sql = "SELECT 
                    author_name,
                    author_email,
                    COUNT(*) as commits,
                    SUM(additions) as additions,
                    SUM(deletions) as deletions
                FROM commits 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                GROUP BY author_email, author_name
                ORDER BY commits DESC
                LIMIT 100";
        
        $contributors = $this->db->fetchAll($sql, [$owner, $repo]);
        
        return [
            'total' => count($contributors),
            'top_contributors' => array_slice($contributors, 0, 10),
            'all' => $contributors,
        ];
    }
    
    /**
     * 获取代码变更统计
     */
    public function getCodeChangeStats(string $owner, string $repo): array
    {
        $sql = "SELECT 
                    SUM(additions) as additions,
                    SUM(deletions) as deletions,
                    SUM(files_changed) as files_changed
                FROM commits 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)";
        
        $result = $this->db->fetchOne($sql, [$owner, $repo]);
        
        // 按周统计
        $weeklyStats = $this->db->fetchAll(
            "SELECT 
                YEARWEEK(created_at) as week,
                SUM(additions) as additions,
                SUM(deletions) as deletions
            FROM commits 
            WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
            GROUP BY YEARWEEK(created_at)
            ORDER BY week",
            [$owner, $repo]
        );
        
        return [
            'additions' => (int)($result['additions'] ?? 0),
            'deletions' => (int)($result['deletions'] ?? 0),
            'files_changed' => (int)($result['files_changed'] ?? 0),
            'weekly' => $weeklyStats,
        ];
    }
    
    /**
     * 获取 Issue 统计
     */
    public function getIssueStats(string $owner, string $repo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN state = 'closed' THEN 1 ELSE 0 END) as closed,
                    AVG(CASE WHEN state = 'closed' THEN DATEDIFF(closed_at, created_at) END) as avg_resolution_days
                FROM issues 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)";
        
        $result = $this->db->fetchOne($sql, [$owner, $repo]);
        
        // 按标签统计
        $labelStats = $this->db->fetchAll(
            "SELECT 
                l.name,
                l.color,
                COUNT(*) as count
            FROM issues i
            JOIN issue_labels il ON i.id = il.issue_id
            JOIN labels l ON il.label_id = l.id
            WHERE i.repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
            GROUP BY l.id
            ORDER BY count DESC",
            [$owner, $repo]
        );
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'open' => (int)($result['open'] ?? 0),
            'closed' => (int)($result['closed'] ?? 0),
            'avg_resolution_days' => round((float)($result['avg_resolution_days'] ?? 0), 1),
            'by_label' => $labelStats,
        ];
    }
    
    /**
     * 获取 Pull Request 统计
     */
    public function getPullRequestStats(string $owner, string $repo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN state = 'closed' AND merged = 1 THEN 1 ELSE 0 END) as merged,
                    SUM(CASE WHEN state = 'closed' AND merged = 0 THEN 1 ELSE 0 END) as closed,
                    AVG(CASE WHEN merged = 1 THEN DATEDIFF(merged_at, created_at) END) as avg_merge_days
                FROM pull_requests 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)";
        
        $result = $this->db->fetchOne($sql, [$owner, $repo]);
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'open' => (int)($result['open'] ?? 0),
            'merged' => (int)($result['merged'] ?? 0),
            'closed' => (int)($result['closed'] ?? 0),
            'merge_rate' => $result['total'] > 0 
                ? round(($result['merged'] / $result['total']) * 100, 1) 
                : 0,
            'avg_merge_days' => round((float)($result['avg_merge_days'] ?? 0), 1),
        ];
    }
    
    /**
     * 获取活动时间线
     */
    public function getActivityTimeline(string $owner, string $repo, int $days = 30): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    'commit' as type,
                    COUNT(*) as count
                FROM commits 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                
                UNION ALL
                
                SELECT 
                    DATE(created_at) as date,
                    'issue' as type,
                    COUNT(*) as count
                FROM issues 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                
                UNION ALL
                
                SELECT 
                    DATE(created_at) as date,
                    'pr' as type,
                    COUNT(*) as count
                FROM pull_requests 
                WHERE repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                
                ORDER BY date, type";
        
        return $this->db->fetchAll($sql, [
            $owner, $repo, $days,
            $owner, $repo, $days,
            $owner, $repo, $days,
        ]);
    }
    
    /**
     * 获取用户贡献统计
     */
    public function getUserStats(int $userId): array
    {
        // 提交统计
        $commitStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(additions) as additions,
                SUM(deletions) as deletions
            FROM commits 
            WHERE author_id = ?",
            [$userId]
        );
        
        // Issue 统计
        $issueStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN state = 'closed' THEN 1 ELSE 0 END) as closed
            FROM issues 
            WHERE author_id = ?",
            [$userId]
        );
        
        // PR 统计
        $prStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN merged = 1 THEN 1 ELSE 0 END) as merged
            FROM pull_requests 
            WHERE author_id = ?",
            [$userId]
        );
        
        // 活跃仓库
        $activeRepos = $this->db->fetchAll(
            "SELECT 
                r.owner,
                r.name,
                COUNT(*) as contributions
            FROM commits c
            JOIN repositories r ON c.repo_id = r.id
            WHERE c.author_id = ?
            GROUP BY r.id
            ORDER BY contributions DESC
            LIMIT 10",
            [$userId]
        );
        
        // 贡献日历（过去一年）
        $calendar = $this->getContributionCalendar($userId);
        
        return [
            'commits' => [
                'total' => (int)($commitStats['total'] ?? 0),
                'additions' => (int)($commitStats['additions'] ?? 0),
                'deletions' => (int)($commitStats['deletions'] ?? 0),
            ],
            'issues' => [
                'total' => (int)($issueStats['total'] ?? 0),
                'open' => (int)($issueStats['open'] ?? 0),
                'closed' => (int)($issueStats['closed'] ?? 0),
            ],
            'pull_requests' => [
                'total' => (int)($prStats['total'] ?? 0),
                'merged' => (int)($prStats['merged'] ?? 0),
            ],
            'active_repos' => $activeRepos,
            'calendar' => $calendar,
        ];
    }
    
    /**
     * 获取贡献日历
     */
    public function getContributionCalendar(int $userId): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM (
                    SELECT created_at FROM commits WHERE author_id = ?
                    UNION ALL
                    SELECT created_at FROM issues WHERE author_id = ?
                    UNION ALL
                    SELECT created_at FROM pull_requests WHERE author_id = ?
                    UNION ALL
                    SELECT created_at FROM comments WHERE author_id = ?
                ) AS activities
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        $data = $this->db->fetchAll($sql, [$userId, $userId, $userId, $userId]);
        
        // 转换为日历格式
        $calendar = [];
        foreach ($data as $row) {
            $calendar[$row['date']] = (int)$row['count'];
        }
        
        return $calendar;
    }
    
    /**
     * 获取趋势数据
     */
    public function getTrends(string $owner, string $repo, string $metric = 'commits', int $days = 30): array
    {
        $dateField = match ($metric) {
            'issues' => 'i.created_at',
            'pull_requests' => 'pr.created_at',
            'stars' => 's.created_at',
            default => 'c.created_at',
        };
        
        $sql = match ($metric) {
            'commits' => "
                SELECT DATE(c.created_at) as date, COUNT(*) as value
                FROM commits c
                WHERE c.repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(c.created_at)
            ",
            'issues' => "
                SELECT DATE(i.created_at) as date, COUNT(*) as value
                FROM issues i
                WHERE i.repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND i.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(i.created_at)
            ",
            'pull_requests' => "
                SELECT DATE(pr.created_at) as date, COUNT(*) as value
                FROM pull_requests pr
                WHERE pr.repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND pr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(pr.created_at)
            ",
            'stars' => "
                SELECT DATE(s.created_at) as date, COUNT(*) as value
                FROM stars s
                WHERE s.repo_id = (SELECT id FROM repositories WHERE owner = ? AND name = ?)
                AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(s.created_at)
            ",
        };
        
        $data = $this->db->fetchAll($sql, [$owner, $repo, $days]);
        
        // 填充缺失的日期
        $result = [];
        $currentDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $result[] = [
                'date' => $dateStr,
                'value' => 0,
            ];
            $currentDate->modify('+1 day');
        }
        
        // 合并实际数据
        foreach ($data as $row) {
            foreach ($result as &$item) {
                if ($item['date'] === $row['date']) {
                    $item['value'] = (int)$row['value'];
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 获取热门仓库
     */
    public function getTrendingRepos(string $period = 'week', int $limit = 10): array
    {
        $interval = match ($period) {
            'day' => '1 DAY',
            'week' => '7 DAY',
            'month' => '30 DAY',
            default => '7 DAY',
        };
        
        $sql = "SELECT 
                    r.owner,
                    r.name,
                    r.description,
                    COUNT(DISTINCT c.id) as commits,
                    COUNT(DISTINCT s.user_id) as new_stars
                FROM repositories r
                LEFT JOIN commits c ON r.id = c.repo_id 
                    AND c.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                LEFT JOIN stars s ON r.id = s.repo_id 
                    AND s.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                WHERE r.is_private = 0
                GROUP BY r.id
                HAVING commits > 0 OR new_stars > 0
                ORDER BY (commits * 2 + new_stars) DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 获取活跃用户
     */
    public function getActiveUsers(string $period = 'week', int $limit = 10): array
    {
        $interval = match ($period) {
            'day' => '1 DAY',
            'week' => '7 DAY',
            'month' => '30 DAY',
            default => '7 DAY',
        };
        
        $sql = "SELECT 
                    u.id,
                    u.username,
                    u.name,
                    u.avatar_url,
                    COUNT(DISTINCT c.id) as commits,
                    COUNT(DISTINCT i.id) as issues,
                    COUNT(DISTINCT pr.id) as pull_requests
                FROM users u
                LEFT JOIN commits c ON u.id = c.author_id 
                    AND c.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                LEFT JOIN issues i ON u.id = i.author_id 
                    AND i.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                LEFT JOIN pull_requests pr ON u.id = pr.author_id 
                    AND pr.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                GROUP BY u.id
                HAVING commits > 0 OR issues > 0 OR pull_requests > 0
                ORDER BY (commits * 3 + issues + pull_requests * 2) DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * 生成统计报告
     */
    public function generateReport(string $owner, string $repo, string $type = 'weekly'): array
    {
        $days = match ($type) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            default => 7,
        };
        
        $stats = $this->getRepoStats($owner, $repo);
        
        return [
            'repo' => "{$owner}/{$repo}",
            'period' => $type,
            'generated_at' => date('c'),
            'summary' => [
                'commits' => $stats['commits']['total'],
                'contributors' => $stats['contributors']['total'],
                'issues_open' => $stats['issues']['open'],
                'issues_closed' => $stats['issues']['closed'],
                'prs_open' => $stats['pull_requests']['open'],
                'prs_merged' => $stats['pull_requests']['merged'],
            ],
            'trends' => [
                'commits' => $this->getTrends($owner, $repo, 'commits', $days),
                'issues' => $this->getTrends($owner, $repo, 'issues', $days),
            ],
            'top_contributors' => array_slice($stats['contributors']['top_contributors'], 0, 5),
        ];
    }
}
