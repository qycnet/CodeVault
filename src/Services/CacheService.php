<?php
/**
 * CodeVault - Redis 缓存服务
 */

namespace CodeVault\Services;

class CacheService
{
    private $redis;
    private $prefix = 'codevault:';
    private $enabled = false;
    
    public function __construct()
    {
        $this->connect();
    }
    
    /**
     * 连接 Redis
     */
    private function connect(): void
    {
        try {
            $this->redis = new \Redis();
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            
            if ($this->redis->connect($host, $port, 2)) {
                $this->enabled = true;
            }
        } catch (\Exception $e) {
            $this->enabled = false;
        }
    }
    
    /**
     * 获取缓存
     */
    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }
        
        try {
            $value = $this->redis->get($this->prefix . $key);
            
            if ($value === false) {
                return null;
            }
            
            return json_decode($value, true);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 设置缓存
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->setex(
                $this->prefix . $key,
                $ttl,
                json_encode($value)
            );
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 删除缓存
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 批量删除缓存
     */
    public function deletePattern(string $pattern): int
    {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $keys = $this->redis->keys($this->prefix . $pattern);
            
            if (empty($keys)) {
                return 0;
            }
            
            return $this->redis->del($keys);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取或设置缓存（回调模式）
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * 缓存仓库信息
     */
    public function getRepo(int $repoId): ?array
    {
        return $this->get("repo:{$repoId}");
    }
    
    /**
     * 缓存仓库信息
     */
    public function setRepo(int $repoId, array $data): bool
    {
        return $this->set("repo:{$repoId}", $data, 3600);
    }
    
    /**
     * 清除仓库缓存
     */
    public function clearRepo(int $repoId): void
    {
        $this->delete("repo:{$repoId}");
        $this->delete("repo:{$repoId}:branches");
        $this->delete("repo:{$repoId}:commits");
    }
    
    /**
     * 缓存用户信息
     */
    public function getUser(int $userId): ?array
    {
        return $this->get("user:{$userId}");
    }
    
    /**
     * 缓存用户信息
     */
    public function setUser(int $userId, array $data): bool
    {
        return $this->set("user:{$userId}", $data, 1800);
    }
    
    /**
     * 清除用户缓存
     */
    public function clearUser(int $userId): void
    {
        $this->delete("user:{$userId}");
        $this->delete("user:{$userId}:repos");
    }
    
    /**
     * 缓存 Issue 列表
     */
    public function getIssues(int $repoId, int $page = 1): ?array
    {
        return $this->get("repo:{$repoId}:issues:page:{$page}");
    }
    
    /**
     * 缓存 Issue 列表
     */
    public function setIssues(int $repoId, int $page, array $data): bool
    {
        return $this->set("repo:{$repoId}:issues:page:{$page}", $data, 300);
    }
    
    /**
     * 缓存提交历史
     */
    public function getCommits(int $repoId, string $branch, int $page = 1): ?array
    {
        return $this->get("repo:{$repoId}:commits:{$branch}:page:{$page}");
    }
    
    /**
     * 缓存提交历史
     */
    public function setCommits(int $repoId, string $branch, int $page, array $data): bool
    {
        return $this->set("repo:{$repoId}:commits:{$branch}:page:{$page}", $data, 600);
    }
    
    /**
     * 缓存文件内容
     */
    public function getFile(int $repoId, string $path, string $ref = 'HEAD'): ?string
    {
        return $this->get("repo:{$repoId}:file:{$ref}:" . md5($path));
    }
    
    /**
     * 缓存文件内容
     */
    public function setFile(int $repoId, string $path, string $ref, string $content): bool
    {
        return $this->set("repo:{$repoId}:file:{$ref}:" . md5($path), $content, 1800);
    }
    
    /**
     * 增加计数器
     */
    public function increment(string $key, int $value = 1): int
    {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            return $this->redis->incrBy($this->prefix . $key, $value);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取计数器
     */
    public function getCounter(string $key): int
    {
        if (!$this->enabled) {
            return 0;
        }
        
        try {
            $value = $this->redis->get($this->prefix . $key);
            return $value === false ? 0 : (int) $value;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * 获取 Redis 实例
     */
    public function getRedis(): ?\Redis
    {
        return $this->enabled ? $this->redis : null;
    }
}
