FROM python:3.11-slim

ENV DEBIAN_FRONTEND=noninteractive \
    PIP_INDEX_URL=https://pypi.tuna.tsinghua.edu.cn/simple

WORKDIR /app

# 统一换成清华 APT 源（删除 deb822 的官方源），并做一些加速与健壮性设置
RUN set -eux; \
    . /etc/os-release; \
    # 移除 deb822 官方源，避免 apt 还去 deb.debian.org
    rm -f /etc/apt/sources.list.d/debian.sources; \
    # 用经典 sources.list（清华源）
    printf "deb http://mirrors.tuna.tsinghua.edu.cn/debian/ %s main contrib non-free non-free-firmware\n" "$VERSION_CODENAME" > /etc/apt/sources.list; \
    printf "deb http://mirrors.tuna.tsinghua.edu.cn/debian/ %s-updates main contrib non-free non-free-firmware\n" "$VERSION_CODENAME" >> /etc/apt/sources.list; \
    printf "deb http://mirrors.tuna.tsinghua.edu.cn/debian/ %s-backports main contrib non-free non-free-firmware\n" "$VERSION_CODENAME" >> /etc/apt/sources.list; \
    printf "deb http://mirrors.tuna.tsinghua.edu.cn/debian-security %s-security main contrib non-free non-free-firmware\n" "$VERSION_CODENAME" >> /etc/apt/sources.list; \
    # 一些 apt 配置：重试、减少交互及加速
    echo 'Acquire::Retries "6";' > /etc/apt/apt.conf.d/80retries; \
    echo 'Dpkg::Use-Pty "0";'    > /etc/apt/apt.conf.d/99nopt; \
    apt-get update; \
    apt-get install -y --no-install-recommends curl ca-certificates; \
    rm -rf /var/lib/apt/lists/*

# 安装 Docker CLI（静态二进制）
RUN set -eux; \
    curl -fsSL https://mirrors.cloud.tencent.com/docker-ce/linux/static/stable/x86_64/docker-27.3.1.tgz -o /tmp/docker.tgz; \
    tar -xzf /tmp/docker.tgz -C /tmp; \
    mv /tmp/docker/docker /usr/local/bin/; \
    rm -rf /tmp/docker /tmp/docker.tgz

# 复制代码
COPY update_cert.py .

# Python 依赖走清华源
RUN pip install --no-cache-dir tencentcloud-sdk-python docker

CMD ["python", "update_cert.py"]
