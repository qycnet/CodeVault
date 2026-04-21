<?php
/**
 * CodeVault 性能优化服务
 * 
 * 功能：
 * - 数据库查询优化
 * - 缓存策略管理
 * - 资源压缩
 * - 懒加载支持
 * - CDN 集成
 */

namespace CodeVault\Services;

use PDO;
use Redis;

class PerformanceOptimizationService
{
    private $db;
    private $redis;
    private $config;
    
    // 慢查询阈值（毫秒）
    private const SLOW_QUERY_THRESHOLD = 100;
    
    // 缓存 TTL 配置
    private const CACHE_TTL = [
        'user' => 3600,           // 1 小时
        'repo' => 1800,           // 30 分钟
        'issue' => 600,           // 10 分钟
        'commit' => 86400,        // 24 小时
        'file' => 3600,           // 1 小时
        'stats' => 300,           // 5 分钟
        'search' => 60,           // 1 分钟
    ];
    
    public function __construct(PDO $db, ?Redis $redis = null, array $config = [])
    {
        $this->db = $db;
        $this->redis = $redis;
        $this->config = array_merge([
            'enable_cache' => true,
            'enable_query_optimization' => true,
            'enable_compression' => true,
            'cdn_base_url' => null,
        ], $config);
    }
    
    // ==================== 数据库查询优化 ====================
    
    /**
     * 分析慢查询
     */
    public function analyzeSlowQueries(int $limit = 100): array
    {
        $slowQueries = [];
        
        // 获取慢查询日志
        $stmt = $this->db->query("
            SELECT * FROM mysql.slow_log 
            ORDER BY query_time DESC 
            LIMIT {$limit}
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $slowQueries[] = [
                'query_time' => $row['query_time'],
                'lock_time' => $row['lock_time'],
                'rows_sent' => $row['rows_sent'],
                'rows_examined' => $row['rows_examined'],
                'sql_text' => $row['sql_text'],
                'suggestions' => $this->analyzeQuery($row['sql_text']),
            ];
        }
        
        return $slowQueries;
    }
    
    /**
     * 分析单个查询
     */
    public function analyzeQuery(string $sql): array
    {
        $suggestions = [];
        
        // 检查是否使用 SELECT *
        if (preg_match('/SELECT\s+\*/i', $sql)) {
            $suggestions[] = '避免使用 SELECT *，明确指定需要的字段';
        }
        
        // 检查是否缺少 WHERE 条件
        if (!preg_match('/WHERE/i', $sql) && preg_match('/SELECT/i', $sql)) {
            $suggestions[] = '考虑添加 WHERE 条件限制结果集';
        }
        
        // 检查是否使用 LIKE '%xxx%'
        if (preg_match("/LIKE\s+['\"]%.*%['\"]/i", $sql)) {
            $suggestions[] = 'LIKE "%xxx%" 无法使用索引，考虑使用全文搜索';
        }
        
        // 检查是否使用 ORDER BY RAND()
        if (preg_match('/ORDER\s+BY\s+RAND\(\)/i', $sql)) {
            $suggestions[] = 'ORDER BY RAND() 性能较差，考虑其他随机排序方式';
        }
        
        // 检查是否使用子查询
        if (preg_match('/\(\s*SELECT/i', $sql)) {
            $suggestions[] = '子查询可能影响性能，考虑使用 JOIN 替代';
        }
        
        return $suggestions;
    }
    
    /**
     * 获取表索引建议
     */
    public function getIndexSuggestions(string $table): array
    {
        $suggestions = [];
        
        // 获取表结构
        $stmt = $this->db->query("SHOW CREATE TABLE `{$table}`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
        
        // 获取现有索引
        $stmt = $this->db->query("SHOW INDEX FROM `{$table}`");
        $existingIndexes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingIndexes[$row['Key_name']][] = $row['Column_name'];
        }
        
        // 分析查询模式
        $stmt = $this->db->query("
            SELECT sql_text 
            FROM mysql.slow_log 
            WHERE sql_text LIKE '%{$table}%'
            LIMIT 100
        ");
        
        $whereColumns = [];
        $joinColumns = [];
        $orderColumns = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sql = $row['sql_text'];
            
            // 提取 WHERE 条件中的列
            if (preg_match_all('/WHERE.*?`?(\w+)`?\s*=/i', $sql, $matches)) {
                $whereColumns = array_merge($whereColumns, $matches[1]);
            }
            
            // 提取 JOIN 条件中的列
            if (preg_match_all('/JOIN.*?ON.*?`?(\w+)`?\s*=/i', $sql, $matches)) {
                $joinColumns = array_merge($joinColumns, $matches[1]);
            }
            
            // 提取 ORDER BY 中的列
            if (preg_match_all('/ORDER\s+BY\s+`?(\w+)`?/i', $sql, $matches)) {
                $orderColumns = array_merge($orderColumns, $matches[1]);
            }
        }
        
        // 统计频率
        $whereColumns = array_count_values($whereColumns);
        $joinColumns = array_count_values($joinColumns);
        $orderColumns = array_count_values($orderColumns);
        
        // 生成建议
        foreach ($whereColumns as $column => $count) {
            if ($count > 10 && !$this->hasIndex($existingIndexes, $column)) {
                $suggestions[] = [
                    'type' => 'where',
                    'column' => $column,
                    'reason' => "在 WHERE 条件中出现 {$count} 次，建议添加索引",
                    'sql' => "ALTER TABLE `{$table}` ADD INDEX `idx_{$column}` (`{$column}`);",
                ];
            }
        }
        
        foreach ($joinColumns as $column => $count) {
            if ($count > 5 && !$this->hasIndex($existingIndexes, $column)) {
                $suggestions[] = [
                    'type' => 'join',
                    'column' => $column,
                    'reason' => "在 JOIN 条件中出现 {$count} 次，建议添加索引",
                    'sql' => "ALTER TABLE `{$table}` ADD INDEX `idx_{$column}` (`{$column}`);",
                ];
            }
        }
        
        foreach ($orderColumns as $column => $count) {
            if ($count > 5 && !$this->hasIndex($existingIndexes, $column)) {
                $suggestions[] = [
                    'type' => 'order',
                    'column' => $column,
                    'reason' => "在 ORDER BY 中出现 {$count} 次，建议添加索引",
                    'sql' => "ALTER TABLE `{$table}` ADD INDEX `idx_{$column}` (`{$column}`);",
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * 检查是否有索引
     */
    private function hasIndex(array $indexes, string $column): bool
    {
        foreach ($indexes as $indexColumns) {
            if (in_array($column, $indexColumns)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 优化表
     */
    public function optimizeTable(string $table): array
    {
        $result = [
            'table' => $table,
            'before' => $this->getTableStats($table),
        ];
        
        // 分析表
        $this->db->exec("ANALYZE TABLE `{$table}`");
        
        // 优化表
        $this->db->exec("OPTIMIZE TABLE `{$table}`");
        
        $result['after'] = $this->getTableStats($table);
        
        return $result;
    }
    
    /**
     * 获取表统计信息
     */
    private function getTableStats(string $table): array
    {
        $stmt = $this->db->query("
            SELECT 
                TABLE_ROWS,
                DATA_LENGTH,
                INDEX_LENGTH,
                DATA_FREE
            FROM information_schema.TABLES
            WHERE TABLE_NAME = '{$table}'
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ==================== 缓存优化 ====================
    
    /**
     * 智能缓存获取
     */
    public function cacheGet(string $type, string $key, callable $callback, ?int $ttl = null)
    {
        if (!$this->redis || !$this->config['enable_cache']) {
            return $callback();
        }
        
        $cacheKey = "cv:{$type}:{$key}";
        $ttl = $ttl ?? self::CACHE_TTL[$type] ?? 3600;
        
        // 尝试从缓存获取
        $cached = $this->redis->get($cacheKey);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
        
        // 执行回调
        $data = $callback();
        
        // 存入缓存
        if ($data !== null) {
            $this->redis->setex($cacheKey, $ttl, json_encode($data));
        }
        
        return $data;
    }
    
    /**
     * 批量缓存获取
     */
    public function cacheGetMulti(string $type, array $keys, callable $callback): array
    {
        if (!$this->redis || !$this->config['enable_cache']) {
            return $callback($keys);
        }
        
        $results = [];
        $missingKeys = [];
        
        // 批量获取缓存
        $cacheKeys = [];
        foreach ($keys as $key) {
            $cacheKeys[$key] = "cv:{$type}:{$key}";
        }
        
        $cached = $this->redis->mGet(array_values($cacheKeys));
        
        foreach ($keys as $i => $key) {
            if ($cached[$i] !== false) {
                $results[$key] = json_decode($cached[$i], true);
            } else {
                $missingKeys[] = $key;
            }
        }
        
        // 获取缺失的数据
        if (!empty($missingKeys)) {
            $missingData = $callback($missingKeys);
            
            // 存入缓存
            $ttl = self::CACHE_TTL[$type] ?? 3600;
            $multiSet = [];
            foreach ($missingData as $key => $value) {
                $results[$key] = $value;
                $multiSet["cv:{$type}:{$key}"] = json_encode($value);
            }
            
            if (!empty($multiSet)) {
                $this->redis->mSet($multiSet);
                foreach ($multiSet as $k => $v) {
                    $this->redis->expire($k, $ttl);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 缓存预热
     */
    public function cacheWarmup(): array
    {
        if (!$this->redis) {
            return ['status' => 'disabled'];
        }
        
        $stats = [
            'users' => 0,
            'repos' => 0,
            'issues' => 0,
        ];
        
        // 预热活跃用户
        $stmt = $this->db->query("
            SELECT id, username, email, avatar_url, created_at
            FROM users
            WHERE last_login_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 100
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->redis->setex(
                "cv:user:{$row['id']}",
                self::CACHE_TTL['user'],
                json_encode($row)
            );
            $stats['users']++;
        }
        
        // 预热热门仓库
        $stmt = $this->db->query("
            SELECT id, owner_id, name, description, visibility, stars, forks
            FROM repositories
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY stars DESC
            LIMIT 100
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->redis->setex(
                "cv:repo:{$row['id']}",
                self::CACHE_TTL['repo'],
                json_encode($row)
            );
            $stats['repos']++;
        }
        
        // 预热活跃 Issue
        $stmt = $this->db->query("
            SELECT id, repo_id, title, status, created_at
            FROM issues
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 100
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->redis->setex(
                "cv:issue:{$row['id']}",
                self::CACHE_TTL['issue'],
                json_encode($row)
            );
            $stats['issues']++;
        }
        
        return $stats;
    }
    
    /**
     * 清理过期缓存
     */
    public function cacheCleanup(): int
    {
        if (!$this->redis) {
            return 0;
        }
        
        $patterns = [
            'cv:user:*',
            'cv:repo:*',
            'cv:issue:*',
            'cv:commit:*',
            'cv:file:*',
        ];
        
        $cleaned = 0;
        foreach ($patterns as $pattern) {
            $keys = $this->redis->keys($pattern);
            foreach ($keys as $key) {
                $ttl = $this->redis->ttl($key);
                if ($ttl < 0) {
                    $this->redis->del($key);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取缓存统计
     */
    public function getCacheStats(): array
    {
        if (!$this->redis) {
            return ['status' => 'disabled'];
        }
        
        $info = $this->redis->info();
        
        return [
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory' => $info['used_memory_human'] ?? '0B',
            'used_memory_peak' => $info['used_memory_peak_human'] ?? '0B',
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'hit_rate' => $this->calculateHitRate(
                $info['keyspace_hits'] ?? 0,
                $info['keyspace_misses'] ?? 0
            ),
        ];
    }
    
    private function calculateHitRate(int $hits, int $misses): float
    {
        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
    
    // ==================== 资源优化 ====================
    
    /**
     * 压缩 HTML
     */
    public function compressHtml(string $html): string
    {
        if (!$this->config['enable_compression']) {
            return $html;
        }
        
        // 移除注释
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        // 移除多余空白
        $html = preg_replace('/\s+/', ' ', $html);
        
        // 移除标签间空白
        $html = preg_replace('/>\s+</', '><', $html);
        
        return trim($html);
    }
    
    /**
     * 压缩 CSS
     */
    public function compressCss(string $css): string
    {
        if (!$this->config['enable_compression']) {
            return $css;
        }
        
        // 移除注释
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // 移除多余空白
        $css = preg_replace('/\s+/', ' ', $css);
        
        // 移除分号前后的空白
        $css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);
        
        return trim($css);
    }
    
    /**
     * 压缩 JavaScript
     */
    public function compressJs(string $js): string
    {
        if (!$this->config['enable_compression']) {
            return $js;
        }
        
        // 移除单行注释
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // 移除多行注释
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        
        // 移除多余空白
        $js = preg_replace('/\s+/', ' ', $js);
        
        return trim($js);
    }
    
    /**
     * 生成资源版本号
     */
    public function getAssetVersion(string $path): string
    {
        $fullPath = __DIR__ . '/../../public' . $path;
        
        if (file_exists($fullPath)) {
            return substr(md5_file($fullPath), 0, 8);
        }
        
        return 'v1';
    }
    
    /**
     * 获取资源 URL（带 CDN 支持）
     */
    public function getAssetUrl(string $path): string
    {
        $version = $this->getAssetVersion($path);
        
        if ($this->config['cdn_base_url']) {
            return rtrim($this->config['cdn_base_url'], '/') . $path . '?' . $version;
        }
        
        return $path . '?' . $version;
    }
    
    // ==================== 懒加载支持 ====================
    
    /**
     * 分页查询（支持懒加载）
     */
    public function paginate(string $table, array $options = []): array
    {
        $page = $options['page'] ?? 1;
        $perPage = $options['per_page'] ?? 20;
        $where = $options['where'] ?? '';
        $orderBy = $options['order_by'] ?? 'id DESC';
        $select = $options['select'] ?? '*';
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取总数
        $countSql = "SELECT COUNT(*) FROM `{$table}`";
        if ($where) {
            $countSql .= " WHERE {$where}";
        }
        
        $total = $this->db->query($countSql)->fetchColumn();
        
        // 获取数据
        $sql = "SELECT {$select} FROM `{$table}`";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'items' => $items,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'has_more' => ($page * $perPage) < $total,
        ];
    }
    
    /**
     * 无限滚动加载
     */
    public function infiniteScroll(
        string $table,
        string $cursorColumn,
        $cursorValue = null,
        array $options = []
    ): array {
        $perPage = $options['per_page'] ?? 20;
        $where = $options['where'] ?? '';
        $orderBy = $options['order_by'] ?? 'id DESC';
        $select = $options['select'] ?? '*';
        
        // 构建查询
        $sql = "SELECT {$select} FROM `{$table}`";
        $conditions = [];
        
        if ($where) {
            $conditions[] = $where;
        }
        
        if ($cursorValue !== null) {
            $direction = strpos($orderBy, 'DESC') !== false ? '<' : '>';
            $conditions[] = "`{$cursorColumn}` {$direction} ?";
        }
        
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY {$orderBy} LIMIT " . ($perPage + 1);
        
        $stmt = $this->db->prepare($sql);
        
        if ($cursorValue !== null) {
            $stmt->execute([$cursorValue]);
        } else {
            $stmt->execute();
        }
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }
        
        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $nextCursor = $items[count($items) - 1][$cursorColumn];
        }
        
        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
    
    // ==================== 性能监控 ====================
    
    /**
     * 记录慢查询
     */
    public function logSlowQuery(string $sql, float $time, array $params = []): void
    {
        if ($time * 1000 < self::SLOW_QUERY_THRESHOLD) {
            return;
        }
        
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'sql' => $sql,
            'time_ms' => round($time * 1000, 2),
            'params' => $params,
        ];
        
        // 写入日志
        file_put_contents(
            __DIR__ . '/../../logs/slow_queries.log',
            json_encode($log) . "\n",
            FILE_APPEND
        );
    }
    
    /**
     * 获取性能报告
     */
    public function getPerformanceReport(): array
    {
        return [
            'database' => $this->getDatabaseStats(),
            'cache' => $this->getCacheStats(),
            'slow_queries' => $this->getRecentSlowQueries(),
        ];
    }
    
    /**
     * 获取数据库统计
     */
    private function getDatabaseStats(): array
    {
        $stats = [
            'tables' => [],
            'total_size' => 0,
        ];
        
        $stmt = $this->db->query("
            SELECT 
                TABLE_NAME,
                TABLE_ROWS,
                DATA_LENGTH,
                INDEX_LENGTH,
                DATA_FREE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $size = $row['DATA_LENGTH'] + $row['INDEX_LENGTH'];
            $stats['tables'][$row['TABLE_NAME']] = [
                'rows' => (int)$row['TABLE_ROWS'],
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'data_free' => (int)$row['DATA_FREE'],
            ];
            $stats['total_size'] += $size;
        }
        
        $stats['total_size_human'] = $this->formatBytes($stats['total_size']);
        
        return $stats;
    }
    
    /**
     * 获取最近的慢查询
     */
    private function getRecentSlowQueries(int $limit = 20): array
    {
        $logFile = __DIR__ . '/../../logs/slow_queries.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice(array_reverse($lines), 0, $limit);
        
        return array_map('json_decode', $lines, array_fill(0, count($lines), true));
    }
    
    /**
     * 格式化字节
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
