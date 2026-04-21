-- Phase 5 体验优化 - 数据库迁移

-- 翻译表
CREATE TABLE IF NOT EXISTS translations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(10) NOT NULL,
    domain VARCHAR(50) NOT NULL DEFAULT 'messages',
    `key` VARCHAR(255) NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_locale_domain_key (locale, domain, `key`),
    INDEX idx_locale (locale),
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户语言偏好
ALTER TABLE users ADD COLUMN IF NOT EXISTS locale VARCHAR(10) DEFAULT 'zh-CN';
ALTER TABLE users ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'Asia/Shanghai';

-- 性能日志表
CREATE TABLE IF NOT EXISTS performance_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    duration_ms FLOAT,
    memory_usage INT UNSIGNED,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 资源版本表
CREATE TABLE IF NOT EXISTS asset_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(255) NOT NULL UNIQUE,
    version VARCHAR(32) NOT NULL,
    content_hash VARCHAR(64),
    size INT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认翻译
INSERT INTO translations (locale, domain, `key`, value) VALUES
-- 中文
('zh-CN', 'messages', 'app.name', 'CodeVault'),
('zh-CN', 'messages', 'app.description', '自托管 Git 托管平台'),
('zh-CN', 'messages', 'nav.home', '首页'),
('zh-CN', 'messages', 'nav.repositories', '仓库'),
('zh-CN', 'messages', 'nav.issues', '问题'),
('zh-CN', 'messages', 'nav.pull_requests', '合并请求'),
('zh-CN', 'messages', 'nav.actions', '工作流'),
('zh-CN', 'messages', 'nav.settings', '设置'),
('zh-CN', 'messages', 'repo.create', '创建仓库'),
('zh-CN', 'messages', 'repo.fork', '复刻'),
('zh-CN', 'messages', 'repo.star', '收藏'),
('zh-CN', 'messages', 'repo.watch', '关注'),
('zh-CN', 'messages', 'issue.open', '开启问题'),
('zh-CN', 'messages', 'issue.close', '关闭问题'),
('zh-CN', 'messages', 'issue.comment', '评论'),
('zh-CN', 'messages', 'pr.create', '创建合并请求'),
('zh-CN', 'messages', 'pr.merge', '合并'),
('zh-CN', 'messages', 'pr.approve', '批准'),
('zh-CN', 'messages', 'user.login', '登录'),
('zh-CN', 'messages', 'user.logout', '退出'),
('zh-CN', 'messages', 'user.register', '注册'),
('zh-CN', 'messages', 'user.profile', '个人资料'),

-- 英文
('en-US', 'messages', 'app.name', 'CodeVault'),
('en-US', 'messages', 'app.description', 'Self-hosted Git hosting platform'),
('en-US', 'messages', 'nav.home', 'Home'),
('en-US', 'messages', 'nav.repositories', 'Repositories'),
('en-US', 'messages', 'nav.issues', 'Issues'),
('en-US', 'messages', 'nav.pull_requests', 'Pull Requests'),
('en-US', 'messages', 'nav.actions', 'Workflows'),
('en-US', 'messages', 'nav.settings', 'Settings'),
('en-US', 'messages', 'repo.create', 'Create Repository'),
('en-US', 'messages', 'repo.fork', 'Fork'),
('en-US', 'messages', 'repo.star', 'Star'),
('en-US', 'messages', 'repo.watch', 'Watch'),
('en-US', 'messages', 'issue.open', 'Open Issue'),
('en-US', 'messages', 'issue.close', 'Close Issue'),
('en-US', 'messages', 'issue.comment', 'Comment'),
('en-US', 'messages', 'pr.create', 'Create Pull Request'),
('en-US', 'messages', 'pr.merge', 'Merge'),
('en-US', 'messages', 'pr.approve', 'Approve'),
('en-US', 'messages', 'user.login', 'Sign In'),
('en-US', 'messages', 'user.logout', 'Sign Out'),
('en-US', 'messages', 'user.register', 'Sign Up'),
('en-US', 'messages', 'user.profile', 'Profile'),

-- 日文
('ja-JP', 'messages', 'app.name', 'CodeVault'),
('ja-JP', 'messages', 'app.description', '自己ホスト型Gitホスティングプラットフォーム'),
('ja-JP', 'messages', 'nav.home', 'ホーム'),
('ja-JP', 'messages', 'nav.repositories', 'リポジトリ'),
('ja-JP', 'messages', 'nav.issues', 'イシュー'),
('ja-JP', 'messages', 'nav.pull_requests', 'プルリクエスト'),
('ja-JP', 'messages', 'nav.actions', 'ワークフロー'),
('ja-JP', 'messages', 'nav.settings', '設定'),
('ja-JP', 'messages', 'repo.create', 'リポジトリを作成'),
('ja-JP', 'messages', 'repo.fork', 'フォーク'),
('ja-JP', 'messages', 'repo.star', 'スター'),
('ja-JP', 'messages', 'repo.watch', 'ウォッチ'),
('ja-JP', 'messages', 'issue.open', 'イシューを開く'),
('ja-JP', 'messages', 'issue.close', 'イシューを閉じる'),
('ja-JP', 'messages', 'issue.comment', 'コメント'),
('ja-JP', 'messages', 'pr.create', 'プルリクエストを作成'),
('ja-JP', 'messages', 'pr.merge', 'マージ'),
('ja-JP', 'messages', 'pr.approve', '承認'),
('ja-JP', 'messages', 'user.login', 'ログイン'),
('ja-JP', 'messages', 'user.logout', 'ログアウト'),
('ja-JP', 'messages', 'user.register', '登録'),
('ja-JP', 'messages', 'user.profile', 'プロフィール'),

-- 韩文
('ko-KR', 'messages', 'app.name', 'CodeVault'),
('ko-KR', 'messages', 'app.description', '자체 호스팅 Git 호스팅 플랫폼'),
('ko-KR', 'messages', 'nav.home', '홈'),
('ko-KR', 'messages', 'nav.repositories', '저장소'),
('ko-KR', 'messages', 'nav.issues', '이슈'),
('ko-KR', 'messages', 'nav.pull_requests', '풀 리퀘스트'),
('ko-KR', 'messages', 'nav.actions', '워크플로우'),
('ko-KR', 'messages', 'nav.settings', '설정'),
('ko-KR', 'messages', 'repo.create', '저장소 생성'),
('ko-KR', 'messages', 'repo.fork', '포크'),
('ko-KR', 'messages', 'repo.star', '스타'),
('ko-KR', 'messages', 'repo.watch', '워치'),
('ko-KR', 'messages', 'issue.open', '이슈 열기'),
('ko-KR', 'messages', 'issue.close', '이슈 닫기'),
('ko-KR', 'messages', 'issue.comment', '댓글'),
('ko-KR', 'messages', 'pr.create', '풀 리퀘스트 생성'),
('ko-KR', 'messages', 'pr.merge', '병합'),
('ko-KR', 'messages', 'pr.approve', '승인'),
('ko-KR', 'messages', 'user.login', '로그인'),
('ko-KR', 'messages', 'user.logout', '로그아웃'),
('ko-KR', 'messages', 'user.register', '가입'),
('ko-KR', 'messages', 'user.profile', '프로필');
