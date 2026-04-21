-- P2 功能数据表

-- releases 表
CREATE TABLE IF NOT EXISTS releases (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    repo_id BIGINT NOT NULL,
    tag_name VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    body TEXT,
    prerelease TINYINT(1) DEFAULT 0,
    author_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_repo_tag (repo_id, tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Release 发布';

-- webhook_logs 表
CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    webhook_id BIGINT NOT NULL,
    event VARCHAR(50) NOT NULL,
    status ENUM('success', 'failed') DEFAULT 'failed',
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook (webhook_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Webhook 日志';

-- 添加 fork_from 字段到 repositories 表
ALTER TABLE repositories ADD COLUMN fork_from BIGINT NULL COMMENT 'Fork 来源仓库ID' AFTER is_private;
ALTER TABLE repositories ADD COLUMN star_count INT DEFAULT 0 COMMENT 'Star 数量' AFTER fork_from;

-- 添加索引
ALTER TABLE repositories ADD INDEX idx_fork_from (fork_from);
ALTER TABLE stars ADD INDEX idx_user_repo (user_id, repo_id);
