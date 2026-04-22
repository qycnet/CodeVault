# CodeVault 部署文档

## 目录

1. [系统要求](#系统要求)
2. [快速部署](#快速部署)
3. [Docker 部署](#docker-部署)
4. [手动部署](#手动部署)
5. [配置说明](#配置说明)
6. [Nginx 配置](#nginx-配置)
7. [SSL 证书](#ssl-证书)
8. [性能优化](#性能优化)
9. [监控配置](#监控配置)
10. [故障排查](#故障排查)

---

## 系统要求

### 最低配置
- CPU: 2 核
- 内存: 4 GB
- 存储: 50 GB SSD
- 操作系统: Ubuntu 22.04 / CentOS 8 / Debian 11

### 推荐配置
- CPU: 4 核
- 内存: 8 GB
- 存储: 100 GB SSD
- 操作系统: Ubuntu 22.04 LTS

### 软件依赖
- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- Redis 7.0+
- Nginx 1.24+
- Git 2.40+

---

## 快速部署

### 使用 Docker Compose（推荐）

```bash
# 克隆仓库
git clone https://github.com/qycnet/CodeVault.git
cd CodeVault

# 复制配置文件
cp .env.example .env

# 生成密钥
openssl rand -hex 32 >> .env

# 启动服务
docker-compose up -d

# 初始化数据库
docker-compose exec php php bin/init.php
```

访问 `https://localhost` 完成安装向导。

---

## Docker 部署

### docker-compose.yml

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:1.24-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/nginx.conf
      - ./public:/var/www/codevault/public
      - ./storage:/var/www/codevault/storage
      - ./certs:/etc/nginx/certs
    depends_on:
      - php
    networks:
      - codevault-network

  php:
    image: php:8.2-fpm-alpine
    volumes:
      - ./:/var/www/codevault
      - ./docker/php.ini:/usr/local/etc/php/php.ini
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    networks:
      - codevault-network

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: codevault
      MYSQL_USER: codevault
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - codevault-network

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis-data:/data
    networks:
      - codevault-network

volumes:
  mysql-data:
  redis-data:

networks:
  codevault-network:
    driver: bridge
```

---

## 手动部署

### 1. 安装依赖

```bash
# Ubuntu
apt update
apt install -y nginx mysql-server redis-server php8.2-fpm php8.2-mysql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip git

# CentOS
yum install -y nginx mysql-server redis php-fpm php-mysqlnd php-redis php-mbstring php-xml php-curl php-zip git
```

### 2. 配置 PHP

```ini
; /etc/php/8.2/fpm/php.ini
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

### 3. 创建数据库

```sql
CREATE DATABASE codevault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'codevault'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON codevault.* TO 'codevault'@'localhost';
FLUSH PRIVILEGES;
```

### 4. 安装 CodeVault

```bash
# 克隆代码
cd /var/www
git clone https://github.com/qycnet/CodeVault.git
cd CodeVault

# 安装依赖
composer install --no-dev

# 设置权限
chown -R www-data:www-data /var/www/CodeVault
chmod -R 755 /var/www/CodeVault
chmod 600 .env

# 创建 Git 仓库目录
mkdir -p /var/git/repositories
chown -R www-data:www-data /var/git/repositories
chmod 700 /var/git/repositories
```

---

## 配置说明

### .env 配置

```env
# 应用配置
APP_NAME=CodeVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://codevault.example.com
APP_SECRET=your_32_character_secret_key

# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=codevault
DB_USER=codevault
DB_PASSWORD=your_secure_password

# Redis 配置
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password
REDIS_DB=0

# Git 配置
GIT_REPOSITORIES_PATH=/var/git/repositories
GIT_DEFAULT_BRANCH=main

# 邮件配置
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls

# 存储配置
STORAGE_PATH=/var/www/codevault/storage
UPLOAD_MAX_SIZE=104857600

# Session 配置
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTPONLY=true
SESSION_SAMESITE=Lax

# 速率限制
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```

---

## Nginx 配置

### /etc/nginx/sites-available/codevault

```nginx
server {
    listen 80;
    server_name codevault.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name codevault.example.com;
    root /var/www/codevault/public;
    index index.php;

    # SSL 配置
    ssl_certificate /etc/letsencrypt/live/codevault.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/codevault.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    add_header Strict-Transport-Security "max-age=63072000" always;

    # 安全响应头
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Git HTTP 协议
    location ~ ^.*\.git(/.*)?$ {
        client_max_body_size 100m;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/codevault/public/git-http-backend.php;
        include fastcgi_params;
    }

    # API 路由
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # 静态文件
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 禁止访问敏感文件
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}
```

---

## SSL 证书

### 使用 Let's Encrypt

```bash
# 安装 Certbot
apt install -y certbot python3-certbot-nginx

# 获取证书
certbot --nginx -d codevault.example.com

# 自动续期
certbot renew --dry-run
```

---

## 性能优化

### PHP-FPM 配置

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### MySQL 优化

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 500
```

### Redis 优化

```conf
# /etc/redis/redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
save ""
```

---

## 监控配置

### Prometheus + Grafana

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'codevault'
    static_configs:
      - targets: ['localhost:9090']
```

### 日志轮转

```conf
# /etc/logrotate.d/codevault
/var/log/codevault/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

---

## 故障排查

### 常见问题

1. **500 错误**
   ```bash
   # 检查日志
   tail -f /var/log/nginx/error.log
   tail -f /var/log/codevault/error.log
   ```

2. **Git 推送失败**
   ```bash
   # 检查权限
   ls -la /var/git/repositories/
   chown -R www-data:www-data /var/git/repositories/
   ```

3. **Redis 连接失败**
   ```bash
   # 检查 Redis
   redis-cli -a your_password ping
   ```

4. **数据库连接失败**
   ```bash
   # 检查 MySQL
   mysql -u codevault -p -h localhost codevault
   ```

---

*最后更新: 2026-04-22*
