<?php
/**
 * CodeVault - 配置加载器
 * 支持 Docker Secrets 和环境变量
 */

namespace CodeVault\Core;

class Config
{
    /**
     * 获取配置值（支持 Docker Secrets）
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // 首先检查是否有 _FILE 后缀的环境变量
        $fileKey = $key . '_FILE';
        $filePath = $_ENV[$fileKey] ?? getenv($fileKey);
        
        if ($filePath && file_exists($filePath)) {
            $value = file_get_contents($filePath);
            return trim($value);
        }
        
        // 然后检查普通环境变量
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * 获取必需的配置值
     * 
     * @param string $key 配置键名
     * @return mixed
     * @throws \RuntimeException
     */
    public static function getRequired(string $key): mixed
    {
        $value = self::get($key);
        
        if ($value === null) {
            throw new \RuntimeException("Required configuration '{$key}' is missing");
        }
        
        return $value;
    }
    
    /**
     * 获取布尔值配置
     * 
     * @param string $key 配置键名
     * @param bool $default 默认值
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
    }
    
    /**
     * 获取整数配置
     * 
     * @param string $key 配置键名
     * @param int $default 默认值
     * @return int
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
    
    /**
     * 获取数组配置（逗号分隔）
     * 
     * @param string $key 配置键名
     * @param array $default 默认值
     * @return array
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key);
        
        if ($value === null) {
            return $default;
        }
        
        return array_map('trim', explode(',', $value));
    }
    
    /**
     * 加载所有数据库配置
     */
    public static function getDatabaseConfig(): array
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::getInt('DB_PORT', 3306),
            'name' => self::get('DB_NAME', 'codevault'),
            'user' => self::get('DB_USER', 'codevault'),
            'password' => self::getRequired('DB_PASS'),
        ];
    }
    
    /**
     * 加载所有 Redis 配置
     */
    public static function getRedisConfig(): array
    {
        return [
            'host' => self::get('REDIS_HOST', 'localhost'),
            'port' => self::getInt('REDIS_PORT', 6379),
            'password' => self::get('REDIS_PASSWORD', ''),
            'database' => self::getInt('REDIS_DATABASE', 0),
        ];
    }
    
    /**
     * 加载应用配置
     */
    public static function getAppConfig(): array
    {
        return [
            'name' => self::get('APP_NAME', 'CodeVault'),
            'env' => self::get('APP_ENV', 'production'),
            'debug' => self::getBool('APP_DEBUG', false),
            'url' => self::get('APP_URL', 'http://localhost'),
        ];
    }
    
    /**
     * 加载安全配置
     */
    public static function getSecurityConfig(): array
    {
        return [
            'session_secret' => self::getRequired('SESSION_SECRET'),
            'jwt_secret' => self::getRequired('JWT_SECRET'),
            'rate_limit_enabled' => self::getBool('RATE_LIMIT_ENABLED', true),
            'rate_limit_max' => self::getInt('RATE_LIMIT_MAX', 100),
            'rate_limit_window' => self::getInt('RATE_LIMIT_WINDOW', 60),
        ];
    }
    
    /**
     * 验证所有必需配置
     * 
     * @return array 缺失的配置列表
     */
    public static function validate(): array
    {
        $required = [
            'DB_PASS',
            'SESSION_SECRET',
            'JWT_SECRET',
        ];
        
        $missing = [];
        
        foreach ($required as $key) {
            if (self::get($key) === null) {
                $missing[] = $key;
            }
        }
        
        return $missing;
    }
}
