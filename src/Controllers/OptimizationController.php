<?php
/**
 * CodeVault 性能与优化 API 控制器
 */

namespace Controllers;

use Services\PerformanceOptimizationService;
use Services\CodeHighlightService;
use Services\ImageDiffService;
use Core\Logger;

class OptimizationController
{
    private $performanceService;
    private $highlightService;
    private $imageDiffService;
    private $logger;
    
    public function __construct(
        PerformanceOptimizationService $performanceService,
        CodeHighlightService $highlightService,
        ImageDiffService $imageDiffService
    ) {
        $this->performanceService = $performanceService;
        $this->highlightService = $highlightService;
        $this->imageDiffService = $imageDiffService;
        $this->logger = new Logger('optimization');
    }
    
    /**
     * 获取性能报告
     * GET /api/optimization/performance
     */
    public function getPerformanceReport(): void
    {
        try {
            $report = $this->performanceService->getPerformanceReport();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取性能报告失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 分析慢查询
     * GET /api/optimization/slow-queries
     */
    public function analyzeSlowQueries(): void
    {
        try {
            $limit = (int)($_GET['limit'] ?? 100);
            $queries = $this->performanceService->analyzeSlowQueries($limit);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $queries,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('分析慢查询失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取索引建议
     * GET /api/optimization/index-suggestions/{table}
     */
    public function getIndexSuggestions(string $table): void
    {
        try {
            $suggestions = $this->performanceService->getIndexSuggestions($table);
            
            $this->jsonResponse([
                'success' => true,
                'table' => $table,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取索引建议失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 优化表
     * POST /api/optimization/optimize-table/{table}
     */
    public function optimizeTable(string $table): void
    {
        try {
            $result = $this->performanceService->optimizeTable($table);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('优化表失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 缓存预热
     * POST /api/optimization/cache/warmup
     */
    public function cacheWarmup(): void
    {
        try {
            $stats = $this->performanceService->cacheWarmup();
            
            $this->jsonResponse([
                'success' => true,
                'message' => '缓存预热完成',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('缓存预热失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 清理缓存
     * POST /api/optimization/cache/cleanup
     */
    public function cacheCleanup(): void
    {
        try {
            $cleaned = $this->performanceService->cacheCleanup();
            
            $this->jsonResponse([
                'success' => true,
                'message' => "已清理 {$cleaned} 个过期缓存",
                'cleaned' => $cleaned,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('清理缓存失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取缓存统计
     * GET /api/optimization/cache/stats
     */
    public function getCacheStats(): void
    {
        try {
            $stats = $this->performanceService->getCacheStats();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取缓存统计失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取支持的语言列表
     * GET /api/optimization/highlight/languages
     */
    public function getSupportedLanguages(): void
    {
        try {
            $languages = $this->highlightService->getSupportedLanguages();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $languages,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取主题列表
     * GET /api/optimization/highlight/themes
     */
    public function getThemes(): void
    {
        try {
            $themes = $this->highlightService->getThemes();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $themes,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 高亮代码
     * POST /api/optimization/highlight
     */
    public function highlightCode(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $code = $data['code'] ?? '';
            $language = $data['language'] ?? 'plaintext';
            $theme = $data['theme'] ?? 'light';
            $showLineNumbers = $data['show_line_numbers'] ?? true;
            
            if ($showLineNumbers) {
                $highlighted = $this->highlightService->highlightWithLineNumbers($code, $language, $theme);
            } else {
                $highlighted = $this->highlightService->highlight($code, $language, $theme);
            }
            
            $this->jsonResponse([
                'success' => true,
                'highlighted' => $highlighted,
                'language' => $language,
                'theme' => $theme,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('代码高亮失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 检测语言
     * POST /api/optimization/detect-language
     */
    public function detectLanguage(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $filename = $data['filename'] ?? '';
            
            $language = $this->highlightService->detectLanguage($filename);
            
            $this->jsonResponse([
                'success' => true,
                'filename' => $filename,
                'language' => $language,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 语法错误检测
     * POST /api/optimization/syntax-check
     */
    public function checkSyntax(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $code = $data['code'] ?? '';
            $language = $data['language'] ?? 'plaintext';
            
            $errors = $this->highlightService->detectSyntaxErrors($code, $language);
            
            $this->jsonResponse([
                'success' => true,
                'errors' => $errors,
                'has_errors' => !empty($errors),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('语法检测失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 检测文件类型
     * POST /api/optimization/detect-file-type
     */
    public function detectFileType(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $filename = $data['filename'] ?? '';
            
            $type = $this->imageDiffService->detectFileType($filename);
            
            $this->jsonResponse([
                'success' => true,
                'filename' => $filename,
                'type' => $type,
                'is_image' => $type === 'image',
                'is_binary' => $type === 'binary',
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 图片对比
     * GET /api/optimization/diff/images
     */
    public function compareImages(): void
    {
        try {
            $image1 = $_GET['image1'] ?? '';
            $image2 = $_GET['image2'] ?? '';
            $mode = $_GET['mode'] ?? 'side-by-side';
            
            if (empty($image1) || empty($image2)) {
                $this->jsonResponse(['success' => false, 'error' => '缺少图片路径'], 400);
                return;
            }
            
            $result = $this->imageDiffService->compareImages($image1, $image2, $mode);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('图片对比失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 二进制文件对比
     * GET /api/optimization/diff/binary
     */
    public function compareBinary(): void
    {
        try {
            $file1 = $_GET['file1'] ?? '';
            $file2 = $_GET['file2'] ?? '';
            
            if (empty($file1) || empty($file2)) {
                $this->jsonResponse(['success' => false, 'error' => '缺少文件路径'], 400);
                return;
            }
            
            $result = $this->imageDiffService->compareBinaryFiles($file1, $file2);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('二进制文件对比失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 三向合并
     * POST /api/optimization/merge/three-way
     */
    public function threeWayMerge(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $baseFile = $data['base'] ?? '';
            $oursFile = $data['ours'] ?? '';
            $theirsFile = $data['theirs'] ?? '';
            $outputPath = $data['output'] ?? sys_get_temp_dir() . '/merge_' . uniqid();
            
            if (empty($baseFile) || empty($oursFile) || empty($theirsFile)) {
                $this->jsonResponse(['success' => false, 'error' => '缺少必要文件'], 400);
                return;
            }
            
            $result = $this->imageDiffService->threeWayMerge($baseFile, $oursFile, $theirsFile, $outputPath);
            
            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('三向合并失败', ['error' => $e->getMessage()]);
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 获取资源 URL
     * GET /api/optimization/asset-url
     */
    public function getAssetUrl(): void
    {
        try {
            $path = $_GET['path'] ?? '';
            
            if (empty($path)) {
                $this->jsonResponse(['success' => false, 'error' => '缺少资源路径'], 400);
                return;
            }
            
            $url = $this->performanceService->getAssetUrl($path);
            
            $this->jsonResponse([
                'success' => true,
                'path' => $path,
                'url' => $url,
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
