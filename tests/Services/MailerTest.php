<?php
/**
 * CodeVault - Mailer 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\Mailer();
    }
    
    public function testEmailValidation(): void
    {
        $this->assertTrue($this->service->isValidEmail('test@example.com'));
        $this->assertTrue($this->service->isValidEmail('user.name@domain.org'));
        
        $this->assertFalse($this->service->isValidEmail('invalid'));
        $this->assertFalse($this->service->isValidEmail('test@'));
        $this->assertFalse($this->service->isValidEmail(''));
    }
    
    public function testSubjectValidation(): void
    {
        $this->assertTrue($this->service->isValidSubject('Test Subject'));
        $this->assertTrue($this->service->isValidSubject('Re: [CodeVault] Issue #123'));
        
        $this->assertFalse($this->service->isValidSubject(''));
        $this->assertFalse($this->service->isValidSubject(str_repeat('a', 1000)));
    }
    
    public function testBodyValidation(): void
    {
        $this->assertTrue($this->service->isValidBody('This is a test email body'));
        $this->assertFalse($this->service->isValidBody(''));
    }
    
    public function testTemplateRendering(): void
    {
        $template = 'Hello {name}, your code is {code}';
        $variables = ['name' => 'User', 'code' => '123456'];
        
        $rendered = $this->service->renderTemplate($template, $variables);
        
        $this->assertEquals('Hello User, your code is 123456', $rendered);
    }
    
    public function testAttachmentValidation(): void
    {
        $attachment = [
            'path' => '/tmp/test.pdf',
            'name' => 'document.pdf',
            'type' => 'application/pdf',
        ];
        
        $this->assertTrue($this->service->isValidAttachment($attachment));
        
        $invalidAttachment = ['path' => ''];
        $this->assertFalse($this->service->isValidAttachment($invalidAttachment));
    }
    
    public function testRateLimiting(): void
    {
        $email = 'test@example.com';
        
        $this->assertTrue($this->service->canSendTo($email));
        
        // 模拟发送多次
        for ($i = 0; $i < 10; $i++) {
            $this->service->recordSend($email);
        }
        
        $this->assertFalse($this->service->canSendTo($email));
    }
    
    public function testQueueStatus(): void
    {
        $this->assertTrue($this->service->isQueueHealthy());
    }
    
    public function testPriorityLevels(): void
    {
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        foreach ($validPriorities as $priority) {
            $this->assertTrue($this->service->isValidPriority($priority));
        }
    }
}
