<?php
/**
 * CodeVault - PerformanceService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class PerformanceServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\PerformanceService();
    }
    
    public function testMetricTypes(): void
    {
        $validMetrics = ['response_time', 'memory_usage', 'cpu_usage', 'query_count', 'cache_hit_rate'];
        foreach ($validMetrics as $metric) {
            $this->assertTrue($this->service->isValidMetric($metric));
        }
    }
    
    public function testThresholdValidation(): void
    {
        $this->assertTrue($this->service->isValidThreshold(100));
        $this->assertTrue($this->service->isValidThreshold(0));
        $this->assertFalse($this->service->isValidThreshold(-1));
    }
    
    public function testResponseTimeTracking(): void
    {
        $this->service->recordResponseTime('/api/test', 150);
        $this->service->recordResponseTime('/api/test', 200);
        
        $avg = $this->service->getAverageResponseTime('/api/test');
        $this->assertGreaterThan(0, $avg);
    }
    
    public function testMemoryTracking(): void
    {
        $usage = $this->service->getMemoryUsage();
        
        $this->assertArrayHasKey('used', $usage);
        $this->assertArrayHasKey('peak', $usage);
        $this->assertArrayHasKey('limit', $usage);
    }
    
    public function testSlowQueryDetection(): void
    {
        $query = 'SELECT * FROM large_table';
        $time = 5000; // 5 seconds
        
        $this->assertTrue($this->service->isSlowQuery($query, $time));
        $this->assertFalse($this->service->isSlowQuery($query, 100));
    }
    
    public function testCacheHitRate(): void
    {
        $this->service->recordCacheHit();
        $this->service->recordCacheHit();
        $this->service->recordCacheMiss();
        
        $rate = $this->service->getCacheHitRate();
        $this->assertEquals(66.67, round($rate, 2));
    }
    
    public function testAlertThresholds(): void
    {
        $this->assertTrue($this->service->shouldAlert('response_time', 5000));
        $this->assertFalse($this->service->shouldAlert('response_time', 100));
    }
    
    public function testPerformanceReport(): void
    {
        $report = $this->service->generateReport('1h');
        
        $this->assertArrayHasKey('avg_response_time', $report);
        $this->assertArrayHasKey('memory_peak', $report);
        $this->assertArrayHasKey('slow_queries', $report);
    }
}
