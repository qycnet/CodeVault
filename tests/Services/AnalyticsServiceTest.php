<?php
/**
 * CodeVault - AnalyticsService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\AnalyticsService();
    }
    
    public function testMetricTypes(): void
    {
        $validMetrics = ['views', 'clones', 'forks', 'stars', 'issues', 'prs'];
        foreach ($validMetrics as $metric) {
            $this->assertTrue($this->service->isValidMetric($metric));
        }
        $this->assertFalse($this->service->isValidMetric('invalid'));
    }
    
    public function testTimeRangeValidation(): void
    {
        $this->assertTrue($this->service->isValidTimeRange('7d'));
        $this->assertTrue($this->service->isValidTimeRange('30d'));
        $this->assertTrue($this->service->isValidTimeRange('90d'));
        $this->assertTrue($this->service->isValidTimeRange('1y'));
        
        $this->assertFalse($this->service->isValidTimeRange('invalid'));
    }
    
    public function testAggregationTypes(): void
    {
        $validTypes = ['sum', 'avg', 'max', 'min', 'count'];
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidAggregation($type));
        }
    }
    
    public function testDateRangeCalculation(): void
    {
        $range = $this->service->calculateDateRange('7d');
        $this->assertCount(2, $range);
        $this->assertArrayHasKey('start', $range);
        $this->assertArrayHasKey('end', $range);
    }
    
    public function testTrafficStats(): void
    {
        $stats = $this->service->getTrafficStats(1, '7d');
        
        $this->assertArrayHasKey('views', $stats);
        $this->assertArrayHasKey('clones', $stats);
        $this->assertArrayHasKey('unique_views', $stats);
    }
    
    public function testContributionStats(): void
    {
        $stats = $this->service->getContributionStats(1, '30d');
        
        $this->assertArrayHasKey('commits', $stats);
        $this->assertArrayHasKey('additions', $stats);
        $this->assertArrayHasKey('deletions', $stats);
    }
    
    public function testReferrerTracking(): void
    {
        $referrers = $this->service->getTopReferrers(1, 10);
        
        $this->assertIsArray($referrers);
        foreach ($referrers as $referrer) {
            $this->assertArrayHasKey('source', $referrer);
            $this->assertArrayHasKey('count', $referrer);
        }
    }
    
    public function testPopularContent(): void
    {
        $content = $this->service->getPopularContent(1, '7d', 10);
        
        $this->assertIsArray($content);
    }
    
    public function testExportFormats(): void
    {
        $validFormats = ['json', 'csv', 'xlsx'];
        foreach ($validFormats as $format) {
            $this->assertTrue($this->service->isValidExportFormat($format));
        }
    }
}
