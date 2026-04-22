<?php
/**
 * CodeVault - SsoService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class SsoServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\SsoService();
    }
    
    public function testProtocolValidation(): void
    {
        $validProtocols = ['saml', 'oauth2', 'oidc', 'cas'];
        foreach ($validProtocols as $protocol) {
            $this->assertTrue($this->service->isValidProtocol($protocol));
        }
        $this->assertFalse($this->service->isValidProtocol('invalid'));
    }
    
    public function testEntityIdValidation(): void
    {
        $this->assertTrue($this->service->isValidEntityId('https://idp.example.com'));
        $this->assertFalse($this->service->isValidEntityId(''));
    }
    
    public function testCertificateValidation(): void
    {
        $cert = "-----BEGIN CERTIFICATE-----\nMIIC...\n-----END CERTIFICATE-----";
        $this->assertTrue($this->service->isValidCertificate($cert));
        
        $this->assertFalse($this->service->isValidCertificate(''));
        $this->assertFalse($this->service->isValidCertificate('invalid'));
    }
    
    public function testAttributeMapping(): void
    {
        $mapping = [
            'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'name' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ];
        
        $this->assertTrue($this->service->isValidAttributeMapping($mapping));
        
        $invalidMapping = ['email' => ''];
        $this->assertFalse($this->service->isValidAttributeMapping($invalidMapping));
    }
    
    public function testSamlRequestGeneration(): void
    {
        $request = $this->service->generateSamlRequest([
            'entity_id' => 'https://sp.example.com',
            'acs_url' => 'https://sp.example.com/acs',
        ]);
        
        $this->assertNotEmpty($request);
    }
    
    public function testSignatureValidation(): void
    {
        $xml = '<samlp:Response>...</samlp:Response>';
        $signature = 'base64_signature';
        
        // 模拟验证
        $result = $this->service->verifySignature($xml, $signature);
        $this->assertArrayHasKey('valid', $result);
    }
    
    public function testSessionIndex(): void
    {
        $index = $this->service->generateSessionIndex();
        
        $this->assertNotEmpty($index);
        $this->assertEquals(32, strlen($index));
    }
    
    public function testLogoutRequest(): void
    {
        $request = $this->service->generateLogoutRequest('session_123');
        
        $this->assertNotEmpty($request);
    }
}
