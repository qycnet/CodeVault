# CodeVault - Docker 部署配置
FROM php:8.2-apache

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 启用 Apache mod_rewrite
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . /var/www/html/

# 配置 Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 创建 Git 仓库目录
RUN mkdir -p /var/git/repositories \
    && chown -R www-data:www-data /var/git

# 暴露端口
EXPOSE 80

# 启动 Apache
CMD ["apache2-foreground"]
