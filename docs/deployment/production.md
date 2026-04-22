# CodeVault 生产环境部署配置

## 1. Nginx 配置

### /etc/nginx/sites-available/codevault.conf

```nginx
server {
    listen 80;
    server_name codevault.example.com;
    root /var/www/codevault/public;
    index index.php index.html;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # SPA 路由支持
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "max_execution_time=300\nmemory_limit=512M";
        include fastcgi_params;
        
        # 超时设置（Git 操作可能较慢）
        fastcgi_read_timeout 300;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Git HTTP 后端
    location ~ ^.*\.git/(HEAD|info/refs|objects/|git-upload-pack|git-receive-pack)$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/git-http.php;
        fastcgi_param GIT_PROJECT_ROOT /var/git/repositories;
        fastcgi_param GIT_HTTP_EXPORT_ALL "";
        include fastcgi_params;
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }

    # 禁止访问敏感文件
    location ~ /\.(env|git|svn|htaccess) {
        deny all;
    }

    # 上传文件大小限制
    client_max_body_size 100M;
}

# HTTPS 配置（使用 certbot 自动生成）
# sudo certbot --nginx -d codevault.example.com
```

## 2. PHP-FPM 配置

### /etc/php/8.1/fpm/pool.d/codevault.conf

```ini
[codevault]
user = git
group = git
listen = /run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; PHP 设置
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_input_vars] = 3000

; 环境变量
env[DB_HOST] = localhost
env[DB_NAME] = codevault
env[DB_USER] = codevault
env[DB_PASS] = your_password
env[REDIS_HOST] = localhost
env[REDIS_PORT] = 6379
```

## 3. Systemd 服务

### /etc/systemd/system/codevault-worker.service

```ini
[Unit]
Description=CodeVault Background Worker
After=network.target mysql.service redis.service

[Service]
Type=simple
User=git
Group=git
WorkingDirectory=/var/www/codevault
ExecStart=/usr/bin/php bin/worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### /etc/systemd/system/codevault-scheduler.service

```ini
[Unit]
Description=CodeVault Scheduler
After=network.target mysql.service redis.service

[Service]
Type=simple
User=git
Group=git
WorkingDirectory=/var/www/codevault
ExecStart=/usr/bin/php bin/scheduler.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

## 4. Git 用户和权限

```bash
# 创建专用用户
sudo useradd -r -s /bin/bash git

# 创建目录
sudo mkdir -p /var/git/repositories
sudo mkdir -p /var/www/codevault
sudo mkdir -p /var/log/codevault
sudo mkdir -p /var/backups/codevault

# 设置权限
sudo chown -R git:git /var/git
sudo chown -R git:git /var/www/codevault
sudo chown -R git:git /var/log/codevault
sudo chmod 750 /var/git
sudo chmod 755 /var/www/codevault
sudo chmod 750 /var/log/codevault

# SSH 目录
sudo -u git mkdir -p /home/git/.ssh
sudo -u git chmod 700 /home/git/.ssh
sudo -u git touch /home/git/.ssh/authorized_keys
sudo -u git chmod 600 /home/git/.ssh/authorized_keys
```

## 5. 数据库用户

```sql
-- 创建专用数据库用户
CREATE USER 'codevault'@'localhost' IDENTIFIED BY 'strong_password_here';

-- 创建数据库
CREATE DATABASE codevault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 授予权限
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
ON codevault.* TO 'codevault'@'localhost';

FLUSH PRIVILEGES;
```

## 6. 日志轮转

### /etc/logrotate.d/codevault

```
/var/log/codevault/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0640 git git
    sharedscripts
    postrotate
        systemctl reload php8.1-fpm > /dev/null 2>&1 || true
    endscript
}
```

## 7. 自动备份

### /etc/cron.daily/codevault-backup

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/codevault"
DATE=$(date +%Y%m%d)
RETENTION_DAYS=30

# 创建备份目录
mkdir -p $BACKUP_DIR

# 数据库备份
mysqldump -u codevault -p'your_password' \
    --single-transaction \
    --routines \
    --triggers \
    codevault | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Git 仓库备份
tar -czf $BACKUP_DIR/repos_$DATE.tar.gz -C /var/git repositories

# 配置文件备份
tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
    -C /var/www/codevault .env config

# 清理旧备份
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete

# 记录日志
echo "$(date): Backup completed" >> /var/log/codevault/backup.log
```

```bash
# 设置权限
sudo chmod +x /etc/cron.daily/codevault-backup
```

## 8. HTTPS 配置

```bash
# 安装 certbot
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d codevault.example.com

# 自动续期
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

## 9. 防火墙配置

```bash
# UFW 配置
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable

# 如果需要 Git SSH
sudo ufw allow 9418/tcp    # Git protocol (可选)
```

## 10. 部署脚本

### /var/www/codevault/deploy.sh

```bash
#!/bin/bash
set -e

cd /var/www/codevault

# 拉取最新代码
git pull origin main

# 安装依赖
composer install --no-dev --optimize-autoloader

# 运行迁移
php bin/migrate.php

# 清除缓存
php bin/cache:clear

# 重启服务
sudo systemctl reload php8.1-fpm
sudo systemctl restart codevault-worker
sudo systemctl restart codevault-scheduler

echo "Deployment completed!"
```

## 11. 环境变量

### /var/www/codevault/.env

```env
# 应用配置
APP_NAME=CodeVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://codevault.example.com

# 数据库配置
DB_HOST=localhost
DB_NAME=codevault
DB_USER=codevault
DB_PASS=your_strong_password

# Redis 配置
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_DATABASE=0

# Git 配置
GIT_ROOT=/var/git/repositories
GIT_USER=git

# 安全配置
SESSION_SECRET=your_random_secret_key_here
JWT_SECRET=your_jwt_secret_key_here

# 邮件配置
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USER=noreply@example.com
MAIL_PASS=your_mail_password

# 日志配置
LOG_PATH=/var/log/codevault
LOG_LEVEL=warning
```

## 12. 启动服务

```bash
# 启用服务
sudo systemctl enable nginx
sudo systemctl enable php8.1-fpm
sudo systemctl enable codevault-worker
sudo systemctl enable codevault-scheduler

# 启动服务
sudo systemctl start nginx
sudo systemctl start php8.1-fpm
sudo systemctl start codevault-worker
sudo systemctl start codevault-scheduler

# 检查状态
sudo systemctl status nginx
sudo systemctl status php8.1-fpm
```

## 13. 监控

### Prometheus metrics endpoint

在 `public/metrics.php` 暴露指标：

```php
<?php
// 系统指标
$metrics = [
    'codevault_users_total' => $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'codevault_repos_total' => $db->query('SELECT COUNT(*) FROM repositories')->fetchColumn(),
    'codevault_issues_open' => $db->query("SELECT COUNT(*) FROM issues WHERE status='open'")->fetchColumn(),
    // ...
];

foreach ($metrics as $name => $value) {
    echo "$name $value\n";
}
```

## 部署检查清单

- [ ] 创建 git 用户
- [ ] 设置目录权限
- [ ] 配置 Nginx
- [ ] 配置 PHP-FPM
- [ ] 创建数据库和用户
- [ ] 运行数据库迁移
- [ ] 配置环境变量
- [ ] 配置 HTTPS
- [ ] 配置日志轮转
- [ ] 配置自动备份
- [ ] 启动所有服务
- [ ] 测试功能
