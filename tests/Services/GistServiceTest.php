<?php
/**
 * CodeVault - GistService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class GistServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\GistService();
    }
    
    public function testVisibilityValidation(): void
    {
        $this->assertTrue($this->service->isValidVisibility('public'));
        $this->assertTrue($this->service->isValidVisibility('private'));
        $this->assertTrue($this->service->isValidVisibility('secret'));
        
        $this->assertFalse($this->service->isValidVisibility('invalid'));
    }
    
    public function testFilenameValidation(): void
    {
        $this->assertTrue($this->service->isValidFilename('test.php'));
        $this->assertTrue($this->service->isValidFilename('readme.md'));
        $this->assertTrue($this->service->isValidFilename('config.json'));
        
        $this->assertFalse($this->service->isValidFilename(''));
        $this->assertFalse($this->service->isValidFilename(str_repeat('a', 300)));
    }
    
    public function testContentValidation(): void
    {
        $this->assertTrue($this->service->isValidContent('<?php echo "test";'));
        $this->assertTrue($this->service->isValidContent(''));
        $this->assertFalse($this->service->isValidContent(str_repeat('a', 11000000))); // > 10MB
    }
    
    public function testGistIdGeneration(): void
    {
        $id1 = $this->service->generateGistId();
        $id2 = $this->service->generateGistId();
        
        $this->assertNotEmpty($id1);
        $this->assertEquals(32, strlen($id1));
        $this->assertNotEquals($id1, $id2);
    }
    
    public function testLanguageDetection(): void
    {
        $this->assertEquals('php', $this->service->detectLanguage('test.php'));
        $this->assertEquals('javascript', $this->service->detectLanguage('test.js'));
        $this->assertEquals('python', $this->service->detectLanguage('test.py'));
        $this->assertEquals('markdown', $this->service->detectLanguage('readme.md'));
    }
    
    public function testFileSizeLimit(): void
    {
        $this->assertTrue($this->service->isValidFileSize(1024));
        $this->assertTrue($this->service->isValidFileSize(10485760)); // 10MB
        $this->assertFalse($this->service->isValidFileSize(10485761));
    }
    
    public function testFileCountLimit(): void
    {
        $files = array_fill(0, 10, ['filename' => 'test.txt', 'content' => 'test']);
        $this->assertTrue($this->service->isValidFileCount(count($files)));
        
        $files = array_fill(0, 301, ['filename' => 'test.txt', 'content' => 'test']);
        $this->assertFalse($this->service->isValidFileCount(count($files)));
    }
    
    public function testForkOperation(): void
    {
        $gist = ['id' => 'abc123', 'public' => true];
        $this->assertTrue($this->service->canFork($gist));
        
        $gist['public'] = false;
        $this->assertFalse($this->service->canFork($gist));
    }
}
