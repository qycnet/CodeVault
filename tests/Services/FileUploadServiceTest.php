<?php
/**
 * CodeVault - FileUploadService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;
use CodeVault\Services\FileUploadService;

class FileUploadServiceTest extends TestCase
{
    private FileUploadService $service;
    
    protected function setUp(): void
    {
        $this->service = new FileUploadService();
    }
    
    /**
     * 测试允许的扩展名
     */
    public function testAllowedExtensions(): void
    {
        // 允许的扩展名
        $this->assertTrue($this->service->isAllowedExtension('txt'));
        $this->assertTrue($this->service->isAllowedExtension('md'));
        $this->assertTrue($this->service->isAllowedExtension('pdf'));
        $this->assertTrue($this->service->isAllowedExtension('png'));
        $this->assertTrue($this->service->isAllowedExtension('jpg'));
        $this->assertTrue($this->service->isAllowedExtension('json'));
        
        // 禁止的扩展名
        $this->assertFalse($this->service->isAllowedExtension('php'));
        $this->assertFalse($this->service->isAllowedExtension('exe'));
        $this->assertFalse($this->service->isAllowedExtension('sh'));
        $this->assertFalse($this->service->isAllowedExtension('bat'));
        $this->assertFalse($this->service->isAllowedExtension('js'));
    }
    
    /**
     * 测试 MIME 类型验证
     */
    public function testMimeTypeValidation(): void
    {
        $this->assertTrue($this->service->isAllowedMimeType('text/plain'));
        $this->assertTrue($this->service->isAllowedMimeType('application/pdf'));
        $this->assertTrue($this->service->isAllowedMimeType('image/png'));
        $this->assertTrue($this->service->isAllowedMimeType('image/jpeg'));
        
        $this->assertFalse($this->service->isAllowedMimeType('application/x-php'));
        $this->assertFalse($this->service->isAllowedMimeType('application/javascript'));
        $this->assertFalse($this->service->isAllowedMimeType('application/x-executable'));
    }
    
    /**
     * 测试文件大小验证
     */
    public function testFileSizeValidation(): void
    {
        // 默认最大 10MB
        $this->assertTrue($this->service->isValidSize(1024)); // 1KB
        $this->assertTrue($this->service->isValidSize(10485760)); // 10MB
        
        $this->assertFalse($this->service->isValidSize(10485761)); // 10MB + 1 byte
        $this->assertFalse($this->service->isValidSize(-1)); // 负数
    }
    
    /**
     * 测试文件名清理
     */
    public function testFileNameSanitization(): void
    {
        $this->assertEquals('test_file.txt', $this->service->sanitizeFilename('test_file.txt'));
        $this->assertEquals('test file.txt', $this->service->sanitizeFilename('test file.txt'));
        
        // 移除危险字符
        $this->assertStringNotContainsString('../', $this->service->sanitizeFilename('../../../etc/passwd'));
        $this->assertStringNotContainsString("\x00", $this->service->sanitizeFilename("test\x00file.txt"));
    }
    
    /**
     * 测试魔数验证
     */
    public function testMagicNumberValidation(): void
    {
        // PNG 魔数
        $pngData = "\x89PNG\r\n\x1a\n";
        $this->assertTrue($this->service->validateMagicNumber($pngData, 'png'));
        
        // JPEG 魔数
        $jpegData = "\xFF\xD8\xFF";
        $this->assertTrue($this->service->validateMagicNumber($jpegData, 'jpg'));
        
        // 错误的魔数
        $this->assertFalse($this->service->validateMagicNumber('not an image', 'png'));
    }
    
    /**
     * 测试路径验证
     */
    public function testPathValidation(): void
    {
        $baseDir = '/var/www/uploads';
        
        $this->assertTrue($this->service->isValidPath($baseDir . '/user/file.txt', $baseDir));
        
        // 路径遍历攻击
        $this->assertFalse($this->service->isValidPath($baseDir . '/../etc/passwd', $baseDir));
        $this->assertFalse($this->service->isValidPath($baseDir . '/../../etc/passwd', $baseDir));
    }
    
    /**
     * 测试文件类型检测
     */
    public function testFileTypeDetection(): void
    {
        $this->assertEquals('image', $this->service->detectFileType('png'));
        $this->assertEquals('image', $this->service->detectFileType('jpg'));
        $this->assertEquals('document', $this->service->detectFileType('pdf'));
        $this->assertEquals('text', $this->service->detectFileType('txt'));
        $this->assertEquals('code', $this->service->detectFileType('json'));
    }
    
    /**
     * 测试病毒扫描状态
     */
    public function testVirusScanStatus(): void
    {
        // 如果启用了病毒扫描
        if ($this->service->isVirusScanEnabled()) {
            $this->assertTrue($this->service->requiresVirusScan('exe'));
            $this->assertFalse($this->service->requiresVirusScan('txt'));
        } else {
            $this->assertFalse($this->service->isVirusScanEnabled());
        }
    }
    
    /**
     * 测试文件哈希计算
     */
    public function testFileHashCalculation(): void
    {
        $content = 'test file content';
        
        $md5 = $this->service->calculateHash($content, 'md5');
        $sha256 = $this->service->calculateHash($content, 'sha256');
        
        $this->assertEquals(32, strlen($md5));
        $this->assertEquals(64, strlen($sha256));
        $this->assertNotEmpty($md5);
        $this->assertNotEmpty($sha256);
    }
    
    /**
     * 测试唯一文件名生成
     */
    public function testUniqueFilenameGeneration(): void
    {
        $filename1 = $this->service->generateUniqueFilename('test.txt');
        $filename2 = $this->service->generateUniqueFilename('test.txt');
        
        $this->assertNotEquals($filename1, $filename2);
        $this->assertStringEndsWith('.txt', $filename1);
        $this->assertStringEndsWith('.txt', $filename2);
    }
    
    /**
     * 测试图片尺寸验证
     */
    public function testImageDimensionValidation(): void
    {
        // 最大尺寸 10000x10000
        $this->assertTrue($this->service->isValidDimension(1920, 1080));
        $this->assertTrue($this->service->isValidDimension(10000, 10000));
        
        $this->assertFalse($this->service->isValidDimension(10001, 10000));
        $this->assertFalse($this->service->isValidDimension(0, 0));
    }
    
    /**
     * 测试压缩文件检测
     */
    public function testCompressedFileDetection(): void
    {
        $this->assertTrue($this->service->isCompressedFile('zip'));
        $this->assertTrue($this->service->isCompressedFile('gz'));
        $this->assertTrue($this->service->isCompressedFile('tar'));
        
        $this->assertFalse($this->service->isCompressedFile('txt'));
        $this->assertFalse($this->service->isCompressedFile('pdf'));
    }
}
