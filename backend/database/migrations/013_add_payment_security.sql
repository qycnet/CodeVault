-- 支付安全相关表

-- 支付记录表
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(64) NOT NULL UNIQUE COMMENT '内部支付 ID',
    user_id INT NOT NULL COMMENT '用户 ID',
    order_id VARCHAR(64) COMMENT '订单 ID',
    amount DECIMAL(10, 2) NOT NULL COMMENT '支付金额',
    currency VARCHAR(10) DEFAULT 'CNY' COMMENT '货币',
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending' COMMENT '支付状态',
    platform VARCHAR(20) COMMENT '支付平台 (alipay/wechat/stripe)',
    transaction_id VARCHAR(128) COMMENT '第三方交易号',
    paid_at DATETIME COMMENT '支付成功时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_order_id (order_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付记录表';

-- 支付日志表
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(64) NOT NULL COMMENT '支付 ID',
    action VARCHAR(50) NOT NULL COMMENT '操作类型',
    data JSON COMMENT '操作数据',
    success TINYINT(1) DEFAULT 0 COMMENT '是否成功',
    ip_address VARCHAR(45) COMMENT 'IP 地址',
    user_agent VARCHAR(500) COMMENT 'User Agent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_payment_id (payment_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付日志表';

-- 支付配置表
CREATE TABLE IF NOT EXISTS payment_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(20) NOT NULL UNIQUE COMMENT '支付平台',
    config JSON NOT NULL COMMENT '配置信息 (加密存储)',
    enabled TINYINT(1) DEFAULT 1 COMMENT '是否启用',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付配置表';

-- 插入默认配置
INSERT INTO payment_configs (platform, config) VALUES
('alipay', '{"app_id": "", "public_key": "", "private_key": "", "sandbox": true}'),
('wechat', '{"app_id": "", "mch_id": "", "api_key": "", "sandbox": true}'),
('stripe', '{"public_key": "", "secret_key": "", "webhook_secret": "", "sandbox": true}');
