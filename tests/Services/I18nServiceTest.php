<?php
/**
 * CodeVault - I18nService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class I18nServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\I18nService();
    }
    
    public function testSupportedLocales(): void
    {
        $locales = ['en', 'zh-CN', 'zh-TW', 'ja', 'ko', 'de', 'fr', 'es'];
        foreach ($locales as $locale) {
            $this->assertTrue($this->service->isSupportedLocale($locale));
        }
        $this->assertFalse($this->service->isSupportedLocale('invalid'));
    }
    
    public function testLocaleFormat(): void
    {
        $this->assertTrue($this->service->isValidLocaleFormat('en'));
        $this->assertTrue($this->service->isValidLocaleFormat('zh-CN'));
        $this->assertTrue($this->service->isValidLocaleFormat('en-US'));
        
        $this->assertFalse($this->service->isValidLocaleFormat(''));
        $this->assertFalse($this->service->isValidLocaleFormat('INVALID'));
    }
    
    public function testTranslationKey(): void
    {
        $this->assertTrue($this->service->isValidKey('common.save'));
        $this->assertTrue($this->service->isValidKey('user.profile.title'));
        
        $this->assertFalse($this->service->isValidKey(''));
        $this->assertFalse($this->service->isValidKey(str_repeat('a', 300)));
    }
    
    public function testPluralRules(): void
    {
        $this->assertEquals('one', $this->service->getPluralForm('en', 1));
        $this->assertEquals('other', $this->service->getPluralForm('en', 2));
        $this->assertEquals('other', $this->service->getPluralForm('en', 0));
    }
    
    public function testNumberFormatting(): void
    {
        $formatted = $this->service->formatNumber(1234.56, 'en-US');
        $this->assertStringContainsString('1,234', $formatted);
        
        $formatted = $this->service->formatNumber(1234.56, 'de-DE');
        $this->assertStringContainsString('1.234', $formatted);
    }
    
    public function testDateFormatting(): void
    {
        $timestamp = strtotime('2024-01-15 10:30:00');
        
        $formatted = $this->service->formatDate($timestamp, 'en-US', 'short');
        $this->assertNotEmpty($formatted);
        
        $formatted = $this->service->formatDate($timestamp, 'zh-CN', 'long');
        $this->assertNotEmpty($formatted);
    }
    
    public function testCurrencyFormatting(): void
    {
        $formatted = $this->service->formatCurrency(99.99, 'USD', 'en-US');
        $this->assertStringContainsString('$', $formatted);
        
        $formatted = $this->service->formatCurrency(99.99, 'CNY', 'zh-CN');
        $this->assertStringContainsString('¥', $formatted);
    }
    
    public function testFallbackLocale(): void
    {
        $this->assertEquals('en', $this->service->getFallbackLocale());
    }
}
