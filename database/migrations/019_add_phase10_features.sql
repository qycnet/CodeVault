-- Phase 10: 企业级功能
-- SSO、审计日志、容器镜像仓库、多租户

-- ============================================
-- SSO 单点登录
-- ============================================

CREATE TABLE IF NOT EXISTS `sso_providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('saml', 'oauth', 'oidc', 'ldap', 'cas') NOT NULL,
    `config` JSON,
    `enabled` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`),
    INDEX `idx_type` (`type`),
    INDEX `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sso_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `identifier` VARCHAR(255) NOT NULL,
    `provider_data` JSON,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_identifier` (`identifier`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_provider_id` (`provider_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `sso_providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sso_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` VARCHAR(64) NOT NULL,
    `provider_id` INT NOT NULL,
    `user_id` INT,
    `redirect_uri` VARCHAR(500),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_request_id` (`request_id`),
    INDEX `idx_provider_id` (`provider_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`provider_id`) REFERENCES `sso_providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 审计日志
-- ============================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `event` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `user_id` INT,
    `username` VARCHAR(100),
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `resource_type` VARCHAR(50),
    `resource_id` INT,
    `resource_name` VARCHAR(255),
    `old_value` JSON,
    `new_value` JSON,
    `details` JSON,
    `risk_level` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_event` (`event`),
    INDEX `idx_action` (`action`),
    INDEX `idx_resource` (`resource_type`, `resource_id`),
    INDEX `idx_risk_level` (`risk_level`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL,
    `event` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `user_id` INT,
    `ip_address` VARCHAR(45),
    `details` JSON,
    `acknowledged` TINYINT(1) DEFAULT 0,
    `acknowledged_by` INT,
    `acknowledged_at` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_acknowledged` (`acknowledged`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 容器镜像仓库
-- ============================================

CREATE TABLE IF NOT EXISTS `container_repositories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `repo_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `visibility` ENUM('public', 'private') DEFAULT 'private',
    `pull_count` BIGINT DEFAULT 0,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`),
    INDEX `idx_repo_id` (`repo_id`),
    INDEX `idx_visibility` (`visibility`),
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `container_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registry_id` INT NOT NULL,
    `digest` VARCHAR(100) NOT NULL,
    `manifest` LONGTEXT,
    `media_type` VARCHAR(100),
    `config` JSON,
    `layers` JSON,
    `size` BIGINT DEFAULT 0,
    `pushed_by` INT NOT NULL,
    `pushed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_registry_digest` (`registry_id`, `digest`),
    INDEX `idx_registry_id` (`registry_id`),
    INDEX `idx_pushed_at` (`pushed_at`),
    FOREIGN KEY (`registry_id`) REFERENCES `container_repositories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pushed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `container_tags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registry_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `digest` VARCHAR(100) NOT NULL,
    `pushed_by` INT NOT NULL,
    `pushed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_registry_name` (`registry_id`, `name`),
    INDEX `idx_registry_id` (`registry_id`),
    INDEX `idx_digest` (`digest`),
    FOREIGN KEY (`registry_id`) REFERENCES `container_repositories`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pushed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `container_scans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registry_id` INT NOT NULL,
    `digest` VARCHAR(100) NOT NULL,
    `vulnerabilities` JSON,
    `scanned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_registry_digest` (`registry_id`, `digest`),
    INDEX `idx_scanned_at` (`scanned_at`),
    FOREIGN KEY (`registry_id`) REFERENCES `container_repositories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 多租户支持
-- ============================================

CREATE TABLE IF NOT EXISTS `organizations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(255),
    `description` TEXT,
    `avatar_url` VARCHAR(500),
    `website` VARCHAR(500),
    `location` VARCHAR(255),
    `email` VARCHAR(255),
    `plan` ENUM('free', 'team', 'business', 'enterprise') DEFAULT 'free',
    `seats` INT DEFAULT 5,
    `private_repos` INT DEFAULT 0,
    `storage_quota` BIGINT DEFAULT 10737418240, -- 10GB
    `storage_used` BIGINT DEFAULT 0,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_plan` (`plan`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `organization_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `org_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('owner', 'admin', 'member', 'guest') DEFAULT 'member',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_org_user` (`org_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_role` (`role`),
    FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `organization_teams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `org_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `privacy` ENUM('secret', 'closed', 'open') DEFAULT 'closed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_org_slug` (`org_id`, `slug`),
    INDEX `idx_org_id` (`org_id`),
    FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `team_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('maintainer', 'member') DEFAULT 'member',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_team_user` (`team_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`team_id`) REFERENCES `organization_teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `team_repos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `team_id` INT NOT NULL,
    `repo_id` INT NOT NULL,
    `permission` ENUM('read', 'write', 'admin') DEFAULT 'read',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_team_repo` (`team_id`, `repo_id`),
    INDEX `idx_repo_id` (`repo_id`),
    FOREIGN KEY (`team_id`) REFERENCES `organization_teams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`repo_id`) REFERENCES `repositories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 合规功能
-- ============================================

CREATE TABLE IF NOT EXISTS `compliance_policies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `org_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('branch_protection', 'required_reviews', 'signed_commits', 'license_check', 'security_scan') NOT NULL,
    `config` JSON,
    `enabled` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_org_id` (`org_id`),
    INDEX `idx_type` (`type`),
    FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `org_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `status` ENUM('pending', 'generating', 'completed', 'failed') DEFAULT 'pending',
    `file_path` VARCHAR(500),
    `generated_at` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_org_id` (`org_id`),
    INDEX `idx_period` (`period_start`, `period_end`),
    FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 高可用配置
-- ============================================

CREATE TABLE IF NOT EXISTS `ha_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ha_nodes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `node_id` VARCHAR(64) NOT NULL UNIQUE,
    `role` ENUM('primary', 'replica') DEFAULT 'replica',
    `status` ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    `last_heartbeat` DATETIME,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_node_id` (`node_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 触发器：更新存储使用量
-- ============================================

DELIMITER //

CREATE TRIGGER IF NOT EXISTS `tr_update_org_storage`
AFTER INSERT ON `repositories`
FOR EACH ROW
BEGIN
    IF NEW.owner_type = 'organization' THEN
        UPDATE organizations 
        SET storage_used = (
            SELECT COALESCE(SUM(size), 0) 
            FROM repositories 
            WHERE owner_id = NEW.owner_id AND owner_type = 'organization'
        )
        WHERE id = NEW.owner_id;
    END IF;
END//

DELIMITER ;

-- ============================================
-- 初始化数据
-- ============================================

-- 插入默认 SSO 提供商配置模板
INSERT INTO `sso_providers` (`name`, `type`, `config`, `enabled`) VALUES
('GitHub OAuth', 'oauth', '{"client_id": "", "client_secret": "", "authorize_url": "https://github.com/login/oauth/authorize", "token_url": "https://github.com/login/oauth/access_token", "userinfo_url": "https://api.github.com/user", "scope": "user:email"}', 0),
('GitLab OAuth', 'oauth', '{"client_id": "", "client_secret": "", "authorize_url": "https://gitlab.com/oauth/authorize", "token_url": "https://gitlab.com/oauth/token", "userinfo_url": "https://gitlab.com/api/v4/user", "scope": "read_user"}', 0),
('Google OAuth', 'oidc', '{"client_id": "", "client_secret": "", "authorize_url": "https://accounts.google.com/o/oauth2/v2/auth", "token_url": "https://oauth2.googleapis.com/token", "userinfo_url": "https://openidconnect.googleapis.com/v1/userinfo", "scope": "openid profile email"}', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);
