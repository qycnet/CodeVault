# CodeVault

基于 PHP 原生实现的轻量级代码仓库管理系统。

## 技术栈

- PHP 8.0+ (原生，无框架)
- MySQL 5.7+
- Git

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
│   │   └── SshKeyController.php
│   ├── Database/          # 数据库层
│   │   └── Connection.php
│   ├── Models/            # 模型
│   │   ├── User.php
│   │   ├── SshKey.php
│   │   └── VerificationCode.php
│   └── Services/          # 服务层
│       └── Session.php
└── README.md
```

## 安装

### 1. 克隆项目

```bash
git clone https://github.com/qycnet/CodeVault.git
cd codevault
```

### 2. 配置环境变量

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=codevault
export DB_USER=root
export DB_PASS=your_password
export APP_URL=http://localhost
export APP_DEBUG=true
```

### 3. 初始化数据库

```bash
mysql -u root -p < database/migrations/001_create_tables.sql
```

### 4. 启动服务

```bash
cd public
php -S localhost:8000
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
| GET | /api/ssh-keys | 获取SSH Key列表 |
| POST | /api/ssh-keys | 添加SSH Key |
| DELETE | /api/ssh-keys | 删除SSH Key |
| GET | /api/ssh-keys/detail | 获取SSH Key详情 |

### 仓库管理

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/repos | 获取当前用户的仓库列表 |
| POST | /api/repos | 创建新仓库 |
| GET | /api/repos/detail | 获取仓库详情 |
| PUT | /api/repos | 更新仓库设置 |
| DELETE | /api/repos | 删除仓库 |

### Git 操作

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /api/git/clone | Clone 仓库 |
| POST | /api/git/push | Push 到远程 |
| POST | /api/git/pull | Pull 从远程 |
| GET | /api/git/status | 获取仓库状态 |
| GET | /api/git/log | 获取提交历史 |

## 使用示例

### 发送验证码

```bash
curl -X POST http://localhost:8000/api/auth/send-code \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

### 用户注册

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "username": "testuser", "password": "password123", "code": "123456"}'
```

### 用户登录

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login": "user@example.com", "password": "password123"}'
```

### 添加 SSH Key

```bash
curl -X POST http://localhost:8000/api/ssh-keys \
  -H "Content-Type: application/json" \
  -d '{"key_name": "my-key", "public_key": "ssh-rsa AAAA..."}'
```

## License

MIT
