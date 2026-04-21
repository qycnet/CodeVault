<?php
/**
 * CodeVault 安全服务
 * 
 * 功能：
 * - 路径验证与白名单
 * - 文件访问控制
 * - 输入验证
 * - XSS 防护
 * - CSRF 防护
 */

namespace CodeVault\Services;

class SecurityService
{
    // 允许访问的目录白名单
    private const ALLOWED_DIRECTORIES = [
        '/var/www/codevault/uploads/',
        '/var/www/codevault/storage/',
        '/var/www/codevault/public/',
        '/var/git/repositories/',
    ];
    
    // 允许上传的文件类型
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
        'text/plain', 'text/markdown', 'text/csv',
        'application/json', 'application/xml',
        'application/zip', 'application/x-gzip',
    ];
    
    // 危险文件扩展名
    private const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'exe', 'bat', 'cmd', 'sh', 'bash',
        'js', 'jsp', 'asp', 'aspx',
        'pl', 'py', 'rb', 'cgi',
    ];
    
    // 最大文件大小（字节）
    private const MAX_FILE_SIZE = 10485760; // 10MB
    
    /**
     * 验证路径是否在允许的目录内
     * 防止路径遍历攻击
     * 
     * @param string $path 要验证的路径
     * @param array|null $allowedDirs 可选的自定义允许目录列表
     */
    public function validatePath(string $path, ?array $allowedDirs = null): bool
    {
        // 使用自定义目录或默认白名单
        $allowedDirectories = $allowedDirs ?? self::ALLOWED_DIRECTORIES;
        
        // 规范化路径
        $realPath = realpath($path);
        
        // 文件不存在时，检查父目录
        if ($realPath === false) {
            $parentDir = dirname($path);
            $realParent = realpath($parentDir);
            
            if ($realParent === false) {
                return false;
            }
            
            return $this->isPathAllowed($realParent, $allowedDirectories);
        }
        
        return $this->isPathAllowed($realPath, $allowedDirectories);
    }
    
    /**
     * 检查路径是否在白名单目录内
     * 
     * @param string $path 要检查的路径
     * @param array|null $allowedDirs 允许的目录列表
     */
    private function isPathAllowed(string $path, ?array $allowedDirs = null): bool
    {
        $allowedDirectories = $allowedDirs ?? self::ALLOWED_DIRECTORIES;
        
        foreach ($allowedDirectories as $allowedDir) {
            if (strpos($path, $allowedDir) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 安全地获取文件路径
     * 防止路径遍历攻击
     */
    public function getSecurePath(string $basePath, string $relativePath): ?string
    {
        // 移除危险字符
        $relativePath = $this->sanitizePath($relativePath);
        
        // 构建完整路径
        $fullPath = rtrim($basePath, '/') . '/' . ltrim($relativePath, '/');
        
        // 规范化路径
        $realPath = realpath($fullPath);
        
        // 验证路径
        if ($realPath === false || !$this->validatePath($realPath)) {
            return null;
        }
        
        return $realPath;
    }
    
    /**
     * 清理路径中的危险字符
     */
    public function sanitizePath(string $path): string
    {
        // 移除空字节
        $path = str_replace("\0", '', $path);
        
        // 移除路径遍历字符
        $path = str_replace(['../', '..\\'], '', $path);
        
        // 移除连续斜杠
        $path = preg_replace('#/+#', '/', $path);
        
        // 移除开头斜杠
        $path = ltrim($path, '/\\');
        
        return $path;
    }
    
    /**
     * 验证文件上传
     */
    public function validateUpload(array $file): array
    {
        $errors = [];
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // 检查文件大小
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = '文件大小超过限制（最大 10MB）';
        }
        
        // 检查 MIME 类型
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            $errors[] = "不允许的文件类型：{$mimeType}";
        }
        
        // 检查文件扩展名
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            $errors[] = "禁止上传 {$extension} 文件";
        }
        
        // 检查文件名是否包含危险字符
        if (!$this->isSafeFilename($file['name'])) {
            $errors[] = '文件名包含非法字符';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType,
            'extension' => $extension,
        ];
    }
    
    /**
     * 检查文件名是否安全
     */
    public function isSafeFilename(string $filename): bool
    {
        // 检查长度
        if (strlen($filename) > 255) {
            return false;
        }
        
        // 检查危险字符
        $dangerousChars = ['<', '>', ':', '"', '|', '?', '*', "\0", "\n", "\r"];
        foreach ($dangerousChars as $char) {
            if (strpos($filename, $char) !== false) {
                return false;
            }
        }
        
        // 检查保留文件名
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 
                     'LPT1', 'LPT2', 'LPT3', 'LPT4', 'NULL'];
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        if (in_array(strtoupper($nameWithoutExt), $reserved)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 生成安全的文件名
     */
    public function generateSafeFilename(string $originalName): string
    {
        // 获取扩展名
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // 生成唯一文件名
        $basename = bin2hex(random_bytes(16));
        
        return $basename . '.' . $extension;
    }
    
    /**
     * XSS 防护：转义 HTML
     */
    public function escapeHtml(string $content): string
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * XSS 防护：移除危险标签
     */
    public function sanitizeHtml(string $html): string
    {
        // 允许的标签
        $allowedTags = '<p><br><strong><em><u><a><img><ul><ol><li><blockquote><code><pre>';
        
        // 移除不允许的标签
        $html = strip_tags($html, $allowedTags);
        
        // 移除危险属性
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        
        return $html;
    }
    
    /**
     * 生成 CSRF Token
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 验证 CSRF Token
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 生成安全的随机字符串
     */
    public function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 安全的密码哈希
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * 验证密码
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * 安全的字符串比较（防止时序攻击）
     */
    public function secureCompare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
    
    /**
     * 速率限制检查
     */
    public function checkRateLimit(string $key, int $maxRequests = 100, int $windowSeconds = 3600): bool
    {
        $redis = $this->getRedis();
        if (!$redis) {
            return true; // Redis 不可用时跳过限制
        }
        
        $current = (int) $redis->get($key);
        
        if ($current >= $maxRequests) {
            return false; // 超过限制
        }
        
        if ($current === 0) {
            $redis->setex($key, $windowSeconds, 1);
        } else {
            $redis->incr($key);
        }
        
        return true;
    }
    
    /**
     * 获取速率限制剩余次数
     */
    public function getRateLimitRemaining(string $key, int $maxRequests = 100): int
    {
        $redis = $this->getRedis();
        if (!$redis) {
            return $maxRequests;
        }
        
        $current = (int) $redis->get($key);
        return max(0, $maxRequests - $current);
    }
    
    /**
     * 获取 Redis 连接
     */
    private function getRedis(): ?\Redis
    {
        static $redis = null;
        
        if ($redis === null) {
            try {
                $redis = new \Redis();
                $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
                $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
                $redis->connect($host, $port, 2);
                $redis->select((int) ($_ENV['REDIS_DB'] ?? 0));
            } catch (\Exception $e) {
                $redis = false;
            }
        }
        
        return $redis ?: null;
    }
    
    /**
     * 输入验证：整数
     */
    public function validateInt($value, ?int $min = null, ?int $max = null): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return null;
        }
        
        if ($min !== null && $int < $min) {
            return null;
        }
        
        if ($max !== null && $int > $max) {
            return null;
        }
        
        return $int;
    }
    
    /**
     * 输入验证：邮箱
     */
    public function validateEmail(string $email): ?string
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        return $email ?: null;
    }
    
    /**
     * 输入验证：URL
     */
    public function validateUrl(string $url): ?string
    {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        
        return $url ?: null;
    }
    
    /**
     * 输入验证：用户名
     */
    public function validateUsername(string $username): bool
    {
        // 只允许字母、数字、下划线、连字符
        return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username) === 1;
    }
    
    /**
     * 输入验证：仓库名
     */
    public function validateRepoName(string $name): bool
    {
        // 只允许字母、数字、下划线、连字符、点
        return preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $name) === 1;
    }
    
    /**
     * SQL 注入防护：转义标识符
     */
    public function escapeIdentifier(string $identifier): string
    {
        // 只允许字母、数字、下划线
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
        
        return $identifier;
    }
    
    /**
     * 命令注入防护：转义 shell 参数
     */
    public function escapeShellArg(string $arg): string
    {
        return escapeshellarg($arg);
    }
    
    /**
     * 获取上传错误消息
     */
    private function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过 php.ini 限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '写入文件失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止',
        ];
        
        return $messages[$error] ?? '未知上传错误';
    }
    
    /**
     * 安全头设置
     */
    public function setSecurityHeaders(): void
    {
        // 防止点击劫持
        header('X-Frame-Options: SAMEORIGIN');
        
        // 防止 MIME 类型嗅探
        header('X-Content-Type-Options: nosniff');
        
        // XSS 防护
        header('X-XSS-Protection: 1; mode=block');
        
        // 内容安全策略
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;");
        
        // 引用策略
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // 权限策略
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }
    
    /**
     * 检查 IP 是否在黑名单中
     */
    public function isIpBlacklisted(string $ip): bool
    {
        // 这里可以从数据库或配置文件读取黑名单
        $blacklist = [
            // 示例黑名单 IP
        ];
        
        return in_array($ip, $blacklist);
    }
    
    /**
     * 速率限制检查（使用 Redis）
     */
    public function checkRateLimit(string $key, int $maxRequests = 100, int $windowSeconds = 3600): bool
    {
        $cacheKey = "rate_limit:{$key}";
        
        try {
            // 优先使用 Redis
            $cache = \Core\Cache::getInstance();
            
            if ($cache->isConnected()) {
                // 使用 Redis 实现
                $current = $cache->increment($cacheKey);
                
                if ($current === 1) {
                    // 第一次请求，设置过期时间
                    $cache->expire($cacheKey, $windowSeconds);
                }
                
                return $current <= $maxRequests;
            }
        } catch (\Exception $e) {
            // Redis 不可用，回退到文件缓存
            error_log('Redis rate limit failed, falling back to file: ' . $e->getMessage());
        }
        
        // 回退：使用文件缓存
        return $this->checkRateLimitFile($key, $maxRequests, $windowSeconds);
    }
    
    /**
     * 速率限制检查（文件缓存回退方案）
     */
    private function checkRateLimitFile(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key);
        
        $data = ['count' => 0, 'reset_at' => time() + $windowSeconds];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }
        
        // 检查是否需要重置
        if (time() > $data['reset_at']) {
            $data = ['count' => 0, 'reset_at' => time() + $windowSeconds];
        }
        
        // 增加计数
        $data['count']++;
        
        // 保存
        file_put_contents($cacheFile, json_encode($data));
        
        return $data['count'] <= $maxRequests;
    }
}
