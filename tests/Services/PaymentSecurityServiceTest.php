<?php
/**
 * CodeVault - PaymentSecurityService 测试
 */

declare(strict_types=1);

namespace CodeVault\Tests\Services;

use PHPUnit\Framework\TestCase;

class PaymentSecurityServiceTest extends TestCase
{
    private $service;
    
    protected function setUp(): void
    {
        $this->service = new \CodeVault\Services\PaymentSecurityService();
    }
    
    public function testAmountValidation(): void
    {
        $this->assertTrue($this->service->isValidAmount(10.00));
        $this->assertTrue($this->service->isValidAmount(0.01));
        $this->assertTrue($this->service->isValidAmount(10000.00));
        
        $this->assertFalse($this->service->isValidAmount(0));
        $this->assertFalse($this->service->isValidAmount(-10));
        $this->assertFalse($this->service->isValidAmount(1000000)); // 超过限制
    }
    
    public function testCurrencyValidation(): void
    {
        $validCurrencies = ['USD', 'EUR', 'GBP', 'CNY', 'JPY'];
        foreach ($validCurrencies as $currency) {
            $this->assertTrue($this->service->isValidCurrency($currency));
        }
        $this->assertFalse($this->service->isValidCurrency('INVALID'));
    }
    
    public function testCardNumberValidation(): void
    {
        // 测试卡号（Luhn 算法验证）
        $this->assertTrue($this->service->isValidCardFormat('4111111111111111'));
        $this->assertTrue($this->service->isValidCardFormat('5500000000000004'));
        
        $this->assertFalse($this->service->isValidCardFormat('1234567890123456'));
        $this->assertFalse($this->service->isValidCardFormat(''));
    }
    
    public function testCardTypeDetection(): void
    {
        $this->assertEquals('visa', $this->service->detectCardType('4111111111111111'));
        $this->assertEquals('mastercard', $this->service->detectCardType('5500000000000004'));
        $this->assertEquals('amex', $this->service->detectCardType('340000000000009'));
    }
    
    public function testExpiryValidation(): void
    {
        $futureDate = date('m/Y', strtotime('+1 year'));
        $this->assertTrue($this->service->isValidExpiry($futureDate));
        
        $pastDate = date('m/Y', strtotime('-1 year'));
        $this->assertFalse($this->service->isValidExpiry($pastDate));
    }
    
    public function testCvvValidation(): void
    {
        $this->assertTrue($this->service->isValidCvv('123', 'visa'));
        $this->assertTrue($this->service->isValidCvv('1234', 'amex'));
        
        $this->assertFalse($this->service->isValidCvv('12', 'visa'));
        $this->assertFalse($this->service->isValidCvv('12345', 'visa'));
    }
    
    public function testFraudScore(): void
    {
        $transaction = [
            'amount' => 100,
            'currency' => 'USD',
            'ip' => '192.168.1.1',
        ];
        
        $score = $this->service->calculateFraudScore($transaction);
        
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
    
    public function testTransactionLimit(): void
    {
        $userId = 999999;
        
        $this->assertTrue($this->service->isWithinLimit($userId, 100));
        
        // 模拟达到日限额
        $this->assertFalse($this->service->isWithinLimit($userId, 100000));
    }
}
