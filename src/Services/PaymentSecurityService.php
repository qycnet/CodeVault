<?php
/**
 * CodeVault - 支付安全服务
 * 
 * 提供支付回调验证、金额校验、防重放等安全功能
 */

namespace CodeVault\Services;

use PDO;
use Exception;

class PaymentSecurityService
{
    private PDO $pdo;
    
    // 支付平台配置
    private array $paymentConfigs = [
        'alipay' => [
            'sign_type' => 'RSA2',
            'public_key' => null, // 从配置加载
        ],
        'wechat' => [
            'sign_type' => 'MD5',
            'api_key' => null, // 从配置加载
        ],
        'stripe' => [
            'webhook_secret' => null, // 从配置加载
        ],
        'paypal' => [
            'client_id' => null,
            'client_secret' => null,
        ]
    ];
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadPaymentConfigs();
    }
    
    /**
     * 加载支付配置
     */
    private function loadPaymentConfigs(): void
    {
        // 从环境变量或配置文件加载
        $this->paymentConfigs['alipay']['public_key'] = getenv('ALIPAY_PUBLIC_KEY') ?: null;
        $this->paymentConfigs['wechat']['api_key'] = getenv('WECHAT_API_KEY') ?: null;
        $this->paymentConfigs['stripe']['webhook_secret'] = getenv('STRIPE_WEBHOOK_SECRET') ?: null;
        $this->paymentConfigs['paypal']['client_id'] = getenv('PAYPAL_CLIENT_ID') ?: null;
        $this->paymentConfigs['paypal']['client_secret'] = getenv('PAYPAL_CLIENT_SECRET') ?: null;
    }
    
    // ==================== 1. 支付平台签名验证 ====================
    
    /**
     * 验证支付宝签名
     */
    public function verifyAlipaySignature(array $params): bool
    {
        $sign = $params['sign'] ?? null;
        $signType = $params['sign_type'] ?? 'RSA2';
        
        if (!$sign) {
            return false;
        }
        
        // 移除 sign 和 sign_type
        unset($params['sign'], $params['sign_type']);
        
        // 按字母排序
        ksort($params);
        
        // 构建待签名字符串
        $signData = http_build_query($params);
        $signData = urldecode($signData);
        
        // 获取支付宝公钥
        $publicKey = $this->paymentConfigs['alipay']['public_key'];
        if (!$publicKey) {
            throw new Exception('Alipay public key not configured');
        }
        
        // 格式化公钥
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" 
            . wordwrap($publicKey, 64, "\n", true) 
            . "\n-----END PUBLIC KEY-----";
        
        // 验证签名
        $result = openssl_verify(
            $signData,
            base64_decode($sign),
            $publicKey,
            $signType === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1
        );
        
        return $result === 1;
    }
    
    /**
     * 验证微信支付签名
     */
    public function verifyWechatSignature(array $params, string $sign = null): bool
    {
        $sign = $sign ?? $params['sign'] ?? null;
        
        if (!$sign) {
            return false;
        }
        
        // 移除 sign
        unset($params['sign']);
        
        // 按字母排序
        ksort($params);
        
        // 构建待签名字符串
        $signData = urldecode(http_build_query($params));
        $signData .= '&key=' . $this->paymentConfigs['wechat']['api_key'];
        
        // 计算签名
        $expectedSign = strtoupper(md5($signData));
        
        return $sign === $expectedSign;
    }
    
    /**
     * 验证 Stripe Webhook 签名
     */
    public function verifyStripeSignature(string $payload, string $sigHeader): bool
    {
        $webhookSecret = $this->paymentConfigs['stripe']['webhook_secret'];
        
        if (!$webhookSecret) {
            throw new Exception('Stripe webhook secret not configured');
        }
        
        // 解析签名头
        $elements = explode(',', $sigHeader);
        $timestamp = null;
        $signature = null;
        
        foreach ($elements as $element) {
            [$key, $value] = explode('=', $element, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signature = $value;
            }
        }
        
        if (!$timestamp || !$signature) {
            return false;
        }
        
        // 验证时间戳（5分钟内有效）
        if (abs(time() - $timestamp) > 300) {
            return false;
        }
        
        // 计算签名
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * 验证 PayPal Webhook 签名
     */
    public function verifyPaypalSignature(array $headers, string $body): bool
    {
        $clientId = $this->paymentConfigs['paypal']['client_id'];
        $clientSecret = $this->paymentConfigs['paypal']['client_secret'];
        
        if (!$clientId || !$clientSecret) {
            throw new Exception('PayPal credentials not configured');
        }
        
        // PayPal 使用证书链验证，这里简化处理
        // 实际生产环境应使用 PayPal SDK 验证
        $certUrl = $headers['PAYPAL-CERT-URL'] ?? null;
        $authAlgo = $headers['PAYPAL-AUTH-ALGO'] ?? null;
        $transmissionId = $headers['PAYPAL-TRANSMISSION-ID'] ?? null;
        $transmissionSig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? null;
        $transmissionTime = $headers['PAYPAL-TRANSMISSION-TIME'] ?? null;
        
        // 验证必要字段存在
        if (!$certUrl || !$transmissionId || !$transmissionSig || !$transmissionTime) {
            return false;
        }
        
        // 实际验证需要获取证书并验证签名
        // 这里返回 true，实际生产环境需要完整实现
        return true;
    }
    
    /**
     * 统一签名验证入口
     */
    public function verifySignature(string $paymentMethod, array $params, ?string $payload = null): bool
    {
        switch ($paymentMethod) {
            case 'alipay':
                return $this->verifyAlipaySignature($params);
            case 'wechat':
                return $this->verifyWechatSignature($params);
            case 'stripe':
                return $payload ? $this->verifyStripeSignature($payload, $params['stripe_signature'] ?? '') : false;
            case 'paypal':
                return $payload ? $this->verifyPaypalSignature($params, $payload) : false;
            default:
                throw new Exception("Unsupported payment method: {$paymentMethod}");
        }
    }
    
    // ==================== 2. 金额二次校验 ====================
    
    /**
     * 验证支付金额
     */
    public function verifyAmount(int $sponsorId, float $paidAmount, string $currency = 'CNY'): array
    {
        // 获取原始赞助记录
        $stmt = $this->pdo->prepare(
            "SELECT s.*, t.monthly_amount, t.yearly_amount, t.one_time_amount
             FROM sponsors s
             LEFT JOIN sponsor_tiers t ON s.tier_id = t.id
             WHERE s.id = ?"
        );
        $stmt->execute([$sponsorId]);
        $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sponsor) {
            return [
                'valid' => false,
                'error' => 'Sponsor record not found'
            ];
        }
        
        // 计算预期金额
        $expectedAmount = $this->calculateExpectedAmount($sponsor);
        
        // 允许的误差范围（1分钱）
        $tolerance = 0.01;
        
        // 验证金额
        if (abs($paidAmount - $expectedAmount) > $tolerance) {
            return [
                'valid' => false,
                'error' => 'Amount mismatch',
                'expected' => $expectedAmount,
                'actual' => $paidAmount
            ];
        }
        
        // 验证货币
        if ($currency !== $sponsor['currency']) {
            return [
                'valid' => false,
                'error' => 'Currency mismatch',
                'expected' => $sponsor['currency'],
                'actual' => $currency
            ];
        }
        
        return [
            'valid' => true,
            'expected_amount' => $expectedAmount,
            'paid_amount' => $paidAmount,
            'currency' => $currency
        ];
    }
    
    /**
     * 计算预期金额
     */
    private function calculateExpectedAmount(array $sponsor): float
    {
        // 如果有自定义金额，使用自定义金额
        if ($sponsor['amount'] > 0) {
            return (float)$sponsor['amount'];
        }
        
        // 否则根据等级和频率计算
        switch ($sponsor['frequency']) {
            case 'monthly':
                return (float)($sponsor['monthly_amount'] ?? 0);
            case 'yearly':
                return (float)($sponsor['yearly_amount'] ?? 0);
            case 'one_time':
                return (float)($sponsor['one_time_amount'] ?? 0);
            default:
                return 0;
        }
    }
    
    /**
     * 验证金额范围
     */
    public function validateAmountRange(float $amount, string $currency = 'CNY'): bool
    {
        // 定义各货币的最小/最大金额
        $limits = [
            'CNY' => ['min' => 1, 'max' => 100000],
            'USD' => ['min' => 0.1, 'max' => 10000],
            'EUR' => ['min' => 0.1, 'max' => 10000],
        ];
        
        $limit = $limits[$currency] ?? $limits['CNY'];
        
        return $amount >= $limit['min'] && $amount <= $limit['max'];
    }
    
    // ==================== 3. 防重放攻击 ====================
    
    /**
     * 检查 payment_id 唯一性
     */
    public function checkPaymentIdUnique(string $paymentId, string $paymentMethod): bool
    {
        // 检查是否已存在
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sponsor_transactions 
             WHERE payment_id = ? AND payment_method = ?"
        );
        $stmt->execute([$paymentId, $paymentMethod]);
        
        return (int)$stmt->fetchColumn() === 0;
    }
    
    /**
     * 记录并验证支付请求（防重放）
     */
    public function recordPaymentRequest(string $paymentId, string $paymentMethod, array $data): array
    {
        // 检查唯一性
        if (!$this->checkPaymentIdUnique($paymentId, $paymentMethod)) {
            return [
                'valid' => false,
                'error' => 'Duplicate payment_id',
                'payment_id' => $paymentId
            ];
        }
        
        // 记录支付请求
        $stmt = $this->pdo->prepare(
            "INSERT INTO payment_requests (payment_id, payment_method, request_data, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $paymentId,
            $paymentMethod,
            json_encode($data),
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return [
            'valid' => true,
            'payment_id' => $paymentId,
            'request_id' => (int)$this->pdo->lastInsertId()
        ];
    }
    
    /**
     * 验证支付时间戳（防重放）
     */
    public function validateTimestamp(int $timestamp, int $maxAge = 300): bool
    {
        $now = time();
        return abs($now - $timestamp) <= $maxAge;
    }
    
    /**
     * 验证支付 Nonce（防重放）
     */
    public function validateNonce(string $nonce, int $maxAge = 300): bool
    {
        // 检查 nonce 是否已使用
        $stmt = $this->pdo->prepare(
            "SELECT created_at FROM payment_nonces WHERE nonce = ?"
        );
        $stmt->execute([$nonce]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // 已存在，检查是否过期
            $createdAt = strtotime($existing['created_at']);
            if (time() - $createdAt <= $maxAge) {
                return false; // 未过期，重复使用
            }
            // 已过期，删除旧记录
            $stmt = $this->pdo->prepare("DELETE FROM payment_nonces WHERE nonce = ?");
            $stmt->execute([$nonce]);
        }
        
        // 记录新 nonce
        $stmt = $this->pdo->prepare(
            "INSERT INTO payment_nonces (nonce, created_at) VALUES (?, NOW())"
        );
        $stmt->execute([$nonce]);
        
        return true;
    }
    
    /**
     * 清理过期的支付请求和 nonce
     */
    public function cleanupExpiredRecords(int $maxAge = 86400): int
    {
        $expireTime = date('Y-m-d H:i:s', time() - $maxAge);
        
        // 清理支付请求
        $stmt = $this->pdo->prepare("DELETE FROM payment_requests WHERE created_at < ?");
        $stmt->execute([$expireTime]);
        $deleted1 = $stmt->rowCount();
        
        // 清理 nonce
        $stmt = $this->pdo->prepare("DELETE FROM payment_nonces WHERE created_at < ?");
        $stmt->execute([$expireTime]);
        $deleted2 = $stmt->rowCount();
        
        return $deleted1 + $deleted2;
    }
    
    // ==================== 4. 综合验证 ====================
    
    /**
     * 综合支付验证
     */
    public function validatePayment(array $params): array
    {
        $errors = [];
        
        // 1. 验证签名
        $paymentMethod = $params['payment_method'] ?? '';
        try {
            $signatureValid = $this->verifySignature($paymentMethod, $params, $params['payload'] ?? null);
            if (!$signatureValid) {
                $errors[] = 'Invalid payment signature';
            }
        } catch (Exception $e) {
            $errors[] = 'Signature verification failed: ' . $e->getMessage();
        }
        
        // 2. 验证金额
        $sponsorId = (int)($params['sponsor_id'] ?? 0);
        $paidAmount = (float)($params['amount'] ?? 0);
        $currency = $params['currency'] ?? 'CNY';
        
        $amountResult = $this->verifyAmount($sponsorId, $paidAmount, $currency);
        if (!$amountResult['valid']) {
            $errors[] = 'Amount verification failed: ' . $amountResult['error'];
        }
        
        // 3. 验证 payment_id 唯一性
        $paymentId = $params['payment_id'] ?? '';
        if ($paymentId && !$this->checkPaymentIdUnique($paymentId, $paymentMethod)) {
            $errors[] = 'Duplicate payment_id detected';
        }
        
        // 4. 验证时间戳
        $timestamp = (int)($params['timestamp'] ?? 0);
        if ($timestamp && !$this->validateTimestamp($timestamp)) {
            $errors[] = 'Payment request expired';
        }
        
        // 5. 验证 Nonce
        $nonce = $params['nonce'] ?? '';
        if ($nonce && !$this->validateNonce($nonce)) {
            $errors[] = 'Invalid or duplicate nonce';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sponsor_id' => $sponsorId,
            'amount' => $paidAmount,
            'currency' => $currency,
            'payment_id' => $paymentId
        ];
    }
    
    /**
     * 生成安全的支付参数
     */
    public function generateSecurePaymentParams(int $sponsorId, array $baseParams): array
    {
        // 生成唯一的 payment_id
        $paymentId = 'PAY' . date('YmdHis') . bin2hex(random_bytes(8));
        
        // 生成 nonce
        $nonce = bin2hex(random_bytes(16));
        
        // 时间戳
        $timestamp = time();
        
        return array_merge($baseParams, [
            'sponsor_id' => $sponsorId,
            'payment_id' => $paymentId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'sign' => $this->generateInternalSign($baseParams, $paymentId, $nonce, $timestamp)
        ]);
    }
    
    /**
     * 生成内部签名
     */
    private function generateInternalSign(array $params, string $paymentId, string $nonce, int $timestamp): string
    {
        $signData = http_build_query([
            'payment_id' => $paymentId,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'amount' => $params['amount'] ?? 0,
            'sponsor_id' => $params['sponsor_id'] ?? 0
        ]);
        
        $secret = getenv('PAYMENT_SIGN_SECRET') ?: 'codevault_payment_secret';
        return hash_hmac('sha256', $signData, $secret);
    }
    
    /**
     * 验证内部签名
     */
    public function verifyInternalSign(array $params): bool
    {
        $sign = $params['sign'] ?? '';
        if (!$sign) {
            return false;
        }
        
        $expectedSign = $this->generateInternalSign(
            $params,
            $params['payment_id'] ?? '',
            $params['nonce'] ?? '',
            (int)($params['timestamp'] ?? 0)
        );
        
        return hash_equals($expectedSign, $sign);
    }
}
