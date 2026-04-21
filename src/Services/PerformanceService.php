/**
 * CodeVault - 性能优化服务
 * 
 * 提供性能监控、缓存策略、资源优化等功能
 */

namespace CodeVault\Services;

use PDO;
use Redis;

class PerformanceService
{
    private PDO $pdo;
    private ?Redis $redis;
    private array $metrics = [];
    
    public function __construct(PDO $pdo, ?Redis $redis = null)
    {
        $this->pdo = $pdo;
        $this->redis = $redis;
    }
    
    // ==================== 性能监控 ====================
    
    /**
     * 开始计时
     */
    public function startTimer(string $name): void
    {
        $this->metrics[$name] = [
            'start' => microtime(true),
            'end' => null,
            'duration' => null
        ];
    }
    
    /**
     * 结束计时
     */
    public function endTimer(string $name): float
    {
        if (!isset($this->metrics[$name])) {
            return 0;
        }
        
        $this->metrics[$name]['end'] = microtime(true);
        $this->metrics[$name]['duration'] = 
            ($this->metrics[$name]['end'] - $this->metrics[$name]['start']) * 1000;
        
        return $this->metrics[$name]['duration'];
    }
    
    /**
     * 获取性能指标
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
    
    /**
     * 记录慢查询
     */
    public function logSlowQuery(string $sql, float $duration, array $params = []): void
    {
        $threshold = 100; // 100ms
        
        if ($duration > $threshold) {
            $log = [
                'sql' => $sql,
                'duration' => $duration,
                'params' => $params,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // 存储到 Redis 或文件
            if ($this->redis) {
                $this->redis->lPush('slow_queries', json_encode($log));
                $this->redis->lTrim('slow_queries', 0, 999); // 保留最近1000条
            }
        }
    }
    
    /**
     * 获取慢查询列表
     */
    public function getSlowQueries(int $limit = 50): array
    {
        if (!$this->redis) {
            return [];
        }
        
        $queries = $this->redis->lRange('slow_queries', 0, $limit - 1);
        return array_map('json_decode', $queries);
    }
    
    // ==================== 数据库优化 ====================
    
    /**
     * 分析表
     */
    public function analyzeTable(string $table): array
    {
        $stmt = $this->pdo->query("ANALYZE TABLE {$table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 优化表
     */
    public function optimizeTable(string $table): array
    {
        $stmt = $this->pdo->query("OPTIMIZE TABLE {$table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取表状态
     */
    public function getTableStatus(string $table): array
    {
        $stmt = $this->pdo->query("SHOW TABLE STATUS LIKE '{$table}'");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * 获取索引信息
     */
    public function getTableIndexes(string $table): array
    {
        $stmt = $this->pdo->query("SHOW INDEX FROM {$table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 建议索引
     */
    public function suggestIndexes(string $table): array
    {
        $suggestions = [];
        
        // 获取慢查询
        $slowQueries = $this->getSlowQueries();
        
        foreach ($slowQueries as $query) {
            if (strpos($query->sql, $table) !== false) {
                // 分析 WHERE 子句
                if (preg_match('/WHERE\s+(\w+)/i', $query->sql, $matches)) {
                    $column = $matches[1];
                    $suggestions[] = [
                        'table' => $table,
                        'column' => $column,
                        'reason' => 'Frequently used in WHERE clause',
                        'query' => "ALTER TABLE {$table} ADD INDEX idx_{$column} ({$column})"
                    ];
                }
            }
        }
        
        return $suggestions;
    }
    
    // ==================== 缓存优化 ====================
    
    /**
     * 预热缓存
     */
    public function warmupCache(): array
    {
        $results = [];
        
        if (!$this->redis) {
            return ['error' => 'Redis not available'];
        }
        
        // 预热热门仓库
        $stmt = $this->pdo->query(
            "SELECT id FROM repositories WHERE is_public = 1 
             ORDER BY stars_count DESC LIMIT 100"
        );
        $repos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($repos as $repoId) {
            $key = "repo:{$repoId}";
            if (!$this->redis->exists($key)) {
                $stmt = $this->pdo->prepare("SELECT * FROM repositories WHERE id = ?");
                $stmt->execute([$repoId]);
                $repo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($repo) {
                    $this->redis->setex($key, 3600, json_encode($repo));
                }
            }
        }
        $results['repos_warmed'] = count($repos);
        
        // 预热活跃用户
        $stmt = $this->pdo->query(
            "SELECT id FROM users WHERE is_active = 1 
             ORDER BY last_login_at DESC LIMIT 100"
        );
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($users as $userId) {
            $key = "user:{$userId}";
            if (!$this->redis->exists($key)) {
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    unset($user['password']);
                    $this->redis->setex($key, 3600, json_encode($user));
                }
            }
        }
        $results['users_warmed'] = count($users);
        
        return $results;
    }
    
    /**
     * 清理过期缓存
     */
    public function cleanupCache(): int
    {
        if (!$this->redis) {
            return 0;
        }
        
        $pattern = '*:expired:*';
        $keys = $this->redis->keys($pattern);
        $deleted = 0;
        
        foreach ($keys as $key) {
            if ($this->redis->del($key)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * 获取缓存统计
     */
    public function getCacheStats(): array
    {
        if (!$this->redis) {
            return ['error' => 'Redis not available'];
        }
        
        $info = $this->redis->info();
        
        return [
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $this->formatBytes($info['used_memory'] ?? 0),
            'connected_clients' => $info['connected_clients'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'hit_rate' => $this->calculateHitRate(
                $info['keyspace_hits'] ?? 0,
                $info['keyspace_misses'] ?? 0
            )
        ];
    }
    
    /**
     * 计算缓存命中率
     */
    private function calculateHitRate(int $hits, int $misses): float
    {
        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
    
    // ==================== 资源优化 ====================
    
    /**
     * 压缩输出
     */
    public function compressOutput(string $content): string
    {
        // 移除多余空白
        $content = preg_replace('/\s+/', ' ', $content);
        // 移除 HTML 注释
        $content = preg_replace('/<!--.*?-->/', '', $content);
        return trim($content);
    }
    
    /**
     * 生成资源版本号
     */
    public function getAssetVersion(string $path): string
    {
        $fullPath = dirname(__DIR__, 3) . '/public' . $path;
        
        if (file_exists($fullPath)) {
            return substr(md5_file($fullPath), 0, 8);
        }
        
        return date('Ymd');
    }
    
    /**
     * 资源 URL 添加版本号
     */
    public function assetUrl(string $path): string
    {
        $version = $this->getAssetVersion($path);
        $separator = strpos($path, '?') !== false ? '&' : '?';
        return $path . $separator . 'v=' . $version;
    }
    
    // ==================== 性能报告 ====================
    
    /**
     * 生成性能报告
     */
    public function generateReport(): array
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php' => $this->getPhpInfo(),
            'database' => $this->getDatabaseInfo(),
            'cache' => $this->getCacheStats(),
            'memory' => $this->getMemoryUsage(),
            'opcache' => $this->getOpcacheStatus()
        ];
        
        return $report;
    }
    
    /**
     * 获取 PHP 信息
     */
    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }
    
    /**
     * 获取数据库信息
     */
    private function getDatabaseInfo(): array
    {
        $stmt = $this->pdo->query("SELECT VERSION() as version");
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->query("SHOW STATUS LIKE 'Queries'");
        $queries = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'version' => $version['version'] ?? 'unknown',
            'connections' => $connections['Value'] ?? 0,
            'total_queries' => $queries['Value'] ?? 0
        ];
    }
    
    /**
     * 获取内存使用
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => $this->formatBytes(memory_get_usage()),
            'peak' => $this->formatBytes(memory_get_peak_usage()),
            'real' => $this->formatBytes(memory_get_usage(true))
        ];
    }
    
    /**
     * 获取 Opcache 状态
     */
    private function getOpcacheStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }
        
        $status = opcache_get_status(false);
        
        if (!$status) {
            return ['enabled' => false];
        }
        
        return [
            'enabled' => true,
            'memory_used' => $this->formatBytes($status['memory_usage']['used_memory']),
            'memory_free' => $this->formatBytes($status['memory_usage']['free_memory']),
            'hit_rate' => round($status['opcache_statistics']['opcache_hit_rate'], 2),
            'cached_scripts' => $status['opcache_statistics']['num_cached_scripts']
        ];
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
