-- ============================================
-- CodeVault Phase 12 可选扩展功能
-- 数据库迁移文件
-- ============================================

-- ==================== 社交功能 ====================

-- 用户关注关系表
CREATE TABLE IF NOT EXISTS `user_follows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `follower_id` INT UNSIGNED NOT NULL COMMENT '关注者ID',
    `following_id` INT UNSIGNED NOT NULL COMMENT '被关注者ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_follower_following` (`follower_id`, `following_id`),
    KEY `idx_following_id` (`following_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_user_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_follows_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户关注关系表';

-- 用户动态表
CREATE TABLE IF NOT EXISTS `user_activities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(50) NOT NULL COMMENT '动态类型',
    `data` JSON COMMENT '动态数据',
    `repo_id` INT UNSIGNED COMMENT '关联仓库ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_repo_id` (`repo_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_user_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_activities_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户动态表';

-- ==================== 第三方集成 ====================

-- OAuth 绑定表
CREATE TABLE IF NOT EXISTS `user_oauth_bindings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
    `platform` VARCHAR(50) NOT NULL COMMENT '平台标识',
    `platform_user_id` VARCHAR(255) NOT NULL COMMENT '平台用户ID',
    `platform_data` JSON COMMENT '平台用户数据',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform_user` (`platform`, `platform_user_id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_oauth_bindings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth绑定表';

-- OAuth 提供商配置表
CREATE TABLE IF NOT EXISTS `oauth_providers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `platform` VARCHAR(50) NOT NULL COMMENT '平台标识',
    `name` VARCHAR(100) NOT NULL COMMENT '平台名称',
    `client_id` VARCHAR(255) NOT NULL COMMENT '客户端ID',
    `client_secret` VARCHAR(255) NOT NULL COMMENT '客户端密钥',
    `redirect_uri` VARCHAR(500) COMMENT '回调地址',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth提供商配置表';

-- 仓库集成配置表
CREATE TABLE IF NOT EXISTS `repo_integrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `platform` VARCHAR(50) NOT NULL COMMENT '平台标识(slack/dingtalk)',
    `config` JSON COMMENT '配置信息',
    `events` JSON COMMENT '订阅事件列表',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_by` INT UNSIGNED COMMENT '创建者ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_repo_platform` (`repo_id`, `platform`),
    KEY `idx_platform` (`platform`),
    CONSTRAINT `fk_repo_integrations_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_repo_integrations_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库集成配置表';

-- ==================== 数据分析 ====================

-- 安全扫描结果表（增强）
-- 注意：ADD COLUMN IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- ALTER TABLE `security_scans` 
-- ADD COLUMN `critical_count` INT UNSIGNED DEFAULT 0 COMMENT '严重漏洞数',
-- ADD COLUMN `high_count` INT UNSIGNED DEFAULT 0 COMMENT '高危漏洞数',
-- ADD COLUMN `medium_count` INT UNSIGNED DEFAULT 0 COMMENT '中危漏洞数',
-- ADD COLUMN `low_count` INT UNSIGNED DEFAULT 0 COMMENT '低危漏洞数';

-- 代码审查统计表
CREATE TABLE IF NOT EXISTS `code_review_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `date` DATE NOT NULL COMMENT '统计日期',
    `total_reviews` INT UNSIGNED DEFAULT 0 COMMENT '总审查数',
    `approved_reviews` INT UNSIGNED DEFAULT 0 COMMENT '批准审查数',
    `changes_requested` INT UNSIGNED DEFAULT 0 COMMENT '请求变更数',
    `avg_review_time_hours` DECIMAL(10,2) COMMENT '平均审查时间(小时)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_repo_date` (`repo_id`, `date`),
    KEY `idx_date` (`date`),
    CONSTRAINT `fk_review_stats_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代码审查统计表';

-- 仓库贡献者统计表
CREATE TABLE IF NOT EXISTS `repo_contributor_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
    `period` VARCHAR(20) NOT NULL COMMENT '统计周期',
    `commits` INT UNSIGNED DEFAULT 0 COMMENT '提交数',
    `prs_created` INT UNSIGNED DEFAULT 0 COMMENT '创建PR数',
    `prs_merged` INT UNSIGNED DEFAULT 0 COMMENT '合并PR数',
    `issues_created` INT UNSIGNED DEFAULT 0 COMMENT '创建Issue数',
    `issues_closed` INT UNSIGNED DEFAULT 0 COMMENT '关闭Issue数',
    `reviews_given` INT UNSIGNED DEFAULT 0 COMMENT '审查数',
    `lines_added` INT UNSIGNED DEFAULT 0 COMMENT '新增行数',
    `lines_removed` INT UNSIGNED DEFAULT 0 COMMENT '删除行数',
    `efficiency_score` DECIMAL(10,2) COMMENT '效率分数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_repo_user_period` (`repo_id`, `user_id`, `period`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_period` (`period`),
    CONSTRAINT `fk_contributor_stats_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_contributor_stats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库贡献者统计表';

-- PR 合并时间统计表
CREATE TABLE IF NOT EXISTS `pr_merge_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `date` DATE NOT NULL COMMENT '统计日期',
    `total_prs` INT UNSIGNED DEFAULT 0 COMMENT '总PR数',
    `merged_prs` INT UNSIGNED DEFAULT 0 COMMENT '已合并PR数',
    `avg_merge_time_hours` DECIMAL(10,2) COMMENT '平均合并时间(小时)',
    `median_merge_time_hours` DECIMAL(10,2) COMMENT '中位数合并时间(小时)',
    `same_day_merges` INT UNSIGNED DEFAULT 0 COMMENT '当天合并数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_repo_date` (`repo_id`, `date`),
    KEY `idx_date` (`date`),
    CONSTRAINT `fk_pr_merge_stats_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PR合并时间统计表';

-- Issue 解决时间统计表
CREATE TABLE IF NOT EXISTS `issue_resolve_stats` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `repo_id` INT UNSIGNED NOT NULL COMMENT '仓库ID',
    `date` DATE NOT NULL COMMENT '统计日期',
    `total_issues` INT UNSIGNED DEFAULT 0 COMMENT '总Issue数',
    `closed_issues` INT UNSIGNED DEFAULT 0 COMMENT '已关闭Issue数',
    `avg_resolve_time_hours` DECIMAL(10,2) COMMENT '平均解决时间(小时)',
    `median_resolve_time_hours` DECIMAL(10,2) COMMENT '中位数解决时间(小时)',
    `same_day_resolves` INT UNSIGNED DEFAULT 0 COMMENT '当天解决数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_repo_date` (`repo_id`, `date`),
    KEY `idx_date` (`date`),
    CONSTRAINT `fk_issue_resolve_stats_repo` FOREIGN KEY (`repo_id`) REFERENCES `repositories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Issue解决时间统计表';

-- ==================== 用户表扩展 ====================

-- 添加关注统计字段
-- 注意：ADD COLUMN IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- ALTER TABLE `users` 
-- ADD COLUMN `followers_count` INT UNSIGNED DEFAULT 0 COMMENT '粉丝数',
-- ADD COLUMN `following_count` INT UNSIGNED DEFAULT 0 COMMENT '关注数';

-- ==================== 索引优化 ====================

-- 为现有表添加索引
-- 注意：CREATE INDEX IF NOT EXISTS 在 MySQL 5.7/8.0 不支持
-- 请使用 022_mysql_compatibility_fix.sql 中的存储过程
-- 或直接执行以下语句（首次部署）：
-- CREATE INDEX `idx_pr_merged_at` ON `pull_requests` (`merged_at`);
-- CREATE INDEX `idx_issues_closed_at` ON `issues` (`closed_at`);
-- CREATE INDEX `idx_commits_created_at` ON `commits` (`created_at`);

-- ==================== 初始数据 ====================

-- 插入默认 OAuth 提供商配置
INSERT INTO `oauth_providers` (`platform`, `name`, `client_id`, `client_secret`, `enabled`) VALUES
('wechat', '微信', '', '', 0),
('qq', 'QQ', '', '', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ==================== 迁移完成 ====================
