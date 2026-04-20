-- 通知设置表
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_enabled TINYINT(1) DEFAULT 1 COMMENT '是否启用邮件通知',
    notify_issue TINYINT(1) DEFAULT 1 COMMENT 'Issue 通知',
    notify_pr TINYINT(1) DEFAULT 1 COMMENT 'Pull Request 通知',
    notify_comment TINYINT(1) DEFAULT 1 COMMENT '评论通知',
    notify_mention TINYINT(1) DEFAULT 1 COMMENT '@ 提及通知',
    notify_watch TINYINT(1) DEFAULT 1 COMMENT '关注的仓库通知',
    digest_enabled TINYINT(1) DEFAULT 0 COMMENT '是否启用摘要邮件',
    digest_frequency ENUM('daily', 'weekly') DEFAULT 'daily' COMMENT '摘要频率',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知设置表';

-- 邮件队列表（用于异步发送）
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮件队列表';
