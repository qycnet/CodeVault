# CodeVault 安全文档

## 目录

1. [安全架构概述](#安全架构概述)
2. [安全功能列表](#安全功能列表)
3. [部署安全指南](#部署安全指南)
4. [安全配置说明](#安全配置说明)
5. [安全监控与告警](#安全监控与告警)
6. [安全事件响应](#安全事件响应)
7. [安全最佳实践](#安全最佳实践)

---

## 安全架构概述

CodeVault 采用多层安全架构，从输入验证到输出转义，从网络传输到数据存储，全方位保护用户数据安全。

### 安全层次

```
┌─────────────────────────────────────────────────────────────┐
│                      应用层安全                              │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  │ CSRF 防护   │ │ XSS 防护    │ │ 速率限制    │          │
│  └─────────────┘ └─────────────┘ └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│                      数据层安全                              │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  │ SQL 注入防护│ │ 密码加密    │ │ Session 安全│          │
│  └─────────────┘ └─────────────┘ └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│                      文件系统安全                            │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  │ 路径遍历防护│ │ 文件上传安全│ │ 命令注入防护│          │
│  └─────────────┘ └─────────────┘ └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│                      网络层安全                              │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐          │
│  │ HTTPS       │ │ CORS 策略   │ │ 安全响应头  │          │
│  └─────────────┘ └─────────────┘ └─────────────┘          │
└─────────────────────────────────────────────────────────────┘
```

---

## 安全功能列表

### 1. SQL 注入防护
- **实现方式**: PDO 预处理语句
- **位置**: 全项目数据库操作
- **配置**: 所有数据库查询使用参数绑定

### 2. XSS 防护
- **实现方式**: 
  - HTML 转义: `SecurityHelper::htmlEscape()`
  - JavaScript 转义: `SecurityHelper::jsEscape()`
  - CSP 响应头
- **位置**: `src/Core/SecurityHelper.php`

### 3. CSRF 防护
- **实现方式**: Token 验证
- **位置**: `src/Core/SecurityHelper.php`
- **使用方法**:
  ```php
  // 生成 Token
  $token = SecurityHelper::generateCsrfToken();
  
  // 验证 Token
  if (!SecurityHelper::verifyCsrfToken($token)) {
      // 拒绝请求
  }
  ```

### 4. 命令注入防护
- **实现方式**: `proc_open()` 替代 `exec()`
- **位置**: 全项目命令执行
- **特点**: 参数分离，防止 shell 解析

### 5. 速率限制
- **实现方式**: Redis 令牌桶
- **位置**: `src/Services/SecurityService.php`
- **配置**: 100 次/分钟/IP

### 6. 文件上传安全
- **实现方式**: 
  - MIME 类型验证
  - 魔数验证
  - 扩展名白名单
  - 内容检查
- **位置**: `src/Services/FileUploadService.php`

### 7. 密码安全
- **实现方式**: bcrypt (cost=12)
- **位置**: `src/Services/SecurityService.php`

### 8. Session 安全
- **配置**:
  - HttpOnly: true
  - SameSite: Lax
  - Secure: true (生产环境)

### 9. 安全响应头
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin
- Content-Security-Policy: (完整策略)

---

## 部署安全指南

### 1. 环境变量保护

**生产环境必须使用 Docker Secrets 或 HashiCorp Vault**

```yaml
# docker-compose.yml
version: '3.8'
services:
  codevault:
    image: codevault:latest
    secrets:
      - db_password
      - redis_password
      - app_secret
    environment:
      - DB_HOST=db
      - DB_NAME=codevault
      - DB_USER=codevault
      - DB_PASSWORD_FILE=/run/secrets/db_password

secrets:
  db_password:
    file: ./secrets/db_password.txt
  redis_password:
    file: ./secrets/redis_password.txt
  app_secret:
    file: ./secrets/app_secret.txt
```

### 2. HTTPS 配置

**必须启用 HTTPS**

```nginx
server {
    listen 443 ssl http2;
    server_name codevault.example.com;
    
    ssl_certificate /etc/letsencrypt/live/codevault.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/codevault.example.com/privkey.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    
    add_header Strict-Transport-Security "max-age=63072000" always;
    
    # ... 其他配置
}

server {
    listen 80;
    server_name codevault.example.com;
    return 301 https://$server_name$request_uri;
}
```

### 3. 防火墙配置

```bash
# 仅开放必要端口
ufw allow 22/tcp   # SSH
ufw allow 80/tcp   # HTTP (重定向到 HTTPS)
ufw allow 443/tcp  # HTTPS
ufw enable
```

### 4. 文件权限

```bash
# 设置正确的文件权限
chown -R www-data:www-data /var/www/codevault
chmod -R 755 /var/www/codevault
chmod 600 /var/www/codevault/.env
chmod 700 /var/git/repositories
```

---

## 安全配置说明

### .env 配置

```env
# 数据库配置
DB_HOST=localhost
DB_NAME=codevault
DB_USER=codevault
DB_PASSWORD=your_secure_password_here

# Redis 配置
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# 应用配置
APP_ENV=production
APP_DEBUG=false
APP_SECRET=your_32_char_secret_key_here

# Session 配置
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Lax

# 速率限制
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```

---

## 安全监控与告警

### 1. 日志记录

**安全事件日志位置**: `/var/log/codevault/security.log`

**记录的事件**:
- CSRF Token 验证失败
- 速率限制触发
- 文件上传失败
- 路径遍历尝试
- SQL 错误

### 2. 监控配置

```yaml
# Prometheus 告警规则
groups:
  - name: codevault_security
    rules:
      - alert: HighCSRFailureRate
        expr: rate(codevault_csrf_failures_total[5m]) > 10
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "High CSRF failure rate detected"
          
      - alert: RateLimitTriggered
        expr: rate(codevault_rate_limit_total[5m]) > 100
        for: 1m
        labels:
          severity: warning
        annotations:
          summary: "Rate limit frequently triggered"
```

### 3. 告警通知

配置 Slack/DingTalk/Email 告警：

```php
// src/Services/AuditService.php
public function sendSecurityAlert(string $event, array $context): void
{
    $webhook = $_ENV['SECURITY_WEBHOOK_URL'];
    
    $payload = [
        'event' => $event,
        'context' => $context,
        'timestamp' => date('c'),
        'server' => gethostname(),
    ];
    
    // 发送到 Slack/DingTalk
    $this->sendWebhook($webhook, $payload);
}
```

---

## 安全事件响应

### 1. 安全事件等级

| 等级 | 描述 | 响应时间 |
|------|------|----------|
| P0 - 紧急 | 数据泄露、系统入侵 | 15 分钟 |
| P1 - 高危 | 漏洞利用、攻击尝试 | 1 小时 |
| P2 - 中危 | 可疑活动、配置错误 | 4 小时 |
| P3 - 低危 | 潜在风险、信息泄露 | 24 小时 |

### 2. 响应流程

```
发现安全事件
    ↓
评估事件等级
    ↓
隔离受影响系统
    ↓
收集证据和日志
    ↓
修复漏洞
    ↓
恢复服务
    ↓
事后分析报告
```

---

## 安全最佳实践

### 1. 定期更新

- 每月更新依赖包
- 每季度安全审计
- 及时应用安全补丁

### 2. 访问控制

- 最小权限原则
- 定期审查权限
- 多因素认证

### 3. 数据保护

- 敏感数据加密
- 定期备份
- 数据分类管理

### 4. 安全培训

- 开发人员安全培训
- 安全编码规范
- 安全意识提升

---

## 联系方式

- **安全邮箱**: security@codevault.example.com
- **安全文档**: https://docs.codevault.example.com/security
- **漏洞报告**: https://github.com/qycnet/CodeVault/security

---

*最后更新: 2026-04-22*
