-- Phase 11: 协作功能增强
-- Pages 静态网站托管

-- ============================================
-- Pages 静态网站托管
-- ============================================

CREATE TABLE IF NOT EXISTS `pages_sites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repo_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `default_domain` VARCHAR(255),
    `custom_domain` VARCHAR(255),
    `source_branch` VARCHAR(100) DEFAULT 'main',
    `source_dir` VARCHAR(255) DEFAULT '/',
    `build_type` ENUM('static', 'jekyll', 'hugo', 'next', 'nuxt', 'vuepress', 'docsify') DEFAULT 'static',
    `https_enabled` TINYINT(1) DEFAULT 1,
    `status` ENUM('building', 'active', 'failed', 'disabled') DEFAULT 'building',
    `last_deployment_id` INT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_repo_id` (`repo_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_custom_domain` (`custom_domain`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pages_deployments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_id` INT NOT NULL,
    `commit_sha` VARCHAR(40),
    `status` ENUM('pending', 'building', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    `build_log` TEXT,
    `error_message` TEXT,
    `triggered_by` INT NOT NULL,
    `started_at` DATETIME,
    `completed_at` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_site_id` (`site_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`site_id`) REFERENCES `pages_sites`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`triggered_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pages_domains` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `site_id` INT NOT NULL,
    `domain` VARCHAR(255) NOT NULL,
    `verification_token` VARCHAR(64),
    `verified` TINYINT(1) DEFAULT 0,
    `verified_at` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_domain` (`domain`),
    INDEX `idx_site_id` (`site_id`),
    INDEX `idx_verified` (`verified`),
    FOREIGN KEY (`site_id`) REFERENCES `pages_sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Gists 增强
-- ============================================

-- 添加 Gist 访问控制
-- 注意：ADD COLUMN IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- ALTER TABLE `gists` ADD COLUMN `access_level` ENUM('public', 'unlisted', 'private') DEFAULT 'public' AFTER `visibility`;
-- ALTER TABLE `gists` ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `access_level`;
-- ALTER TABLE `gists` ADD COLUMN `expires_at` DATETIME NULL AFTER `password_hash`;
-- ALTER TABLE `gists` ADD COLUMN `view_count` BIGINT DEFAULT 0 AFTER `expires_at`;

-- Gist 嵌入代码
CREATE TABLE IF NOT EXISTS `gist_embeds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gist_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `embed_token` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_embed_token` (`embed_token`),
    INDEX `idx_gist_id` (`gist_id`),
    FOREIGN KEY (`gist_id`) REFERENCES `gists`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Wiki 增强
-- ============================================

-- Wiki 富文本编辑
-- 注意：ADD COLUMN IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- ALTER TABLE `wiki_pages` ADD COLUMN `format` ENUM('markdown', 'html', 'asciidoc') DEFAULT 'markdown' AFTER `content`;
-- ALTER TABLE `wiki_pages` ADD COLUMN `word_count` INT DEFAULT 0 AFTER `format`;
-- ALTER TABLE `wiki_pages` ADD COLUMN `reading_time` INT DEFAULT 0 AFTER `word_count`;

-- Wiki 目录
CREATE TABLE IF NOT EXISTS `wiki_toc` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wiki_id` INT NOT NULL,
    `page_id` INT NOT NULL,
    `parent_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `level` INT DEFAULT 1,
    `order_index` INT DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wiki_id` (`wiki_id`),
    INDEX `idx_page_id` (`page_id`),
    INDEX `idx_parent_id` (`parent_id`),
    FOREIGN KEY (`wiki_id`) REFERENCES `wikis`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`page_id`) REFERENCES `wiki_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `wiki_toc`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wiki 附件
CREATE TABLE IF NOT EXISTS `wiki_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `wiki_id` INT NOT NULL,
    `page_id` INT,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT DEFAULT 0,
    `mime_type` VARCHAR(100),
    `uploaded_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wiki_id` (`wiki_id`),
    INDEX `idx_page_id` (`page_id`),
    FOREIGN KEY (`wiki_id`) REFERENCES `wikis`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`page_id`) REFERENCES `wiki_pages`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Webhooks 增强
-- ============================================

-- Webhook 事件筛选
-- 注意：ADD COLUMN IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- ALTER TABLE `webhooks` ADD COLUMN `events` JSON AFTER `active`;
-- ALTER TABLE `webhooks` ADD COLUMN `content_type` VARCHAR(50) DEFAULT 'json' AFTER `events`;
-- ALTER TABLE `webhooks` ADD COLUMN `secret` VARCHAR(255) NULL AFTER `content_type`;
-- ALTER TABLE `webhooks` ADD COLUMN `insecure_ssl` TINYINT(1) DEFAULT 0 AFTER `secret`;

-- Webhook 重试配置
-- ALTER TABLE `webhook_deliveries` ADD COLUMN `retry_count` INT DEFAULT 0 AFTER `response_headers`;
-- ALTER TABLE `webhook_deliveries` ADD COLUMN `max_retries` INT DEFAULT 3 AFTER `retry_count`;
-- ALTER TABLE `webhook_deliveries` ADD COLUMN `next_retry_at` DATETIME NULL AFTER `max_retries`;

-- Webhook 签名验证日志
CREATE TABLE IF NOT EXISTS `webhook_signatures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `delivery_id` INT NOT NULL,
    `algorithm` VARCHAR(20) DEFAULT 'sha256',
    `signature` VARCHAR(255) NOT NULL,
    `verified` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_delivery_id` (`delivery_id`),
    FOREIGN KEY (`delivery_id`) REFERENCES `webhook_deliveries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Webhook 事件类型
CREATE TABLE IF NOT EXISTS `webhook_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `webhook_id` INT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_webhook_event` (`webhook_id`, `event_type`),
    INDEX `idx_webhook_id` (`webhook_id`),
    FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 索引优化
-- ============================================

-- 优化查询性能
-- 注意：CREATE INDEX IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- CREATE INDEX `idx_pages_sites_status` ON `pages_sites` (`status`, `updated_at`);
-- CREATE INDEX `idx_pages_deployments_status` ON `pages_deployments` (`site_id`, `status`, `created_at`);
-- CREATE INDEX `idx_gists_user_created` ON `gists` (`user_id`, `created_at`);
-- CREATE INDEX `idx_wiki_pages_wiki_updated` ON `wiki_pages` (`wiki_id`, `updated_at`);
-- CREATE INDEX `idx_webhook_deliveries_webhook_created` ON `webhook_deliveries` (`webhook_id`, `created_at`);
