-- Sponsors 赞助系统
-- 创建时间: 2026-04-21

-- 赞助计划表
CREATE TABLE IF NOT EXISTS sponsor_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    monthly_amount DECIMAL(10,2) NOT NULL,
    yearly_amount DECIMAL(10,2) DEFAULT NULL,
    one_time_amount DECIMAL(10,2) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#0366d6',
    icon VARCHAR(50) DEFAULT 'heart',
    benefits JSON,
    is_active TINYINT(1) DEFAULT 1,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助者表
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_user_id INT NOT NULL,
    creator_user_id INT NOT NULL,
    tier_id INT DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'CNY',
    payment_method ENUM('alipay', 'wechat', 'stripe', 'paypal', 'other') DEFAULT 'alipay',
    payment_id VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'active', 'cancelled', 'expired', 'refunded') DEFAULT 'pending',
    frequency ENUM('one_time', 'monthly', 'yearly') DEFAULT 'monthly',
    is_anonymous TINYINT(1) DEFAULT 0,
    message TEXT,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    next_billing_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sponsor (sponsor_user_id),
    INDEX idx_creator (creator_user_id),
    INDEX idx_tier (tier_id),
    INDEX idx_status (status),
    INDEX idx_payment (payment_id),
    INDEX idx_next_billing (next_billing_date),
    FOREIGN KEY (sponsor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES sponsor_tiers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助历史记录
CREATE TABLE IF NOT EXISTS sponsor_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'CNY',
    payment_method VARCHAR(50) NOT NULL,
    payment_id VARCHAR(255),
    status ENUM('success', 'failed', 'pending', 'refunded') DEFAULT 'pending',
    transaction_type ENUM('payment', 'refund', 'subscription') DEFAULT 'payment',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_payment (payment_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助者徽章
CREATE TABLE IF NOT EXISTS sponsor_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_type ENUM('bronze', 'silver', 'gold', 'platinum', 'diamond') DEFAULT 'bronze',
    total_sponsored DECIMAL(10,2) DEFAULT 0,
    months_sponsored INT DEFAULT 0,
    is_displayed TINYINT(1) DEFAULT 1,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_badge (badge_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助奖励
CREATE TABLE IF NOT EXISTS sponsor_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    reward_type ENUM('badge', 'access', 'content', 'early_access', 'custom') DEFAULT 'badge',
    reward_data JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tier (tier_id),
    FOREIGN KEY (tier_id) REFERENCES sponsor_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助者奖励领取记录
CREATE TABLE IF NOT EXISTS sponsor_reward_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT NOT NULL,
    reward_id INT NOT NULL,
    status ENUM('pending', 'fulfilled', 'cancelled') DEFAULT 'pending',
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fulfilled_at TIMESTAMP NULL,
    notes TEXT,
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_reward (reward_id),
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES sponsor_rewards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助目标
CREATE TABLE IF NOT EXISTS sponsor_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    target_amount DECIMAL(10,2) NOT NULL,
    current_amount DECIMAL(10,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'CNY',
    deadline DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_achieved TINYINT(1) DEFAULT 0,
    achieved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助私密内容
CREATE TABLE IF NOT EXISTS sponsor_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    content_type ENUM('post', 'file', 'video', 'link') DEFAULT 'post',
    file_path VARCHAR(500) DEFAULT NULL,
    is_public TINYINT(1) DEFAULT 0,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_tier (tier_id),
    INDEX idx_published (published_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES sponsor_tiers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助者访问记录
CREATE TABLE IF NOT EXISTS sponsor_content_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    sponsor_id INT NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_content (content_id),
    INDEX idx_sponsor (sponsor_id),
    FOREIGN KEY (content_id) REFERENCES sponsor_content(id) ON DELETE CASCADE,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 赞助统计
CREATE TABLE IF NOT EXISTS sponsor_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_sponsors INT DEFAULT 0,
    active_sponsors INT DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0,
    monthly_earnings DECIMAL(10,2) DEFAULT 0,
    yearly_earnings DECIMAL(10,2) DEFAULT 0,
    total_transactions INT DEFAULT 0,
    avg_sponsorship DECIMAL(10,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 支付安全表
CREATE TABLE IF NOT EXISTS payment_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(128) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    request_data TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_id (payment_id, payment_method),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_nonces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nonce VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 支付配置表
CREATE TABLE IF NOT EXISTS payment_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(20) NOT NULL UNIQUE,
    config_name VARCHAR(100) NOT NULL,
    config_value TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认支付配置
INSERT INTO payment_configs (payment_method, config_name, config_value) VALUES
('alipay', '支付宝', '{"app_id": "", "public_key": "", "private_key": "", "notify_url": ""}'),
('wechat', '微信支付', '{"app_id": "", "mch_id": "", "api_key": "", "notify_url": ""}'),
('stripe', 'Stripe', '{"publishable_key": "", "secret_key": "", "webhook_secret": ""}'),
('paypal', 'PayPal', '{"client_id": "", "client_secret": "", "mode": "sandbox"}');
