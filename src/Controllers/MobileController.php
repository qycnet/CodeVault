<?php
/**
 * CodeVault 移动端 API 控制器
 */

namespace CodeVault\Controllers;

use Services\MobileAdaptationService;
use Core\Logger;

class MobileController
{
    private $mobileService;
    private $logger;
    
    public function __construct(MobileAdaptationService $mobileService)
    {
        $this->mobileService = $mobileService;
        $this->logger = new Logger('mobile');
    }
    
    /**
     * 检测设备信息
     * GET /api/mobile/detect
     */
    public function detectDevice(): void
    {
        try {
            $userAgent = $_GET['user_agent'] ?? null;
            $device = $this->mobileService->detectDevice($userAgent);
            
            $this->jsonResponse([
                'success' => true,
                'device' => $device,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('设备检测失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取断点配置
     * GET /api/mobile/breakpoints
     */
    public function getBreakpoints(): void
    {
        try {
            $breakpoints = $this->mobileService->getBreakpoints();
            
            $this->jsonResponse([
                'success' => true,
                'breakpoints' => $breakpoints,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取当前断点
     * GET /api/mobile/breakpoint
     */
    public function getCurrentBreakpoint(): void
    {
        try {
            $width = (int)($_GET['width'] ?? 1920);
            $breakpoint = $this->mobileService->getCurrentBreakpoint($width);
            
            $this->jsonResponse([
                'success' => true,
                'width' => $width,
                'breakpoint' => $breakpoint,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取响应式 CSS
     * GET /api/mobile/css
     */
    public function getResponsiveCSS(): void
    {
        try {
            $css = $this->mobileService->generateResponsiveCSS();
            
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo $css;
        } catch (\Exception $e) {
            $this->logger->error('获取 CSS 失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取触摸手势 JS
     * GET /api/mobile/touch-js
     */
    public function getTouchGesturesJS(): void
    {
        try {
            $js = $this->mobileService->generateTouchGesturesJS();
            
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo $js;
        } catch (\Exception $e) {
            $this->logger->error('获取 JS 失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取移动端头部标签
     * GET /api/mobile/head
     */
    public function getMobileHead(): void
    {
        try {
            $head = $this->mobileService->generateMobileHead();
            
            $this->jsonResponse([
                'success' => true,
                'head' => $head,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取移动端优化配置
     * GET /api/mobile/optimizations
     */
    public function getOptimizations(): void
    {
        try {
            $optimizations = $this->mobileService->getMobileOptimizations();
            
            $this->jsonResponse([
                'success' => true,
                'optimizations' => $optimizations,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 是否为移动设备
     * GET /api/mobile/is-mobile
     */
    public function isMobile(): void
    {
        try {
            $userAgent = $_GET['user_agent'] ?? null;
            $isMobile = $this->mobileService->isMobile($userAgent);
            
            $this->jsonResponse([
                'success' => true,
                'is_mobile' => $isMobile,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 是否为平板设备
     * GET /api/mobile/is-tablet
     */
    public function isTablet(): void
    {
        try {
            $userAgent = $_GET['user_agent'] ?? null;
            $isTablet = $this->mobileService->isTablet($userAgent);
            
            $this->jsonResponse([
                'success' => true,
                'is_tablet' => $isTablet,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 是否为触摸设备
     * GET /api/mobile/is-touch
     */
    public function isTouchDevice(): void
    {
        try {
            $userAgent = $_GET['user_agent'] ?? null;
            $isTouch = $this->mobileService->isTouchDevice($userAgent);
            
            $this->jsonResponse([
                'success' => true,
                'is_touch' => $isTouch,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * JSON 响应
     */
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
