# CodeVault

基于 PHP 原生实现的轻量级代码仓库管理系统（GitHub Clone）。

## 功能特性

### 用户系统
- ✅ 邮箱注册（验证码验证）
- ✅ 用户登录（Session 管理）
- ✅ SSH Key 管理（支持 RSA/ED25519）

### 代码仓库
- ✅ 创建/列表/详情/更新/删除仓库
- ✅ Git 裸仓库自动初始化
- ✅ 公开/私有仓库支持
- ✅ 访问权限控制

### Git 操作
- ✅ Clone/Push/Pull 操作
- ✅ SSH Key 认证
- ✅ 状态查询
- ✅ 提交历史查看
- ✅ 分支管理

### Issue 管理
- ✅ 创建/列表/详情/更新/删除 Issue
- ✅ 状态管理（open/closed）
- ✅ 权限验证

### Pull Request
- ✅ 创建/查看/合并/关闭 PR
- ✅ 代码 Diff 查看
- ✅ 行内评论
- ✅ 分支验证

### 评论系统
- ✅ Issue 评论
- ✅ PR 评论
- ✅ 行内代码评论
- ✅ 编辑/删除评论

### 前端界面
- ✅ Vue 3 + TypeScript + Element Plus
- ✅ 响应式设计
- ✅ 文件浏览器
- ✅ 提交历史页
- ✅ 分支管理页
- ✅ 搜索功能
- ✅ 通知中心
- ✅ 用户设置

### 高级功能
- ✅ CI/CD 工作流管理
- ✅ 项目看板（Projects）
- ✅ 组织与团队协作
- ✅ Releases 版本发布
- ✅ Wiki 文档
- ✅ Webhooks
- ✅ Stars/Forks/Watch
- ✅ 标签与里程碑
- ✅ 管理后台
- ✅ API 文档

## 技术栈

- **后端**: PHP 8.0+ (原生，无框架)
- **数据库**: MySQL 5.7+
- **前端**: Vue 3 + TypeScript + Element Plus + Vite
- **缓存**: Redis 7.0+
- **容器化**: Docker + Docker Compose
- **Git**: Git 2.0+

## 安全特性

- ✅ PDO 预处理防 SQL 注入
- ✅ escapeshellarg() 防命令注入
- ✅ bcrypt 密码哈希 (cost=12)
- ✅ Cookie 安全配置 (SameSite=Strict + Secure)
- ✅ CORS 白名单限制
- ✅ Session 安全管理
- ✅ XSS 防护（后端 htmlspecialchars + 前端 DOMPurify）
- ✅ 邮箱格式验证

## 目录结构

```
codevault/
├── config/                 # 配置文件
│   ├── app.php            # 应用配置
│   └── database.php       # 数据库配置
├── database/
│   ├── migrations/        # 数据库迁移
│   │   ├── 001_create_tables.sql
│   │   └── 002_add_pr_tables.sql
│   ├── migrate.php        # 迁移脚本
│   └── schema.sql         # 完整表结构
├── frontend/              # Vue 3 前端项目
│   ├── src/
│   │   ├── views/         # 页面组件
│   │   ├── components/    # 通用组件
│   │   ├── api/           # API 封装
│   │   ├── stores/        # Pinia 状态管理
│   │   └── router/        # 路由配置
│   ├── vite.config.ts     # Vite 配置
│   └── package.json
├── public/
│   └── index.php          # API 入口
├── src/
│   ├── Controllers/       # 控制器
│   │   ├── AuthController.php
│   │   ├── SshKeyController.php
│   │   ├── RepositoryController.php
│   │   ├── IssueController.php
│   │   ├── PRController.php
│   │   ├── CommentController.php
│   │   └── GitController.php
│   ├── Database/          # 数据库层
│   │   └── Connection.php
│   ├── Models/            # 模型
│   │   ├── User.php
│   │   ├── SshKey.php
│   │   ├── VerificationCode.php
│   │   ├── Repository.php
│   │   ├── Issue.php
│   │   ├── PullRequest.php
│   │   └── Comment.php
│   └── Services/          # 服务层
│       ├── Session.php
│       └── GitService.php
├── tests/                 # 测试文件
│   ├── Unit/
│   ├── Security/
│   └── TEST_REPORT.md
├── docs/                  # 文档
│   └── DEPLOYMENT.md      # 部署指南
├── Dockerfile             # Docker 镜像
├── docker-compose.yml     # Docker Compose 配置
└── README.md
```

## 环境要求

- PHP >= 8.0
- MySQL >= 5.7
- Redis >= 7.0
- Git >= 2.0
- Node.js >= 18.0
- PHP 扩展：pdo, pdo_mysql, json, mbstring

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

#### 1. 克隆项目

```bash
git clone https://github.com/qycnet/CodeVault.git
cd codevault
```

#### 2. 安装依赖

```bash
# 安装前端依赖
cd frontend
npm install
npm run build
cd ..
```

#### 3. 配置环境变量

```bash
# 数据库配置
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=codevault
export DB_USER=codevault_user
export DB_PASS=your_password

# 应用配置
export APP_URL=http://localhost
export APP_DEBUG=true
```

#### 4. 创建数据库

```bash
mysql -u root -p << EOF
CREATE DATABASE codevault DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'codevault_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON codevault.* TO 'codevault_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

#### 5. 初始化数据库

```bash
php database/migrate.php
```

#### 6. 创建 Git 仓库目录

```bash
sudo mkdir -p /var/git/repositories
sudo chown -R www-data:www-data /var/git/repositories
sudo chmod -R 755 /var/git/repositories
```

#### 7. 启动服务

```bash
# 开发环境
cd public
php -S localhost:8000

# 前端开发服务器
cd frontend
npm run dev
```

## API 接口

### 认证相关

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/auth/send-code | 发送注册验证码 |
| POST | /api/auth/register | 用户注册 |
| POST | /api/auth/login | 用户登录 |
| POST | /api/auth/logout | 用户登出 |
| GET | /api/auth/me | 获取当前用户信息 |

### SSH Key 管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/ssh-keys | 获取 SSH Key 列表 |
| POST | /api/ssh-keys | 添加 SSH Key |
| DELETE | /api/ssh-keys | 删除 SSH Key |

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
| GET | /api/repos/blob | 获取文件内容 |
| GET | /api/repos/commits | 获取提交历史 |
| GET | /api/repos/commit | 获取提交详情 |
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
| GET | /api/pull-requests/detail | 获取 PR 详情 |
| POST | /api/pull-requests/merge | 合并 PR |
| POST | /api/pull-requests/close | 关闭 PR |

### 评论

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/comments | 获取评论列表 |
| POST | /api/comments | 创建评论 |
| PUT | /api/comments | 更新评论 |
| DELETE | /api/comments | 删除评论 |

## 使用示例

### 1. 用户注册

```bash
# 发送验证码
curl -X POST http://localhost:8000/api/auth/send-code \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'

# 注册
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "username": "testuser",
    "password": "password123",
    "code": "123456"
  }'
```

### 2. 创建仓库

```bash
curl -X POST http://localhost:8000/api/repos \
  -H "Content-Type: application/json" \
  -b "codevault_session=your_session_cookie" \
  -d '{
    "name": "my-project",
    "description": "My first project",
    "is_private": false
  }'
```

### 3. 创建 Pull Request

```bash
curl -X POST http://localhost:8000/api/pull-requests \
  -H "Content-Type: application/json" \
  -b "codevault_session=your_session_cookie" \
  -d '{
    "repo_id": 1,
    "title": "Feature: Add new feature",
    "description": "Description...",
    "source_branch": "feature/new-feature",
    "target_branch": "main"
  }'
```

## Git 使用

### Clone 仓库

```bash
git clone ssh://git@codevault.example.com:2222/{user}/{repo}.git
```

### Push 代码

```bash
git add .
git commit -m "Initial commit"
git push origin main
```

## 功能完成度

| 功能模块 | 状态 | 说明 |
|---------|------|------|
| 用户系统 | ✅ | 注册/登录/SSH Key |
| 仓库管理 | ✅ | CRUD + Git 初始化 |
| Git 操作 | ✅ | Clone/Push/Pull/History/Branch |
| Issue 管理 | ✅ | CRUD + 状态管理 |
| Pull Request | ✅ | 创建/合并/关闭/评论 |
| 评论系统 | ✅ | Issue/PR/行内评论 |
| 前端界面 | ✅ | Vue 3 + Element Plus |
| 文件浏览 | ✅ | 文件树/代码高亮 |
| 提交历史 | ✅ | 分页/分支切换 |
| 分支管理 | ✅ | 创建/删除/保护 |
| 搜索功能 | ✅ | 仓库/Issue/PR/用户 |
| 通知系统 | ✅ | 未读/已读/删除 |
| CI/CD | ✅ | 工作流管理 |
| Projects | ✅ | 项目看板 |
| 管理后台 | ✅ | 用户/仓库/系统管理 |
| API 文档 | ✅ | 完整 API 文档 |
| Docker 部署 | ✅ | 一键部署 |

## License

Apache License 2.0
