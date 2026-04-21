<?php
/**
 * CodeVault - 安全辅助类
 * 提供输入验证和输出转义功能
 */

namespace CodeVault\Core;

class SecurityHelper
{
    /**
     * HTML 转义（防止 XSS）
     */
    public static function htmlEscape(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * 批量 HTML 转义
     */
    public static function htmlEscapeArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::htmlEscapeArray($value);
            } elseif (is_string($value)) {
                $result[$key] = self::htmlEscape($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    
    /**
     * JavaScript 字符串转义
     */
    public static function jsEscape(string $input): string
    {
        return json_encode($input, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
    
    /**
     * URL 安全验证
     */
    public static function sanitizeUrl(string $url): string
    {
        // 仅允许 http/https 协议
        if (preg_match('/^https?:\/\//i', $url)) {
            return filter_var($url, FILTER_SANITIZE_URL) ?: '';
        }
        return '';
    }
    
    /**
     * 文件名安全过滤
     */
    public static function sanitizeFilename(string $filename): string
    {
        // 移除路径遍历字符
        $filename = basename($filename);
        // 仅保留安全字符
        return preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $filename);
    }
    
    /**
     * 路径安全验证（防止路径遍历）
     */
    public static function sanitizePath(string $path, string $basePath): string
    {
        $realPath = realpath($path);
        $realBasePath = realpath($basePath);
        
        if ($realPath === false || $realBasePath === false) {
            return '';
        }
        
        if (!str_starts_with($realPath, $realBasePath)) {
            return '';
        }
        
        return $realPath;
    }
    
    /**
     * 白名单验证
     */
    public static function whitelist(string $input, array $allowed): string
    {
        return in_array($input, $allowed, true) ? $input : '';
    }
    
    /**
     * 整数验证
     */
    public static function validateInt($input, int $min = null, int $max = null): int
    {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return 0;
        }
        
        if ($min !== null && $value < $min) {
            return $min;
        }
        
        if ($max !== null && $value > $max) {
            return $max;
        }
        
        return $value;
    }
    
    /**
     * 邮箱验证
     */
    public static function validateEmail(string $email): string
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        return $email ?: '';
    }
    
    /**
     * 生成安全随机令牌
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * 常量时间字符串比较（防止时序攻击）
     */
    public static function timingSafeEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
    
    /**
     * 密码强度验证
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = '密码长度至少 8 位';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = '密码必须包含小写字母';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = '密码必须包含大写字母';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = '密码必须包含数字';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * 验证文件上传
     */
    public static function validateFileUpload(array $file, array $allowedTypes, int $maxSize = 10485760): array
    {
        $errors = [];
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '文件上传失败';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // 检查文件大小
        if ($file['size'] > $maxSize) {
            $errors[] = '文件大小超过限制';
        }
        
        // 使用 finfo 验证 MIME 类型
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes, true)) {
            $errors[] = '不允许的文件类型: ' . $mimeType;
        }
        
        // 检查文件扩展名
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = [];
        foreach ($allowedTypes as $type) {
            $allowedExtensions = array_merge($allowedExtensions, self::getExtensionsForMimeType($type));
        }
        
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = '不允许的文件扩展名';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType,
            'extension' => $extension,
        ];
    }
    
    /**
     * MIME 类型对应的扩展名
     */
    private static function getExtensionsForMimeType(string $mimeType): array
    {
        $map = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt', 'md'],
            'text/markdown' => ['md'],
            'application/json' => ['json'],
            'application/zip' => ['zip'],
        ];
        
        return $map[$mimeType] ?? [];
    }
    
    /**
     * CSP 头生成
     */
    public static function generateCSPHeader(array $directives = []): string
    {
        $default = [
            "default-src" => "'self'",
            "script-src" => "'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src" => "'self' 'unsafe-inline'",
            "img-src" => "'self' data: https:",
            "font-src" => "'self' data:",
            "connect-src" => "'self'",
            "frame-ancestors" => "'self'",
            "base-uri" => "'self'",
            "form-action" => "'self'",
        ];
        
        $directives = array_merge($default, $directives);
        
        $parts = [];
        foreach ($directives as $name => $value) {
            $parts[] = "$name $value";
        }
        
        return implode('; ', $parts);
    }
    
    /**
     * 设置安全响应头
     */
    public static function setSecurityHeaders(): void
    {
        // 防止点击劫持
        header('X-Frame-Options: SAMEORIGIN');
        
        // 防止 MIME 类型嗅探
        header('X-Content-Type-Options: nosniff');
        
        // XSS 保护
        header('X-XSS-Protection: 1; mode=block');
        
        // 引用策略
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // 权限策略
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        
        // CSP（可选，根据需要启用）
        // header('Content-Security-Policy: ' . self::generateCSPHeader());
    }
}
