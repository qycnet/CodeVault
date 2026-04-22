<?php
/**
 * CodeVault - AdvancedSearchService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class AdvancedSearchServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\AdvancedSearchService();
    }
    
    public function testQueryValidation(): void
    {
        $this->assertTrue($this->service->isValidQuery('test'));
        $this->assertTrue($this->service->isValidQuery('repo:owner/name test'));
        $this->assertTrue($this->service->isValidQuery('user:testuser is:issue'));
        
        $this->assertFalse($this->service->isValidQuery(''));
        $this->assertFalse($this->service->isValidQuery(str_repeat('a', 1001)));
    }
    
    public function testSearchFilters(): void
    {
        $validFilters = [
            'type' => 'issue',
            'state' => 'open',
            'author' => 'testuser',
            'label' => 'bug',
        ];
        
        $this->assertTrue($this->service->areValidFilters($validFilters));
        
        $invalidFilters = ['type' => 'invalid'];
        $this->assertFalse($this->service->areValidFilters($invalidFilters));
    }
    
    public function testQueryParsing(): void
    {
        $query = 'repo:owner/name is:issue is:open label:bug';
        $parsed = $this->service->parseQuery($query);
        
        $this->assertEquals('owner/name', $parsed['repo']);
        $this->assertEquals('issue', $parsed['is']);
        $this->assertEquals('open', $parsed['state']);
        $this->assertEquals('bug', $parsed['label']);
    }
    
    public function testSearchScopes(): void
    {
        $validScopes = ['repositories', 'issues', 'pull_requests', 'users', 'code'];
        foreach ($validScopes as $scope) {
            $this->assertTrue($this->service->isValidScope($scope));
        }
        $this->assertFalse($this->service->isValidScope('invalid'));
    }
    
    public function testSortOptions(): void
    {
        $validSorts = ['relevance', 'date', 'stars', 'forks', 'updated'];
        foreach ($validSorts as $sort) {
            $this->assertTrue($this->service->isValidSortOption($sort));
        }
    }
    
    public function testPagination(): void
    {
        $this->assertEquals(1, $this->service->validatePage(1));
        $this->assertEquals(1, $this->service->validatePage(0));
        $this->assertEquals(100, $this->service->validatePerPage(100));
        $this->assertEquals(30, $this->service->validatePerPage(200)); // 最大 100
    }
    
    public function testHighlightExcerpt(): void
    {
        $content = 'This is a test content with search term';
        $query = 'search';
        
        $excerpt = $this->service->createExcerpt($content, $query, 50);
        $this->assertStringContainsString('search', $excerpt);
    }
    
    public function testFacetGeneration(): void
    {
        $results = [
            ['type' => 'issue', 'state' => 'open'],
            ['type' => 'issue', 'state' => 'closed'],
            ['type' => 'pr', 'state' => 'open'],
        ];
        
        $facets = $this->service->generateFacets($results);
        $this->assertArrayHasKey('type', $facets);
        $this->assertArrayHasKey('state', $facets);
    }
}
