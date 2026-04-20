-- 工作流表
CREATE TABLE IF NOT EXISTS workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    config TEXT NOT NULL COMMENT 'YAML 配置',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repo (repo_id),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作流定义表';

-- 工作流运行记录表
CREATE TABLE IF NOT EXISTS workflow_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    status ENUM('pending', 'running', 'success', 'failed', 'cancelled') DEFAULT 'pending',
    branch VARCHAR(255) DEFAULT 'main',
    commit_sha VARCHAR(40),
    triggered_by INT COMMENT '触发用户ID',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    logs LONGTEXT COMMENT '执行日志',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workflow (workflow_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工作流运行记录表';
