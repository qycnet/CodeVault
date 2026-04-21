# CodeVault

基于 PHP 原生实现的轻量级代码仓库管理系统（GitHub Clone）。

## 项目概览

| 指标 | 数据 |
|------|------|
| 完成度 | **100%** |
| 新增文件 | 115+ 个 |
| 新增代码 | 40,000+ 行 |
| P0 功能 | 16/16 ✅ |
| P1 功能 | 8/8 ✅ |
| P2 功能 | 7/7 ✅ |
| Phase 4 社区功能 | 3/3 ✅ |
| Phase 5 体验优化 | 3/3 ✅ |
| 测试覆盖 | 95+ 测试用例 |
| 支持语言 | 11 种 |

---

## 功能特性

### P0 - 核心功能 ✅

#### 用户系统
- ✅ 邮箱注册（验证码验证）
- ✅ 用户登录（Session 管理）
- ✅ SSH Key 管理（支持 RSA/ED25519）

#### 代码仓库
- ✅ 创建/列表/详情/更新/删除仓库
- ✅ Git 裸仓库自动初始化
- ✅ 公开/私有仓库支持
- ✅ 访问权限控制

#### Git 操作
- ✅ Clone/Push/Pull 操作
- ✅ SSH Key 认证
- ✅ 状态查询
- ✅ 提交历史查看
- ✅ 分支管理

#### Issue 管理
- ✅ 创建/列表/详情/更新/删除 Issue
- ✅ 状态管理（open/closed）
- ✅ 权限验证

#### Pull Request
- ✅ 创建/查看/合并/关闭 PR
- ✅ 代码 Diff 查看
- ✅ 行内评论
- ✅ 分支验证

#### 评论系统
- ✅ Issue 评论
- ✅ PR 评论
- ✅ 行内代码评论
- ✅ 编辑/删除评论

#### Actions 执行引擎
- ✅ 工作流定义（YAML）
- ✅ 工作流执行
- ✅ 日志查看
- ✅ 状态检查
- ✅ **定时触发器**（Cron 表达式）
- ✅ **日志实时输出**（WebSocket）
- ✅ **可视化编辑器**（拖拽式）

#### Email 通知
- ✅ 邮件发送
- ✅ Issue/PR 通知
- ✅ 评论通知

#### REST API
- ✅ 完整 API 接口
- ✅ JSON 响应
- ✅ 错误处理
- ✅ **API Token 管理**
  - Token CRUD
  - 权限范围管理
  - SHA256 哈希存储

#### 分支保护规则
- ✅ 保护分支设置
- ✅ PR 必须审核
- ✅ 状态检查要求

#### 文件上传
- ✅ Web 文件上传
- ✅ 安全文件类型检查
- ✅ 路径验证

#### OAuth2 授权
- ✅ 授权服务器
- ✅ 应用注册/管理
- ✅ 授权码流程
- ✅ 刷新令牌
- ✅ 客户端凭证授权

#### 通知中心完善
- ✅ 通知聚合展示
- ✅ WebSocket 实时推送
- ✅ 桌面通知支持
- ✅ 按类型/仓库筛选

---

### P1 - 高级功能 ✅

#### 1. 安全扫描
- ✅ 依赖漏洞扫描（npm/packagist/pypi/maven/go）
- ✅ 代码安全扫描（敏感信息泄露/SQL注入/XSS/命令注入）
- ✅ 已知漏洞数据库（CVE 集成）
- ✅ 扫描历史记录

#### 2. 代码审查增强
- ✅ 审批流程（approved/changes_requested/commented）
- ✅ 批量评论功能
- ✅ 分支保护规则检查
- ✅ 合并前状态检查
- ✅ 审查统计

#### 3. 组织团队管理
- ✅ 团队 CRUD 操作
- ✅ 成员管理（添加/移除/角色）
- ✅ 仓库权限分配
- ✅ 批量权限操作
- ✅ 组织成员管理

#### 4. 高级搜索
- ✅ 高级搜索语法支持
  - `repo:owner/name` 指定仓库
  - `user:username` 指定用户
  - `lang:language` 指定语言
  - `is:issue/is:pr/is:open/is:closed` 状态过滤
  - `label:name` 标签过滤
  - `created:>2024-01-01` 日期过滤
- ✅ 仓库/Issue/PR/代码/用户 统一搜索

#### 5. 依赖漏洞告警
- ✅ 自动检测依赖漏洞
- ✅ 多语言包管理器支持
- ✅ 漏洞等级分类

#### 6. 管理后台增强
- ✅ 系统概览仪表盘
- ✅ 用户管理（禁用/启用/管理员设置）
- ✅ 仓库管理（列表/删除）
- ✅ 系统配置管理
- ✅ 日志查看
- ✅ 数据清理

#### 7. Redis 缓存优化
- ✅ 仓库/用户/Issue/提交/文件缓存
- ✅ remember 回调模式
- ✅ 计数器支持
- ✅ 模式删除

#### 8. 队列系统
- ✅ 基于 Redis Stream 的消息队列
- ✅ 延迟任务支持
- ✅ Git/Email/Notification/Webhook 队列
- ✅ 后备直接执行模式

---

### P2 - 扩展功能 ✅

#### 1. Dark Mode
- ✅ CSS 变量主题切换
- ✅ Element Plus 暗色主题覆盖
- ✅ 本地存储持久化

#### 2. 快捷键支持
- ✅ `Ctrl+/` 搜索
- ✅ `Ctrl+N` 新建仓库
- ✅ `Ctrl+I` 我的 Issue
- ✅ `Ctrl+P` 我的 PR
- ✅ `Shift+?` 显示帮助
- ✅ `Escape` 关闭弹窗

#### 3. 仓库模板
- ✅ 从模板创建仓库
- ✅ 设置/取消模板
- ✅ 模板列表
- ✅ 官方模板（PHP/Vue/Python/Go）

#### 4. Issue/PR 模板
- ✅ 从 .github/ISSUE_TEMPLATE/ 读取
- ✅ 默认 Bug 报告模板
- ✅ 默认功能请求模板
- ✅ PR 模板支持

#### 5. 全文代码搜索
- ✅ Elasticsearch / Meilisearch 支持
- ✅ 仓库代码索引
- ✅ 高级搜索（语言/文件路径过滤）
- ✅ 后备 git grep 方案

#### 6. GraphQL API
- ✅ Query: viewer/repository/repositories/user/users/issue/pullRequest/search
- ✅ Mutation: createRepository/createIssue/createPullRequest/updateIssue/closeIssue
- ✅ 完整 Schema 定义
- ✅ GraphQL Playground 支持

#### 7. Git LFS
- ✅ LFS 批量 API（上传/下载）
- ✅ 对象存储（分片存储）
- ✅ SHA256 验证
- ✅ 清理未引用对象

---

### Phase 4 - 社区功能 ✅ NEW

#### 1. Discussions 讨论区
- ✅ 分类讨论（公告/一般/Q&A/想法/展示）
- ✅ 嵌套回复
- ✅ 投票系统（up/down）
- ✅ 精选回答标记
- ✅ 置顶/锁定功能
- ✅ 搜索和筛选

#### 2. Projects 项目管理
- ✅ Kanban 看板视图
- ✅ 拖拽排序
- ✅ Issue/PR 关联
- ✅ 优先级和截止日期
- ✅ 负责人分配
- ✅ 进度统计
- ✅ 自定义字段

#### 3. Sponsors 赞助系统
- ✅ 赞助等级管理（Bronze/Silver/Gold/Platinum/Diamond）
- ✅ 月度/年度/一次性赞助
- ✅ 徽章奖励系统
- ✅ 赞助目标进度
- ✅ 私密内容访问
- ✅ 匿名赞助支持
- ✅ **支付安全措施**
  - 支付平台签名验证（支付宝/微信/Stripe/PayPal）
  - 金额二次校验
  - payment_id 唯一性检查（防重放）
  - Nonce 验证
  - 时间戳验证

---

### Phase 5 - 体验优化 ✅ NEW

#### 1. 移动端适配
- ✅ 响应式布局（5个断点）
- ✅ 移动端导航（底部导航栏/抽屉菜单）
- ✅ 移动端组件适配
- ✅ 触摸手势支持（滑动删除/下拉刷新）
- ✅ iOS 安全区域适配
- ✅ 暗色主题移动端支持

#### 2. 性能优化
- ✅ 性能监控（计时器/慢查询日志）
- ✅ 数据库优化（分析表/优化表/索引建议）
- ✅ 缓存优化（预热/清理/统计）
- ✅ 资源优化（压缩/版本号）

#### 3. 国际化支持
- ✅ **支持 11 种语言**
  - zh-CN 简体中文
  - zh-TW 繁體中文
  - en-US English (US)
  - en-GB English (UK)
  - ja-JP 日本語
  - ko-KR 한국어
  - es-ES Español
  - fr-FR Français
  - de-DE Deutsch
  - ru-RU Русский
  - ar-SA العربية (RTL)
- ✅ 语言自动检测
- ✅ 时区处理
- ✅ 数字/货币格式化
- ✅ RTL 语言支持

---

## 技术栈

- **后端**: PHP 8.0+ (原生，无框架)
- **数据库**: MySQL 5.7+
- **前端**: Vue 3 + TypeScript + Element Plus + Vite
- **缓存**: Redis 7.0+
- **搜索**: Elasticsearch / Meilisearch
- **容器化**: Docker + Docker Compose
- **Git**: Git 2.0+

---

## 安全特性

| 项目 | 状态 |
|------|------|
| SQL 注入防护 | ✅ PDO 预处理 |
| 命令注入防护 | ✅ escapeshellarg + 白名单 |
| XSS 防护 | ✅ htmlspecialchars + 纯文本渲染 |
| 密码哈希 | ✅ bcrypt (cost=12) |
| Session 安全 | ✅ HttpOnly + SameSite=Lax |
| CORS 限制 | ✅ 白名单 |
| OAuth2 安全 | ✅ random_bytes + bcrypt |
| API Token 安全 | ✅ SHA256 哈希存储 |
| 文件上传安全 | ✅ 黑名单 + 路径验证 |
| 支付安全 | ✅ 签名验证 + 金额校验 + 防重放 |

---

## 目录结构

```
codevault/
├── config/                 # 配置文件
├── database/
│   ├── migrations/        # 数据库迁移 (001-016)
│   ├── migrate.php        # 迁移脚本
│   └── schema.sql         # 完整表结构
├── frontend/              # Vue 3 前端项目
│   ├── src/
│   │   ├── views/         # 页面组件
│   │   ├── components/    # 通用组件
│   │   ├── layouts/       # 布局组件
│   │   │   └── MobileLayout.vue  # 移动端布局
│   │   ├── api/           # API 封装
│   │   ├── stores/        # Pinia 状态管理
│   │   ├── router/        # 路由配置
│   │   ├── styles/        # 样式文件
│   │   │   └── mobile.css # 移动端样式
│   │   └── utils/         # 工具函数
│   ├── vite.config.ts     # Vite 配置
│   └── package.json
├── public/
│   └── index.php          # API 入口
├── src/
│   ├── Controllers/       # 控制器
│   ├── Database/          # 数据库层
│   ├── Models/            # 模型
│   ├── Services/          # 服务层
│   │   ├── SecurityScanner.php
│   │   ├── CodeReviewService.php
│   │   ├── AdvancedSearchService.php
│   │   ├── CacheService.php
│   │   ├── QueueService.php
│   │   ├── FullTextSearchService.php
│   │   ├── GraphQLService.php
│   │   ├── LfsService.php
│   │   ├── DiscussionService.php
│   │   ├── ProjectService.php
│   │   ├── SponsorService.php
│   │   ├── PaymentSecurityService.php
│   │   ├── PerformanceService.php
│   │   ├── I18nService.php
│   │   └── ...
│   └── Core/              # 核心组件
│       ├── ErrorHandler.php
│       ├── Logger.php
│       └── QueryOptimizer.php
├── tests/                 # 测试文件
│   ├── BaseTestCase.php   # 测试基类
│   ├── Services/          # 服务测试
│   ├── Controllers/       # 控制器测试
│   └── Security/          # 安全测试
├── docs/                  # 文档
├── Dockerfile             # Docker 镜像
├── docker-compose.yml     # Docker Compose 配置
└── README.md
```

---

## 环境要求

- PHP >= 8.0
- MySQL >= 5.7
- Redis >= 7.0
- Git >= 2.0
- Node.js >= 18.0
- PHP 扩展：pdo, pdo_mysql, json, mbstring, redis

---

## 快速部署

### Docker 部署（推荐）

```bash
# 1. 克隆项目
git clone https://github.com/qycnet/CodeVault.git
cd codevault

# 2. 启动服务
docker-compose up -d

# 3. 访问应用
# http://localhost:8080
```

### 手动部署

```bash
# 1. 克隆项目
git clone https://github.com/qycnet/CodeVault.git
cd codevault

# 2. 安装前端依赖
cd frontend
npm install
npm run build
cd ..

# 3. 配置环境变量
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=codevault
export DB_USER=codevault_user
export DB_PASS=your_password
export REDIS_HOST=localhost
export REDIS_PORT=6379

# 4. 创建数据库
mysql -u root -p << EOF
CREATE DATABASE codevault DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'codevault_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON codevault.* TO 'codevault_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# 5. 初始化数据库
php database/migrate.php

# 6. 创建 Git 仓库目录
sudo mkdir -p /var/git/repositories
sudo mkdir -p /var/git/lfs
sudo chown -R www-data:www-data /var/git
sudo chmod -R 755 /var/git

# 7. 启动服务
cd public
php -S localhost:8000
```

---

## API 接口

### 认证相关

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/send-code | 发送注册验证码 |
| POST | /api/auth/register | 用户注册 |
| POST | /api/auth/login | 用户登录 |
| POST | /api/auth/logout | 用户登出 |
| GET | /api/auth/me | 获取当前用户信息 |

### 仓库管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/repos | 获取仓库列表 |
| POST | /api/repos | 创建仓库 |
| GET | /api/repos/detail | 获取仓库详情 |
| PUT | /api/repos | 更新仓库 |
| DELETE | /api/repos | 删除仓库 |

### Git 操作

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/repos/tree | 获取文件树 |
| GET | /api/repos/commits | 获取提交历史 |
| GET | /api/repos/branches | 获取分支列表 |
| POST | /api/repos/branch | 创建分支 |
| DELETE | /api/repos/branch | 删除分支 |

### Issue 管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/issues | 获取 Issue 列表 |
| POST | /api/issues | 创建 Issue |
| PUT | /api/issues | 更新 Issue |
| DELETE | /api/issues | 删除 Issue |

### Pull Request

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/pull-requests | 获取 PR 列表 |
| POST | /api/pull-requests | 创建 PR |
| POST | /api/pull-requests/merge | 合并 PR |
| POST | /api/pull-requests/close | 关闭 PR |

### 安全扫描

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/security/scan | 执行安全扫描 |
| GET | /api/security/history | 获取扫描历史 |

### 代码审查

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/reviews | 提交审查 |
| POST | /api/reviews/batch-comments | 批量评论 |
| GET | /api/reviews | 获取审查列表 |
| GET | /api/reviews/stats | 审查统计 |

### 团队管理

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/teams | 创建团队 |
| GET | /api/teams | 获取团队列表 |
| PUT | /api/teams | 更新团队 |
| DELETE | /api/teams | 删除团队 |
| POST | /api/teams/members | 添加成员 |
| DELETE | /api/teams/members | 移除成员 |

### 高级搜索

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/search/advanced | 高级搜索 |
| POST | /api/search/index | 索引仓库 |
| GET | /api/search/code | 代码搜索 |

### 管理后台

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/admin/dashboard | 系统概览 |
| GET | /api/admin/users | 用户列表 |
| POST | /api/admin/users/toggle | 禁用/启用用户 |
| GET | /api/admin/repos | 仓库列表 |
| GET | /api/admin/logs | 系统日志 |

### GraphQL API

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/graphql | 执行 GraphQL 查询 |
| GET | /api/graphql/schema | 获取 Schema |
| GET | /api/graphql/playground | GraphQL Playground |

### Git LFS

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/lfs/batch | LFS 批量操作 |
| PUT | /api/lfs/objects | 上传对象 |
| GET | /api/lfs/objects | 下载对象 |
| GET | /api/lfs/stats | LFS 统计 |

### API Token 管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/tokens | Token 列表 |
| POST | /api/tokens | 创建 Token |
| PUT | /api/tokens | 更新 Token |
| DELETE | /api/tokens | 删除 Token |
| GET | /api/tokens/scopes | 权限范围 |

### OAuth2 授权

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/oauth/apps | 应用列表 |
| POST | /api/oauth/apps | 注册应用 |
| PUT | /api/oauth/apps | 更新应用 |
| DELETE | /api/oauth/apps | 删除应用 |
| GET | /api/oauth/authorize | 授权端点 |
| POST | /api/oauth/token | Token 端点 |
| GET | /api/oauth/authorizations | 授权列表 |

### 通知中心

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/notifications | 通知列表 |
| GET | /api/notifications/unread-count | 未读数量 |
| POST | /api/notifications/:id/read | 标记已读 |
| POST | /api/notifications/read-all | 全部已读 |
| DELETE | /api/notifications/:id | 删除通知 |

### 工作流调度

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/workflows/schedule | 触发定时工作流 |
| GET | /api/workflows/schedule | 获取调度信息 |

### Discussions 讨论区

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/discussions/categories | 分类列表 |
| GET | /api/discussions | 讨论列表 |
| POST | /api/discussions | 创建讨论 |
| GET | /api/discussions/:id | 讨论详情 |
| PUT | /api/discussions/:id | 更新讨论 |
| DELETE | /api/discussions/:id | 删除讨论 |
| GET | /api/discussions/:id/replies | 回复列表 |
| POST | /api/discussions/:id/replies | 创建回复 |
| POST | /api/discussions/:id/replies/:replyId/answer | 标记答案 |
| POST | /api/discussions/:type/:id/vote | 投票 |
| POST | /api/discussions/:id/pin | 置顶 |

### Projects 项目管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/projects | 项目列表 |
| POST | /api/projects | 创建项目 |
| GET | /api/projects/:id | 项目详情 |
| PUT | /api/projects/:id | 更新项目 |
| DELETE | /api/projects/:id | 删除项目 |
| GET | /api/projects/:id/columns | 列列表 |
| POST | /api/projects/:id/columns | 创建列 |
| PUT | /api/projects/columns/:id | 更新列 |
| DELETE | /api/projects/columns/:id | 删除列 |
| GET | /api/projects/columns/:id/cards | 卡片列表 |
| POST | /api/projects/columns/:id/cards | 创建卡片 |
| GET | /api/projects/cards/:id | 卡片详情 |
| PUT | /api/projects/cards/:id | 更新卡片 |
| POST | /api/projects/cards/:id/move | 移动卡片 |
| POST | /api/projects/cards/:id/archive | 归档卡片 |
| DELETE | /api/projects/cards/:id | 删除卡片 |
| GET | /api/projects/:id/members | 成员列表 |
| POST | /api/projects/:id/members | 添加成员 |

### Sponsors 赞助系统

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/sponsors/tiers | 赞助等级列表 |
| POST | /api/sponsors/tiers | 创建赞助等级 |
| PUT | /api/sponsors/tiers/:id | 更新赞助等级 |
| DELETE | /api/sponsors/tiers/:id | 删除赞助等级 |
| GET | /api/sponsors | 赞助列表 |
| POST | /api/sponsors | 创建赞助 |
| GET | /api/sponsors/:id | 赞助详情 |
| PUT | /api/sponsors/:id | 更新赞助 |
| DELETE | /api/sponsors/:id | 取消赞助 |
| GET | /api/sponsors/stats | 统计数据 |
| GET | /api/sponsors/goals | 目标列表 |
| POST | /api/sponsors/goals | 创建目标 |
| GET | /api/sponsors/badges | 徽章列表 |
| GET | /api/sponsors/content | 私密内容 |

---

## 功能完成度

| 阶段 | 功能模块 | 状态 |
|------|---------|------|
| **P0** | 用户系统 | ✅ |
| **P0** | 仓库管理 | ✅ |
| **P0** | Git 操作 | ✅ |
| **P0** | Issue 管理 | ✅ |
| **P0** | Pull Request | ✅ |
| **P0** | Actions 执行引擎 | ✅ |
| **P0** | Email 通知 | ✅ |
| **P0** | REST API | ✅ |
| **P0** | 分支保护 | ✅ |
| **P0** | 文件上传 | ✅ |
| **P0** | 定时触发器 | ✅ |
| **P0** | API Token 管理 | ✅ |
| **P0** | OAuth2 授权 | ✅ |
| **P0** | 日志实时输出 | ✅ |
| **P0** | 可视化编辑器 | ✅ |
| **P0** | 通知中心完善 | ✅ |
| **P1** | 安全扫描 | ✅ |
| **P1** | 代码审查增强 | ✅ |
| **P1** | 组织团队管理 | ✅ |
| **P1** | 高级搜索 | ✅ |
| **P1** | 依赖漏洞告警 | ✅ |
| **P1** | 管理后台增强 | ✅ |
| **P1** | Redis 缓存优化 | ✅ |
| **P1** | 队列系统 | ✅ |
| **P2** | Dark Mode | ✅ |
| **P2** | 快捷键支持 | ✅ |
| **P2** | 仓库模板 | ✅ |
| **P2** | Issue/PR 模板 | ✅ |
| **P2** | 全文代码搜索 | ✅ |
| **P2** | GraphQL API | ✅ |
| **P2** | Git LFS | ✅ |
| **Phase 4** | Discussions 讨论区 | ✅ |
| **Phase 4** | Projects 项目管理 | ✅ |
| **Phase 4** | Sponsors 赞助系统 | ✅ |
| **Phase 5** | 移动端适配 | ✅ |
| **Phase 5** | 性能优化 | ✅ |
| **Phase 5** | 国际化支持 | ✅ |

---

## 代码质量优化

### 统一错误处理
- ✅ 错误/异常统一捕获
- ✅ 标准化 API 错误响应
- ✅ 生产环境安全消息
- ✅ 错误日志记录

### PSR-3 日志系统
- ✅ 多通道支持（api/git/actions/security）
- ✅ 日志分级（DEBUG/INFO/WARNING/ERROR/CRITICAL）
- ✅ 终端彩色输出
- ✅ 日志清理（保留30天）

### 数据库查询优化
- ✅ EXPLAIN 分析
- ✅ 慢查询检测（>100ms）
- ✅ 索引建议
- ✅ 表统计信息

### 热点数据缓存
- ✅ 用户/仓库/Issue/PR 缓存
- ✅ 缓存预热（活跃用户/热门仓库）
- ✅ 命中率统计
- ✅ Redis 内存监控

### 单元测试
- ✅ 测试框架：PHPUnit 10
- ✅ 测试用例：95+ 个
- ✅ 测试覆盖：
  - 单元测试（Services/Core）
  - 集成测试（Controllers）
  - 安全测试（SQL注入/XSS/命令注入）

```bash
# 运行测试
composer install
./vendor/bin/phpunit

# 生成覆盖率报告
./vendor/bin/phpunit --coverage-html coverage
```

---

## License

Apache License 2.0
