-- P2 功能数据表

-- 仓库模板标记
ALTER TABLE repositories ADD COLUMN is_template TINYINT(1) DEFAULT 0 COMMENT '是否为模板仓库' AFTER is_private;

-- Issue 模板表
CREATE TABLE IF NOT EXISTS issue_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    repo_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_repo_name (repo_id, name),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Issue 模板';

-- 添加索引
CREATE INDEX idx_template ON repositories(is_template);
