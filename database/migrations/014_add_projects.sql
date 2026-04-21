-- Projects 项目管理功能
-- 创建时间: 2026-04-21

-- 项目表
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    team_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    body TEXT,
    state ENUM('open', 'closed') DEFAULT 'open',
    visibility ENUM('public', 'private', 'admin') DEFAULT 'public',
    view_type ENUM('kanban', 'list', 'roadmap') DEFAULT 'kanban',
    start_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    progress INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    INDEX idx_repository (repository_id),
    INDEX idx_user (user_id),
    INDEX idx_team (team_id),
    INDEX idx_state (state),
    INDEX idx_created (created_at),
    FOREIGN KEY (repository_id) REFERENCES repositories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 项目列（看板列）
CREATE TABLE IF NOT EXISTS project_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#0366d6',
    position INT DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    wip_limit INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_position (position),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 项目卡片
CREATE TABLE IF NOT EXISTS project_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column_id INT NOT NULL,
    issue_id INT DEFAULT NULL,
    pull_request_id INT DEFAULT NULL,
    title VARCHAR(255),
    body TEXT,
    position INT DEFAULT 0,
    is_archived TINYINT(1) DEFAULT 0,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    estimated_hours DECIMAL(6,2) DEFAULT NULL,
    actual_hours DECIMAL(6,2) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_by INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL,
    INDEX idx_column (column_id),
    INDEX idx_issue (issue_id),
    INDEX idx_pr (pull_request_id),
    INDEX idx_position (position),
    INDEX idx_archived (is_archived),
    INDEX idx_assigned (assigned_to),
    FOREIGN KEY (column_id) REFERENCES project_columns(id) ON DELETE CASCADE,
    FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE SET NULL,
    FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 卡片标签关联
CREATE TABLE IF NOT EXISTS project_card_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    label_id INT NOT NULL,
    INDEX idx_card (card_id),
    INDEX idx_label (label_id),
    UNIQUE KEY uk_card_label (card_id, label_id),
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 卡片检查项
CREATE TABLE IF NOT EXISTS project_card_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_card (card_id),
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 检查项条目
CREATE TABLE IF NOT EXISTS project_card_checklist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    content VARCHAR(500) NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    position INT DEFAULT 0,
    completed_by INT DEFAULT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_checklist (checklist_id),
    INDEX idx_completed (is_completed),
    FOREIGN KEY (checklist_id) REFERENCES project_card_checklists(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 卡片评论
CREATE TABLE IF NOT EXISTS project_card_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card (card_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 卡片附件
CREATE TABLE IF NOT EXISTS project_card_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_card (card_id),
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 项目成员
CREATE TABLE IF NOT EXISTS project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'write', 'read') DEFAULT 'write',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_user (user_id),
    UNIQUE KEY uk_project_user (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 项目活动日志
CREATE TABLE IF NOT EXISTS project_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    card_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_card (card_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 自定义字段定义
CREATE TABLE IF NOT EXISTS project_custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'number', 'date', 'select', 'multiselect', 'user', 'checkbox') NOT NULL,
    options JSON,
    is_required TINYINT(1) DEFAULT 0,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 自定义字段值
CREATE TABLE IF NOT EXISTS project_custom_field_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    field_id INT NOT NULL,
    value TEXT,
    INDEX idx_card (card_id),
    INDEX idx_field (field_id),
    UNIQUE KEY uk_card_field (card_id, field_id),
    FOREIGN KEY (card_id) REFERENCES project_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES project_custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
