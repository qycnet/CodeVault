<?php
/**
 * CodeVault - PSR-3 兼容日志系统
 * 支持多通道、日志分级、格式化输出
 */

namespace CodeVault\Core;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger
{
    /**
     * 日志级别权重
     */
    private const LEVEL_WEIGHTS = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * 日志级别颜色（终端）
     */
    private const LEVEL_COLORS = [
        LogLevel::DEBUG => "\033[36m",    // 青色
        LogLevel::INFO => "\033[32m",     // 绿色
        LogLevel::NOTICE => "\033[34m",   // 蓝色
        LogLevel::WARNING => "\033[33m",  // 黄色
        LogLevel::ERROR => "\033[31m",    // 红色
        LogLevel::CRITICAL => "\033[35m", // 紫色
        LogLevel::ALERT => "\033[35m",    // 紫色
        LogLevel::EMERGENCY => "\033[41m", // 红底
    ];

    private string $channel;
    private string $logPath;
    private string $minLevel;
    private array $processors;
    private bool $useColors;

    /**
     * 构造函数
     */
    public function __construct(
        string $channel = 'app',
        string $logPath = '/var/log/codevault',
        string $minLevel = LogLevel::DEBUG,
        array $processors = [],
        bool $useColors = true
    ) {
        $this->channel = $channel;
        $this->logPath = $logPath;
        $this->minLevel = $minLevel;
        $this->processors = $processors;
        $this->useColors = $useColors && $this->isCli();
        
        // 确保日志目录存在
        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }
    }

    /**
     * 实现 PSR-3 日志方法
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        // 检查日志级别
        if (!$this->shouldLog($level)) {
            return;
        }
        
        // 格式化消息
        $formatted = $this->formatMessage($level, $message, $context);
        
        // 写入日志文件
        $this->write($level, $formatted);
    }

    /**
     * 检查是否应该记录日志
     */
    private function shouldLog(string $level): bool
    {
        $minWeight = self::LEVEL_WEIGHTS[$this->minLevel] ?? 0;
        $levelWeight = self::LEVEL_WEIGHTS[$level] ?? 0;
        
        return $levelWeight >= $minWeight;
    }

    /**
     * 格式化日志消息
     */
    private function formatMessage(string $level, string $message, array $context): string
    {
        // 处理上下文占位符
        $message = $this->interpolate($message, $context);
        
        // 应用处理器
        foreach ($this->processors as $processor) {
            $message = $processor($message, $context);
        }
        
        // 构建日志条目
        $timestamp = date('Y-m-d H:i:s') . substr(microtime(), 1, 4);
        $levelUpper = strtoupper($level);
        
        // 添加额外上下文
        $extra = [];
        if (isset($context['user_id'])) {
            $extra['user'] = $context['user_id'];
        }
        if (isset($context['repo_id'])) {
            $extra['repo'] = $context['repo_id'];
        }
        if (isset($context['duration'])) {
            $extra['duration'] = $context['duration'] . 'ms';
        }
        
        $extraStr = !empty($extra) ? ' [' . http_build_query($extra, '', ', ') . ']' : '';
        
        return sprintf(
            "[%s] [%s] [%s]%s %s\n",
            $timestamp,
            $levelUpper,
            $this->channel,
            $extraStr,
            $message
        );
    }

    /**
     * 替换上下文占位符
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            } elseif (is_array($val) || is_object($val)) {
                $replace['{' . $key . '}'] = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
        }
        
        return strtr($message, $replace);
    }

    /**
     * 写入日志
     */
    private function write(string $level, string $message): void
    {
        // 按日期分文件
        $filename = $this->getLogFilename($level);
        $filepath = $this->logPath . '/' . $filename;
        
        // 写入文件
        @file_put_contents($filepath, $message, FILE_APPEND | LOCK_EX);
        
        // 如果是 CLI 模式，输出到终端
        if ($this->isCli()) {
            $this->writeToTerminal($level, $message);
        }
    }

    /**
     * 获取日志文件名
     */
    private function getLogFilename(string $level): string
    {
        // 错误级别单独文件
        if (in_array($level, [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
            return $this->channel . '-error-' . date('Y-m-d') . '.log';
        }
        
        // 普通日志
        return $this->channel . '-' . date('Y-m-d') . '.log';
    }

    /**
     * 输出到终端
     */
    private function writeToTerminal(string $level, string $message): void
    {
        if ($this->useColors && isset(self::LEVEL_COLORS[$level])) {
            $color = self::LEVEL_COLORS[$level];
            $reset = "\033[0m";
            echo $color . $message . $reset;
        } else {
            echo $message;
        }
    }

    /**
     * 检查是否为 CLI 模式
     */
    private function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * 创建特定通道的日志器
     */
    public static function channel(string $name): self
    {
        return new self($name);
    }

    /**
     * 创建 API 日志器
     */
    public static function api(): self
    {
        return new self('api', '/var/log/codevault', LogLevel::INFO);
    }

    /**
     * 创建 Git 日志器
     */
    public static function git(): self
    {
        return new self('git', '/var/log/codevault', LogLevel::DEBUG);
    }

    /**
     * 创建 Actions 日志器
     */
    public static function actions(): self
    {
        return new self('actions', '/var/log/codevault', LogLevel::DEBUG);
    }

    /**
     * 创建安全日志器
     */
    public static function security(): self
    {
        return new self('security', '/var/log/codevault', LogLevel::WARNING);
    }

    /**
     * 清理旧日志
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $logPath = getenv('LOG_PATH') ?: '/var/log/codevault';
        $count = 0;
        $threshold = time() - ($daysToKeep * 86400);
        
        $files = glob($logPath . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * 获取日志统计
     */
    public static function getStats(): array
    {
        $logPath = getenv('LOG_PATH') ?: '/var/log/codevault';
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'files' => [],
        ];
        
        $files = glob($logPath . '/*.log');
        
        foreach ($files as $file) {
            $size = filesize($file);
            $stats['total_files']++;
            $stats['total_size'] += $size;
            $stats['files'][] = [
                'name' => basename($file),
                'size' => $size,
                'modified' => filemtime($file),
            ];
        }
        
        $stats['total_size_human'] = self::formatBytes($stats['total_size']);
        
        return $stats;
    }

    /**
     * 格式化字节
     */
    private static function formatBytes(int $bytes): string
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

/**
 * 日志处理器 - 添加请求ID
 */
function addRequestId(): callable
{
    return function (string $message, array $context): string {
        $requestId = $context['request_id'] ?? substr(uniqid(), -8);
        return "[{$requestId}] {$message}";
    };
}

/**
 * 日志处理器 - 添加执行时间
 */
function addExecutionTime(): callable
{
    static $startTime = null;
    
    if ($startTime === null) {
        $startTime = microtime(true);
    }
    
    return function (string $message, array $context) use ($startTime): string {
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        return "{$message} ({$duration}ms)";
    };
}
