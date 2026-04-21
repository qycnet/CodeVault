-- P2 剩余功能数据表

-- LFS 对象表
CREATE TABLE IF NOT EXISTS lfs_objects (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    repo_id BIGINT NOT NULL,
    oid VARCHAR(64) NOT NULL COMMENT 'SHA256 hash',
    size BIGINT NOT NULL,
    path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_oid (oid),
    INDEX idx_repo (repo_id),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='LFS 对象';

-- 全文搜索索引表（后备方案）
CREATE TABLE IF NOT EXISTS code_index (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    repo_id BIGINT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    content LONGTEXT,
    language VARCHAR(50),
    indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_repo_file (repo_id, file_path),
    FULLTEXT INDEX ft_content (content),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代码索引';
