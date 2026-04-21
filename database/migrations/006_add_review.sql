-- 审查表
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pr_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'changes_requested', 'commented') DEFAULT 'pending',
    body TEXT,
    submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr (pr_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (pr_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PR 审查表';

-- 行内评论表
CREATE TABLE IF NOT EXISTS line_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pr_id INT NOT NULL,
    user_id INT NOT NULL,
    file VARCHAR(500) NOT NULL,
    line INT NOT NULL,
    start_line INT DEFAULT NULL COMMENT '多行评论起始行',
    side ENUM('LEFT', 'RIGHT') DEFAULT 'RIGHT' COMMENT '左侧（旧）或右侧（新）',
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pr (pr_id),
    INDEX idx_file (file(191)),
    FOREIGN KEY (pr_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='代码行内评论表';
