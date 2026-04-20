-- 分支保护规则表
CREATE TABLE IF NOT EXISTS branch_protections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT NOT NULL,
    branch_name VARCHAR(255) NOT NULL,
    require_pr TINYINT(1) DEFAULT 1 COMMENT '是否要求通过 PR 提交',
    required_reviewers INT DEFAULT 0 COMMENT '要求的审查者数量',
    dismiss_stale_reviews TINYINT(1) DEFAULT 0 COMMENT '新提交时是否清除旧的审批',
    require_status_checks TINYINT(1) DEFAULT 0 COMMENT '是否要求状态检查通过',
    enforce_admins TINYINT(1) DEFAULT 0 COMMENT '是否对管理员强制执行',
    allow_force_pushes TINYINT(1) DEFAULT 0 COMMENT '是否允许强制推送',
    allow_deletions TINYINT(1) DEFAULT 0 COMMENT '是否允许删除分支',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_repo_branch (repo_id, branch_name),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分支保护规则表';

-- 协作者表（用于仓库权限管理）
CREATE TABLE IF NOT EXISTS collaborators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repo_id INT NOT NULL,
    user_id INT NOT NULL,
    permission ENUM('read', 'write', 'admin') DEFAULT 'write',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_repo_user (repo_id, user_id),
    FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库协作者表';
