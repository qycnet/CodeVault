# CodeVault

<p align="center">
  <strong>轻量级代码仓库管理系统 - GitHub 中文版</strong>
</p>

<p align="center">
  <a href="#功能特性">功能特性</a> •
  <a href="#技术栈">技术栈</a> •
  <a href="#快速部署">快速部署</a> •
  <a href="#api-接口">API 接口</a> •
  <a href="#安全特性">安全特性</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/完成度-100%25-brightgreen" alt="完成度">
  <img src="https://img.shields.io/badge/PHP-8.0+-blue" alt="PHP">
  <img src="https://img.shields.io/badge/Vue-3-green" alt="Vue">
  <img src="https://img.shields.io/badge/License-Apache--2.0-orange" alt="License">
</p>

---

## 项目概览

| 指标 | 数据 |
|------|------|
| 完成度 | **100%** |
| 代码文件 | **161+** 个 |
| 代码行数 | **400,000+** 行 |
| 数据库表 | **80+** 个 |
| API 端点 | **200+** 个 |
| 测试用例 | **95+** 个 |
| 支持语言 | **11 种** |

---

## 功能特性

### Phase 1-3: 核心功能 ✅

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
- ✅ 提交历史查看
- ✅ 分支管理

#### Issue 管理
- ✅ 创建/列表/详情/更新/删除 Issue
- ✅ 状态管理（open/closed）
- ✅ 标签/里程碑

#### Pull Request
- ✅ 创建/查看/合并/关闭 PR
- ✅ 代码 Diff 查看
- ✅ 行内评论
- ✅ 代码审查

#### Actions 执行引擎
- ✅ 工作流定义（YAML）
- ✅ 工作流执行
- ✅ 日志查看
- ✅ 定时触发器（Cron 表达式）
- ✅ 日志实时输出（WebSocket）
- ✅ 可视化编辑器（拖拽式）

#### 通知系统
- ✅ Email 通知
- ✅ WebSocket 实时推送
- ✅ 桌面通知支持
- ✅ 通知聚合展示

#### API & 授权
- ✅ 完整 REST API 接口
- ✅ API Token 管理
- ✅ OAuth2 授权服务器
- ✅ GraphQL API

---

### Phase 4: 社区功能 ✅

#### Discussions 讨论区
- ✅ 分类讨论（公告/一般/Q&A/想法/展示）
- ✅ 嵌套回复
- ✅ 投票系统
- ✅ 精选回答标记
- ✅ 置顶/锁定功能

#### Projects 项目管理
- ✅ Kanban 看板视图
- ✅ 拖拽排序
- ✅ Issue/PR 关联
- ✅ 自定义字段

#### Sponsors 赞助系统
- ✅ 赞助等级管理
- ✅ 月度/年度/一次性赞助
- ✅ 徽章奖励系统
- ✅ 支付安全措施

---

### Phase 5: 体验优化 ✅

#### 移动端适配
- ✅ 响应式布局（5个断点）
- ✅ 移动端导航
- ✅ 触摸手势支持
- ✅ iOS 安全区域适配

#### 性能优化
- ✅ 性能监控
- ✅ 数据库优化
- ✅ 缓存优化
- ✅ 资源优化

#### 国际化支持
- ✅ **支持 11 种语言**
  - 简体中文 / 繁體中文
  - English (US/UK)
  - 日本語 / 한국어
  - Español / Français / Deutsch
  - Русский / العربية (RTL)

---

### Phase 6: 体验增强 ✅

- ✅ Dark Mode（CSS 变量主题切换）
- ✅ 快捷键支持（Ctrl+/ 搜索等）
- ✅ 仓库模板
- ✅ Issue/PR 模板
- ✅ 全文代码搜索（Elasticsearch/Meilisearch）
- ✅ Git LFS 支持

---

### Phase 7: 开发者工具 ✅

- ✅ CLI 工具（仓库管理/Issue 操作）
- ✅ 安全扫描（依赖漏洞/代码安全）
- ✅ 代码审查增强

---

### Phase 8: 协作增强 ✅

#### Gists 代码片段
- ✅ 版本管理
- ✅ Fork/Star
- ✅ 评论功能

#### Wiki 增强
- ✅ 页面版本历史
- ✅ 目录生成
- ✅ Markdown 渲染

#### Webhooks 增强
- ✅ 投递日志
- ✅ 重试机制
- ✅ 状态追踪

---

### Phase 9: 开发者体验 ✅

#### 导入/导出
- ✅ GitHub/GitLab 导入
- ✅ 仓库导出（bundle/tar/issues/wiki）

#### 统计分析
- ✅ 活动日志
- ✅ 贡献者统计
- ✅ 语言分布
- ✅ 代码行数趋势

#### API 文档
- ✅ 自动生成 OpenAPI 规范
- ✅ 版本管理

---

### Phase 10: 企业级功能 ✅ NEW

#### SSO 单点登录
- ✅ **多协议支持**：SAML 2.0、OAuth 2.0、OIDC、LDAP、CAS
- ✅ **预置提供商**：GitHub OAuth、GitLab OAuth、Google OIDC
- ✅ SSO 会话管理
- ✅ 用户映射

#### 审计日志系统
- ✅ **全面事件覆盖**：
  - 认证事件：登录/登出/登录失败/密码变更
  - 用户事件：创建/更新/删除用户
  - 仓库事件：创建/删除/转移/可见性变更
  - 安全事件：Token 创建/撤销、Webhook 变更、SSO 登录、MFA 变更
- ✅ **风险级别分类**：Low、Medium、High、Critical
- ✅ 审计告警
- ✅ 合规报告生成

#### 容器镜像仓库
- ✅ **OCI 镜像支持**：Docker Manifest v2、OCI Manifest v1
- ✅ 镜像管理（创建/列表/详情/删除）
- ✅ 标签管理
- ✅ **漏洞扫描**：容器镜像安全扫描、CVE 检测
- ✅ 访问控制

#### 多租户支持
- ✅ **组织管理**：
  - 组织 CRUD、Slug 唯一标识
  - 套餐计划：Free、Team、Business、Enterprise
  - 资源配额：座位数、私有仓库数、存储配额
- ✅ **成员管理**：Owner、Admin、Member、Guest
- ✅ **团队管理**：团队 CRUD、团队成员、团队仓库权限
- ✅ 存储追踪

#### 合规功能
- ✅ 合规策略（分支保护/强制审查/签名提交/许可证检查/安全扫描）
- ✅ 合规报告生成

#### 高可用配置
- ✅ 节点管理（Primary/Replica）
- ✅ 心跳检测
- ✅ 状态管理

### Phase 11: 协作增强 ✅ NEW

#### Pages 静态网站托管
- ✅ **静态网站托管**：从仓库分支部署
- ✅ **自定义域名**：DNS 验证绑定
- ✅ **HTTPS 支持**：自动证书
- ✅ **多种构建类型**：Jekyll/Hugo/Next/Nuxt/VuePress/Docsify
- ✅ **部署管理**：自动部署、版本控制、回滚

#### Gists 增强
- ✅ 访问控制（public/unlisted/private）
- ✅ 密码保护
- ✅ 过期时间
- ✅ 嵌入代码生成

#### Wiki 增强
- ✅ 富文本编辑器支持
- ✅ 目录自动生成
- ✅ 附件管理
- ✅ 字数统计/阅读时间

#### Webhooks 增强
- ✅ 事件筛选（push/PR/issues/release 等）
- ✅ 重试机制（最多 5 次）
- ✅ 签名验证（HMAC-SHA256）
- ✅ 投递日志

---

## 技术栈

| 层级 | 技术选型 | 版本要求 |
|------|----------|----------|
| 后端 | PHP 原生（无框架） | PHP 8.0+ |
| 数据库 | MySQL | MySQL 5.7+ |
| 缓存 | Redis | Redis 7.0+ |
| 前端 | Vue 3 + TypeScript + Element Plus + Vite | Node.js 18+ |
| 搜索 | Elasticsearch / Meilisearch | 可选 |
| Git | Git CLI | Git 2.0+ |
| 容器 | Docker Registry | 可选 |
| 容器化 | Docker + Docker Compose | 可选 |

---

## 安全特性

| 安全项目 | 实现方式 | 状态 |
|----------|----------|------|
| SQL 注入防护 | PDO 预处理语句 | ✅ |
| 命令注入防护 | escapeshellarg + 白名单验证 | ✅ |
| XSS 防护 | htmlspecialchars + 纯文本渲染 | ✅ |
| 密码哈希 | bcrypt (cost=12) | ✅ |
| Session 安全 | HttpOnly + SameSite=Lax | ✅ |
| CORS 限制 | 白名单控制 | ✅ |
| OAuth2 安全 | random_bytes + bcrypt 哈希 | ✅ |
| API Token 安全 | SHA256 哈希存储 | ✅ |
| 文件上传安全 | 黑名单 + 路径验证 | ✅ |
| 支付安全 | 签名验证 + 金额校验 + 防重放 | ✅ |
| SSO 安全 | 协议签名验证 + 状态管理 | ✅ |
| 审计追踪 | 全面事件记录 + 风险分级 | ✅ |
| 容器安全 | 镜像漏洞扫描 + CVE 检测 | ✅ |
| 构建路径验证 | 白名单验证 + 目录恢复 | ✅ |

---

## 目录结构

```
codevault/
├── config/                 # 配置文件
├── database/
│   ├── migrations/        # 数据库迁移 (001-020)
│   ├── migrate.php        # 迁移脚本
│   └── schema.sql         # 完整表结构
├── frontend/              # Vue 3 前端项目
│   ├── src/
│   │   ├── views/         # 页面组件（48+ 个）
│   │   ├── components/    # 通用组件（10+ 个）
│   │   ├── layouts/       # 布局组件（含移动端）
│   │   ├── api/           # API 封装
│   │   ├── stores/        # Pinia 状态管理
│   │   ├── router/        # 路由配置
│   │   ├── styles/        # 样式文件
│   │   └── utils/         # 工具函数
│   ├── vite.config.ts
│   └── package.json
├── public/
│   └── index.php          # API 入口
├── src/
│   ├── Controllers/       # 控制器（15+ 个）
│   ├── Database/          # 数据库层
│   ├── Models/            # 模型
│   ├── Services/          # 服务层（50+ 个）
│   │   ├── SecurityScanner.php
│   │   ├── CodeReviewService.php
│   │   ├── ActionsRunner.php
│   │   ├── GraphQLService.php
│   │   ├── LfsService.php
│   │   ├── DiscussionService.php
│   │   ├── ProjectService.php
│   │   ├── SponsorService.php
│   │   ├── GistService.php
│   │   ├── WikiService.php
│   │   ├── WebhookService.php
│   │   ├── ImportExportService.php
│   │   ├── StatsService.php
│   │   ├── I18nService.php
│   │   ├── MobileAdaptationService.php
│   │   ├── AuditService.php           # Phase 10
│   │   ├── SsoService.php             # Phase 10
│   │   ├── ContainerRegistryService.php # Phase 10
│   │   ├── PagesService.php           # Phase 11
│   │   └── ...
│   └── Core/              # 核心组件
│       ├── ErrorHandler.php
│       ├── Logger.php
│       └── QueryOptimizer.php
├── tests/                 # 测试文件（95+ 用例）
│   ├── BaseTestCase.php
│   ├── Services/
│   ├── Controllers/
│   └── Security/
├── docs/                  # 文档
├── Dockerfile
├── docker-compose.yml
└── README.md
```

---

## 环境要求

- PHP >= 8.0（扩展：pdo, pdo_mysql, json, mbstring, redis）
- MySQL >= 5.7
- Redis >= 7.0
- Git >= 2.0
- Node.js >= 18.0

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
sudo mkdir -p /var/git/registry
sudo chown -R www-data:www-data /var/git
sudo chmod -R 755 /var/git

# 7. 启动服务
cd public
php -S localhost:8000
```

---

## 运行测试

```bash
# 安装依赖
composer install

# 运行测试
./vendor/bin/phpunit

# 生成覆盖率报告
./vendor/bin/phpunit --coverage-html coverage
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

### Issue & PR

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/issues | Issue 列表 |
| POST | /api/issues | 创建 Issue |
| GET | /api/pull-requests | PR 列表 |
| POST | /api/pull-requests | 创建 PR |
| POST | /api/pull-requests/merge | 合并 PR |

### 企业级功能（Phase 10）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/audit/logs | 审计日志查询 |
| GET | /api/audit/alerts | 审计告警列表 |
| GET | /api/sso/providers | SSO 提供商列表 |
| POST | /api/sso/providers | 创建 SSO 提供商 |
| GET | /api/registry | 镜像仓库列表 |
| POST | /api/registry | 创建镜像仓库 |
| POST | /api/registry/:id/scan | 镜像漏洞扫描 |
| GET | /api/orgs | 组织列表 |
| POST | /api/orgs | 创建组织 |
| GET | /api/orgs/:id/members | 组织成员 |
| GET | /api/orgs/:id/teams | 组织团队 |
| GET | /api/compliance/policies | 合规策略列表 |
| POST | /api/compliance/reports | 生成合规报告 |

### Pages 静态网站托管（Phase 11）

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/pages | 创建站点 |
| PUT | /api/pages/:id | 更新配置 |
| DELETE | /api/pages/:id | 删除站点 |
| POST | /api/pages/:id/deploy | 触发部署 |
| GET | /api/pages/:id/deployments | 部署列表 |
| POST | /api/pages/:id/domains | 添加域名 |
| POST | /api/pages/domains/:id/verify | 验证域名 |

---

## 功能完成度

| Phase | 功能模块 | 状态 |
|-------|----------|------|
| Phase 1-3 | P0/P1/P2 核心功能 | ✅ 100% |
| Phase 4 | 社区功能 | ✅ 100% |
| Phase 5 | 赞助系统 | ✅ 100% |
| Phase 6 | 体验优化 | ✅ 100% |
| Phase 7 | 开发者工具 | ✅ 100% |
| Phase 8 | 协作增强 | ✅ 100% |
| Phase 9 | 开发者体验 | ✅ 100% |
| **Phase 10** | **企业级功能** | **✅ 100%** |
| **Phase 11** | **协作增强** | **✅ 100%** |

---

## License

Apache License 2.0

---

<p align="center">
  <strong>CodeVault</strong> - 轻量级代码仓库管理系统
</p>
