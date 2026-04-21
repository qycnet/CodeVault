<?php
/**
 * CodeVault - PHPUnit 测试配置
 */

use PHPUnit\Framework\TestCase;

// 加载自动加载器
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * 基础测试类
 */
abstract class BaseTestCase extends TestCase
{
    protected static $pdo;
    protected static $redis;
    
    /**
     * 设置测试环境
     */
    public static function setUpBeforeClass(): void
    {
        // 使用测试数据库
        $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';dbname=codevault_test';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        
        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Redis 连接
        try {
            self::$redis = new Redis();
            self::$redis->connect(getenv('REDIS_HOST') ?: 'localhost', (int)(getenv('REDIS_PORT') ?: 6379));
            self::$redis->select(15); // 使用测试数据库
        } catch (Exception $e) {
            self::$redis = null;
        }
    }
    
    /**
     * 清理测试数据
     */
    public static function tearDownAfterClass(): void
    {
        // 清理测试数据
        if (self::$redis) {
            self::$redis->flushDB();
        }
    }
    
    /**
     * 创建测试用户
     */
    protected function createTestUser(array $data = []): array
    {
        $id = uniqid('test_');
        $defaults = [
            'username' => 'testuser_' . $id,
            'email' => 'test_' . $id . '@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $data = array_merge($defaults, $data);
        
        $sql = "INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, ?)";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$data['username'], $data['email'], $data['password'], $data['created_at']]);
        
        $data['id'] = self::$pdo->lastInsertId();
        return $data;
    }
    
    /**
     * 创建测试仓库
     */
    protected function createTestRepo(int $userId, array $data = []): array
    {
        $id = uniqid('repo_');
        $defaults = [
            'name' => 'test-repo-' . $id,
            'description' => 'Test repository',
            'user_id' => $userId,
            'is_private' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $data = array_merge($defaults, $data);
        
        $sql = "INSERT INTO repositories (name, description, user_id, is_private, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$data['name'], $data['description'], $data['user_id'], $data['is_private'], $data['created_at']]);
        
        $data['id'] = self::$pdo->lastInsertId();
        return $data;
    }
    
    /**
     * 创建测试 Issue
     */
    protected function createTestIssue(int $repoId, int $userId, array $data = []): array
    {
        $defaults = [
            'title' => 'Test Issue',
            'content' => 'Test issue content',
            'repo_id' => $repoId,
            'user_id' => $userId,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $data = array_merge($defaults, $data);
        
        $sql = "INSERT INTO issues (title, content, repo_id, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$data['title'], $data['content'], $data['repo_id'], $data['user_id'], $data['status'], $data['created_at']]);
        
        $data['id'] = self::$pdo->lastInsertId();
        return $data;
    }
    
    /**
     * 断言数组包含指定键
     */
    protected function assertArrayHasKeys(array $keys, array $array): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, "Array should have key: {$key}");
        }
    }
    
    /**
     * 模拟 HTTP 请求
     */
    protected function mockRequest(string $method, string $uri, array $data = [], array $headers = []): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }
        
        if ($method === 'GET') {
            $_GET = $data;
        } else {
            file_put_contents('php://input', json_encode($data));
        }
        
        return $data;
    }
}
