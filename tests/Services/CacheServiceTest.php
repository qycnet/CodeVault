<?php
/**
 * CodeVault - CacheService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\CacheService;

class CacheServiceTest extends TestCase
{
    private CacheService $service;
    
    protected function setUp(): void
    {
        $this->service = new CacheService();
    }
    
    /**
     * 测试键名验证
     */
    public function testKeyValidation(): void
    {
        $this->assertTrue($this->service->isValidKey('user:123'));
        $this->assertTrue($this->service->isValidKey('cache_data'));
        $this->assertTrue($this->service->isValidKey('repo:owner:name:branch'));
        
        $this->assertFalse($this->service->isValidKey(''));
        $this->assertFalse($this->service->isValidKey(str_repeat('a', 300))); // 太长
        $this->assertFalse($this->service->isValidKey('key with spaces'));
    }
    
    /**
     * 测试 TTL 验证
     */
    public function testTtlValidation(): void
    {
        $this->assertEquals(3600, $this->service->validateTtl(3600));
        $this->assertEquals(60, $this->service->validateTtl(60));
        $this->assertEquals(0, $this->service->validateTtl(0)); // 永不过期
        
        // 边界值
        $this->assertEquals(86400 * 30, $this->service->validateTtl(86400 * 30)); // 30 天
        $this->assertEquals(86400 * 30, $this->service->validateTtl(86400 * 365)); // 超过最大值，限制为 30 天
    }
    
    /**
     * 测试键名生成
     */
    public function testKeyGeneration(): void
    {
        $key1 = $this->service->generateKey('user', 123);
        $key2 = $this->service->generateKey('user', 123);
        
        $this->assertEquals('user:123', $key1);
        $this->assertEquals($key1, $key2);
        
        $key3 = $this->service->generateKey('repo', 'owner', 'name');
        $this->assertEquals('repo:owner:name', $key3);
    }
    
    /**
     * 测试序列化
     */
    public function testSerialization(): void
    {
        $data = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];
        
        $serialized = $this->service->serialize($data);
        $this->assertNotEmpty($serialized);
        
        $unserialized = $this->service->unserialize($serialized);
        $this->assertEquals($data, $unserialized);
    }
    
    /**
     * 测试缓存标签
     */
    public function testCacheTags(): void
    {
        $tags = ['user', 'profile', 'settings'];
        
        $taggedKey = $this->service->tagKey('user:123', $tags);
        $this->assertNotEmpty($taggedKey);
        
        $this->assertTrue($this->service->hasTag($taggedKey, 'user'));
        $this->assertTrue($this->service->hasTag($taggedKey, 'profile'));
        $this->assertFalse($this->service->hasTag($taggedKey, 'admin'));
    }
    
    /**
     * 测试缓存统计
     */
    public function testCacheStats(): void
    {
        $stats = $this->service->getStats();
        
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
    }
    
    /**
     * 测试锁机制
     */
    public function testLockMechanism(): void
    {
        $lockKey = 'test_lock_' . time();
        
        // 获取锁
        $this->assertTrue($this->service->acquireLock($lockKey, 10));
        
        // 重复获取应该失败
        $this->assertFalse($this->service->acquireLock($lockKey, 10));
        
        // 释放锁
        $this->service->releaseLock($lockKey);
        
        // 再次获取应该成功
        $this->assertTrue($this->service->acquireLock($lockKey, 10));
        
        // 清理
        $this->service->releaseLock($lockKey);
    }
    
    /**
     * 测试批量操作
     */
    public function testBatchOperations(): void
    {
        $keys = ['batch:1', 'batch:2', 'batch:3'];
        
        $this->assertTrue($this->service->validateKeys($keys));
        
        $invalidKeys = ['batch:1', 'invalid key with space', 'batch:3'];
        $this->assertFalse($this->service->validateKeys($invalidKeys));
    }
    
    /**
     * 测试缓存预热
     */
    public function testCacheWarmup(): void
    {
        $keys = ['warmup:1', 'warmup:2', 'warmup:3'];
        
        $this->assertTrue($this->service->canWarmup($keys));
        
        // 太多键
        $tooManyKeys = array_map(fn($i) => "warmup:$i", range(1, 10000));
        $this->assertFalse($this->service->canWarmup($tooManyKeys));
    }
}
