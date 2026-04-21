<?php
/**
 * CodeVault 图片/二进制文件对比服务
 * 
 * 功能：
 * - 图片对比（并排、滑动、叠加模式）
 * - 二进制文件对比
 * - 三向合并支持
 * - 文件类型检测
 */

namespace Services;

class ImageDiffService
{
    // 支持的图片格式
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
    
    // 支持的二进制格式
    private const BINARY_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'zip', 'rar', '7z', 'tar', 'gz',
        'mp3', 'mp4', 'avi', 'mov', 'wav',
        'exe', 'dll', 'so', 'dylib',
        'ttf', 'otf', 'woff', 'woff2',
    ];
    
    // 对比模式
    public const DIFF_MODE_SIDE_BY_SIDE = 'side-by-side';
    public const DIFF_MODE_SLIDER = 'slider';
    public const DIFF_MODE_OVERLAY = 'overlay';
    public const DIFF_MODE_DIFF = 'diff';
    
    /**
     * 检测文件类型
     */
    public function detectFileType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, self::IMAGE_EXTENSIONS)) {
            return 'image';
        }
        
        if (in_array($ext, self::BINARY_EXTENSIONS)) {
            return 'binary';
        }
        
        // 检查 MIME 类型
        if (file_exists($filename)) {
            $mime = mime_content_type($filename);
            if (strpos($mime, 'image/') === 0) {
                return 'image';
            }
            if (strpos($mime, 'application/') === 0 && strpos($mime, 'text/') === false) {
                return 'binary';
            }
        }
        
        return 'text';
    }
    
    /**
     * 检查是否为图片文件
     */
    public function isImageFile(string $filename): bool
    {
        return $this->detectFileType($filename) === 'image';
    }
    
    /**
     * 检查是否为二进制文件
     */
    public function isBinaryFile(string $filename): bool
    {
        return $this->detectFileType($filename) === 'binary';
    }
    
    /**
     * 图片对比
     */
    public function compareImages(string $image1Path, string $image2Path, string $mode = self::DIFF_MODE_SIDE_BY_SIDE): array
    {
        if (!file_exists($image1Path) || !file_exists($image2Path)) {
            return [
                'success' => false,
                'error' => '文件不存在',
            ];
        }
        
        // 获取图片信息
        $info1 = $this->getImageInfo($image1Path);
        $info2 = $this->getImageInfo($image2Path);
        
        if (!$info1 || !$info2) {
            return [
                'success' => false,
                'error' => '无法读取图片信息',
            ];
        }
        
        $result = [
            'success' => true,
            'mode' => $mode,
            'image1' => $info1,
            'image2' => $info2,
            'diff' => null,
        ];
        
        // 计算差异
        $result['diff'] = $this->calculateImageDiff($image1Path, $image2Path, $info1, $info2);
        
        // 根据模式生成对比视图
        switch ($mode) {
            case self::DIFF_MODE_SIDE_BY_SIDE:
                $result['view'] = $this->generateSideBySideView($info1, $info2);
                break;
            case self::DIFF_MODE_SLIDER:
                $result['view'] = $this->generateSliderView($info1, $info2);
                break;
            case self::DIFF_MODE_OVERLAY:
                $result['view'] = $this->generateOverlayView($info1, $info2);
                break;
            case self::DIFF_MODE_DIFF:
                $result['view'] = $this->generateDiffView($image1Path, $image2Path, $info1, $info2);
                break;
        }
        
        return $result;
    }
    
    /**
     * 获取图片信息
     */
    private function getImageInfo(string $path): ?array
    {
        $info = @getimagesize($path);
        
        if (!$info) {
            // SVG 特殊处理
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'svg') {
                $content = file_get_contents($path);
                preg_match('/width="([^"]+)"/', $content, $widthMatch);
                preg_match('/height="([^"]+)"/', $content, $heightMatch);
                
                return [
                    'path' => $path,
                    'width' => (int)($widthMatch[1] ?? 0),
                    'height' => (int)($heightMatch[1] ?? 0),
                    'mime' => 'image/svg+xml',
                    'type' => 'svg',
                    'size' => filesize($path),
                    'size_human' => $this->formatBytes(filesize($path)),
                ];
            }
            
            return null;
        }
        
        return [
            'path' => $path,
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'type' => image_type_to_extension($info[2], false),
            'size' => filesize($path),
            'size_human' => $this->formatBytes(filesize($path)),
            'bits' => $info['bits'] ?? 8,
            'channels' => $info['channels'] ?? 3,
        ];
    }
    
    /**
     * 计算图片差异
     */
    private function calculateImageDiff(string $path1, string $path2, array $info1, array $info2): array
    {
        $diff = [
            'size_changed' => $info1['size'] !== $info2['size'],
            'dimensions_changed' => ($info1['width'] !== $info2['width']) || ($info1['height'] !== $info2['height']),
            'format_changed' => $info1['type'] !== $info2['type'],
            'width_diff' => $info2['width'] - $info1['width'],
            'height_diff' => $info2['height'] - $info1['height'],
            'size_diff' => $info2['size'] - $info1['size'],
            'size_diff_percent' => $info1['size'] > 0 ? round((($info2['size'] - $info1['size']) / $info1['size']) * 100, 2) : 0,
        ];
        
        // 计算像素差异（如果尺寸相同）
        if (!$diff['dimensions_changed'] && $info1['type'] !== 'svg' && $info2['type'] !== 'svg') {
            $pixelDiff = $this->calculatePixelDiff($path1, $path2, $info1);
            $diff['pixel_diff'] = $pixelDiff;
            $diff['similarity'] = 100 - $pixelDiff['percent'];
        }
        
        return $diff;
    }
    
    /**
     * 计算像素差异
     */
    private function calculatePixelDiff(string $path1, string $path2, array $info): array
    {
        // 创建图片资源
        $img1 = $this->createImageResource($path1, $info['type']);
        $img2 = $this->createImageResource($path2, $info['type']);
        
        if (!$img1 || !$img2) {
            return ['total' => 0, 'different' => 0, 'percent' => 0];
        }
        
        $width = $info['width'];
        $height = $info['height'];
        $totalPixels = $width * $height;
        $differentPixels = 0;
        
        // 采样检测（每 10 个像素检测一次，提高性能）
        $sampleRate = 10;
        $sampledPixels = 0;
        
        for ($x = 0; $x < $width; $x += $sampleRate) {
            for ($y = 0; $y < $height; $y += $sampleRate) {
                $color1 = imagecolorat($img1, $x, $y);
                $color2 = imagecolorat($img2, $x, $y);
                
                if ($color1 !== $color2) {
                    $differentPixels++;
                }
                $sampledPixels++;
            }
        }
        
        imagedestroy($img1);
        imagedestroy($img2);
        
        $percent = $sampledPixels > 0 ? round(($differentPixels / $sampledPixels) * 100, 2) : 0;
        
        return [
            'total' => $totalPixels,
            'sampled' => $sampledPixels,
            'different' => $differentPixels,
            'percent' => $percent,
        ];
    }
    
    /**
     * 创建图片资源
     */
    private function createImageResource(string $path, string $type)
    {
        switch ($type) {
            case 'jpeg':
            case 'jpg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                return imagecreatefromwebp($path);
            case 'bmp':
                return imagecreatefrombmp($path);
            default:
                return null;
        }
    }
    
    /**
     * 生成并排视图
     */
    private function generateSideBySideView(array $info1, array $info2): array
    {
        return [
            'type' => 'side-by-side',
            'html' => '<div class="image-diff-side-by-side">
                <div class="image-panel">
                    <div class="image-label">Before</div>
                    <img src="' . $info1['path'] . '" alt="Before" />
                    <div class="image-info">' . $info1['width'] . 'x' . $info1['height'] . ' · ' . $info1['size_human'] . '</div>
                </div>
                <div class="image-panel">
                    <div class="image-label">After</div>
                    <img src="' . $info2['path'] . '" alt="After" />
                    <div class="image-info">' . $info2['width'] . 'x' . $info2['height'] . ' · ' . $info2['size_human'] . '</div>
                </div>
            </div>',
        ];
    }
    
    /**
     * 生成滑动视图
     */
    private function generateSliderView(array $info1, array $info2): array
    {
        return [
            'type' => 'slider',
            'html' => '<div class="image-diff-slider">
                <div class="slider-container">
                    <img class="slider-image before" src="' . $info1['path'] . '" alt="Before" />
                    <img class="slider-image after" src="' . $info2['path'] . '" alt="After" />
                    <div class="slider-handle"></div>
                </div>
                <div class="slider-labels">
                    <span class="before-label">Before</span>
                    <span class="after-label">After</span>
                </div>
            </div>',
        ];
    }
    
    /**
     * 生成叠加视图
     */
    private function generateOverlayView(array $info1, array $info2): array
    {
        return [
            'type' => 'overlay',
            'html' => '<div class="image-diff-overlay">
                <div class="overlay-container">
                    <img class="overlay-image before" src="' . $info1['path'] . '" alt="Before" />
                    <img class="overlay-image after" src="' . $info2['path'] . '" alt="After" style="opacity: 0.5" />
                </div>
                <div class="overlay-controls">
                    <label>透明度: <input type="range" min="0" max="100" value="50" class="opacity-slider" /></label>
                </div>
            </div>',
        ];
    }
    
    /**
     * 生成差异视图
     */
    private function generateDiffView(string $path1, string $path2, array $info1, array $info2): array
    {
        // 创建差异图片
        $diffPath = $this->createDiffImage($path1, $path2, $info1, $info2);
        
        return [
            'type' => 'diff',
            'html' => '<div class="image-diff-result">
                <div class="diff-container">
                    <img src="' . $diffPath . '" alt="Diff" />
                </div>
                <div class="diff-legend">
                    <span class="legend-item removed">红色: 删除区域</span>
                    <span class="legend-item added">绿色: 添加区域</span>
                    <span class="legend-item unchanged">灰色: 未变化</span>
                </div>
            </div>',
            'diff_path' => $diffPath,
        ];
    }
    
    /**
     * 创建差异图片
     */
    private function createDiffImage(string $path1, string $path2, array $info1, array $info2): string
    {
        $img1 = $this->createImageResource($path1, $info1['type']);
        $img2 = $this->createImageResource($path2, $info2['type']);
        
        if (!$img1 || !$img2) {
            return '';
        }
        
        // 使用较大的尺寸
        $width = max($info1['width'], $info2['width']);
        $height = max($info1['height'], $info2['height']);
        
        // 创建差异图片
        $diffImg = imagecreatetruecolor($width, $height);
        
        // 定义颜色
        $red = imagecolorallocate($diffImg, 255, 0, 0);      // 删除
        $green = imagecolorallocate($diffImg, 0, 255, 0);    // 添加
        $gray = imagecolorallocate($diffImg, 128, 128, 128); // 未变化
        
        // 填充背景
        imagefill($diffImg, 0, 0, $gray);
        
        // 比较像素
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color1 = ($x < $info1['width'] && $y < $info1['height']) ? imagecolorat($img1, $x, $y) : -1;
                $color2 = ($x < $info2['width'] && $y < $info2['height']) ? imagecolorat($img2, $x, $y) : -1;
                
                if ($color1 === -1 && $color2 !== -1) {
                    // 新增像素
                    imagesetpixel($diffImg, $x, $y, $green);
                } elseif ($color1 !== -1 && $color2 === -1) {
                    // 删除像素
                    imagesetpixel($diffImg, $x, $y, $red);
                } elseif ($color1 !== $color2) {
                    // 变化像素
                    imagesetpixel($diffImg, $x, $y, $green);
                }
            }
        }
        
        // 保存差异图片
        $diffPath = sys_get_temp_dir() . '/diff_' . uniqid() . '.png';
        imagepng($diffImg, $diffPath);
        
        imagedestroy($img1);
        imagedestroy($img2);
        imagedestroy($diffImg);
        
        return $diffPath;
    }
    
    /**
     * 二进制文件对比
     */
    public function compareBinaryFiles(string $file1Path, string $file2Path): array
    {
        if (!file_exists($file1Path) || !file_exists($file2Path)) {
            return [
                'success' => false,
                'error' => '文件不存在',
            ];
        }
        
        $info1 = $this->getBinaryFileInfo($file1Path);
        $info2 = $this->getBinaryFileInfo($file2Path);
        
        $result = [
            'success' => true,
            'file1' => $info1,
            'file2' => $info2,
            'diff' => [
                'size_diff' => $info2['size'] - $info1['size'],
                'size_diff_percent' => $info1['size'] > 0 ? round((($info2['size'] - $info1['size']) / $info1['size']) * 100, 2) : 0,
                'type_changed' => $info1['mime'] !== $info2['mime'],
                'identical' => $this->filesAreIdentical($file1Path, $file2Path),
            ],
        ];
        
        // 计算哈希差异
        if (!$result['diff']['identical']) {
            $result['diff']['hash_diff'] = [
                'md5_1' => $info1['md5'],
                'md5_2' => $info2['md5'],
                'sha256_1' => $info1['sha256'],
                'sha256_2' => $info2['sha256'],
            ];
        }
        
        // 十六进制对比
        $result['hex_diff'] = $this->generateHexDiff($file1Path, $file2Path);
        
        return $result;
    }
    
    /**
     * 获取二进制文件信息
     */
    private function getBinaryFileInfo(string $path): array
    {
        return [
            'path' => $path,
            'name' => basename($path),
            'size' => filesize($path),
            'size_human' => $this->formatBytes(filesize($path)),
            'mime' => mime_content_type($path),
            'md5' => md5_file($path),
            'sha256' => hash_file('sha256', $path),
            'modified' => filemtime($path),
        ];
    }
    
    /**
     * 检查文件是否相同
     */
    private function filesAreIdentical(string $path1, string $path2): bool
    {
        if (filesize($path1) !== filesize($path2)) {
            return false;
        }
        
        return md5_file($path1) === md5_file($path2);
    }
    
    /**
     * 生成十六进制对比
     */
    private function generateHexDiff(string $path1, string $path2): array
    {
        $content1 = file_get_contents($path1);
        $content2 = file_get_contents($path2);
        
        $hex1 = bin2hex(substr($content1, 0, 1024)); // 只对比前 1KB
        $hex2 = bin2hex(substr($content2, 0, 1024));
        
        $lines1 = str_split($hex1, 32); // 每行 16 字节
        $lines2 = str_split($hex2, 32);
        
        $diff = [];
        $maxLines = max(count($lines1), count($lines2));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $line1 = $lines1[$i] ?? '';
            $line2 = $lines2[$i] ?? '';
            
            if ($line1 !== $line2) {
                $offset = $i * 16;
                $diff[] = [
                    'offset' => sprintf('0x%04X', $offset),
                    'before' => strtoupper(chunk_split($line1, 2, ' ')),
                    'after' => strtoupper(chunk_split($line2, 2, ' ')),
                ];
            }
        }
        
        return [
            'lines' => $diff,
            'truncated' => max(strlen($content1), strlen($content2)) > 1024,
        ];
    }
    
    /**
     * 三向合并
     */
    public function threeWayMerge(string $baseFile, string $oursFile, string $theirsFile, string $outputPath): array
    {
        $result = [
            'success' => false,
            'conflicts' => [],
            'merged_path' => $outputPath,
        ];
        
        // 检测文件类型
        $type = $this->detectFileType($baseFile);
        
        if ($type === 'image') {
            // 图片文件无法自动合并
            $result['error'] = '图片文件无法自动合并，需要手动选择版本';
            $result['options'] = [
                'ours' => $oursFile,
                'theirs' => $theirsFile,
                'base' => $baseFile,
            ];
            return $result;
        }
        
        if ($type === 'binary') {
            // 二进制文件无法自动合并
            $result['error'] = '二进制文件无法自动合并，需要手动选择版本';
            $result['options'] = [
                'ours' => $oursFile,
                'theirs' => $theirsFile,
                'base' => $baseFile,
            ];
            return $result;
        }
        
        // 文本文件三向合并
        $baseContent = file_get_contents($baseFile);
        $oursContent = file_get_contents($oursFile);
        $theirsContent = file_get_contents($theirsFile);
        
        $baseLines = explode("\n", $baseContent);
        $oursLines = explode("\n", $oursContent);
        $theirsLines = explode("\n", $theirsContent);
        
        // 简单的三向合并算法
        $mergedLines = [];
        $conflicts = [];
        
        $maxLines = max(count($baseLines), count($oursLines), count($theirsLines));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $baseLine = $baseLines[$i] ?? '';
            $oursLine = $oursLines[$i] ?? '';
            $theirsLine = $theirsLines[$i] ?? '';
            
            if ($oursLine === $theirsLine) {
                // 两边相同，直接使用
                $mergedLines[] = $oursLine;
            } elseif ($oursLine === $baseLine) {
                // 我们没改，使用他们的
                $mergedLines[] = $theirsLine;
            } elseif ($theirsLine === $baseLine) {
                // 他们没改，使用我们的
                $mergedLines[] = $oursLine;
            } else {
                // 冲突
                $conflicts[] = $i + 1;
                $mergedLines[] = '<<<<<<< OURS';
                $mergedLines[] = $oursLine;
                $mergedLines[] = '=======';
                $mergedLines[] = $theirsLine;
                $mergedLines[] = '>>>>>>> THEIRS';
            }
        }
        
        // 写入合并结果
        file_put_contents($outputPath, implode("\n", $mergedLines));
        
        $result['success'] = true;
        $result['conflicts'] = $conflicts;
        $result['has_conflicts'] = !empty($conflicts);
        
        return $result;
    }
    
    /**
     * 格式化字节
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
