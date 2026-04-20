#!/usr/bin/env php
<?php
/**
 * CodeVault 数据库迁移脚本
 * 执行所有迁移文件
 */

echo "=== CodeVault 数据库迁移 ===\n\n";

// 加载配置
$config = require __DIR__ . '/../config/database.php';

try {
    // 先连接到 MySQL 服务器（不指定数据库）
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=utf8mb4',
        $config['host'],
        $config['port']
    );
    
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    echo "✅ MySQL 连接成功\n";
    
    // 创建数据库（如果不存在）
    $pdo->exec(sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        $config['dbname']
    ));
    echo "✅ 数据库已创建/确认: {$config['dbname']}\n";
    
    // 切换到目标数据库
    $pdo->exec(sprintf("USE `%s`", $config['dbname']));
    echo "✅ 已切换到数据库: {$config['dbname']}\n\n";
    
    // 创建迁移记录表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ 迁移记录表已创建\n\n";
    
    // 获取已执行的迁移
    $executed = $pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    
    // 获取所有迁移文件
    $migrationDir = __DIR__ . '/migrations';
    $files = glob("$migrationDir/*.sql");
    sort($files);
    
    if (empty($files)) {
        echo "⚠️  没有找到迁移文件\n";
        exit(0);
    }
    
    $newMigrations = 0;
    
    foreach ($files as $file) {
        $name = basename($file);
        
        if (in_array($name, $executed)) {
            echo "⏭️  已跳过: $name (已执行)\n";
            continue;
        }
        
        echo "🔄 执行迁移: $name\n";
        
        // 读取 SQL 文件
        $sql = file_get_contents($file);
        
        // 执行整个 SQL 文件
        try {
            // 分割并执行语句
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement) && !str_starts_with($statement, '--')) {
                    $pdo->exec($statement);
                }
            }
            
            // 记录迁移
            $stmt = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
            $stmt->execute([$name]);
            
            echo "✅ 完成: $name\n\n";
            $newMigrations++;
            
        } catch (PDOException $e) {
            echo "❌ 失败: $name\n";
            echo "   错误: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "\n=== 迁移完成 ===\n";
    echo "新增迁移: $newMigrations 个\n";
    echo "总计迁移: " . (count($executed) + $newMigrations) . " 个\n";
    
} catch (PDOException $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
