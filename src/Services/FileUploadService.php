<?php
/**
 * CodeVault - 文件上传服务
 * 安全的文件上传处理
 */

namespace CodeVault\Services;

use CodeVault\Core\SecurityHelper;

class FileUploadService
{
    // 允许的图片类型
    private const IMAGE_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
    ];
    
    // 允许的文档类型
    private const DOCUMENT_TYPES = [
        'application/pdf' => ['pdf'],
        'text/plain' => ['txt', 'md'],
        'text/markdown' => ['md'],
        'application/json' => ['json'],
    ];
    
    // 允许的压缩文件类型
    private const ARCHIVE_TYPES = [
        'application/zip' => ['zip'],
        'application/x-gzip' => ['gz', 'gzip'],
        'application/x-tar' => ['tar'],
    ];
    
    // 危险扩展名（禁止上传）
    private const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'phps',
        'exe', 'bat', 'cmd', 'sh', 'bash', 'ps1',
        'js', 'jsp', 'asp', 'aspx', 'asa',
        'pl', 'py', 'rb', 'cgi', 'dll', 'so',
        'htaccess', 'htpasswd',
    ];
    
    // 最大文件大小（10MB）
    private const MAX_FILE_SIZE = 10485760;
    
    /**
     * 验证并上传文件
     */
    public function upload(array $file, string $destination, string $type = 'image'): array
    {
        // 验证上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error('文件上传失败: ' . $this->getUploadErrorMessage($file['error']));
        }
        
        // 验证文件大小
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return $this->error('文件大小超过限制（最大 10MB）');
        }
        
        // 验证 MIME 类型
        $mimeType = $this->getMimeType($file['tmp_name']);
        $allowedTypes = $this->getAllowedTypes($type);
        
        if (!in_array($mimeType, array_keys($allowedTypes), true)) {
            return $this->error('不允许的文件类型: ' . $mimeType);
        }
        
        // 验证扩展名
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            return $this->error('禁止上传此类型的文件');
        }
        
        $allowedExtensions = [];
        foreach ($allowedTypes as $exts) {
            $allowedExtensions = array_merge($allowedExtensions, $exts);
        }
        
        if (!in_array($extension, $allowedExtensions, true)) {
            return $this->error('文件扩展名与类型不匹配');
        }
        
        // 验证文件内容（防止伪造）
        $contentValidation = $this->validateFileContent($file['tmp_name'], $mimeType);
        if (!$contentValidation['valid']) {
            return $this->error($contentValidation['error']);
        }
        
        // 生成安全文件名
        $safeFilename = $this->generateSafeFilename($extension);
        $targetPath = rtrim($destination, '/') . '/' . $safeFilename;
        
        // 确保目标目录存在
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $this->error('文件保存失败');
        }
        
        // 设置文件权限（禁止执行）
        chmod($targetPath, 0644);
        
        return [
            'success' => true,
            'filename' => $safeFilename,
            'original_name' => $file['name'],
            'path' => $targetPath,
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'extension' => $extension,
        ];
    }
    
    /**
     * 获取文件真实 MIME 类型
     */
    private function getMimeType(string $filepath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filepath);
    }
    
    /**
     * 根据类型获取允许的 MIME 类型
     */
    private function getAllowedTypes(string $type): array
    {
        return match ($type) {
            'image' => self::IMAGE_TYPES,
            'document' => self::DOCUMENT_TYPES,
            'archive' => self::ARCHIVE_TYPES,
            'all' => array_merge(self::IMAGE_TYPES, self::DOCUMENT_TYPES, self::ARCHIVE_TYPES),
            default => self::IMAGE_TYPES,
        };
    }
    
    /**
     * 验证文件内容（检查魔数）
     */
    private function validateFileContent(string $filepath, string $mimeType): array
    {
        // 读取文件头
        $handle = fopen($filepath, 'rb');
        $header = fread($handle, 64);
        fclose($handle);
        
        // 检查图片魔数
        if (str_starts_with($mimeType, 'image/')) {
            $signatures = [
                'image/jpeg' => ["\xFF\xD8\xFF"],
                'image/png' => ["\x89\x50\x4E\x47"],
                'image/gif' => ["GIF87a", "GIF89a"],
                'image/webp' => ["RIFF"],
            ];
            
            if (isset($signatures[$mimeType])) {
                foreach ($signatures[$mimeType] as $sig) {
                    if (str_starts_with($header, $sig)) {
                        return ['valid' => true];
                    }
                }
                return ['valid' => false, 'error' => '文件内容与类型不匹配'];
            }
        }
        
        // 检查是否包含 PHP 代码（防止图片马）
        if (preg_match('/<\?php|<\?=|<script\s+language\s*=\s*["\']php/i', file_get_contents($filepath))) {
            return ['valid' => false, 'error' => '文件包含可疑代码'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 生成安全文件名
     */
    private function generateSafeFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
    
    /**
     * 获取上传错误信息
     */
    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件上传不完整',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止',
            default => '未知错误',
        };
    }
    
    /**
     * 返回错误结果
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
    
    /**
     * 删除文件
     */
    public function delete(string $filepath): bool
    {
        // 验证路径安全
        $realPath = realpath($filepath);
        if ($realPath === false) {
            return false;
        }
        
        // 确保在允许的目录内
        $allowedDirs = ['/var/www/codevault/uploads/', '/var/www/codevault/storage/'];
        $isAllowed = false;
        foreach ($allowedDirs as $dir) {
            if (str_starts_with($realPath, $dir)) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            return false;
        }
        
        return unlink($realPath);
    }
}
