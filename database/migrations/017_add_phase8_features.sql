-- Phase 8: 协作增强功能
-- Gists, Wiki 增强, Webhooks 增强

-- ============================================
-- Gists 代码片段
-- ============================================

CREATE TABLE IF NOT EXISTS `gists` (
    `id` VARCHAR(32) NOT NULL PRIMARY KEY,
    `user_id` INT NOT NULL,
    `description` TEXT,
    `visibility` ENUM('public', 'private', 'link_only') DEFAULT 'public',
    `current_version_id` INT NULL,
    `forked_from` VARCHAR(32) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_visibility` (`visibility`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_forked_from` (`forked_from`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gist_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gist_id` VARCHAR(32) NOT NULL,
    `user_id` INT NOT NULL,
    `comment` VARCHAR(255) DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_gist_id` (`gist_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`gist_id`) REFERENCES `gists`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gist_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `language` VARCHAR(50) DEFAULT 'Text',
    `size` INT DEFAULT 0,
    INDEX `idx_version_id` (`version_id`),
    FOREIGN KEY (`version_id`) REFERENCES `gist_versions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gist_stars` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gist_id` VARCHAR(32) NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_gist_user` (`gist_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`gist_id`) REFERENCES `gists`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gist_forks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gist_id` VARCHAR(32) NOT NULL,
    `user_id` INT NOT NULL,
    `forked_from` VARCHAR(32) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_gist_id` (`gist_id`),
    INDEX `idx_forked_from` (`forked_from`),
    FOREIGN KEY (`gist_id`) REFERENCES `gists`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gist_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gist_id` VARCHAR(32) NOT NULL,
    `user_id` INT NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_gist_id` (`gist_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`gist_id`) REFERENCES `gists`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Wiki 增强
-- ============================================

CREATE TABLE IF NOT EXISTS `wiki_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repo_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `content` LONGTEXT,
    `format` ENUM('markdown', 'html', 'text') DEFAULT 'markdown',
    `author_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_repo_slug` (`repo_id`, `slug`),
    INDEX `idx_repo_id` (`repo_id`),
    INDEX `idx_author_id` (`author_id`),
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wiki_revisions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `page_id` INT NOT NULL,
    `author_id` INT NOT NULL,
    `content` LONGTEXT,
    `comment` VARCHAR(255) DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_page_id` (`page_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`page_id`) REFERENCES `wiki_pages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wiki_toc` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `page_id` INT NOT NULL,
    `level` TINYINT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `anchor` VARCHAR(255) NOT NULL,
    `position` INT DEFAULT 0,
    INDEX `idx_page_id` (`page_id`),
    FOREIGN KEY (`page_id`) REFERENCES `wiki_pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Webhooks 增强
-- ============================================

-- 更新 webhooks 表添加新字段
ALTER TABLE `webhooks` 
ADD COLUMN IF NOT EXISTS `content_type` ENUM('json', 'form') DEFAULT 'json' AFTER `events`,
ADD COLUMN IF NOT EXISTS `created_by` INT NULL AFTER `active`,
ADD COLUMN IF NOT EXISTS `last_triggered_at` DATETIME NULL AFTER `created_at`;

-- Webhook 投递日志
CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
    `id` VARCHAR(32) NOT NULL PRIMARY KEY,
    `webhook_id` INT NOT NULL,
    `event` VARCHAR(50) NOT NULL,
    `payload` LONGTEXT,
    `status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    `http_code` INT NULL,
    `response` TEXT,
    `duration` INT NULL COMMENT 'Duration in milliseconds',
    `error` VARCHAR(500) NULL,
    `retry_count` INT DEFAULT 0,
    `next_retry_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivered_at` DATETIME NULL,
    INDEX `idx_webhook_id` (`webhook_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_next_retry` (`next_retry_at`),
    FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 索引优化
-- ============================================

-- Gists 搜索优化
CREATE FULLTEXT INDEX IF NOT EXISTS `idx_gist_search` ON `gist_files`(`filename`, `content`);

-- Wiki 搜索优化
CREATE FULLTEXT INDEX IF NOT EXISTS `idx_wiki_search` ON `wiki_pages`(`title`, `content`);
