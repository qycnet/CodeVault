-- P0 功能完善数据表

-- API Tokens 表
CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Token 名称',
    token_hash VARCHAR(64) NOT NULL COMMENT 'Token SHA256 哈希',
    token_prefix VARCHAR(16) NOT NULL COMMENT 'Token 前缀（用于识别）',
    scopes JSON COMMENT '权限范围',
    last_used_at TIMESTAMP NULL COMMENT '最后使用时间',
    expires_at TIMESTAMP NULL COMMENT '过期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_token_hash (token_hash),
    INDEX idx_user (user_id),
    INDEX idx_prefix (token_prefix),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API 访问令牌';

-- OAuth 应用表
CREATE TABLE IF NOT EXISTS oauth_applications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL COMMENT '应用所有者',
    name VARCHAR(100) NOT NULL COMMENT '应用名称',
    client_id VARCHAR(64) NOT NULL COMMENT '客户端 ID',
    client_secret_hash VARCHAR(255) NOT NULL COMMENT '客户端密钥哈希',
    redirect_uri VARCHAR(500) NOT NULL COMMENT '回调地址',
    description TEXT COMMENT '应用描述',
    homepage_url VARCHAR(500) COMMENT '主页地址',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_client_id (client_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth 应用';

-- OAuth 授权记录表
CREATE TABLE IF NOT EXISTS oauth_authorizations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    app_id BIGINT NOT NULL,
    scopes VARCHAR(500) NOT NULL COMMENT '授权范围',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_app (user_id, app_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES oauth_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth 用户授权';

-- OAuth 授权码表
CREATE TABLE IF NOT EXISTS oauth_auth_codes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL COMMENT '授权码',
    user_id BIGINT NOT NULL,
    app_id BIGINT NOT NULL,
    scopes VARCHAR(500) NOT NULL,
    redirect_uri VARCHAR(500) NOT NULL,
    used_at TIMESTAMP NULL COMMENT '使用时间',
    expires_at TIMESTAMP NOT NULL COMMENT '过期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_code (code),
    INDEX idx_app (app_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES oauth_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth 授权码';

-- OAuth Token 表
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    app_id BIGINT NOT NULL,
    user_id BIGINT NULL COMMENT '用户 ID（client_credentials 可能为空）',
    access_token VARCHAR(128) NOT NULL COMMENT '访问令牌',
    refresh_token VARCHAR(128) NULL COMMENT '刷新令牌',
    scopes VARCHAR(500) NOT NULL COMMENT '权限范围',
    expires_at TIMESTAMP NOT NULL COMMENT '过期时间',
    revoked_at TIMESTAMP NULL COMMENT '撤销时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_access_token (access_token),
    INDEX idx_refresh_token (refresh_token),
    INDEX idx_app_user (app_id, user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (app_id) REFERENCES oauth_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OAuth 访问令牌';

-- 工作流调度日志表
CREATE TABLE IF NOT EXISTS workflow_schedule_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    workflow_id BIGINT NOT NULL,
    run_id BIGINT NOT NULL,
    cron_expr VARCHAR(100) NOT NULL COMMENT 'Cron 表达式',
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_workflow (workflow_id),
    INDEX idx_triggered (triggered_at),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工作流调度日志';

-- 为 workflow_runs 添加 event 字段（如果不存在）
ALTER TABLE workflow_runs ADD COLUMN IF NOT EXISTS event VARCHAR(50) DEFAULT 'push' COMMENT '触发事件';
