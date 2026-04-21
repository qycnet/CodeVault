<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentSecurityService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * 支付回调控制器
 * 
 * 处理支付平台回调，包含完整的安全验证
 */
class PaymentCallbackController
{
    private PaymentSecurityService $securityService;
    
    public function __construct(PaymentSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }
    
    /**
     * 支付宝回调
     */
    public function alipay(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        
        // 完整的安全验证
        $result = $this->securityService->validatePaymentCallback('alipay', $data);
        
        if (!$result['success']) {
            // 记录失败日志
            $this->securityService->logPaymentAttempt(
                $result['payment_id'] ?? 'unknown',
                'alipay_callback',
                $data,
                false
            );
            
            // 支付宝要求返回 failure
            $response->getBody()->write('failure');
            return $response->withStatus(400);
        }
        
        // 标记支付成功
        $this->securityService->markPaymentProcessed(
            $result['payment_id'],
            $result['transaction_id']
        );
        
        // 支付宝要求返回 success
        $response->getBody()->write('success');
        return $response;
    }
    
    /**
     * 微信支付回调
     */
    public function wechat(Request $request, Response $response): Response
    {
        $xml = $request->getBody()->getContents();
        $data = $this->parseXml($xml);
        
        // 完整的安全验证
        $result = $this->securityService->validatePaymentCallback('wechat', $data);
        
        $response = $response->withHeader('Content-Type', 'application/xml');
        
        if (!$result['success']) {
            // 记录失败日志
            $this->securityService->logPaymentAttempt(
                $result['payment_id'] ?? 'unknown',
                'wechat_callback',
                $data,
                false
            );
            
            $response->getBody()->write($this->buildXmlResponse('FAIL', $result['message']));
            return $response->withStatus(400);
        }
        
        // 标记支付成功
        $this->securityService->markPaymentProcessed(
            $result['payment_id'],
            $result['transaction_id']
        );
        
        $response->getBody()->write($this->buildXmlResponse('SUCCESS', 'OK'));
        return $response;
    }
    
    /**
     * Stripe 回调
     */
    public function stripe(Request $request, Response $response): Response
    {
        $payload = $request->getBody()->getContents();
        $sigHeader = $request->getHeaderLine('Stripe-Signature');
        
        $data = json_decode($payload, true);
        
        // Stripe 签名验证需要特殊处理
        $result = $this->securityService->validatePaymentCallback('stripe', [
            'data' => $data,
            'signature' => $sigHeader
        ]);
        
        $response = $response->withHeader('Content-Type', 'application/json');
        
        if (!$result['success']) {
            $this->securityService->logPaymentAttempt(
                $result['payment_id'] ?? 'unknown',
                'stripe_callback',
                ['payload' => $payload],
                false
            );
            
            $response->getBody()->write(json_encode(['error' => $result['message']]));
            return $response->withStatus(400);
        }
        
        // 标记支付成功
        $this->securityService->markPaymentProcessed(
            $result['payment_id'],
            $result['transaction_id']
        );
        
        $response->getBody()->write(json_encode(['status' => 'success']));
        return $response;
    }
    
    /**
     * 解析 XML
     */
    private function parseXml(string $xml): array
    {
        libxml_disable_entity_loader(true);
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($data), true);
    }
    
    /**
     * 构建 XML 响应
     */
    private function buildXmlResponse(string $code, string $message): string
    {
        return "<xml><return_code><![CDATA[{$code}]]></return_code><return_msg><![CDATA[{$message}]]></return_msg></xml>";
    }
}
