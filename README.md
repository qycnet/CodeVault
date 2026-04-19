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

## 技术栈

- **PHP 8.0+** (原生，无框架)
- **MySQL 5.7+**
- **Git**

## 安全特性

- ✅ PDO 预处理防 SQL 注入
- ✅ escapeshellarg() 防命令注入
- ✅ bcrypt 密码哈希 (cost=12)
- ✅ Cookie 安全配置 (SameSite=Strict + Secure)
- ✅ CORS 白名单限制
- ✅ Session 安全管理

## 目录结构

```
codevault/
├── config/                 # 配置文件
│   ├── app.php            # 应用配置
│   └── database.php       # 数据库配置
├── database/
│   └── migrations/        # 数据库迁移
│       └── 001_create_tables.sql
├── public/
│   └── index.php          # API 入口
├── src/
│   ├── Controllers/       # 控制器
│   │   ├── AuthController.php
│   │   ├── SshKeyController.php
│   │   ├── RepositoryController.php
│   │   └── IssueController.php
│   ├── Database/          # 数据库层
│   │   └── Connection.php
│   ├── Models/            # 模型
│   │   ├── User.php
│   │   ├── SshKey.php
│   │   ├── VerificationCode.php
│   │   ├── Repository.php
│   │   └── Issue.php
│   └── Services/          # 服务层
│       ├── Session.php
│       └── GitService.php
└── README.md
```

## 环境要求

- PHP >= 8.0
- MySQL >= 5.7
- Git >= 2.0
- PHP 扩展：pdo, pdo_mysql, json, mbstring

## 安装部署

### 1. 克隆项目

```bash
git clone https://github.com/qycnet/CodeVault.git
cd codevault
```

### 2. 配置环境变量

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
export APP_ENV=development

# Git 仓库路径
export GIT_REPOS_PATH=/var/git/repositories/
```

### 3. 创建数据库

```bash
mysql -u root -p << EOF
CREATE DATABASE codevault DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'codevault_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON codevault.* TO 'codevault_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 4. 初始化数据库表

```bash
mysql -u codevault_user -p codevault < database/migrations/001_create_tables.sql
```

### 5. 创建 Git 仓库目录

```bash
sudo mkdir -p /var/git/repositories
sudo chown -R www-data:www-data /var/git/repositories
sudo chmod -R 755 /var/git/repositories
```

### 6. 启动开发服务器

```bash
cd public
php -S localhost:8000
```

### 7. 生产环境部署

使用 Nginx + PHP-FPM：

```nginx
server {
    listen 80;
    server_name codevault.example.com;
    root /var/www/codevault/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
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
| GET | /api/repos/status | 获取仓库状态 |
| GET | /api/repos/log | 获取提交历史 |
| GET | /api/repos/branches | 获取分支列表 |

### Issue 管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/issues | 获取 Issue 列表 |
| POST | /api/issues | 创建 Issue |
| PUT | /api/issues | 更新 Issue |
| DELETE | /api/issues | 删除 Issue |

## 使用示例

### 1. 发送验证码

```bash
curl -X POST http://localhost:8000/api/auth/send-code \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

### 2. 用户注册

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "username": "testuser",
    "password": "password123",
    "code": "123456"
  }'
```

### 3. 用户登录

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "login": "user@example.com",
    "password": "password123"
  }'
```

### 4. 创建仓库

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

### 5. 添加 SSH Key

```bash
curl -X POST http://localhost:8000/api/ssh-keys \
  -H "Content-Type: application/json" \
  -b "codevault_session=your_session_cookie" \
  -d '{
    "key_name": "my-laptop",
    "public_key": "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAAB..."
  }'
```

### 6. 创建 Issue

```bash
curl -X POST http://localhost:8000/api/issues \
  -H "Content-Type: application/json" \
  -b "codevault_session=your_session_cookie" \
  -d '{
    "repo_id": 1,
    "title": "Bug: Login fails",
    "content": "Description of the bug..."
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

## License

MIT License

Copyright (c) 2026 CodeVault

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
