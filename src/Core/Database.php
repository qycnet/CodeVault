<?php
/**
 * CodeVault 数据库核心类
 * 
 * 数据库连接和操作的统一入口
 */

namespace Core;

use CodeVault\Database\Connection;

class Database
{
    /**
     * 获取数据库连接实例
     */
    public static function getInstance(): Connection
    {
        return Connection::getInstance();
    }
    
    /**
     * 执行 SQL 查询
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        return self::getInstance()->query($sql, $params);
    }
    
    /**
     * 执行 SQL 语句
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::getInstance()->execute($sql, $params);
    }
    
    /**
     * 获取单行结果
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        return self::getInstance()->fetchOne($sql, $params);
    }
    
    /**
     * 获取多行结果
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::getInstance()->fetchAll($sql, $params);
    }
    
    /**
     * 获取最后插入的 ID
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
    
    /**
     * 开始事务
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }
    
    /**
     * 回滚事务
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollback();
    }
}
