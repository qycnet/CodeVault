<?php
/**
 * CodeVault 移动端适配服务
 * 
 * 功能：
 * - 设备检测与适配
 * - 响应式布局管理
 * - 触摸手势支持
 * - 移动端性能优化
 */

namespace CodeVault\Services;

class MobileAdaptationService
{
    // 断点配置
    private const BREAKPOINTS = [
        'xs' => 0,      // < 576px
        'sm' => 576,    // >= 576px
        'md' => 768,    // >= 768px
        'lg' => 992,    // >= 992px
        'xl' => 1200,   // >= 1200px
        'xxl' => 1400,  // >= 1400px
    ];
    
    // 移动设备 User-Agent 特征
    private const MOBILE_PATTERNS = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'Windows Phone',
        'BlackBerry', 'Opera Mini', 'IEMobile', 'webOS', 'SymbianOS',
    ];
    
    // 平板设备特征
    private const TABLET_PATTERNS = [
        'iPad', 'Android', 'Tablet', 'Kindle', 'Silk',
    ];
    
    // 触摸设备特征
    private const TOUCH_PATTERNS = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'Windows Phone',
        'BlackBerry', 'Opera Mini', 'IEMobile',
    ];
    
    /**
     * 检测设备类型
     */
    public function detectDevice(string $userAgent = null): array
    {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $isMobile = $this->isMobile($ua);
        $isTablet = $this->isTablet($ua);
        $isTouch = $this->isTouchDevice($ua);
        
        // 确定设备类型
        $deviceType = 'desktop';
        if ($isTablet) {
            $deviceType = 'tablet';
        } elseif ($isMobile) {
            $deviceType = 'mobile';
        }
        
        // 检测操作系统
        $os = $this->detectOS($ua);
        
        // 检测浏览器
        $browser = $this->detectBrowser($ua);
        
        // 检测屏幕尺寸（基于 UA 猜测）
        $screenSize = $this->guessScreenSize($ua, $deviceType);
        
        return [
            'type' => $deviceType,
            'is_mobile' => $isMobile,
            'is_tablet' => $isTablet,
            'is_touch' => $isTouch,
            'os' => $os,
            'browser' => $browser,
            'screen' => $screenSize,
            'user_agent' => $ua,
        ];
    }
    
    /**
     * 是否为移动设备
     */
    public function isMobile(string $userAgent = null): bool
    {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        foreach (self::MOBILE_PATTERNS as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 是否为平板设备
     */
    public function isTablet(string $userAgent = null): bool
    {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // iPad 检测
        if (stripos($ua, 'iPad') !== false) {
            return true;
        }
        
        // Android 平板检测（不包含 Mobile）
        if (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false) {
            return true;
        }
        
        // 其他平板特征
        foreach (self::TABLET_PATTERNS as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 是否为触摸设备
     */
    public function isTouchDevice(string $userAgent = null): bool
    {
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        foreach (self::TOUCH_PATTERNS as $pattern) {
            if (stripos($ua, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检测操作系统
     */
    private function detectOS(string $userAgent): array
    {
        $patterns = [
            'iOS' => '/iPhone|iPad|iPod/',
            'Android' => '/Android/',
            'Windows' => '/Windows NT|Windows Phone/',
            'macOS' => '/Macintosh|Mac OS X/',
            'Linux' => '/Linux/',
            'Chrome OS' => '/CrOS/',
        ];
        
        foreach ($patterns as $os => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'name' => $os,
                    'version' => $this->extractOSVersion($userAgent, $os),
                ];
            }
        }
        
        return ['name' => 'Unknown', 'version' => null];
    }
    
    /**
     * 提取操作系统版本
     */
    private function extractOSVersion(string $userAgent, string $os): ?string
    {
        $patterns = [
            'iOS' => '/OS (\d+[._]\d+[._]?\d*)/',
            'Android' => '/Android (\d+\.?\d*\.?\d*)/',
            'Windows' => '/Windows NT (\d+\.?\d*)/',
            'macOS' => '/Mac OS X (\d+[._]\d+[._]?\d*)/',
        ];
        
        if (isset($patterns[$os]) && preg_match($patterns[$os], $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }
        
        return null;
    }
    
    /**
     * 检测浏览器
     */
    private function detectBrowser(string $userAgent): array
    {
        $patterns = [
            'Edge' => '/Edg\/(\d+\.?\d*)/',
            'Opera' => '/OPR\/(\d+\.?\d*)/',
            'Chrome' => '/Chrome\/(\d+\.?\d*)/',
            'Firefox' => '/Firefox\/(\d+\.?\d*)/',
            'Safari' => '/Version\/(\d+\.?\d*).*Safari/',
            'IE' => '/MSIE (\d+\.?\d*)|Trident.*rv:(\d+\.?\d*)/',
        ];
        
        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return [
                    'name' => $browser,
                    'version' => $matches[1] ?? $matches[2] ?? null,
                ];
            }
        }
        
        return ['name' => 'Unknown', 'version' => null];
    }
    
    /**
     * 猜测屏幕尺寸
     */
    private function guessScreenSize(string $userAgent, string $deviceType): array
    {
        // 基于设备类型猜测
        $defaults = [
            'mobile' => ['width' => 375, 'height' => 667, 'breakpoint' => 'xs'],
            'tablet' => ['width' => 768, 'height' => 1024, 'breakpoint' => 'md'],
            'desktop' => ['width' => 1920, 'height' => 1080, 'breakpoint' => 'xl'],
        ];
        
        return $defaults[$deviceType] ?? $defaults['desktop'];
    }
    
    /**
     * 获取当前断点
     */
    public function getCurrentBreakpoint(int $width): string
    {
        foreach (array_reverse(self::BREAKPOINTS, true) as $name => $minWidth) {
            if ($width >= $minWidth) {
                return $name;
            }
        }
        
        return 'xs';
    }
    
    /**
     * 获取所有断点配置
     */
    public function getBreakpoints(): array
    {
        return self::BREAKPOINTS;
    }
    
    /**
     * 生成响应式 CSS
     */
    public function generateResponsiveCSS(): string
    {
        return <<<'CSS'
/* CodeVault 移动端适配样式 */

/* 基础变量 */
:root {
  --mobile-header-height: 56px;
  --mobile-tab-height: 48px;
  --mobile-bottom-nav-height: 56px;
  --mobile-safe-area-bottom: env(safe-area-inset-bottom, 0px);
  --mobile-safe-area-top: env(safe-area-inset-top, 0px);
}

/* 容器 */
.container {
  width: 100%;
  padding-right: 15px;
  padding-left: 15px;
  margin-right: auto;
  margin-left: auto;
}

@media (min-width: 576px) {
  .container { max-width: 540px; }
}

@media (min-width: 768px) {
  .container { max-width: 720px; }
}

@media (min-width: 992px) {
  .container { max-width: 960px; }
}

@media (min-width: 1200px) {
  .container { max-width: 1140px; }
}

@media (min-width: 1400px) {
  .container { max-width: 1320px; }
}

/* 响应式隐藏 */
.hide-mobile { display: block; }
.show-mobile { display: none; }

@media (max-width: 767px) {
  .hide-mobile { display: none !important; }
  .show-mobile { display: block !important; }
}

.hide-tablet { display: block; }
.show-tablet { display: none; }

@media (min-width: 768px) and (max-width: 991px) {
  .hide-tablet { display: none !important; }
  .show-tablet { display: block !important; }
}

/* 移动端头部 */
.mobile-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: var(--mobile-header-height);
  background: var(--bg-color, #fff);
  border-bottom: 1px solid var(--border-color, #e1e4e8);
  z-index: 1000;
  display: none;
  align-items: center;
  padding: 0 16px;
  padding-top: var(--mobile-safe-area-top);
}

@media (max-width: 767px) {
  .mobile-header {
    display: flex;
  }
  
  body {
    padding-top: calc(var(--mobile-header-height) + var(--mobile-safe-area-top));
  }
}

.mobile-header-title {
  flex: 1;
  font-size: 18px;
  font-weight: 600;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 移动端底部导航 */
.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--mobile-bottom-nav-height);
  background: var(--bg-color, #fff);
  border-top: 1px solid var(--border-color, #e1e4e8);
  z-index: 1000;
  display: none;
  padding-bottom: var(--mobile-safe-area-bottom);
}

@media (max-width: 767px) {
  .mobile-bottom-nav {
    display: flex;
  }
  
  body {
    padding-bottom: calc(var(--mobile-bottom-nav-height) + var(--mobile-safe-area-bottom));
  }
}

.mobile-nav-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 8px 0;
  color: var(--text-secondary, #586069);
  text-decoration: none;
  font-size: 12px;
}

.mobile-nav-item.active {
  color: var(--primary-color, #0366d6);
}

.mobile-nav-item i {
  font-size: 20px;
  margin-bottom: 4px;
}

/* 移动端标签栏 */
.mobile-tabs {
  display: none;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  background: var(--bg-color, #fff);
  border-bottom: 1px solid var(--border-color, #e1e4e8);
  scrollbar-width: none;
}

.mobile-tabs::-webkit-scrollbar {
  display: none;
}

@media (max-width: 767px) {
  .mobile-tabs {
    display: flex;
  }
}

.mobile-tab {
  flex-shrink: 0;
  padding: 12px 16px;
  font-size: 14px;
  color: var(--text-secondary, #586069);
  border-bottom: 2px solid transparent;
  white-space: nowrap;
}

.mobile-tab.active {
  color: var(--primary-color, #0366d6);
  border-bottom-color: var(--primary-color, #0366d6);
}

/* 移动端卡片 */
.mobile-card {
  background: var(--bg-color, #fff);
  border-radius: 8px;
  margin: 8px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

@media (min-width: 768px) {
  .mobile-card {
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    border: 1px solid var(--border-color, #e1e4e8);
  }
}

/* 移动端列表 */
.mobile-list {
  background: var(--bg-color, #fff);
}

.mobile-list-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-color, #e1e4e8);
}

.mobile-list-item:last-child {
  border-bottom: none;
}

.mobile-list-item:active {
  background: var(--bg-secondary, #f6f8fa);
}

/* 移动端按钮 */
.mobile-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 24px;
  font-size: 16px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  min-height: 48px;
}

.mobile-btn-primary {
  background: var(--primary-color, #0366d6);
  color: white;
}

.mobile-btn-secondary {
  background: var(--bg-secondary, #f6f8fa);
  color: var(--text-color, #24292e);
}

/* 移动端输入框 */
.mobile-input {
  width: 100%;
  padding: 12px 16px;
  font-size: 16px;
  border: 1px solid var(--border-color, #e1e4e8);
  border-radius: 8px;
  background: var(--bg-color, #fff);
  min-height: 48px;
}

.mobile-input:focus {
  outline: none;
  border-color: var(--primary-color, #0366d6);
  box-shadow: 0 0 0 3px rgba(3, 102, 214, 0.1);
}

/* 移动端搜索栏 */
.mobile-search {
  display: flex;
  align-items: center;
  background: var(--bg-secondary, #f6f8fa);
  border-radius: 8px;
  padding: 8px 12px;
  margin: 8px 16px;
}

.mobile-search input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 16px;
  outline: none;
}

.mobile-search i {
  color: var(--text-secondary, #586069);
  margin-right: 8px;
}

/* 触摸反馈 */
.touchable {
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.touchable:active {
  opacity: 0.7;
}

/* 滑动手势区域 */
.swipe-area {
  touch-action: pan-y;
  -webkit-overflow-scrolling: touch;
}

/* 下拉刷新 */
.pull-refresh {
  position: relative;
  overflow: hidden;
}

.pull-refresh-indicator {
  position: absolute;
  top: -50px;
  left: 0;
  right: 0;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary, #586069);
}

/* 骨架屏 */
.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s infinite;
}

@keyframes skeleton-loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton-text {
  height: 16px;
  border-radius: 4px;
  margin-bottom: 8px;
}

.skeleton-text:last-child {
  width: 60%;
}

.skeleton-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.skeleton-image {
  width: 100%;
  height: 200px;
  border-radius: 8px;
}

/* 移动端代码块 */
@media (max-width: 767px) {
  pre, code {
    font-size: 14px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  pre {
    padding: 12px;
    border-radius: 8px;
  }
}

/* 移动端表格 */
@media (max-width: 767px) {
  .table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  table {
    min-width: 600px;
  }
}

/* 移动端模态框 */
.mobile-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  display: flex;
  align-items: flex-end;
}

.mobile-modal-content {
  width: 100%;
  max-height: 90vh;
  background: var(--bg-color, #fff);
  border-radius: 16px 16px 0 0;
  padding: 16px;
  padding-bottom: calc(16px + var(--mobile-safe-area-bottom));
  overflow-y: auto;
}

.mobile-modal-handle {
  width: 36px;
  height: 4px;
  background: var(--border-color, #e1e4e8);
  border-radius: 2px;
  margin: 0 auto 16px;
}

/* 移动端抽屉 */
.mobile-drawer {
  position: fixed;
  top: 0;
  left: -280px;
  width: 280px;
  height: 100%;
  background: var(--bg-color, #fff);
  z-index: 2000;
  transition: transform 0.3s ease;
}

.mobile-drawer.open {
  transform: translateX(280px);
}

.mobile-drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1999;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
}

.mobile-drawer-overlay.open {
  opacity: 1;
  pointer-events: auto;
}

/* 暗色主题 */
@media (prefers-color-scheme: dark) {
  :root {
    --bg-color: #0d1117;
    --bg-secondary: #161b22;
    --text-color: #c9d1d9;
    --text-secondary: #8b949e;
    --border-color: #30363d;
    --primary-color: #58a6ff;
  }
}

/* 打印样式 */
@media print {
  .mobile-header,
  .mobile-bottom-nav,
  .mobile-tabs {
    display: none !important;
  }
  
  body {
    padding: 0 !important;
  }
}
CSS;
    }
    
    /**
     * 生成触摸手势 JavaScript
     */
    public function generateTouchGesturesJS(): string
    {
        return <<<'JS'
/**
 * CodeVault 触摸手势支持
 */
class TouchGestures {
  constructor(element, options = {}) {
    this.element = element;
    this.options = {
      swipeThreshold: 50,
      swipeTimeout: 300,
      tapThreshold: 10,
      tapTimeout: 250,
      ...options
    };
    
    this.startX = 0;
    this.startY = 0;
    this.startTime = 0;
    this.isSwiping = false;
    
    this.bindEvents();
  }
  
  bindEvents() {
    this.element.addEventListener('touchstart', this.onTouchStart.bind(this), { passive: true });
    this.element.addEventListener('touchmove', this.onTouchMove.bind(this), { passive: false });
    this.element.addEventListener('touchend', this.onTouchEnd.bind(this), { passive: true });
  }
  
  onTouchStart(e) {
    const touch = e.touches[0];
    this.startX = touch.clientX;
    this.startY = touch.clientY;
    this.startTime = Date.now();
    this.isSwiping = true;
    
    this.options.onTouchStart?.({
      x: this.startX,
      y: this.startY,
      event: e
    });
  }
  
  onTouchMove(e) {
    if (!this.isSwiping) return;
    
    const touch = e.touches[0];
    const deltaX = touch.clientX - this.startX;
    const deltaY = touch.clientY - this.startY;
    
    // 检测是否为横向滑动
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      e.preventDefault();
    }
    
    this.options.onTouchMove?.({
      deltaX,
      deltaY,
      event: e
    });
  }
  
  onTouchEnd(e) {
    if (!this.isSwiping) return;
    this.isSwiping = false;
    
    const touch = e.changedTouches[0];
    const endX = touch.clientX;
    const endY = touch.clientY;
    const deltaX = endX - this.startX;
    const deltaY = endY - this.startY;
    const deltaTime = Date.now() - this.startTime;
    
    // 检测手势类型
    const gesture = this.detectGesture(deltaX, deltaY, deltaTime);
    
    this.options.onTouchEnd?.({
      gesture,
      deltaX,
      deltaY,
      deltaTime,
      event: e
    });
    
    // 触发回调
    if (gesture.type === 'swipe') {
      this.options.onSwipe?.(gesture);
    } else if (gesture.type === 'tap') {
      this.options.onTap?.(gesture);
    }
  }
  
  detectGesture(deltaX, deltaY, deltaTime) {
    const absX = Math.abs(deltaX);
    const absY = Math.abs(deltaY);
    
    // 检测滑动
    if ((absX > this.options.swipeThreshold || absY > this.options.swipeThreshold) &&
        deltaTime < this.options.swipeTimeout) {
      const direction = absX > absY
        ? (deltaX > 0 ? 'right' : 'left')
        : (deltaY > 0 ? 'down' : 'up');
      
      return { type: 'swipe', direction, distance: Math.max(absX, absY) };
    }
    
    // 检测点击
    if (absX < this.options.tapThreshold && absY < this.options.tapThreshold &&
        deltaTime < this.options.tapTimeout) {
      return { type: 'tap', x: this.startX, y: this.startY };
    }
    
    return { type: 'none' };
  }
}

/**
 * 下拉刷新
 */
class PullToRefresh {
  constructor(container, options = {}) {
    this.container = container;
    this.options = {
      threshold: 80,
      onRefresh: () => {},
      ...options
    };
    
    this.startY = 0;
    this.currentY = 0;
    this.isPulling = false;
    this.isRefreshing = false;
    
    this.createIndicator();
    this.bindEvents();
  }
  
  createIndicator() {
    this.indicator = document.createElement('div');
    this.indicator.className = 'pull-refresh-indicator';
    this.indicator.innerHTML = '<span class="refresh-icon">↓</span>';
    this.container.insertBefore(this.indicator, this.container.firstChild);
  }
  
  bindEvents() {
    this.container.addEventListener('touchstart', this.onTouchStart.bind(this), { passive: true });
    this.container.addEventListener('touchmove', this.onTouchMove.bind(this), { passive: false });
    this.container.addEventListener('touchend', this.onTouchEnd.bind(this), { passive: true });
  }
  
  onTouchStart(e) {
    if (this.container.scrollTop === 0 && !this.isRefreshing) {
      this.startY = e.touches[0].clientY;
      this.isPulling = true;
    }
  }
  
  onTouchMove(e) {
    if (!this.isPulling || this.isRefreshing) return;
    
    this.currentY = e.touches[0].clientY;
    const deltaY = this.currentY - this.startY;
    
    if (deltaY > 0) {
      e.preventDefault();
      const distance = Math.min(deltaY * 0.5, this.options.threshold * 1.5);
      this.indicator.style.transform = `translateY(${distance}px)`;
      this.indicator.querySelector('.refresh-icon').textContent = 
        distance >= this.options.threshold ? '↻' : '↓';
    }
  }
  
  onTouchEnd() {
    if (!this.isPulling) return;
    this.isPulling = false;
    
    const deltaY = this.currentY - this.startY;
    
    if (deltaY >= this.options.threshold && !this.isRefreshing) {
      this.refresh();
    } else {
      this.reset();
    }
  }
  
  async refresh() {
    this.isRefreshing = true;
    this.indicator.querySelector('.refresh-icon').textContent = '↻';
    this.indicator.querySelector('.refresh-icon').classList.add('spinning');
    
    try {
      await this.options.onRefresh();
    } finally {
      this.reset();
    }
  }
  
  reset() {
    this.isRefreshing = false;
    this.indicator.style.transform = '';
    this.indicator.querySelector('.refresh-icon').classList.remove('spinning');
  }
}

/**
 * 无限滚动
 */
class InfiniteScroll {
  constructor(container, options = {}) {
    this.container = container;
    this.options = {
      threshold: 100,
      onLoadMore: () => {},
      ...options
    };
    
    this.isLoading = false;
    this.hasMore = true;
    
    this.bindEvents();
  }
  
  bindEvents() {
    this.container.addEventListener('scroll', this.onScroll.bind(this), { passive: true });
  }
  
  onScroll() {
    if (this.isLoading || !this.hasMore) return;
    
    const { scrollTop, scrollHeight, clientHeight } = this.container;
    
    if (scrollHeight - scrollTop - clientHeight < this.options.threshold) {
      this.loadMore();
    }
  }
  
  async loadMore() {
    this.isLoading = true;
    
    try {
      const hasMore = await this.options.onLoadMore();
      this.hasMore = hasMore !== false;
    } finally {
      this.isLoading = false;
    }
  }
  
  reset() {
    this.hasMore = true;
    this.isLoading = false;
  }
}

// 导出
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { TouchGestures, PullToRefresh, InfiniteScroll };
}
JS;
    }
    
    /**
     * 生成移动端适配 HTML 头部
     */
    public function generateMobileHead(): string
    {
        return <<<'HTML'
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#0366d6">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
HTML;
    }
    
    /**
     * 获取移动端优化配置
     */
    public function getMobileOptimizations(): array
    {
        return [
            'viewport' => [
                'width' => 'device-width',
                'initial_scale' => 1.0,
                'maximum_scale' => 1.0,
                'user_scalable' => false,
            ],
            'performance' => [
                'lazy_load_images' => true,
                'lazy_load_components' => true,
                'prefetch_links' => true,
                'compress_assets' => true,
            ],
            'ux' => [
                'pull_to_refresh' => true,
                'infinite_scroll' => true,
                'skeleton_screens' => true,
                'touch_feedback' => true,
            ],
            'offline' => [
                'service_worker' => true,
                'cache_strategy' => 'stale-while-revalidate',
            ],
        ];
    }
}
