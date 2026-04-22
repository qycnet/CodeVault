<?php
/**
 * CodeVault - SocialService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class SocialServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\SocialService();
    }
    
    public function testProviderValidation(): void
    {
        $validProviders = ['github', 'gitlab', 'bitbucket', 'google', 'twitter'];
        foreach ($validProviders as $provider) {
            $this->assertTrue($this->service->isValidProvider($provider));
        }
        $this->assertFalse($this->service->isValidProvider('invalid'));
    }
    
    public function testOAuthStateGeneration(): void
    {
        $state1 = $this->service->generateState();
        $state2 = $this->service->generateState();
        
        $this->assertNotEmpty($state1);
        $this->assertEquals(32, strlen($state1));
        $this->assertNotEquals($state1, $state2);
    }
    
    public function testStateValidation(): void
    {
        $state = $this->service->generateState();
        
        $this->assertTrue($this->service->validateState($state));
        $this->assertFalse($this->service->validateState('invalid'));
    }
    
    public function testCallbackUrlValidation(): void
    {
        $this->assertTrue($this->service->isValidCallbackUrl('https://example.com/callback'));
        $this->assertFalse($this->service->isValidCallbackUrl('not-a-url'));
    }
    
    public function testTokenValidation(): void
    {
        $token = str_repeat('a', 40);
        $this->assertTrue($this->service->isValidToken($token));
        
        $this->assertFalse($this->service->isValidToken(''));
        $this->assertFalse($this->service->isValidToken('short'));
    }
    
    public function testProfileMapping(): void
    {
        $providerData = [
            'id' => '12345',
            'login' => 'testuser',
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];
        
        $profile = $this->service->mapProfile('github', $providerData);
        
        $this->assertEquals('testuser', $profile['username']);
        $this->assertEquals('test@example.com', $profile['email']);
    }
    
    public function testConnectionStatus(): void
    {
        $connection = ['provider' => 'github', 'connected' => true];
        $this->assertTrue($this->service->isConnected($connection));
        
        $connection['connected'] = false;
        $this->assertFalse($this->service->isConnected($connection));
    }
    
    public function testScopeValidation(): void
    {
        $scopes = ['user', 'repo', 'email'];
        $this->assertTrue($this->service->areValidScopes('github', $scopes));
        
        $invalidScopes = ['user', 'invalid_scope'];
        $this->assertFalse($this->service->areValidScopes('github', $invalidScopes));
    }
}
