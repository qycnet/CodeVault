<?php
/**
 * CodeVault - 数据库连接类
 * PDO 单例封装
 */

namespace CodeVault\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    
    /**
     * 获取数据库连接实例
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }
    
    /**
     * 建立数据库连接
     */
    private static function connect(): void
    {
        $config = require __DIR__ . '/../../config/database.php';
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );
        
        try {
            self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            throw new PDOException('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 执行查询并返回所有结果
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * 执行查询并返回单行结果
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * 执行 INSERT/UPDATE/DELETE 并返回影响行数
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * 插入数据并返回自增ID
     */
    public static function insert(string $sql, array $params = []): int
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }
    
    /**
     * 开启事务
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }
    
    /**
     * 回滚事务
     */
    public static function rollback(): void
    {
        self::getInstance()->rollBack();
    }
}
