-- Discussions 讨论区功能
-- 创建时间: 2026-04-21

-- 讨论分类表
CREATE TABLE IF NOT EXISTS discussion_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    emoji VARCHAR(10),
    color VARCHAR(7) DEFAULT '#0366d6',
    sort_order INT DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    INDEX idx_slug (slug),
    UNIQUE KEY uk_repo_slug (repository_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 讨论主题表
CREATE TABLE IF NOT EXISTS discussions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    body_html TEXT,
    status ENUM('open', 'closed', 'answered', 'duplicate') DEFAULT 'open',
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    vote_count INT DEFAULT 0,
    accepted_reply_id INT DEFAULT NULL,
    labels JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    INDEX idx_category (category_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_pinned (is_pinned),
    FULLTEXT INDEX ft_title_body (title, body),
    FOREIGN KEY (repository_id) REFERENCES repositories(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES discussion_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 讨论回复表
CREATE TABLE IF NOT EXISTS discussion_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    body TEXT NOT NULL,
    body_html TEXT,
    is_answer TINYINT(1) DEFAULT 0,
    vote_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discussion (discussion_id),
    INDEX idx_user (user_id),
    INDEX idx_parent (parent_id),
    INDEX idx_answer (is_answer),
    FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES discussion_replies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 讨论投票表
CREATE TABLE IF NOT EXISTS discussion_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT DEFAULT NULL,
    reply_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('up', 'down') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_discussion (discussion_id),
    INDEX idx_reply (reply_id),
    INDEX idx_user (user_id),
    UNIQUE KEY uk_user_discussion (user_id, discussion_id),
    UNIQUE KEY uk_user_reply (user_id, reply_id),
    FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_id) REFERENCES discussion_replies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 讨论标签表
CREATE TABLE IF NOT EXISTS discussion_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#0366d6',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    UNIQUE KEY uk_repo_name (repository_id, name),
    FOREIGN KEY (repository_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 讨论订阅表
CREATE TABLE IF NOT EXISTS discussion_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_discussion (discussion_id),
    INDEX idx_user (user_id),
    UNIQUE KEY uk_user_discussion (user_id, discussion_id),
    FOREIGN KEY (discussion_id) REFERENCES discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认分类模板
-- 注意：实际使用时需要根据 repository_id 插入
