# CodeVault 部署指南

## 生产环境部署

### 1. 环境变量配置

```bash
# 复制环境变量模板
cp .env.example .env

# 编辑 .env 文件，设置强密码
vim .env
```

### 2. 密码要求

- **最小长度**: 32 字符
- **复杂度**: 大小写字母 + 数字 + 特殊字符
- **示例**: `Kj8#mP2$vX9@nQ5!wR3&yT7^zU4*`

### 3. 启动服务

```bash
# 生产环境
docker-compose up -d

# 开发环境（包含前端开发服务器）
docker-compose --profile dev up -d
```

### 4. 安全检查清单

- [ ] 数据库端口未暴露（docker-compose.yml 已移除 ports）
- [ ] Redis 端口未暴露
- [ ] 所有密码使用环境变量
- [ ] .env 文件未提交到版本控制
- [ ] APP_DEBUG=false
- [ ] APP_ENV=production

### 5. Docker Secrets (推荐)

如果使用 Docker Swarm:

```yaml
secrets:
  db_password:
    external: true
  redis_password:
    external: true
```

```bash
echo "your_strong_password" | docker secret create db_password -
```

### 6. Kubernetes Secrets (推荐)

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: codevault-secrets
type: Opaque
stringData:
  DB_PASS: "your_strong_password"
  MYSQL_ROOT_PASSWORD: "your_root_password"
  REDIS_PASSWORD: "your_redis_password"
```

### 7. 监控与日志

```bash
# 查看日志
docker-compose logs -f web

# 健康检查
curl http://localhost:8080/health
```

### 8. 备份

```bash
# 数据库备份
docker exec codevault-db-1 mysqldump -u root -p codevault > backup.sql

# Redis 备份
docker exec codevault-redis-1 redis-cli BGSAVE
```

## 开发环境

开发环境配置保持简单，密码较弱但仅用于本地测试：

```bash
# 开发环境
docker-compose --profile dev up -d
```

## 注意事项

1. **生产环境**：数据库和 Redis 端口不暴露到宿主机
2. **开发环境**：可以暴露端口方便调试
3. **密码管理**：生产环境必须使用强密码
4. **定期更新**：定期更新密码和依赖版本
