#!/bin/bash
# User Data untuk Launch Template lks-lt (Ubuntu 22.04)
# Ubah bagian KONFIGURASI sebelum dipakai. Gunakan password DB tanpa spasi dan tanda kutip.

# ===================== KONFIGURASI =====================
REPO_URL="https://github.com/USERNAME/pustaka-digital.git"
DB_HOST="lks-db.xxxxxxxxxxxx.ap-southeast-1.rds.amazonaws.com"
DB_NAME="pustaka"
DB_USER="admin"
DB_PASS="GANTI_PASSWORD_DB"
S3_BASE_URL="https://GANTI-NAMA-BUCKET.s3.ap-southeast-1.amazonaws.com/covers"
# =======================================================

exec > >(tee -a /var/log/user-data.log) 2>&1
set -x
export DEBIAN_FRONTEND=noninteractive

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

chown -R www-data:www-data /var/www/pustaka
chmod 640 /var/www/pustaka/.env

cp /var/www/pustaka/deploy/pustaka.conf /etc/apache2/sites-available/pustaka.conf
a2dissite 000-default
a2ensite pustaka
systemctl enable apache2
systemctl restart apache2
