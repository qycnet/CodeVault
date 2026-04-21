-- Phase 9: 开发者体验功能
-- API 文档、统计分析、导入/导出

-- ============================================
-- 导入任务表
-- ============================================

CREATE TABLE IF NOT EXISTS `import_tasks` (
    `id` VARCHAR(32) NOT NULL PRIMARY KEY,
    `user_id` INT NOT NULL,
    `source` ENUM('github', 'gitlab', 'git', 'bundle') NOT NULL,
    `config` JSON,
    `status` ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    `progress` INT DEFAULT 0,
    `message` VARCHAR(255),
    `result` JSON,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 导出任务表
-- ============================================

CREATE TABLE IF NOT EXISTS `export_tasks` (
    `id` VARCHAR(32) NOT NULL PRIMARY KEY,
    `user_id` INT NOT NULL,
    `repo_id` INT NOT NULL,
    `type` ENUM('bundle', 'tar', 'issues', 'wiki') NOT NULL,
    `status` ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    `file_path` VARCHAR(500),
    `file_size` BIGINT DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_repo_id` (`repo_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 活动日志表（用于统计）
-- ============================================

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `repo_id` INT,
    `type` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50),
    `entity_id` INT,
    `data` JSON,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_repo_id` (`repo_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 统计缓存表
-- ============================================

CREATE TABLE IF NOT EXISTS `stats_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` JSON,
    `expires_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`key`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- API 文档缓存表
-- ============================================

CREATE TABLE IF NOT EXISTS `api_docs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(20) NOT NULL,
    `spec` LONGTEXT,
    `format` ENUM('json', 'yaml') DEFAULT 'json',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 仓库语言统计表
-- ============================================

CREATE TABLE IF NOT EXISTS `repo_languages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repo_id` INT NOT NULL,
    `language` VARCHAR(50) NOT NULL,
    `bytes` BIGINT DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_repo_lang` (`repo_id`, `language`),
    INDEX `idx_repo_id` (`repo_id`),
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 贡献者统计表
-- ============================================

CREATE TABLE IF NOT EXISTS `repo_contributors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repo_id` INT NOT NULL,
    `author_email` VARCHAR(255) NOT NULL,
    `author_name` VARCHAR(255),
    `commits` INT DEFAULT 0,
    `additions` BIGINT DEFAULT 0,
    `deletions` BIGINT DEFAULT 0,
    `first_commit_at` DATETIME,
    `last_commit_at` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_repo_email` (`repo_id`, `author_email`),
    INDEX `idx_repo_id` (`repo_id`),
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 触发器：更新仓库统计
-- ============================================

DELIMITER //

CREATE TRIGGER IF NOT EXISTS `tr_update_repo_stats_on_commit`
AFTER INSERT ON `commits`
FOR EACH ROW
BEGIN
    UPDATE repositories 
    SET size = (
        SELECT COALESCE(SUM(additions - deletions), 0) 
        FROM commits 
        WHERE repo_id = NEW.repo_id
    ),
    pushed_at = NEW.created_at
    WHERE id = NEW.repo_id;
    
    -- 更新贡献者统计
    INSERT INTO repo_contributors (repo_id, author_email, author_name, commits, additions, deletions, first_commit_at, last_commit_at)
    VALUES (NEW.repo_id, NEW.author_email, NEW.author_name, 1, NEW.additions, NEW.deletions, NEW.created_at, NEW.created_at)
    ON DUPLICATE KEY UPDATE
        commits = commits + 1,
        additions = additions + NEW.additions,
        deletions = deletions + NEW.deletions,
        last_commit_at = NEW.created_at;
END//

CREATE TRIGGER IF NOT EXISTS `tr_update_issue_stats`
AFTER INSERT ON `issues`
FOR EACH ROW
BEGIN
    UPDATE repositories 
    SET open_issues_count = (
        SELECT COUNT(*) FROM issues WHERE repo_id = NEW.repo_id AND state = 'open'
    )
    WHERE id = NEW.repo_id;
END//

CREATE TRIGGER IF NOT EXISTS `tr_update_issue_stats_on_update`
AFTER UPDATE ON `issues`
FOR EACH ROW
BEGIN
    UPDATE repositories 
    SET open_issues_count = (
        SELECT COUNT(*) FROM issues WHERE repo_id = NEW.repo_id AND state = 'open'
    )
    WHERE id = NEW.repo_id;
END//

DELIMITER ;

-- ============================================
-- 存储过程：清理过期数据
-- ============================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `sp_cleanup_expired_data`()
BEGIN
    -- 清理过期导出文件
    DELETE FROM export_tasks WHERE expires_at < NOW();
    
    -- 清理过期统计缓存
    DELETE FROM stats_cache WHERE expires_at < NOW();
    
    -- 清理超过 90 天的活动日志
    DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END//

DELIMITER ;

-- ============================================
-- 事件：定期清理
-- ============================================

CREATE EVENT IF NOT EXISTS `ev_cleanup_expired_data`
ON SCHEDULE EVERY 1 HOUR
DO CALL sp_cleanup_expired_data();
