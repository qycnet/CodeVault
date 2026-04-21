<?php
/**
 * CodeVault - 缓存优化器
 * 热点数据缓存、缓存预热、缓存统计
 */

namespace CodeVault\Services;

use CodeVault\Database\Connection;

class CacheOptimizer
{
    private CacheService $cache;
    private array $hotDataConfig;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->cache = new CacheService();
        $this->hotDataConfig = $this->getDefaultHotDataConfig();
    }

    /**
     * 默认热点数据配置
     */
    private function getDefaultHotDataConfig(): array
    {
        return [
            // 用户数据
            'user_profile' => [
                'key_pattern' => 'user:profile:{user_id}',
                'ttl' => 3600,
                'loader' => function ($userId) {
                    return Connection::queryOne(
                        "SELECT id, username, email, avatar, bio, created_at FROM users WHERE id = ?",
                        [$userId]
                    );
                },
            ],
            
            // 仓库数据
            'repo_info' => [
                'key_pattern' => 'repo:info:{repo_id}',
                'ttl' => 1800,
                'loader' => function ($repoId) {
                    return Connection::queryOne(
                        "SELECT r.*, u.username as owner_name FROM repositories r 
                         JOIN users u ON r.user_id = u.id WHERE r.id = ?",
                        [$repoId]
                    );
                },
            ],
            
            // 仓库统计
            'repo_stats' => [
                'key_pattern' => 'repo:stats:{repo_id}',
                'ttl' => 300,
                'loader' => function ($repoId) {
                    $stars = Connection::queryOne(
                        "SELECT COUNT(*) as count FROM stars WHERE repo_id = ?",
                        [$repoId]
                    );
                    $forks = Connection::queryOne(
                        "SELECT COUNT(*) as count FROM forks WHERE repo_id = ?",
                        [$repoId]
                    );
                    $issues = Connection::queryOne(
                        "SELECT COUNT(*) as count FROM issues WHERE repo_id = ? AND status = 'open'",
                        [$repoId]
                    );
                    $prs = Connection::queryOne(
                        "SELECT COUNT(*) as count FROM pull_requests WHERE repo_id = ? AND status = 'open'",
                        [$repoId]
                    );
                    
                    return [
                        'stars' => (int) $stars['count'],
                        'forks' => (int) $forks['count'],
                        'open_issues' => (int) $issues['count'],
                        'open_prs' => (int) $prs['count'],
                    ];
                },
            ],
            
            // Issue 数据
            'issue_detail' => [
                'key_pattern' => 'issue:detail:{issue_id}',
                'ttl' => 600,
                'loader' => function ($issueId) {
                    return Connection::queryOne(
                        "SELECT i.*, u.username as author_name, r.name as repo_name, r.owner as repo_owner
                         FROM issues i
                         JOIN users u ON i.user_id = u.id
                         JOIN repositories r ON i.repo_id = r.id
                         WHERE i.id = ?",
                        [$issueId]
                    );
                },
            ],
            
            // PR 数据
            'pr_detail' => [
                'key_pattern' => 'pr:detail:{pr_id}',
                'ttl' => 600,
                'loader' => function ($prId) {
                    return Connection::queryOne(
                        "SELECT pr.*, u.username as author_name, r.name as repo_name, r.owner as repo_owner
                         FROM pull_requests pr
                         JOIN users u ON pr.user_id = u.id
                         JOIN repositories r ON pr.repo_id = r.id
                         WHERE pr.id = ?",
                        [$prId]
                    );
                },
            ],
            
            // 用户仓库列表
            'user_repos' => [
                'key_pattern' => 'user:repos:{user_id}:page:{page}',
                'ttl' => 300,
                'loader' => function ($params) {
                    $userId = $params['user_id'];
                    $page = $params['page'] ?? 1;
                    $perPage = 20;
                    $offset = ($page - 1) * $perPage;
                    
                    return Connection::query(
                        "SELECT * FROM repositories WHERE user_id = ? 
                         ORDER BY updated_at DESC LIMIT ? OFFSET ?",
                        [$userId, $perPage, $offset]
                    );
                },
            ],
            
            // 热门仓库
            'trending_repos' => [
                'key_pattern' => 'trending:repos:{period}',
                'ttl' => 3600,
                'loader' => function ($period = 'day') {
                    $interval = match ($period) {
                        'week' => '7 DAY',
                        'month' => '30 DAY',
                        default => '1 DAY',
                    };
                    
                    return Connection::query(
                        "SELECT r.*, u.username as owner_name, COUNT(s.id) as star_count
                         FROM repositories r
                         JOIN users u ON r.user_id = u.id
                         LEFT JOIN stars s ON r.id = s.repo_id AND s.created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                         WHERE r.is_private = 0
                         GROUP BY r.id
                         ORDER BY star_count DESC, r.updated_at DESC
                         LIMIT 20"
                    );
                },
            ],
            
            // 文件内容
            'file_content' => [
                'key_pattern' => 'file:content:{repo_id}:{branch}:{path_hash}',
                'ttl' => 600,
                'loader' => null, // 由 GitService 处理
            ],
            
            // 提交历史
            'commit_history' => [
                'key_pattern' => 'commits:{repo_id}:{branch}:page:{page}',
                'ttl' => 300,
                'loader' => null, // 由 GitService 处理
            ],
        ];
    }

    /**
     * 获取热点数据
     */
    public function getHotData(string $type, $identifier)
    {
        if (!isset($this->hotDataConfig[$type])) {
            throw new \InvalidArgumentException("Unknown hot data type: {$type}");
        }
        
        $config = $this->hotDataConfig[$type];
        $key = $this->buildKey($config['key_pattern'], $identifier);
        
        // 尝试从缓存获取
        $data = $this->cache->get($key);
        
        if ($data !== null) {
            $this->stats['hits']++;
            return $data;
        }
        
        $this->stats['misses']++;
        
        // 从数据源加载
        if ($config['loader'] !== null) {
            $data = $config['loader']($identifier);
            
            if ($data !== null) {
                $this->cache->set($key, $data, $config['ttl']);
                $this->stats['sets']++;
            }
        }
        
        return $data;
    }

    /**
     * 设置热点数据
     */
    public function setHotData(string $type, $identifier, $data, ?int $ttl = null): bool
    {
        if (!isset($this->hotDataConfig[$type])) {
            return false;
        }
        
        $config = $this->hotDataConfig[$type];
        $key = $this->buildKey($config['key_pattern'], $identifier);
        $ttl = $ttl ?? $config['ttl'];
        
        $this->stats['sets']++;
        return $this->cache->set($key, $data, $ttl);
    }

    /**
     * 删除热点数据
     */
    public function deleteHotData(string $type, $identifier): bool
    {
        if (!isset($this->hotDataConfig[$type])) {
            return false;
        }
        
        $config = $this->hotDataConfig[$type];
        $key = $this->buildKey($config['key_pattern'], $identifier);
        
        $this->stats['deletes']++;
        return $this->cache->delete($key);
    }

    /**
     * 批量预热缓存
     */
    public function warmup(array $types = []): array
    {
        if (empty($types)) {
            $types = ['user_profile', 'repo_info', 'trending_repos'];
        }
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];
        
        foreach ($types as $type) {
            try {
                $count = $this->warmupType($type);
                $results['success'] += $count;
                $results['details'][$type] = [
                    'status' => 'success',
                    'count' => $count,
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][$type] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * 预热特定类型
     */
    private function warmupType(string $type): int
    {
        $count = 0;
        
        switch ($type) {
            case 'user_profile':
                // 预热活跃用户
                $users = Connection::query(
                    "SELECT id FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 100"
                );
                foreach ($users as $user) {
                    $this->getHotData('user_profile', $user['id']);
                    $count++;
                }
                break;
                
            case 'repo_info':
                // 预热热门仓库
                $repos = Connection::query(
                    "SELECT DISTINCT repo_id FROM stars 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
                     LIMIT 50"
                );
                foreach ($repos as $repo) {
                    $this->getHotData('repo_info', $repo['repo_id']);
                    $this->getHotData('repo_stats', $repo['repo_id']);
                    $count++;
                }
                break;
                
            case 'trending_repos':
                // 预热热门仓库列表
                $this->getHotData('trending_repos', 'day');
                $this->getHotData('trending_repos', 'week');
                $this->getHotData('trending_repos', 'month');
                $count = 3;
                break;
        }
        
        return $count;
    }

    /**
     * 构建缓存键
     */
    private function buildKey(string $pattern, $identifier): string
    {
        if (is_array($identifier)) {
            $key = $pattern;
            foreach ($identifier as $k => $v) {
                $key = str_replace("{{$k}}", $v, $key);
            }
            return $key;
        }
        
        return str_replace('{id}', (string) $identifier, $pattern);
    }

    /**
     * 获取缓存统计
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'deletes' => $this->stats['deletes'],
            'hit_rate' => $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0,
            'miss_rate' => $total > 0 ? round(($this->stats['misses'] / $total) * 100, 2) : 0,
        ];
    }

    /**
     * 重置统计
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
        ];
    }

    /**
     * 清除所有热点数据缓存
     */
    public function clearAll(): int
    {
        $count = 0;
        
        foreach ($this->hotDataConfig as $type => $config) {
            $pattern = str_replace(['{user_id}', '{repo_id}', '{issue_id}', '{pr_id}', '{page}', '{branch}', '{path_hash}', '{period}'], '*', $config['key_pattern']);
            $count += $this->cache->deleteByPattern($pattern);
        }
        
        return $count;
    }

    /**
     * 获取 Redis 内存使用情况
     */
    public function getMemoryUsage(): array
    {
        try {
            $redis = new \Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: 'localhost',
                (int) (getenv('REDIS_PORT') ?: 6379)
            );
            
            $info = $redis->info('memory');
            
            return [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? '0B',
                'mem_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 添加自定义热点数据配置
     */
    public function addHotDataConfig(string $type, array $config): void
    {
        $this->hotDataConfig[$type] = $config;
    }
}
