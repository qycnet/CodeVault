-- ============================================
-- CodeVault MySQL 5.7/8.0 兼容性修复
-- 替换 ADD COLUMN IF NOT EXISTS 语法
-- ============================================

-- 创建辅助存储过程
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition VARCHAR(500)
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO col_exists
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
    AND table_name = p_table
    AND column_name = p_column;
    
    IF col_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- 创建索引辅助存储过程
DROP PROCEDURE IF EXISTS create_index_if_not_exists;

DELIMITER //
CREATE PROCEDURE create_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(500)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO idx_exists
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = p_table
    AND index_name = p_index;
    
    IF idx_exists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX `', p_index, '` ON `', p_table, '` (', p_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ==================== Phase 12 字段修复 ====================

-- security_scans 表
CALL add_column_if_not_exists('security_scans', 'critical_count', 'INT UNSIGNED DEFAULT 0 COMMENT "严重漏洞数"');
CALL add_column_if_not_exists('security_scans', 'high_count', 'INT UNSIGNED DEFAULT 0 COMMENT "高危漏洞数"');
CALL add_column_if_not_exists('security_scans', 'medium_count', 'INT UNSIGNED DEFAULT 0 COMMENT "中危漏洞数"');
CALL add_column_if_not_exists('security_scans', 'low_count', 'INT UNSIGNED DEFAULT 0 COMMENT "低危漏洞数"');

-- users 表
CALL add_column_if_not_exists('users', 'followers_count', 'INT UNSIGNED DEFAULT 0 COMMENT "粉丝数"');
CALL add_column_if_not_exists('users', 'following_count', 'INT UNSIGNED DEFAULT 0 COMMENT "关注数"');

-- ==================== Phase 11 字段修复 ====================

-- gists 表
CALL add_column_if_not_exists('gists', 'access_level', 'ENUM("public", "unlisted", "private") DEFAULT "public" AFTER visibility');
CALL add_column_if_not_exists('gists', 'password_hash', 'VARCHAR(255) NULL AFTER access_level');
CALL add_column_if_not_exists('gists', 'expires_at', 'DATETIME NULL AFTER password_hash');
CALL add_column_if_not_exists('gists', 'view_count', 'BIGINT DEFAULT 0 AFTER expires_at');

-- wiki_pages 表
CALL add_column_if_not_exists('wiki_pages', 'format', 'ENUM("markdown", "html", "asciidoc") DEFAULT "markdown" AFTER content');
CALL add_column_if_not_exists('wiki_pages', 'word_count', 'INT DEFAULT 0 AFTER format');
CALL add_column_if_not_exists('wiki_pages', 'reading_time', 'INT DEFAULT 0 AFTER word_count');

-- webhooks 表
CALL add_column_if_not_exists('webhooks', 'events', 'JSON AFTER active');
CALL add_column_if_not_exists('webhooks', 'content_type', 'VARCHAR(50) DEFAULT "json" AFTER events');
CALL add_column_if_not_exists('webhooks', 'secret', 'VARCHAR(255) NULL AFTER content_type');
CALL add_column_if_not_exists('webhooks', 'insecure_ssl', 'TINYINT(1) DEFAULT 0 AFTER secret');

-- webhook_deliveries 表
CALL add_column_if_not_exists('webhook_deliveries', 'retry_count', 'INT DEFAULT 0 AFTER response_headers');
CALL add_column_if_not_exists('webhook_deliveries', 'max_retries', 'INT DEFAULT 3 AFTER retry_count');
CALL add_column_if_not_exists('webhook_deliveries', 'next_retry_at', 'DATETIME NULL AFTER max_retries');

-- ==================== Phase 8 字段修复 ====================

-- webhooks 表（Phase 8）
CALL add_column_if_not_exists('webhooks', 'content_type', 'ENUM("json", "form") DEFAULT "json" AFTER events');
CALL add_column_if_not_exists('webhooks', 'created_by', 'INT NULL AFTER active');
CALL add_column_if_not_exists('webhooks', 'last_triggered_at', 'DATETIME NULL AFTER created_at');

-- ==================== Phase 5 字段修复 ====================

-- users 表
CALL add_column_if_not_exists('users', 'locale', 'VARCHAR(10) DEFAULT "zh-CN"');
CALL add_column_if_not_exists('users', 'timezone', 'VARCHAR(50) DEFAULT "Asia/Shanghai"');

-- ==================== Phase 0 字段修复 ====================

-- workflow_runs 表
CALL add_column_if_not_exists('workflow_runs', 'event', 'VARCHAR(50) DEFAULT "push" COMMENT "触发事件"');

-- ==================== 索引修复 ====================

CALL create_index_if_not_exists('pull_requests', 'idx_pr_merged_at', '`merged_at`');
CALL create_index_if_not_exists('issues', 'idx_issues_closed_at', '`closed_at`');
CALL create_index_if_not_exists('commits', 'idx_commits_created_at', '`created_at`');

-- ==================== 清理存储过程 ====================

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS create_index_if_not_exists;

-- ==================== 迁移完成 ====================
