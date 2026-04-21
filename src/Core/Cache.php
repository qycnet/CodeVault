<?php
/**
 * CodeVault 缓存核心类
 * 
 * Redis 缓存操作的统一入口
 */

namespace Core;

class Cache
{
    private static ?Cache $instance = null;
    private ?\Redis $redis = null;
    private bool $connected = false;
    
    /**
     * 私有构造函数（单例模式）
     */
    private function __construct()
    {
        $this->connect();
    }
    
    /**
     * 获取缓存实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 连接 Redis
     */
    private function connect(): void
    {
        try {
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $password = $_ENV['REDIS_PASSWORD'] ?? null;
            $database = (int)($_ENV['REDIS_DATABASE'] ?? 0);
            
            $this->redis = new \Redis();
            $this->connected = $this->redis->connect($host, $port, 2.0);
            
            if ($password) {
                $this->redis->auth($password);
            }
            
            if ($database > 0) {
                $this->redis->select($database);
            }
        } catch (\Exception $e) {
            $this->connected = false;
            error_log('Cache connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取缓存值
     */
    public function get(string $key): mixed
    {
        if (!$this->connected || !$this->redis) {
            return null;
        }
        
        try {
            $value = $this->redis->get($key);
            if ($value === false) {
                return null;
            }
            return json_decode($value, true) ?? $value;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * 设置缓存值
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            $serialized = is_array($value) || is_object($value) 
                ? json_encode($value) 
                : (string)$value;
            
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $serialized);
            }
            return $this->redis->set($key, $serialized);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 删除缓存
     */
    public function delete(string $key): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 检查键是否存在
     */
    public function exists(string $key): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 设置过期时间
     */
    public function expire(string $key, int $ttl): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            return $this->redis->expire($key, $ttl);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取剩余过期时间
     */
    public function ttl(string $key): int
    {
        if (!$this->connected || !$this->redis) {
            return -1;
        }
        
        try {
            return $this->redis->ttl($key);
        } catch (\Exception $e) {
            return -1;
        }
    }
    
    /**
     * 自增
     */
    public function increment(string $key, int $value = 1): int
    {
        if (!$this->connected || !$this->redis) {
            return 0;
        }
        
        try {
            return $this->redis->incrBy($key, $value);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 自减
     */
    public function decrement(string $key, int $value = 1): int
    {
        if (!$this->connected || !$this->redis) {
            return 0;
        }
        
        try {
            return $this->redis->decrBy($key, $value);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * 清空缓存
     */
    public function flush(): bool
    {
        if (!$this->connected || !$this->redis) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取原生 Redis 实例
     */
    public function getRedis(): ?\Redis
    {
        return $this->redis;
    }
    
    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }
}
