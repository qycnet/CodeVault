<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * 支付安全服务
 * 
 * 提供支付回调验证、金额校验、防重放攻击等安全措施
 */
class PaymentSecurityService
{
    private \PDO $db;
    private LoggerInterface $logger;
    private array $config;
    
    // 支付平台配置
    private array $platforms = [
        'alipay' => [
            'sign_type' => 'RSA2',
            'public_key' => '', // 支付宝公钥
            'gateway' => 'https://openapi.alipay.com/gateway.do'
        ],
        'wechat' => [
            'sign_type' => 'MD5',
            'api_key' => '', // 微信支付 API 密钥
            'gateway' => 'https://api.mch.weixin.qq.com/'
        ],
        'stripe' => [
            'sign_type' => 'HMAC-SHA256',
            'secret_key' => '', // Stripe Webhook Secret
            'gateway' => 'https://api.stripe.com/'
        ]
    ];
    
    public function __construct(\PDO $db, LoggerInterface $logger, array $config = [])
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = array_merge($this->platforms, $config);
    }
    
    /**
     * 1. 支付回调签名验证
     * 
     * @param string $platform 支付平台 (alipay/wechat/stripe)
     * @param array $data 回调数据
     * @param string $signature 签名
     * @return bool
     */
    public function verifyCallbackSignature(string $platform, array $data, string $signature): bool
    {
        $platformConfig = $this->config[$platform] ?? null;
        
        if (!$platformConfig) {
            $this->logger->error("Unknown payment platform: {$platform}");
            return false;
        }
        
        switch ($platform) {
            case 'alipay':
                return $this->verifyAlipaySignature($data, $signature, $platformConfig);
                
            case 'wechat':
                return $this->verifyWechatSignature($data, $signature, $platformConfig);
                
            case 'stripe':
                return $this->verifyStripeSignature($data, $signature, $platformConfig);
                
            default:
                $this->logger->error("Unsupported platform: {$platform}");
                return false;
        }
    }
    
    /**
     * 验证支付宝签名 (RSA2)
     */
    private function verifyAlipaySignature(array $data, string $signature, array $config): bool
    {
        $publicKey = $config['public_key'] ?? '';
        
        if (empty($publicKey)) {
            $this->logger->error('Alipay public key not configured');
            return false;
        }
        
        // 构建待验签字符串
        ksort($data);
        $signData = '';
        foreach ($data as $key => $value) {
            if ($key !== 'sign' && $key !== 'sign_type' && !empty($value)) {
                $signData .= "{$key}={$value}&";
            }
        }
        $signData = rtrim($signData, '&');
        
        // RSA2 验签
        $publicKeyResource = openssl_pkey_get_public($publicKey);
        if (!$publicKeyResource) {
            $this->logger->error('Invalid Alipay public key');
            return false;
        }
        
        $result = openssl_verify(
            $signData,
            base64_decode($signature),
            $publicKeyResource,
            OPENSSL_ALGO_SHA256
        );
        
        return $result === 1;
    }
    
    /**
     * 验证微信支付签名 (MD5/HMAC-SHA256)
     */
    private function verifyWechatSignature(array $data, string $signature, array $config): bool
    {
        $apiKey = $config['api_key'] ?? '';
        
        if (empty($apiKey)) {
            $this->logger->error('WeChat API key not configured');
            return false;
        }
        
        $signType = $data['sign_type'] ?? 'MD5';
        
        // 构建待签名字符串
        ksort($data);
        $signData = '';
        foreach ($data as $key => $value) {
            if ($key !== 'sign' && !empty($value)) {
                $signData .= "{$key}={$value}&";
            }
        }
        $signData .= "key={$apiKey}";
        
        // 计算签名
        if ($signType === 'HMAC-SHA256') {
            $calculatedSign = strtoupper(hash_hmac('sha256', $signData, $apiKey));
        } else {
            $calculatedSign = strtoupper(md5($signData));
        }
        
        return $calculatedSign === $signature;
    }
    
    /**
     * 验证 Stripe 签名 (HMAC-SHA256)
     */
    private function verifyStripeSignature(array $data, string $signature, array $config): bool
    {
        $secretKey = $config['secret_key'] ?? '';
        
        if (empty($secretKey)) {
            $this->logger->error('Stripe secret key not configured');
            return false;
        }
        
        // Stripe 使用 payload + timestamp 验签
        $payload = json_encode($data);
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secretKey);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * 2. 支付金额二次校验
     * 
     * @param string $paymentId 支付 ID
     * @param float $paidAmount 实际支付金额
     * @param string $currency 货币
     * @return bool
     */
    public function verifyPaymentAmount(string $paymentId, float $paidAmount, string $currency = 'CNY'): bool
    {
        // 查询订单金额
        $stmt = $this->db->prepare("
            SELECT amount, currency, status 
            FROM payments 
            WHERE payment_id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$payment) {
            $this->logger->error("Payment not found: {$paymentId}");
            return false;
        }
        
        // 检查订单状态
        if ($payment['status'] !== 'pending') {
            $this->logger->warning("Payment already processed: {$paymentId}, status: {$payment['status']}");
            return false;
        }
        
        // 金额校验（允许 0.01 的误差，处理浮点数精度问题）
        $expectedAmount = (float) $payment['amount'];
        $amountDiff = abs($expectedAmount - $paidAmount);
        
        if ($amountDiff > 0.01) {
            $this->logger->error("Amount mismatch for payment {$paymentId}: expected {$expectedAmount}, got {$paidAmount}");
            return false;
        }
        
        // 货币校验
        if ($payment['currency'] !== $currency) {
            $this->logger->error("Currency mismatch for payment {$paymentId}: expected {$payment['currency']}, got {$currency}");
            return false;
        }
        
        return true;
    }
    
    /**
     * 3. 防重放攻击 - payment_id 唯一性检查
     * 
     * @param string $paymentId 支付 ID
     * @param string $transactionId 第三方交易号
     * @return bool true 表示可以处理，false 表示重复
     */
    public function checkPaymentUniqueness(string $paymentId, string $transactionId): bool
    {
        // 检查 payment_id 是否已处理
        $stmt = $this->db->prepare("
            SELECT id, status, transaction_id 
            FROM payments 
            WHERE payment_id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$payment) {
            $this->logger->error("Payment not found: {$paymentId}");
            return false;
        }
        
        // 如果已经成功处理，拒绝重复处理
        if ($payment['status'] === 'success') {
            $this->logger->warning("Payment already processed: {$paymentId}");
            return false;
        }
        
        // 检查第三方交易号是否已存在（防止同一笔交易重复通知）
        if (!empty($payment['transaction_id']) && $payment['transaction_id'] === $transactionId) {
            $this->logger->warning("Duplicate transaction: {$transactionId} for payment {$paymentId}");
            return false;
        }
        
        // 检查是否有其他订单使用了相同的第三方交易号
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM payments 
            WHERE transaction_id = ? AND payment_id != ?
        ");
        $stmt->execute([$transactionId, $paymentId]);
        
        if ((int) $stmt->fetchColumn() > 0) {
            $this->logger->error("Transaction ID already used: {$transactionId}");
            return false;
        }
        
        return true;
    }
    
    /**
     * 记录支付处理日志
     */
    public function logPaymentAttempt(string $paymentId, string $action, array $data, bool $success): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_logs (payment_id, action, data, success, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $paymentId,
            $action,
            json_encode($data),
            $success ? 1 : 0
        ]);
    }
    
    /**
     * 完整的支付回调验证流程
     * 
     * @param string $platform 支付平台
     * @param array $callbackData 回调数据
     * @return array ['success' => bool, 'message' => string, 'payment_id' => string]
     */
    public function validatePaymentCallback(string $platform, array $callbackData): array
    {
        // 1. 签名验证
        $signature = $callbackData['sign'] ?? '';
        
        if (!$this->verifyCallbackSignature($platform, $callbackData, $signature)) {
            $this->logger->error("Signature verification failed for {$platform}");
            return [
                'success' => false,
                'message' => '签名验证失败'
            ];
        }
        
        // 提取关键信息
        $paymentId = $callbackData['out_trade_no'] ?? $callbackData['payment_id'] ?? '';
        $transactionId = $callbackData['trade_no'] ?? $callbackData['transaction_id'] ?? '';
        $paidAmount = (float) ($callbackData['total_amount'] ?? $callbackData['total_fee'] / 100 ?? 0);
        $currency = $callbackData['currency'] ?? 'CNY';
        
        if (empty($paymentId)) {
            return [
                'success' => false,
                'message' => '缺少支付 ID'
            ];
        }
        
        // 2. 防重放检查
        if (!$this->checkPaymentUniqueness($paymentId, $transactionId)) {
            return [
                'success' => false,
                'message' => '重复的支付请求',
                'payment_id' => $paymentId
            ];
        }
        
        // 3. 金额校验
        if (!$this->verifyPaymentAmount($paymentId, $paidAmount, $currency)) {
            return [
                'success' => false,
                'message' => '金额校验失败',
                'payment_id' => $paymentId
            ];
        }
        
        // 记录验证成功
        $this->logPaymentAttempt($paymentId, 'callback_validation', $callbackData, true);
        
        return [
            'success' => true,
            'message' => '验证通过',
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId
        ];
    }
    
    /**
     * 标记支付为已处理
     */
    public function markPaymentProcessed(string $paymentId, string $transactionId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE payments 
            SET status = 'success', 
                transaction_id = ?, 
                paid_at = NOW()
            WHERE payment_id = ? AND status = 'pending'
        ");
        
        return $stmt->execute([$transactionId, $paymentId]);
    }
}
