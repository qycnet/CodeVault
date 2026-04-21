<?php
/**
 * CodeVault - 数据库查询优化器
 * EXPLAIN 分析、慢查询检测、索引建议
 */

namespace CodeVault\Core;

use CodeVault\Database\Connection;

class QueryOptimizer
{
    /**
     * 慢查询阈值（毫秒）
     */
    private int $slowQueryThreshold;
    
    /**
     * 是否启用查询日志
     */
    private bool $enableQueryLog;
    
    /**
     * 查询日志
     */
    private array $queryLog = [];
    
    /**
     * 允许的表名白名单
     */
    private const ALLOWED_TABLES = [
        'users', 'repositories', 'ssh_keys', 'issues', 'pull_requests',
        'commits', 'webhooks', 'webhook_deliveries', 'gists', 'wikis',
        'wiki_pages', 'workflows', 'workflow_runs', 'user_follows',
        'user_activities', 'user_oauth_bindings', 'api_tokens', 'sessions',
        'pages_sites', 'pages_deployments', 'security_scans', 'comments',
        'stars', 'watchers', 'forks', 'labels', 'milestones', 'branches',
        'tags', 'releases', 'deploy_keys', 'protected_branches', 'reviews',
        'review_comments', 'discussion_categories', 'discussions',
        'discussion_replies', 'notifications', 'audit_logs', 'cache',
        'performance_logs', 'asset_versions', 'verification_codes',
    ];
    
    /**
     * 验证表名是否在白名单中
     */
    private function validateTableName(string $tableName): bool
    {
        return in_array($tableName, self::ALLOWED_TABLES, true);
    }
    
    /**
     * 安全获取表名（带验证）
     */
    private function getSafeTableName(string $tableName): string
    {
        if (!$this->validateTableName($tableName)) {
            throw new \InvalidArgumentException("Invalid table name: {$tableName}");
        }
        return $tableName;
    }
    
    /**
     * 构造函数
     */
    public function __construct(int $slowQueryThreshold = 100, bool $enableQueryLog = true)
    {
        $this->slowQueryThreshold = $slowQueryThreshold;
        $this->enableQueryLog = $enableQueryLog;
    }
    
    /**
     * 分析查询 - EXPLAIN
     */
    public function explain(string $sql, array $params = []): array
    {
        $explainSql = "EXPLAIN " . $sql;
        $results = Connection::query($explainSql, $params);
        
        $analysis = [
            'query' => $sql,
            'params' => $params,
            'explain' => $results,
            'issues' => [],
            'suggestions' => [],
        ];
        
        // 分析 EXPLAIN 结果
        foreach ($results as $row) {
            $this->analyzeExplainRow($row, $analysis);
        }
        
        return $analysis;
    }
    
    /**
     * 分析 EXPLAIN 行
     */
    private function analyzeExplainRow(array $row, array &$analysis): void
    {
        // 检查全表扫描
        if ($row['type'] === 'ALL') {
            $analysis['issues'][] = [
                'type' => 'full_table_scan',
                'table' => $row['table'],
                'message' => "表 {$row['table']} 正在进行全表扫描",
            ];
            $analysis['suggestions'][] = [
                'table' => $row['table'],
                'suggestion' => "考虑为表 {$row['table']} 添加索引，特别是 WHERE/JOIN 条件中的列",
            ];
        }
        
        // 检查临时表
        if ($row['Extra'] && strpos($row['Extra'], 'Using temporary') !== false) {
            $analysis['issues'][] = [
                'type' => 'temporary_table',
                'table' => $row['table'],
                'message' => "查询使用了临时表",
            ];
        }
        
        // 检查文件排序
        if ($row['Extra'] && strpos($row['Extra'], 'Using filesort') !== false) {
            $analysis['issues'][] = [
                'type' => 'filesort',
                'table' => $row['table'],
                'message' => "查询使用了文件排序",
            ];
            $analysis['suggestions'][] = [
                'table' => $row['table'],
                'suggestion' => "考虑为 ORDER BY 列添加索引",
            ];
        }
        
        // 检查扫描行数
        if (isset($row['rows']) && $row['rows'] > 10000) {
            $analysis['issues'][] = [
                'type' => 'large_scan',
                'table' => $row['table'],
                'rows' => $row['rows'],
                'message' => "扫描了 {$row['rows']} 行，可能影响性能",
            ];
        }
        
        // 检查索引使用
        if ($row['key'] === null && $row['possible_keys'] !== null) {
            $analysis['issues'][] = [
                'type' => 'no_index_used',
                'table' => $row['table'],
                'possible_keys' => $row['possible_keys'],
                'message' => "有可用索引但未使用",
            ];
        }
    }
    
    /**
     * 记录查询
     */
    public function logQuery(string $sql, array $params, float $duration, int $rowCount): void
    {
        if (!$this->enableQueryLog) {
            return;
        }
        
        $entry = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'row_count' => $rowCount,
            'timestamp' => microtime(true),
            'is_slow' => $duration > $this->slowQueryThreshold,
        ];
        
        $this->queryLog[] = $entry;
        
        // 如果是慢查询，记录到日志
        if ($entry['is_slow']) {
            $this->logSlowQuery($entry);
        }
    }
    
    /**
     * 记录慢查询
     */
    private function logSlowQuery(array $entry): void
    {
        $logFile = getenv('LOG_PATH') ?: '/var/log/codevault';
        $logFile .= '/slow-queries-' . date('Y-m-d') . '.log';
        
        $message = sprintf(
            "[%s] [%.2fms] [rows:%d] %s | Params: %s\n",
            date('Y-m-d H:i:s'),
            $entry['duration'],
            $entry['row_count'],
            $entry['sql'],
            json_encode($entry['params'], JSON_UNESCAPED_UNICODE)
        );
        
        @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 获取查询日志
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
    
    /**
     * 获取慢查询
     */
    public function getSlowQueries(): array
    {
        return array_filter($this->queryLog, fn($q) => $q['is_slow']);
    }
    
    /**
     * 获取查询统计
     */
    public function getStats(): array
    {
        $totalQueries = count($this->queryLog);
        $totalDuration = array_sum(array_column($this->queryLog, 'duration'));
        $slowQueries = count($this->getSlowQueries());
        
        return [
            'total_queries' => $totalQueries,
            'total_duration_ms' => round($totalDuration, 2),
            'avg_duration_ms' => $totalQueries > 0 ? round($totalDuration / $totalQueries, 2) : 0,
            'slow_queries' => $slowQueries,
            'slow_query_percentage' => $totalQueries > 0 ? round(($slowQueries / $totalQueries) * 100, 2) : 0,
        ];
    }
    
    /**
     * 清空查询日志
     */
    public function clearLog(): void
    {
        $this->queryLog = [];
    }
    
    /**
     * 分析表索引
     */
    public function analyzeTableIndexes(string $tableName): array
    {
        $tableName = $this->getSafeTableName($tableName);
        $indexes = Connection::query("SHOW INDEX FROM `{$tableName}`");
        
        $analysis = [
            'table' => $tableName,
            'indexes' => [],
            'suggestions' => [],
        ];
        
        $indexGroups = [];
        foreach ($indexes as $index) {
            $indexName = $index['Key_name'];
            if (!isset($indexGroups[$indexName])) {
                $indexGroups[$indexName] = [
                    'name' => $indexName,
                    'type' => $index['Index_type'],
                    'unique' => !$index['Non_unique'],
                    'columns' => [],
                ];
            }
            $indexGroups[$indexName]['columns'][] = [
                'column' => $index['Column_name'],
                'order' => $index['Seq_in_index'],
            ];
        }
        
        $analysis['indexes'] = array_values($indexGroups);
        
        // 检查是否有主键
        $hasPrimaryKey = false;
        foreach ($indexGroups as $index) {
            if ($index['name'] === 'PRIMARY') {
                $hasPrimaryKey = true;
                break;
            }
        }
        
        if (!$hasPrimaryKey) {
            $analysis['suggestions'][] = [
                'type' => 'missing_primary_key',
                'message' => '表缺少主键，建议添加自增主键',
            ];
        }
        
        return $analysis;
    }
    
    /**
     * 获取表统计信息
     */
    public function getTableStats(string $tableName): array
    {
        $tableName = $this->getSafeTableName($tableName);
        $status = Connection::queryOne("SHOW TABLE STATUS LIKE ?", [$tableName]);
        
        return [
            'table' => $tableName,
            'engine' => $status['Engine'] ?? 'Unknown',
            'rows' => (int) ($status['Rows'] ?? 0),
            'avg_row_length' => (int) ($status['Avg_row_length'] ?? 0),
            'data_length' => (int) ($status['Data_length'] ?? 0),
            'index_length' => (int) ($status['Index_length'] ?? 0),
            'data_free' => (int) ($status['Data_free'] ?? 0),
            'auto_increment' => $status['Auto_increment'] ?? null,
            'collation' => $status['Collation'] ?? 'Unknown',
        ];
    }
    
    /**
     * 优化表
     */
    public function optimizeTable(string $tableName): array
    {
        $tableName = $this->getSafeTableName($tableName);
        $result = Connection::query("OPTIMIZE TABLE `{$tableName}`");
        
        return [
            'table' => $tableName,
            'result' => $result[0] ?? null,
        ];
    }
    
    /**
     * 分析所有表
     */
    public function analyzeAllTables(): array
    {
        $tables = Connection::query("SHOW TABLES");
        $results = [];
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $results[$tableName] = [
                'stats' => $this->getTableStats($tableName),
                'indexes' => $this->analyzeTableIndexes($tableName),
            ];
        }
        
        return $results;
    }
    
    /**
     * 建议索引
     */
    public function suggestIndexes(string $tableName): array
    {
        $tableName = $this->getSafeTableName($tableName);
        $suggestions = [];
        
        // 获取表结构
        $columns = Connection::query("DESCRIBE `{$tableName}`");
        
        // 获取外键信息
        $foreignKeys = Connection::query("
            SELECT COLUMN_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$tableName]);
        
        // 建议为外键添加索引
        foreach ($foreignKeys as $fk) {
            $suggestions[] = [
                'type' => 'foreign_key',
                'column' => $fk['COLUMN_NAME'],
                'reason' => "外键列 {$fk['COLUMN_NAME']} 引用 {$fk['REFERENCED_TABLE_NAME']}，建议添加索引",
                'sql' => "CREATE INDEX idx_{$fk['COLUMN_NAME']} ON {$tableName} ({$fk['COLUMN_NAME']})",
            ];
        }
        
        // 检查常用查询模式（从慢查询日志）
        $slowQueries = $this->getSlowQueries();
        foreach ($slowQueries as $query) {
            // 简单的 WHERE 条件检测
            if (preg_match('/WHERE\s+(\w+)\s*=/', $query['sql'], $matches)) {
                $column = $matches[1];
                $suggestions[] = [
                    'type' => 'where_condition',
                    'column' => $column,
                    'reason' => "WHERE 条件列 {$column} 频繁使用，建议添加索引",
                    'sql' => "CREATE INDEX idx_{$column} ON {$tableName} ({$column})",
                ];
            }
        }
        
        return $suggestions;
    }
}
