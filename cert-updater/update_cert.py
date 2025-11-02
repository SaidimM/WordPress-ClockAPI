import os
import time
import base64
import zipfile
import tempfile
import shutil
import docker
import logging
from datetime import datetime
from tencentcloud.common import credential
from tencentcloud.ssl.v20191205 import ssl_client, models
from tencentcloud.common.profile.client_profile import ClientProfile
from tencentcloud.common.profile.http_profile import HttpProfile

# 配置日志
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

DOMAIN = os.getenv("DOMAIN", "saidim.com")
DEST_DIR = os.getenv("SSL_DEST_DIR", "/certs")
# Additional directory for nginx certificates on the host
HOST_SSL_DIR = os.getenv("HOST_SSL_DIR", "/host-ssl")
# Check interval in days (default: 30 days = 1 month)
CHECK_INTERVAL_DAYS = int(os.getenv("CERT_CHECK_INTERVAL_DAYS", "30"))

def get_latest_cert_id(client, domain):
    """获取指定域名的最新证书ID"""
    try:
        req = models.DescribeCertificatesRequest()
        params = {"SearchKey": domain, "Limit": 50}
        req.from_json_string(str(params).replace("'", '"'))
        resp = client.DescribeCertificates(req)

        certs = resp.Certificates
        valid_certs = []

        for cert in certs:
            # Check for both possible status values for issued certificates
            domain_match = cert.Domain == domain
            if cert.SubjectAltName:
                # SubjectAltName can be a list or string
                if isinstance(cert.SubjectAltName, list):
                    domain_match = domain_match or domain in cert.SubjectAltName
                else:
                    domain_match = domain_match or domain in cert.SubjectAltName.split(",")

            if cert.StatusName in ["已签发", "证书已颁发"] and domain_match:
                valid_certs.append(cert)

        if not valid_certs:
            raise Exception(f"没有找到 {domain} 的已签发证书")

        # 按创建时间排序，获取最新的
        latest_cert = max(valid_certs, key=lambda x: x.CertBeginTime)
        logger.info(f"找到最新证书 ID: {latest_cert.CertificateId}, 域名: {latest_cert.Domain}")
        return latest_cert.CertificateId

    except Exception as e:
        logger.error(f"获取证书ID失败: {e}")
        raise

def check_cert_expiry():
    """检查当前证书是否需要更新"""
    cert_file = os.path.join(DEST_DIR, "fullchain.pem")

    if not os.path.exists(cert_file):
        logger.info("证书文件不存在，需要下载")
        return True

    try:
        # 简单的过期检查，实际项目中可以用 cryptography 库来解析证书
        stat = os.stat(cert_file)
        file_age_days = (time.time() - stat.st_mtime) / (24 * 3600)

        # Use configurable threshold (default 60 days, but can be customized)
        update_threshold = int(os.getenv("CERT_UPDATE_THRESHOLD_DAYS", "60"))
        if file_age_days > update_threshold:
            logger.info(f"证书文件已存在 {file_age_days:.1f} 天，超过阈值 {update_threshold} 天，需要更新")
            return True
        else:
            logger.info(f"证书文件仅存在 {file_age_days:.1f} 天，未超过阈值 {update_threshold} 天，暂时不需要更新")
            return False
    except Exception as e:
        logger.error(f"检查证书文件失败: {e}")
        return True

def reload_nginx():
    """重新加载 Nginx 配置"""
    try:
        client = docker.from_env()

        # 查找 nginx 容器
        containers = client.containers.list()
        nginx_container = None

        for container in containers:
            if 'nginx' in container.name.lower() or 'nginx' in str(container.image.tags):
                nginx_container = container
                break

        if nginx_container:
            logger.info(f"找到 Nginx 容器: {nginx_container.name}")
            result = nginx_container.exec_run("nginx -s reload")
            if result.exit_code == 0:
                logger.info("✅ Nginx 重新加载成功")
            else:
                logger.error(f"❌ Nginx 重新加载失败: {result.output.decode()}")
        else:
            logger.warning("未找到 Nginx 容器")

    except Exception as e:
        logger.error(f"重新加载 Nginx 失败: {e}")

def update_cert():
    """更新证书的主要函数"""
    secret_id = os.getenv("TENCENT_SECRET_ID")
    secret_key = os.getenv("TENCENT_SECRET_KEY")

    if not secret_id or not secret_key:
        logger.error("❌ 腾讯云 API 密钥未设置")
        return False

    try:
        # 初始化腾讯云客户端
        cred = credential.Credential(secret_id, secret_key)
        httpProfile = HttpProfile(endpoint="ssl.tencentcloudapi.com")
        clientProfile = ClientProfile(httpProfile=httpProfile)
        client = ssl_client.SslClient(cred, "", clientProfile)

        # 获取最新证书 ID
        cert_id = get_latest_cert_id(client, DOMAIN)

        # 下载证书
        logger.info("开始下载证书...")
        req = models.DownloadCertificateRequest()
        req.from_json_string(f'{{"CertificateId":"{cert_id}"}}')
        resp = client.DownloadCertificate(req)

        # 解压证书文件
        zip_bytes = base64.b64decode(resp.Content)
        tmp_dir = tempfile.mkdtemp()
        tmp_zip = os.path.join(tmp_dir, "cert.zip")

        with open(tmp_zip, "wb") as f:
            f.write(zip_bytes)

        with zipfile.ZipFile(tmp_zip, "r") as z:
            z.extractall(tmp_dir)
            logger.info(f"证书文件解压到: {tmp_dir}")

        # 创建目标目录
        os.makedirs(DEST_DIR, exist_ok=True)

        # 复制证书文件
        cert_files_found = False
        cert_source = None
        key_source = None

        for fname in os.listdir(tmp_dir):
            if fname.endswith((".pem", ".crt", ".key")):
                src = os.path.join(tmp_dir, fname)

                if "key" in fname.lower():
                    key_source = src
                    dest = os.path.join(DEST_DIR, "privkey.pem")
                    logger.info(f"复制私钥: {fname} -> privkey.pem")
                elif fname.endswith((".pem", ".crt")):
                    cert_source = src
                    dest = os.path.join(DEST_DIR, "fullchain.pem")
                    logger.info(f"复制证书: {fname} -> fullchain.pem")
                else:
                    continue

                shutil.copy2(src, dest)
                # 设置适当的文件权限
                os.chmod(dest, 0o644 if "fullchain" in os.path.basename(dest) else 0o600)
                cert_files_found = True

        # Also copy to host SSL directory if it exists
        if cert_files_found and os.path.exists(HOST_SSL_DIR):
            if cert_source:
                host_cert = os.path.join(HOST_SSL_DIR, f"{DOMAIN}_bundle.crt")
                shutil.copy2(cert_source, host_cert)
                os.chmod(host_cert, 0o644)
                logger.info(f"复制证书到主机目录: {host_cert}")
            if key_source:
                host_key = os.path.join(HOST_SSL_DIR, f"{DOMAIN}.key")
                shutil.copy2(key_source, host_key)
                os.chmod(host_key, 0o600)
                logger.info(f"复制私钥到主机目录: {host_key}")

        # 清理临时文件
        shutil.rmtree(tmp_dir, ignore_errors=True)

        if cert_files_found:
            logger.info(f"✅ 证书已成功更新到 {DEST_DIR}")

            # 重新加载 Nginx
            reload_nginx()
            return True
        else:
            logger.error("❌ 未找到有效的证书文件")
            return False

    except Exception as e:
        logger.error(f"❌ 证书更新失败: {e}")
        return False

def main():
    """主循环"""
    logger.info("=== 证书自动更新服务启动 ===")
    logger.info(f"域名: {DOMAIN}")
    logger.info(f"证书目录: {DEST_DIR}")
    logger.info(f"检查间隔: {CHECK_INTERVAL_DAYS} 天")
    logger.info(f"更新阈值: {os.getenv('CERT_UPDATE_THRESHOLD_DAYS', '60')} 天")

    # 首次启动时检查并更新证书
    if check_cert_expiry():
        logger.info("开始首次证书更新...")
        update_cert()

    # 主循环
    while True:
        try:
            logger.info("=== 开始定时检查证书 ===")

            if check_cert_expiry():
                logger.info("证书需要更新，开始更新流程...")
                success = update_cert()
                if success:
                    logger.info("✅ 证书更新成功")
                else:
                    logger.error("❌ 证书更新失败")
            else:
                logger.info("证书暂时不需要更新")

        except Exception as e:
            logger.error(f"❌ 定时检查失败: {e}")

        # 等待下次检查（使用配置的间隔）
        check_interval_seconds = CHECK_INTERVAL_DAYS * 24 * 3600
        logger.info(f"等待下次检查（{CHECK_INTERVAL_DAYS} 天后）...")
        time.sleep(check_interval_seconds)

if __name__ == "__main__":
    main()