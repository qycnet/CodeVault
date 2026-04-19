-- CodeVault 数据库结构
-- PHP 原生开发，MySQL 5.7+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `email` VARCHAR(100) NOT NULL COMMENT '邮箱',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希 (bcrypt)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- SSH Key 表
-- ----------------------------
DROP TABLE IF EXISTS `ssh_keys`;
CREATE TABLE `ssh_keys` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
    `title` VARCHAR(100) NOT NULL COMMENT 'Key 标题',
    `public_key` TEXT NOT NULL COMMENT '公钥内容',
    `fingerprint` VARCHAR(64) NOT NULL COMMENT 'SHA256 指纹',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    UNIQUE KEY `uk_fingerprint` (`fingerprint`),
    CONSTRAINT `fk_ssh_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SSH公钥表';

-- ----------------------------
-- 仓库表
-- ----------------------------
DROP TABLE IF EXISTS `repositories`;
CREATE TABLE `repositories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id` INT UNSIGNED NOT NULL COMMENT '所有者ID',
    `name` VARCHAR(100) NOT NULL COMMENT '仓库名称',
    `description` TEXT COMMENT '描述',
    `is_private` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否私有',
    `git_path` VARCHAR(255) NOT NULL COMMENT 'Git 存储路径',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_owner_id` (`owner_id`),
    UNIQUE KEY `uk_owner_name` (`owner_id`, `name`),
    CONSTRAINT `fk_repos_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代码仓库表';

-- ----------------------------
-- Issue 表
-- ----------------------------
DROP TABLE IF EXISTS `issues`;
CREATE TABLE `issues` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `author_id` INT UNSIGNED NOT NULL COMMENT '创建者ID',
    `title` VARCHAR(255) NOT NULL COMMENT '标题',
    `content` TEXT COMMENT '内容',
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open' COMMENT '状态',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_repo_id` (`repo_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_issues_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_issues_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Issue表';

SET FOREIGN_KEY_CHECKS = 1;
