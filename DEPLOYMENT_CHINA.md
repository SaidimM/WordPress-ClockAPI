# 中国大陆部署指南 (China Mainland Deployment Guide)

针对腾讯云等中国大陆服务器的优化部署指南。

## 🇨🇳 中国大陆优化

部署脚本会自动检测是否在中国大陆环境，并使用以下国内镜像源：

### 自动配置的镜像

1. **Docker 镜像加速**
   - 中国科技大学镜像: `https://docker.mirrors.ustc.edu.cn`
   - 网易云镜像: `https://hub-mirror.c.163.com`
   - 腾讯云镜像: `https://mirror.ccs.tencentyun.com`

2. **Docker 安装源**
   - 腾讯云镜像: `https://mirrors.cloud.tencent.com/docker-ce/`

3. **Docker Compose**
   - DaoCloud 镜像: `https://get.daocloud.io/docker/compose/`

4. **NPM 包管理器**
   - 淘宝镜像: `https://registry.npmmirror.com`

5. **Node.js 安装**
   - 腾讯云镜像: `https://mirrors.cloud.tencent.com/nodejs-release/`

6. **WordPress 下载**
   - 中文官方镜像: `https://cn.wordpress.org/`

## 🚀 快速部署

### 在腾讯云服务器上部署

```bash
# 1. 克隆仓库
git clone https://github.com/SaidimM/WordPress-ClockAPI.git
cd WordPress-ClockAPI

# 2. 运行自动部署脚本
./deploy.sh
```

脚本会自动：
- ✅ 检测是否在中国大陆环境
- ✅ 自动使用国内镜像源
- ✅ 配置 Docker 镜像加速
- ✅ 使用淘宝 npm 镜像
- ✅ 完整安装所有依赖

### 手动指定使用中国镜像

如果自动检测失败，脚本会询问：

```
Are you deploying in China mainland? (y/N): y
```

输入 `y` 即可启用所有国内镜像源。

## 📋 腾讯云服务器要求

### 最低配置
- **CPU**: 2核
- **内存**: 2GB
- **硬盘**: 20GB
- **系统**: Ubuntu 20.04 / 22.04 或 Debian 10 / 11

### 推荐配置
- **CPU**: 2核或以上
- **内存**: 4GB 或以上
- **硬盘**: 40GB 或以上
- **带宽**: 3Mbps 或以上

### 安全组配置

在腾讯云控制台配置安全组，开放以下端口：

| 端口 | 协议 | 用途 |
|------|------|------|
| 22 | TCP | SSH 登录 |
| 80 | TCP | HTTP (自动跳转到 HTTPS) |
| 443 | TCP | HTTPS |

## 🔐 SSL 证书配置

### 选项 1: 使用腾讯云 SSL 证书

1. 在腾讯云控制台申请免费 SSL 证书
2. 下载 Nginx 格式证书
3. 部署时选择 "Use existing certificates"
4. 提供证书文件路径

### 选项 2: Let's Encrypt (推荐)

脚本支持自动申请 Let's Encrypt 证书：

```bash
# 部署时选择选项 2
SSL Certificate options:
1) Use existing certificates
2) Generate with Let's Encrypt (Recommended)
3) Skip SSL setup

Choose option [1-3]: 2
```

**注意**: 使用 Let's Encrypt 前确保：
- 域名已解析到服务器 IP
- 80 和 443 端口已开放
- 没有其他服务占用 80/443 端口

## 🌐 域名配置

### 腾讯云 DNSPod 配置

1. 登录 [DNSPod 控制台](https://console.dnspod.cn/)
2. 添加域名记录：

| 记录类型 | 主机记录 | 记录值 | TTL |
|----------|----------|--------|-----|
| A | @ | 服务器IP | 600 |
| A | www | 服务器IP | 600 |

3. 等待 DNS 生效（通常 5-10 分钟）

## 📝 部署步骤详解

### 1. 连接服务器

```bash
# 使用 SSH 连接腾讯云服务器
ssh ubuntu@your-server-ip

# 或使用腾讯云控制台的"登录"功能
```

### 2. 更新系统

```bash
sudo apt update && sudo apt upgrade -y
```

### 3. 克隆项目

```bash
git clone https://github.com/SaidimM/WordPress-ClockAPI.git
cd WordPress-ClockAPI
```

### 4. 运行部署脚本

```bash
chmod +x deploy.sh
./deploy.sh
```

### 5. 按提示配置

脚本会询问：
- 域名名称
- 数据库密码（可自动生成）
- Unsplash API 密钥
- SSL 证书选项

### 6. 等待部署完成

部署通常需要 5-15 分钟，具体取决于网络速度。

## 🔧 部署后配置

### WordPress 初始化

1. 访问 `https://your-domain.com`
2. 选择简体中文（如果使用中国镜像下载）
3. 创建管理员账号
4. 完成安装

### 激活自定义时钟插件

1. 登录 WordPress 后台 (`/wp-admin`)
2. 进入"插件"页面
3. 激活 "Custom Clock" 插件
4. 创建新页面，添加短代码: `[custom_clock]`

## 🐛 常见问题

### Docker 安装失败

如果 Docker 安装失败，手动安装：

```bash
# 配置腾讯云镜像源
curl -fsSL https://mirrors.cloud.tencent.com/docker-ce/linux/ubuntu/gpg | sudo apt-key add -
sudo add-apt-repository "deb [arch=amd64] https://mirrors.cloud.tencent.com/docker-ce/linux/ubuntu $(lsb_release -cs) stable"

# 安装 Docker
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io
```

### Docker 拉取镜像慢

手动配置 Docker 镜像加速：

```bash
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json <<-'EOF'
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com",
    "https://mirror.ccs.tencentyun.com"
  ]
}
EOF

sudo systemctl daemon-reload
sudo systemctl restart docker
```

### npm 安装包慢

手动配置 npm 淘宝镜像：

```bash
npm config set registry https://registry.npmmirror.com
```

### 端口被占用

检查端口占用：

```bash
sudo netstat -tulpn | grep -E ':(80|443|3306)'
```

停止占用端口的服务：

```bash
sudo systemctl stop nginx   # 如果安装了 nginx
sudo systemctl stop apache2  # 如果安装了 apache
```

### Let's Encrypt 证书申请失败

确认：
1. 域名已正确解析到服务器
2. 防火墙/安全组已开放 80、443 端口
3. 没有其他服务占用 80 端口

手动测试域名解析：

```bash
ping your-domain.com
nslookup your-domain.com
```

## 🔒 安全建议

1. **修改 SSH 端口**
   ```bash
   sudo nano /etc/ssh/sshd_config
   # 修改 Port 22 为其他端口
   sudo systemctl restart sshd
   ```

2. **配置防火墙**
   ```bash
   sudo ufw allow 22/tcp
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

3. **定期更新系统**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

4. **WordPress 安全加固**
   - 安装 Wordfence 安全插件
   - 启用两步验证
   - 定期备份数据库

5. **设置自动备份**
   ```bash
   # 添加到 crontab
   0 2 * * * docker-compose exec mysql mysqldump -u root -p$MYSQL_ROOT_PASSWORD wordpress > /backup/wp_$(date +\%Y\%m\%d).sql
   ```

## 📊 性能优化

### 1. 启用 Redis 缓存

编辑 `docker-compose.yml` 添加 Redis 服务：

```yaml
  redis:
    image: redis:7-alpine
    restart: always
    command: redis-server --appendonly yes
    volumes:
      - ./redis:/data
```

### 2. 配置 Nginx 缓存

已在配置中启用了 gzip 压缩和浏览器缓存。

### 3. WordPress 优化插件

推荐安装：
- WP Super Cache 或 W3 Total Cache
- Autoptimize (CSS/JS 优化)
- EWWW Image Optimizer (图片优化)

## 📞 技术支持

- GitHub Issues: https://github.com/SaidimM/WordPress-ClockAPI/issues
- 腾讯云文档: https://cloud.tencent.com/document

---

**Generated with [Claude Code](https://claude.com/claude-code)**
