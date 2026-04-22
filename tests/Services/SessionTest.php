<?php
/**
 * CodeVault - Session 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\Session();
    }
    
    public function testSessionIdGeneration(): void
    {
        $id1 = $this->service->generateId();
        $id2 = $this->service->generateId();
        
        $this->assertNotEmpty($id1);
        $this->assertEquals(64, strlen($id1));
        $this->assertNotEquals($id1, $id2);
    }
    
    public function testSessionKeyValidation(): void
    {
        $this->assertTrue($this->service->isValidKey('user_id'));
        $this->assertTrue($this->service->isValidKey('csrf_token'));
        
        $this->assertFalse($this->service->isValidKey(''));
        $this->assertFalse($this->service->isValidKey(str_repeat('a', 100)));
    }
    
    public function testSessionTtl(): void
    {
        $this->assertEquals(7200, $this->service->getDefaultTtl());
        $this->assertTrue($this->service->isValidTtl(3600));
        $this->assertFalse($this->service->isValidTtl(-1));
    }
    
    public function testSessionData(): void
    {
        $this->service->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $this->service->get('test_key'));
        $this->assertNull($this->service->get('nonexistent'));
        $this->assertEquals('default', $this->service->get('nonexistent', 'default'));
    }
    
    public function testSessionRegeneration(): void
    {
        $oldId = $this->service->getId();
        $this->service->set('data', 'value');
        
        $this->service->regenerate();
        
        $newId = $this->service->getId();
        $this->assertNotEquals($oldId, $newId);
        $this->assertEquals('value', $this->service->get('data'));
    }
    
    public function testSessionDestroy(): void
    {
        $this->service->set('test', 'value');
        $this->service->destroy();
        
        $this->assertNull($this->service->get('test'));
    }
    
    public function testFlashData(): void
    {
        $this->service->flash('message', 'Success!');
        
        $this->assertEquals('Success!', $this->service->getFlash('message'));
        $this->assertNull($this->service->getFlash('message')); // 第二次获取为空
    }
    
    public function testSessionSecurity(): void
    {
        $config = $this->service->getSecurityConfig();
        
        $this->assertTrue($config['httponly']);
        $this->assertEquals('Lax', $config['samesite']);
        $this->assertTrue($config['use_strict_mode']);
    }
}
