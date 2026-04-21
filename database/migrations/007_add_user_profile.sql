-- 用户资料扩展字段
ALTER TABLE users ADD COLUMN bio TEXT COMMENT '个人简介' AFTER email;
ALTER TABLE users ADD COLUMN location VARCHAR(255) COMMENT '位置' AFTER bio;
ALTER TABLE users ADD COLUMN website VARCHAR(500) COMMENT '个人网站' AFTER location;

-- 组织成员角色索引
ALTER TABLE org_members ADD INDEX idx_org_user (org_id, user_id);
