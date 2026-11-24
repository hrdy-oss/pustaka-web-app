# Pustaka Digital SMK - Aplikasi dan Panduan Deploy

Aplikasi perpustakaan digital (PHP + MySQL) untuk soal LKS Cloud Computing tingkat menengah.
Dokumen ini juga berfungsi sebagai **kunci pengerjaan** untuk juri/pembuat soal.

## Fitur aplikasi

- Katalog buku dengan pencarian dan halaman, cover diambil dari S3 (folder `covers/`)
- Daftar, masuk, keluar; meminjam dan mengembalikan buku
- Halaman admin: tambah, ubah, hapus buku, dan lihat peminjaman terakhir
- `/health.php` untuk health check ALB (`/health.php?deep=1` juga mengecek database)
- Footer menampilkan **instance-id dan AZ** server yang melayani, jadi load balancing terlihat saat refresh
- Session PHP disimpan di **tabel MySQL**, jadi login tidak hilang saat request pindah antar-EC2

Akun contoh: `admin@pustaka.test` / `admin123` dan `siswa@pustaka.test` / `siswa123`.

## Struktur repo

```
public/        document root Apache (index.php, login.php, loans.php, admin/, health.php, assets/)
src/           env.php, bootstrap.php, layout.php (di luar document root)
database/      pustaka.sql (skema + data awal)
s3-covers/     10 gambar cover untuk diunggah ke S3
deploy/        userdata.sh, pustaka.conf (Apache), s3-bucket-policy.json
.env.example   contoh konfigurasi (file .env asli jangan di-commit)
```

> Aplikasi ditulis untuk PHP 8.1 (bawaan Ubuntu 22.04). Kode belum dijalankan di lingkungan AWS
> oleh pembuatnya, jadi lakukan uji coba penuh sebelum dipakai untuk lomba.

## 0. Persiapan

1. Push folder ini ke repo GitHub **publik** (misalnya `pustaka-digital`). File `.env` sudah ada di `.gitignore`.
2. Siapkan: key pair (`lks-key`), nama bucket unik (`lks-pustaka-<nama>`), dan password database tanpa spasi atau tanda kutip.
3. Region **ap-southeast-1**. Semua resource berawalan `lks-`, tag `Project = pustaka-digital`.

## 1. Networking (VPC)

1. VPC `lks-vpc`, CIDR `10.0.0.0/16`.
2. Buat 6 subnet (pilih AZ yang berbeda untuk a dan b):

   | Nama | CIDR | AZ |
   |---|---|---|
   | lks-public-a | 10.0.1.0/24 | ap-southeast-1a |
   | lks-public-b | 10.0.2.0/24 | ap-southeast-1b |
   | lks-app-a | 10.0.11.0/24 | ap-southeast-1a |
   | lks-app-b | 10.0.12.0/24 | ap-southeast-1b |
   | lks-db-a | 10.0.21.0/24 | ap-southeast-1a |
   | lks-db-b | 10.0.22.0/24 | ap-southeast-1b |

3. Internet Gateway `lks-igw`, lalu attach ke `lks-vpc`.
4. NAT Gateway `lks-nat` di `lks-public-a` (Zonal, alokasikan Elastic IP).
5. Route table:
   - `lks-rt-public`: `0.0.0.0/0` ke `lks-igw`, asosiasi ke `lks-public-a` dan `lks-public-b`
   - `lks-rt-app`: `0.0.0.0/0` ke `lks-nat`, asosiasi ke `lks-app-a` dan `lks-app-b`
   - `lks-rt-db`: hanya rute lokal, asosiasi ke `lks-db-a` dan `lks-db-b`

## 2. Security Group

Buat di `lks-vpc` dengan urutan berikut supaya bisa saling mereferensi:

| Nama | Inbound |
|---|---|
| lks-sg-alb | HTTP 80 dari 0.0.0.0/0 |
| lks-sg-bastion | SSH 22 dari 0.0.0.0/0 |
| lks-sg-app | HTTP 80, source `lks-sg-alb`; SSH 22, source `lks-sg-bastion` |
| lks-sg-db | MySQL/Aurora 3306, source `lks-sg-app` (tambah `lks-sg-bastion` sementara untuk import SQL) |

> Untuk mengimpor SQL dari bastion, `lks-sg-db` perlu mengizinkan 3306 dari `lks-sg-bastion`.
> Hapus aturan itu setelah import selesai agar tetap least privilege.

## 3. Database (RDS)

1. DB subnet group `lks-db-subnet-group`: `lks-db-a` dan `lks-db-b`.
2. Create database: MySQL 8.0, template Dev/Test, identifier `lks-db`, master user `admin`, instance `db.t3.micro`.
3. Connectivity: VPC `lks-vpc`, subnet group di atas, **Public access = No**, SG `lks-sg-db`.
4. Additional configuration: Backup retention **1 hari**, automated backups aktif.
5. Catat **endpoint** RDS setelah status Available.

## 4. Storage (S3)

1. Buat bucket `lks-pustaka-<nama>` di ap-southeast-1.
2. Di tab Permissions, matikan dua opsi Block Public Access yang berkaitan dengan *bucket policy*
   (`Block public access to buckets and objects granted through new public bucket or access policies` dan
   `... through any public bucket or access policies`). Dua opsi ACL boleh tetap aktif.
3. Buat folder `covers/` dan unggah semua file dari `s3-covers/`.
4. Pasang bucket policy dari `deploy/s3-bucket-policy.json` (ganti nama bucket). Policy hanya membuka `covers/*`.
5. Aktifkan **Bucket Versioning**.
6. Uji: buka `https://<bucket>.s3.ap-southeast-1.amazonaws.com/covers/cover-01.png` di browser, harus tampil.

Karena cover dibaca lewat URL publik, aplikasi tidak butuh access key atau IAM Role.

## 5. Bastion dan impor database

1. EC2 `lks-bastion`: Ubuntu 22.04, t3.micro, subnet `lks-public-a`, auto-assign public IP, SG `lks-sg-bastion`, key pair `lks-key`.
2. SSH ke bastion, lalu:
   ```bash
   sudo apt-get update && sudo apt-get install -y mysql-client git
   git clone https://github.com/USERNAME/pustaka-digital.git
   cd pustaka-digital
   mysql -h <ENDPOINT-RDS> -u admin -p < database/pustaka.sql
   mysql -h <ENDPOINT-RDS> -u admin -p -e "USE pustaka; SELECT COUNT(*) FROM books;"   # hasil: 10
   ```
3. Hapus aturan 3306 dari `lks-sg-bastion` di `lks-sg-db`.

## 6. Instance uji (opsional, sebelum Launch Template)

Jalankan satu EC2 di `lks-app-a` dengan User Data dari langkah 7. Dari bastion:
`ssh -i lks-key.pem ubuntu@<IP-PRIVATE>`, lalu `curl -s localhost/health.php` (harus `OK`)
dan `sudo tail -n 30 /var/log/user-data.log` jika ada masalah.

## 7. Launch Template

1. `lks-lt`: AMI Ubuntu Server 22.04 LTS, instance type `t3.small`, key pair `lks-key`.
2. Network: **jangan** set subnet; SG `lks-sg-app`.
3. Advanced details, User Data: isi dengan `deploy/userdata.sh` yang bagian KONFIGURASI-nya sudah diubah
   (`REPO_URL`, `DB_HOST`, `DB_PASS`, `S3_BASE_URL`).

## 8. Target Group dan ALB

1. Target group `lks-tg`: tipe Instances, HTTP 80, VPC `lks-vpc`, health check path `/health.php`,
   healthy threshold 2, interval 15 detik. Jangan daftarkan target manual.
2. ALB `lks-alb`: Internet-facing, subnet `lks-public-a` dan `lks-public-b`, SG `lks-sg-alb`,
   listener HTTP 80 ke `lks-tg`.

## 9. Auto Scaling Group

1. `lks-asg` memakai `lks-lt`, VPC `lks-vpc`, subnet `lks-app-a` dan `lks-app-b`.
2. Attach ke load balancer yang ada, pilih `lks-tg`; aktifkan **ELB health checks**,
   health check grace period **300 detik** (instalasi User Data butuh 2 sampai 4 menit).
3. Min 2, desired 2, max 4.
4. Scaling policy: Target tracking, metric Average CPU utilization, target **50**.
5. Tunggu 2 instance berstatus *healthy* di `lks-tg`, lalu buka DNS ALB di browser.

## 10. Monitoring (CloudWatch)

1. Alarm `lks-cpu-high`: metric EC2 > By Auto Scaling Group > `CPUUtilization` untuk `lks-asg`,
   statistic Average, period 1 menit, threshold **> 70**, 2 dari 2 datapoint. Notifikasi boleh dikosongkan.
2. Dashboard `lks-dashboard` dengan tiga widget:
   - CPU ASG (`AWS/EC2`, `CPUUtilization`, dimensi `AutoScalingGroupName = lks-asg`)
   - ALB `RequestCount` (`AWS/ApplicationELB`, dimensi `LoadBalancer`)
   - RDS `DatabaseConnections` (`AWS/RDS`, dimensi `DBInstanceIdentifier = lks-db`)

## 11. Pengujian dan checklist juri

| # | Uji | Cara | Hasil yang benar |
|---|---|---|---|
| 1 | Load balancing | Buka DNS ALB, refresh berkali-kali | Instance-id di footer berganti antar-AZ |
| 2 | Login lintas server | Login, lalu refresh beberapa kali | Tetap login (session di DB) |
| 3 | Ketersediaan tinggi | Terminate satu instance di EC2 | Aplikasi tetap bisa diakses; ASG membuat pengganti |
| 4 | RDS privat | Cek `Publicly accessible = No`; coba `mysql` dari laptop | Koneksi gagal; dari instance aplikasi berhasil |
| 5 | Private subnet | Cek EC2 aplikasi | Tidak ada public IP; SSH hanya lewat bastion |
| 6 | Cover S3 | Buka katalog | Cover tampil; folder selain `covers/` tidak bisa dibuka publik |
| 7 | Auto scaling | Di **kedua** instance: `sudo stress-ng --cpu 0 --timeout 600s` | Instance bertambah (maks. 4); alarm `lks-cpu-high` berstatus In alarm |
| 8 | Penamaan dan tag | Cek semua resource | Berawalan `lks-` dan bertag `Project = pustaka-digital` |

## Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Target `unhealthy` terus | User Data belum selesai atau gagal: lihat `/var/log/user-data.log`. Pastikan NAT Gateway aktif dan route `lks-rt-app` benar. |
| Halaman error 500 | `.env` salah (endpoint atau password DB) atau `lks-sg-db` belum mengizinkan `lks-sg-app`. Cek `/var/log/apache2/pustaka-error.log`. |
| Cover tidak tampil | Bucket policy belum terpasang, Block Public Access masih aktif, atau `S3_BASE_URL` salah. |
| Login selalu kembali ke halaman login | Tabel `sessions` tidak ada (SQL belum diimpor penuh). |
| ASG terus mengganti instance | Grace period terlalu pendek, atau health check path salah. |

## Menjalankan secara lokal (opsional)

```bash
cp .env.example .env        # isi dengan MySQL lokal, S3_BASE_URL boleh kosong
mysql -u root -p < database/pustaka.sql
php -S localhost:8000 -t public
```
# pustaka-web-app
