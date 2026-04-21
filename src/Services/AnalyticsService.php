<?php
/**
 * CodeVault 数据分析服务
 * 
 * 功能：
 * - 代码质量趋势图
 * - 团队效率分析
 * - PR 合并时间统计
 * - Issue 解决时间分析
 */

namespace Services;

use Core\Database;
use Core\Logger;
use Core\Cache;

class AnalyticsService
{
    private $db;
    private $logger;
    private $cache;
    
    // 缓存时间
    private const CACHE_TTL = 3600; // 1小时
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger('analytics');
        $this->cache = new Cache();
    }
    
    // ==================== 代码质量趋势 ====================
    
    /**
     * 获取代码质量趋势
     */
    public function getCodeQualityTrend(int $repoId, string $period = 'month'): array
    {
        $cacheKey = "code_quality:{$repoId}:{$period}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateRange = $this->getDateRange($period);
        
        // 获取安全扫描结果趋势
        $securityTrend = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_scans,
                    SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed,
                    SUM(critical_count) as critical,
                    SUM(high_count) as high,
                    SUM(medium_count) as medium,
                    SUM(low_count) as low
             FROM security_scans
             WHERE repo_id = ? AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$repoId, $dateRange]
        );
        
        // 获取代码审查统计
        $reviewTrend = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_reviews,
                    AVG(changes_requested) as avg_changes_requested,
                    AVG(comments_count) as avg_comments
             FROM code_reviews
             WHERE repo_id = ? AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$repoId, $dateRange]
        );
        
        // 计算质量分数
        $qualityScore = $this->calculateQualityScore($securityTrend, $reviewTrend);
        
        $result = [
            'period' => $period,
            'security_trend' => $securityTrend,
            'review_trend' => $reviewTrend,
            'quality_score' => $qualityScore,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    /**
     * 计算质量分数
     */
    private function calculateQualityScore(array $securityTrend, array $reviewTrend): array
    {
        $scores = [];
        
        foreach ($securityTrend as $scan) {
            $date = $scan['date'];
            
            // 安全分数：100 - (critical*20 + high*10 + medium*5 + low*2)
            $securityScore = max(0, 100 - (
                ($scan['critical'] ?? 0) * 20 +
                ($scan['high'] ?? 0) * 10 +
                ($scan['medium'] ?? 0) * 5 +
                ($scan['low'] ?? 0) * 2
            ));
            
            $scores[$date] = [
                'security_score' => $securityScore,
                'scan_count' => $scan['total_scans'] ?? 0,
            ];
        }
        
        // 计算平均分数
        $avgSecurityScore = count($scores) > 0 
            ? array_sum(array_column($scores, 'security_score')) / count($scores)
            : 100;
        
        return [
            'average_security_score' => round($avgSecurityScore, 2),
            'daily_scores' => $scores,
            'grade' => $this->getQualityGrade($avgSecurityScore),
        ];
    }
    
    /**
     * 获取质量等级
     */
    private function getQualityGrade(float $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    // ==================== 团队效率分析 ====================
    
    /**
     * 获取团队效率分析
     */
    public function getTeamEfficiency(int $repoId, string $period = 'month'): array
    {
        $cacheKey = "team_efficiency:{$repoId}:{$period}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateRange = $this->getDateRange($period);
        
        // 获取贡献者效率数据
        $contributors = $this->db->fetchAll(
            "SELECT u.id, u.username, u.name, u.avatar_url,
                    COUNT(DISTINCT c.id) as commits,
                    COUNT(DISTINCT pr.id) as prs_created,
                    COUNT(DISTINCT pr_merged.id) as prs_merged,
                    COUNT(DISTINCT i.id) as issues_created,
                    COUNT(DISTINCT i_closed.id) as issues_closed,
                    COUNT(DISTINCT r.id) as reviews_given
             FROM users u
             LEFT JOIN commits c ON u.id = c.author_id AND c.repo_id = ? AND c.created_at >= ?
             LEFT JOIN pull_requests pr ON u.id = pr.author_id AND pr.repo_id = ? AND pr.created_at >= ?
             LEFT JOIN pull_requests pr_merged ON u.id = pr_merged.merged_by AND pr_merged.repo_id = ? AND pr_merged.merged_at >= ?
             LEFT JOIN issues i ON u.id = i.author_id AND i.repo_id = ? AND i.created_at >= ?
             LEFT JOIN issues i_closed ON u.id = i_closed.closed_by AND i_closed.repo_id = ? AND i_closed.closed_at >= ?
             LEFT JOIN code_reviews r ON u.id = r.reviewer_id AND r.repo_id = ? AND r.created_at >= ?
             WHERE u.id IN (SELECT user_id FROM repo_contributors WHERE repo_id = ?)
             GROUP BY u.id
             ORDER BY commits DESC",
            [$repoId, $dateRange, $repoId, $dateRange, $repoId, $dateRange, $repoId, $dateRange, $repoId, $dateRange, $repoId, $dateRange, $repoId]
        );
        
        // 计算效率分数
        foreach ($contributors as &$contributor) {
            $contributor['efficiency_score'] = $this->calculateEfficiencyScore($contributor);
        }
        
        // 获取团队整体统计
        $teamStats = $this->getTeamOverallStats($repoId, $dateRange);
        
        // 获取协作网络
        $collaboration = $this->getCollaborationNetwork($repoId, $dateRange);
        
        $result = [
            'period' => $period,
            'contributors' => $contributors,
            'team_stats' => $teamStats,
            'collaboration_network' => $collaboration,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    /**
     * 计算效率分数
     */
    private function calculateEfficiencyScore(array $contributor): float
    {
        // 权重：提交 1，PR 创建 3，PR 合并 5，Issue 创建 2，Issue 关闭 4，审查 3
        $score = 
            ($contributor['commits'] ?? 0) * 1 +
            ($contributor['prs_created'] ?? 0) * 3 +
            ($contributor['prs_merged'] ?? 0) * 5 +
            ($contributor['issues_created'] ?? 0) * 2 +
            ($contributor['issues_closed'] ?? 0) * 4 +
            ($contributor['reviews_given'] ?? 0) * 3;
        
        return round($score, 2);
    }
    
    /**
     * 获取团队整体统计
     */
    private function getTeamOverallStats(int $repoId, string $dateRange): array
    {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(DISTINCT c.author_id) as active_contributors,
                COUNT(DISTINCT c.id) as total_commits,
                COUNT(DISTINCT pr.id) as total_prs,
                AVG(DATEDIFF(pr.merged_at, pr.created_at)) as avg_pr_merge_time,
                COUNT(DISTINCT i.id) as total_issues,
                AVG(DATEDIFF(i.closed_at, i.created_at)) as avg_issue_resolve_time
             FROM repositories r
             LEFT JOIN commits c ON r.id = c.repo_id AND c.created_at >= ?
             LEFT JOIN pull_requests pr ON r.id = pr.repo_id AND pr.created_at >= ?
             LEFT JOIN issues i ON r.id = i.repo_id AND i.created_at >= ?
             WHERE r.id = ?",
            [$dateRange, $dateRange, $dateRange, $repoId]
        ) ?: [];
    }
    
    /**
     * 获取协作网络
     */
    private function getCollaborationNetwork(int $repoId, string $dateRange): array
    {
        // 获取共同审查关系
        return $this->db->fetchAll(
            "SELECT u1.id as user1_id, u1.username as user1_name,
                    u2.id as user2_id, u2.username as user2_name,
                    COUNT(*) as collaboration_count
             FROM code_reviews r1
             JOIN code_reviews r2 ON r1.pr_id = r2.pr_id AND r1.reviewer_id < r2.reviewer_id
             JOIN users u1 ON r1.reviewer_id = u1.id
             JOIN users u2 ON r2.reviewer_id = u2.id
             WHERE r1.repo_id = ? AND r1.created_at >= ?
             GROUP BY r1.reviewer_id, r2.reviewer_id
             ORDER BY collaboration_count DESC
             LIMIT 20",
            [$repoId, $dateRange]
        );
    }
    
    // ==================== PR 合并时间统计 ====================
    
    /**
     * 获取 PR 合并时间统计
     */
    public function getPRMergeTimeStats(int $repoId, string $period = 'month'): array
    {
        $cacheKey = "pr_merge_time:{$repoId}:{$period}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateRange = $this->getDateRange($period);
        
        // 获取 PR 合并时间分布
        $mergeTimeDistribution = $this->db->fetchAll(
            "SELECT 
                CASE 
                    WHEN DATEDIFF(merged_at, created_at) = 0 THEN 'same_day'
                    WHEN DATEDIFF(merged_at, created_at) <= 3 THEN 'within_3_days'
                    WHEN DATEDIFF(merged_at, created_at) <= 7 THEN 'within_week'
                    WHEN DATEDIFF(merged_at, created_at) <= 30 THEN 'within_month'
                    ELSE 'over_month'
                END as time_bucket,
                COUNT(*) as count
             FROM pull_requests
             WHERE repo_id = ? AND merged_at IS NOT NULL AND created_at >= ?
             GROUP BY time_bucket",
            [$repoId, $dateRange]
        );
        
        // 获取平均合并时间趋势
        $mergeTimeTrend = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_prs,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, merged_at)) as avg_merge_hours,
                    MIN(TIMESTAMPDIFF(HOUR, created_at, merged_at)) as min_merge_hours,
                    MAX(TIMESTAMPDIFF(HOUR, created_at, merged_at)) as max_merge_hours
             FROM pull_requests
             WHERE repo_id = ? AND merged_at IS NOT NULL AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$repoId, $dateRange]
        );
        
        // 获取合并时间最长的 PR
        $slowestPRs = $this->db->fetchAll(
            "SELECT pr.id, pr.title, pr.number,
                    TIMESTAMPDIFF(HOUR, pr.created_at, pr.merged_at) as merge_hours,
                    u.username as author
             FROM pull_requests pr
             JOIN users u ON pr.author_id = u.id
             WHERE pr.repo_id = ? AND pr.merged_at IS NOT NULL AND pr.created_at >= ?
             ORDER BY merge_hours DESC
             LIMIT 10",
            [$repoId, $dateRange]
        );
        
        // 获取合并时间最短的 PR
        $fastestPRs = $this->db->fetchAll(
            "SELECT pr.id, pr.title, pr.number,
                    TIMESTAMPDIFF(HOUR, pr.created_at, pr.merged_at) as merge_hours,
                    u.username as author
             FROM pull_requests pr
             JOIN users u ON pr.author_id = u.id
             WHERE pr.repo_id = ? AND pr.merged_at IS NOT NULL AND pr.created_at >= ?
             ORDER BY merge_hours ASC
             LIMIT 10",
            [$repoId, $dateRange]
        );
        
        // 计算整体统计
        $overallStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_merged,
                AVG(TIMESTAMPDIFF(HOUR, created_at, merged_at)) as avg_merge_hours,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, merged_at)) as avg_merge_minutes
             FROM pull_requests
             WHERE repo_id = ? AND merged_at IS NOT NULL AND created_at >= ?",
            [$repoId, $dateRange]
        );
        
        $result = [
            'period' => $period,
            'distribution' => $mergeTimeDistribution,
            'trend' => $mergeTimeTrend,
            'slowest_prs' => $slowestPRs,
            'fastest_prs' => $fastestPRs,
            'overall' => $overallStats,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    // ==================== Issue 解决时间分析 ====================
    
    /**
     * 获取 Issue 解决时间分析
     */
    public function getIssueResolveTimeStats(int $repoId, string $period = 'month'): array
    {
        $cacheKey = "issue_resolve_time:{$repoId}:{$period}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $dateRange = $this->getDateRange($period);
        
        // 获取解决时间分布
        $resolveTimeDistribution = $this->db->fetchAll(
            "SELECT 
                CASE 
                    WHEN DATEDIFF(closed_at, created_at) = 0 THEN 'same_day'
                    WHEN DATEDIFF(closed_at, created_at) <= 3 THEN 'within_3_days'
                    WHEN DATEDIFF(closed_at, created_at) <= 7 THEN 'within_week'
                    WHEN DATEDIFF(closed_at, created_at) <= 30 THEN 'within_month'
                    ELSE 'over_month'
                END as time_bucket,
                COUNT(*) as count
             FROM issues
             WHERE repo_id = ? AND closed_at IS NOT NULL AND created_at >= ?
             GROUP BY time_bucket",
            [$repoId, $dateRange]
        );
        
        // 获取解决时间趋势
        $resolveTimeTrend = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_issues,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_resolve_hours,
                    MIN(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as min_resolve_hours,
                    MAX(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as max_resolve_hours
             FROM issues
             WHERE repo_id = ? AND closed_at IS NOT NULL AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$repoId, $dateRange]
        );
        
        // 按标签统计解决时间
        $labelStats = $this->db->fetchAll(
            "SELECT l.name as label_name, l.color,
                    COUNT(i.id) as issue_count,
                    AVG(TIMESTAMPDIFF(HOUR, i.created_at, i.closed_at)) as avg_resolve_hours
             FROM issues i
             JOIN issue_labels il ON i.id = il.issue_id
             JOIN labels l ON il.label_id = l.id
             WHERE i.repo_id = ? AND i.closed_at IS NOT NULL AND i.created_at >= ?
             GROUP BY l.id
             ORDER BY issue_count DESC",
            [$repoId, $dateRange]
        );
        
        // 获取解决时间最长的 Issue
        $slowestIssues = $this->db->fetchAll(
            "SELECT i.id, i.title, i.number,
                    TIMESTAMPDIFF(DAY, i.created_at, i.closed_at) as resolve_days,
                    u.username as author
             FROM issues i
             JOIN users u ON i.author_id = u.id
             WHERE i.repo_id = ? AND i.closed_at IS NOT NULL AND i.created_at >= ?
             ORDER BY resolve_days DESC
             LIMIT 10",
            [$repoId, $dateRange]
        );
        
        // 计算整体统计
        $overallStats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_closed,
                AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_resolve_hours,
                AVG(TIMESTAMPDIFF(DAY, created_at, closed_at)) as avg_resolve_days
             FROM issues
             WHERE repo_id = ? AND closed_at IS NOT NULL AND created_at >= ?",
            [$repoId, $dateRange]
        );
        
        // 计算未关闭 Issue 龄期
        $openIssueAging = $this->db->fetchAll(
            "SELECT 
                CASE 
                    WHEN DATEDIFF(NOW(), created_at) <= 7 THEN 'within_week'
                    WHEN DATEDIFF(NOW(), created_at) <= 30 THEN 'within_month'
                    WHEN DATEDIFF(NOW(), created_at) <= 90 THEN 'within_quarter'
                    ELSE 'over_quarter'
                END as age_bucket,
                COUNT(*) as count
             FROM issues
             WHERE repo_id = ? AND closed_at IS NULL
             GROUP BY age_bucket",
            [$repoId]
        );
        
        $result = [
            'period' => $period,
            'distribution' => $resolveTimeDistribution,
            'trend' => $resolveTimeTrend,
            'label_stats' => $labelStats,
            'slowest_issues' => $slowestIssues,
            'overall' => $overallStats,
            'open_aging' => $openIssueAging,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    // ==================== 综合报告 ====================
    
    /**
     * 生成综合分析报告
     */
    public function generateReport(int $repoId, string $period = 'month'): array
    {
        return [
            'repo_id' => $repoId,
            'period' => $period,
            'code_quality' => $this->getCodeQualityTrend($repoId, $period),
            'team_efficiency' => $this->getTeamEfficiency($repoId, $period),
            'pr_merge_time' => $this->getPRMergeTimeStats($repoId, $period),
            'issue_resolve_time' => $this->getIssueResolveTimeStats($repoId, $period),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    // ==================== 辅助方法 ====================
    
    /**
     * 获取日期范围
     */
    private function getDateRange(string $period): string
    {
        return match ($period) {
            'week' => date('Y-m-d', strtotime('-1 week')),
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            default => date('Y-m-d', strtotime('-1 month')),
        };
    }
}
