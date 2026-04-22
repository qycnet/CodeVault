<?php
/**
 * CodeVault - PagesService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class PagesServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\PagesService();
    }
    
    public function testDomainValidation(): void
    {
        $this->assertTrue($this->service->isValidDomain('user.github.io'));
        $this->assertTrue($this->service->isValidDomain('custom-domain.com'));
        
        $this->assertFalse($this->service->isValidDomain(''));
        $this->assertFalse($this->service->isValidDomain('invalid domain'));
    }
    
    public function testBranchValidation(): void
    {
        $this->assertTrue($this->service->isValidBranch('gh-pages'));
        $this->assertTrue($this->service->isValidBranch('main'));
        $this->assertTrue($this->service->isValidBranch('docs'));
        
        $this->assertFalse($this->service->isValidBranch(''));
        $this->assertFalse($this->service->isValidBranch('invalid branch'));
    }
    
    public function testSourceValidation(): void
    {
        $validSource = [
            'branch' => 'gh-pages',
            'path' => '/',
        ];
        
        $this->assertTrue($this->service->isValidSource($validSource));
        
        $invalidSource = ['branch' => ''];
        $this->assertFalse($this->service->isValidSource($invalidSource));
    }
    
    public function testBuildStatus(): void
    {
        $validStatuses = ['queued', 'building', 'built', 'errored'];
        foreach ($validStatuses as $status) {
            $this->assertTrue($this->service->isValidBuildStatus($status));
        }
    }
    
    public function testCustomDomainVerification(): void
    {
        $domain = 'example.com';
        $verification = $this->service->getDomainVerification($domain);
        
        $this->assertArrayHasKey('type', $verification);
        $this->assertArrayHasKey('value', $verification);
    }
    
    public function testHttpsEnforcement(): void
    {
        $pages = ['https_enforced' => false];
        $this->assertFalse($this->service->isHttpsEnforced($pages));
        
        $pages['https_enforced'] = true;
        $this->assertTrue($this->service->isHttpsEnforced($pages));
    }
    
    public function testFileSizeLimit(): void
    {
        $this->assertTrue($this->service->isValidFileSize(1024 * 1024)); // 1MB
        $this->assertFalse($this->service->isValidFileSize(100 * 1024 * 1024)); // 100MB
    }
    
    public function testSiteUrl(): void
    {
        $url = $this->service->getSiteUrl('owner', 'repo');
        
        $this->assertStringContainsString('owner', $url);
        $this->assertStringContainsString('repo', $url);
    }
}
