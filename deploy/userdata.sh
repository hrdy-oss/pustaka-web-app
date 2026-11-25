#!/bin/bash
# User Data untuk Launch Template lks-lt (Ubuntu 22.04)
# Isi konfigurasi di bawah sebelum membuat Launch Template.

# ===================== KONFIGURASI =====================
REPO_URL="https://github.com/hrdy-oss/pustaka-web-app.git"
DB_HOST="lks-db.xxxxxxxxxxxx.ap-southeast-1.rds.amazonaws.com"
DB_NAME="pustaka"
DB_USER="admin"
DB_PASS="GANTI_PASSWORD_DB"
S3_BASE_URL=""
# =======================================================

exec > >(tee -a /var/log/user-data.log) 2>&1
set -Eeuo pipefail
export DEBIAN_FRONTEND=noninteractive

if [[ "$REPO_URL" == *"USERNAME"* || "$DB_HOST" == *"xxxxxxxxxxxx"* || "$DB_PASS" == "GANTI_PASSWORD_DB" ]]; then
    echo "Konfigurasi deploy belum lengkap. Isi REPO_URL, DB_HOST, dan DB_PASS di User Data." >&2
    exit 1
fi

apt-get update -y
apt-get install -y apache2 php libapache2-mod-php php-mysql git curl stress-ng

rm -rf /var/www/pustaka
git clone "$REPO_URL" /var/www/pustaka

cat > /var/www/pustaka/.env <<ENVEOF
DB_HOST=${DB_HOST}
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
S3_BASE_URL=${S3_BASE_URL}
ENVEOF

chown -R root:www-data /var/www/pustaka
find /var/www/pustaka -type d -exec chmod 750 {} +
find /var/www/pustaka -type f -exec chmod 640 {} +
chmod 640 /var/www/pustaka/.env

cp /var/www/pustaka/deploy/pustaka.conf /etc/apache2/sites-available/pustaka.conf
a2dissite 000-default
a2ensite pustaka
systemctl enable apache2
apache2ctl configtest
systemctl restart apache2
