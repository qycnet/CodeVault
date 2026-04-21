<?php
/**
 * CodeVault - 统一错误处理器
 * 实现标准化错误响应和异常处理
 */

namespace CodeVault\Core;

use CodeVault\Services\Session;

class ErrorHandler
{
    /**
     * 错误级别映射
     */
    private static array $errorLevels = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED',
    ];

    /**
     * HTTP 状态码映射
     */
    private static array $httpStatusCodes = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * 初始化错误处理器
     */
    public static function init(): void
    {
        // 设置错误处理函数
        set_error_handler([self::class, 'handleError']);
        
        // 设置异常处理函数
        set_exception_handler([self::class, 'handleException']);
        
        // 设置关闭函数
        register_shutdown_function([self::class, 'handleShutdown']);
        
        // 报告所有错误
        error_reporting(E_ALL);
        
        // 不显示错误到屏幕
        ini_set('display_errors', '0');
        
        // 记录错误到日志
        ini_set('log_errors', '1');
    }

    /**
     * 错误处理函数
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // 不处理 @ 抑制的错误
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorLevel = self::$errorLevels[$errno] ?? 'UNKNOWN';
        $message = "[{$errorLevel}] {$errstr} in {$errfile} on line {$errline}";
        
        // 记录日志
        self::log($message, $errno);
        
        // 如果是严重错误，抛出异常
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
        
        return true;
    }

    /**
     * 异常处理函数
     */
    public static function handleException(\Throwable $exception): void
    {
        $message = self::formatException($exception);
        
        // 记录日志
        self::log($message, E_ERROR);
        
        // 发送错误响应
        self::sendErrorResponse($exception);
    }

    /**
     * 关闭函数 - 捕获致命错误
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = "[FATAL] {$error['message']} in {$error['file']} on line {$error['line']}";
            
            // 记录日志
            self::log($message, E_ERROR);
            
            // 发送错误响应
            self::sendFatalErrorResponse($error);
        }
    }

    /**
     * 格式化异常信息
     */
    private static function formatException(\Throwable $exception): string
    {
        $message = sprintf(
            "[%s] %s in %s on line %d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // 如果有前一个异常
        if ($previous = $exception->getPrevious()) {
            $message .= "\n\nPrevious exception:\n" . self::formatException($previous);
        }
        
        return $message;
    }

    /**
     * 记录日志
     */
    private static function log(string $message, int $level): void
    {
        $logFile = getenv('LOG_PATH') ?: '/var/log/codevault/error.log';
        $logDir = dirname($logFile);
        
        // 确保日志目录存在
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$errorLevels[$level] ?? 'UNKNOWN';
        $user = Session::user();
        $userId = $user['id'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logLine = sprintf(
            "[%s] [%s] [User:%s] [IP:%s] [URI:%s] %s\n",
            $timestamp,
            $levelName,
            $userId,
            $ip,
            $uri,
            $message
        );
        
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * 发送错误响应
     */
    private static function sendErrorResponse(\Throwable $exception): void
    {
        // 如果已经发送了响应头，不再发送
        if (headers_sent()) {
            return;
        }
        
        // 清除任何已输出的内容
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // 确定HTTP状态码
        $statusCode = self::getHttpStatusCode($exception);
        
        // 设置响应头
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // 构建错误响应
        $response = [
            'success' => false,
            'error' => [
                'code' => $exception->getCode() ?: $statusCode,
                'message' => self::getSafeMessage($exception),
            ],
        ];
        
        // 开发环境显示详细信息
        if (getenv('APP_ENV') === 'development') {
            $response['error']['file'] = $exception->getFile();
            $response['error']['line'] = $exception->getLine();
            $response['error']['trace'] = explode("\n", $exception->getTraceAsString());
        }
        
        // 记录错误ID用于追踪
        $errorId = uniqid('err_', true);
        $response['error']['id'] = $errorId;
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 发送致命错误响应
     */
    private static function sendFatalErrorResponse(array $error): void
    {
        if (headers_sent()) {
            return;
        }
        
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'error' => [
                'code' => 500,
                'message' => 'Internal Server Error',
                'id' => uniqid('fatal_', true),
            ],
        ];
        
        if (getenv('APP_ENV') === 'development') {
            $response['error']['details'] = $error;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 获取HTTP状态码
     */
    private static function getHttpStatusCode(\Throwable $exception): int
    {
        // 如果异常有自定义状态码
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }
        
        // 根据异常类型映射状态码
        $code = $exception->getCode();
        
        if ($code >= 400 && $code < 600) {
            return $code;
        }
        
        // 默认500
        return 500;
    }

    /**
     * 获取安全的错误消息
     */
    private static function getSafeMessage(\Throwable $exception): string
    {
        // 生产环境不暴露详细错误
        if (getenv('APP_ENV') === 'production') {
            return 'An error occurred. Please try again later.';
        }
        
        return $exception->getMessage();
    }

    /**
     * 创建API错误响应
     */
    public static function apiError(string $message, int $code = 400, array $details = []): array
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        
        if (!empty($details)) {
            $response['error']['details'] = $details;
        }
        
        return $response;
    }

    /**
     * 创建验证错误响应
     */
    public static function validationError(array $errors): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 422,
                'message' => 'Validation failed',
                'details' => $errors,
            ],
        ];
    }

    /**
     * 创建未授权错误响应
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 401,
                'message' => $message,
            ],
        ];
    }

    /**
     * 创建禁止访问错误响应
     */
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 403,
                'message' => $message,
            ],
        ];
    }

    /**
     * 创建未找到错误响应
     */
    public static function notFound(string $message = 'Resource not found'): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 404,
                'message' => $message,
            ],
        ];
    }

    /**
     * 创建服务器错误响应
     */
    public static function serverError(string $message = 'Internal server error'): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => 500,
                'message' => $message,
            ],
        ];
    }
}

/**
 * HTTP 异常类
 */
class HttpException extends \RuntimeException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(int $statusCode, string $message = '', ?\Throwable $previous = null, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}

/**
 * 验证异常类
 */
class ValidationException extends HttpException
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        $this->errors = $errors;
        parent::__construct(422, $message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
