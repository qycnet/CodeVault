<?php
/**
 * CodeVault - StatsService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\StatsService();
    }
    
    public function testStatTypeValidation(): void
    {
        $validTypes = ['commits', 'issues', 'prs', 'stars', 'forks', 'contributors'];
        foreach ($validTypes as $type) {
            $this->assertTrue($this->service->isValidStatType($type));
        }
        $this->assertFalse($this->service->isValidStatType('invalid'));
    }
    
    public function testTimeRangeValidation(): void
    {
        $this->assertTrue($this->service->isValidTimeRange('day'));
        $this->assertTrue($this->service->isValidTimeRange('week'));
        $this->assertTrue($this->service->isValidTimeRange('month'));
        $this->assertTrue($this->service->isValidTimeRange('year'));
        
        $this->assertFalse($this->service->isValidTimeRange('invalid'));
    }
    
    public function testCommitStats(): void
    {
        $stats = $this->service->getCommitStats(1, 'week');
        
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_author', $stats);
    }
    
    public function testCodeFrequency(): void
    {
        $frequency = $this->service->getCodeFrequency(1, 52);
        
        $this->assertIsArray($frequency);
        foreach ($frequency as $week) {
            $this->assertCount(2, $week); // [additions, deletions]
        }
    }
    
    public function testParticipation(): void
    {
        $participation = $this->service->getParticipation(1);
        
        $this->assertArrayHasKey('all', $participation);
        $this->assertArrayHasKey('owner', $participation);
    }
    
    public function testPunchCard(): void
    {
        $punchCard = $this->service->getPunchCard(1);
        
        $this->assertIsArray($punchCard);
        foreach ($punchCard as $point) {
            $this->assertCount(3, $point); // [day, hour, count]
        }
    }
    
    public function testContributorStats(): void
    {
        $contributors = $this->service->getContributorStats(1);
        
        $this->assertIsArray($contributors);
        foreach ($contributors as $contributor) {
            $this->assertArrayHasKey('author', $contributor);
            $this->assertArrayHasKey('total', $contributor);
        }
    }
    
    public function testClonesStats(): void
    {
        $clones = $this->service->getClones(1, 'week');
        
        $this->assertArrayHasKey('count', $clones);
        $this->assertArrayHasKey('uniques', $clones);
    }
    
    public function testViewsStats(): void
    {
        $views = $this->service->getViews(1, 'week');
        
        $this->assertArrayHasKey('count', $views);
        $this->assertArrayHasKey('uniques', $views);
    }
}
