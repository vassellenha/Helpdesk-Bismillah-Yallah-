# Deploy ke Ubuntu Server

Runbook untuk memasang Helpdesk 2.0 di server Ubuntu (24.04 LTS) dengan Nginx,
PHP-FPM, dan MySQL, lalu memperbarui dari Git.

Ditulis dari kebutuhan nyata proyek ini — bukan panduan Laravel generik. Bagian
yang khas proyek ini ditandai **PENTING**.

---

## 0. Yang khas dari proyek ini — baca dulu

Empat hal ini yang paling sering bikin deploy "berhasil" tapi aplikasinya pincang:

**Wajib MySQL, bukan PostgreSQL.** Pencarian EVA bergantung pada indeks FULLTEXT
MySQL. Migrasinya memakai penjaga driver, jadi di PostgreSQL indeksnya **dilewati
tanpa error** — migrasi sukses, aplikasi jalan, tapi EVA diam-diam turun ke
pencarian `LIKE` dan kualitas jawabannya anjlok tanpa gejala.

**Queue worker wajib hidup.** Indexing dokumen (termasuk OCR) berjalan di antrean.
Tanpa worker, dokumen yang diunggah berhenti di status `processing` selamanya dan
admin menunggu sesuatu yang tak akan datang.

**Scheduler wajib jalan.** Tiga perintah terjadwal: snapshot coverage harian,
penyapu dokumen macet tiap 5 menit, dan penyapu log kedaluwarsa harian.

**OCR butuh biner sistem.** `poppler-utils` dan `tesseract-ocr` beserta paket
bahasa Indonesia. Tanpa itu PDF hasil pindaian menghasilkan 0 potongan.

---

## 1. Spesifikasi minimum

| | Minimum | Disarankan |
|---|---|---|
| RAM | 2 GB | 4 GB (OCR rakus memori) |
| CPU | 2 vCPU | 2–4 vCPU |
| Disk | 20 GB | 40 GB (dokumen menumpuk) |
| OS | Ubuntu 22.04 | Ubuntu 24.04 LTS |

---

## 2. Paket sistem

```bash
sudo apt update && sudo apt upgrade -y

# Ubuntu 24.04 sudah membawa PHP 8.3. Untuk 22.04, tambahkan PPA dulu:
#   sudo add-apt-repository ppa:ondrej/php -y && sudo apt update

sudo apt install -y \
  nginx mysql-server git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

# PENTING — biner OCR. Tanpa ini dokumen pindaian gagal diekstrak.
sudo apt install -y poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 22 (Vite 7 butuh Node 20+)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Cek biner OCR benar-benar ada:

```bash
which pdftotext pdftoppm pdfinfo tesseract
tesseract --list-langs   # harus memuat ind dan eng
```

---

## 3. User dan direktori

Jangan deploy sebagai root.

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy

sudo mkdir -p /var/www/helpdesk
sudo chown deploy:www-data /var/www/helpdesk
```

---

## 4. MySQL

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'helpdesk'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON helpdesk.* TO 'helpdesk'@'localhost';
FLUSH PRIVILEGES;
```

`utf8mb4` bukan pilihan gaya — teks Indonesia dan emoji di catatan tiket akan
rusak pada `utf8` lama.

---

## 5. Ambil kode dari Git

```bash
sudo -u deploy -i
cd /var/www/helpdesk

# Repo privat → buat deploy key dulu:
#   ssh-keygen -t ed25519 -C "deploy@helpdesk" -f ~/.ssh/id_ed25519 -N ""
#   cat ~/.ssh/id_ed25519.pub    → tempel ke GitHub: Settings → Deploy keys
#   lalu pakai URL SSH: git@github.com:vassellenha/Helpdesk-Bismillah-Yallah-.git

git clone https://github.com/vassellenha/Helpdesk-Bismillah-Yallah- .
git checkout main
```

---

## 6. Berkas `.env` produksi

```bash
cp .env.example .env
nano .env
```

Nilai yang **wajib** berbeda dari lokal:

```dotenv
APP_ENV=production
APP_DEBUG=false                      # PENTING: true membocorkan isi .env di layar error
APP_URL=https://helpdesk.adhi.co.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=helpdesk
DB_USERNAME=helpdesk
DB_PASSWORD=GANTI_PASSWORD_KUAT

# Ketiganya memakai tabel database — pastikan migrasi jalan.
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FILESYSTEM_DISK=local                # dokumen disimpan di luar public/, jangan diubah

# Surel notifikasi. `log` hanya untuk lokal — di produksi tidak ada yang terkirim.
MAIL_MAILER=smtp
MAIL_HOST=smtp.adhi.co.id
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=helpdesk@adhi.co.id

# Biner OCR — ditulis eksplisit supaya tidak bergantung pada PATH milik PHP-FPM.
EVA_PDFINFO_PATH=/usr/bin/pdfinfo
EVA_PDFTOTEXT_PATH=/usr/bin/pdftotext
EVA_PDFTOPPM_PATH=/usr/bin/pdftoppm
EVA_TESSERACT_PATH=/usr/bin/tesseract
EVA_OCR_LANGUAGES=ind+eng

# Masa simpan log EVA (hari). Penghapusan permanen — lihat docs.
EVA_LOG_RETENTION_DAYS=14
```

---

## 7. Pasang dan bangun

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # hasilnya ke public/build

php artisan key:generate
php artisan migrate --force      # --force wajib di production
php artisan storage:link
php artisan optimize             # config + route + view cache
```

Isi data awal **hanya saat pertama kali**:

```bash
php artisan db:seed --force
```

---

## 8. Hak akses

```bash
exit                             # kembali ke user sudo
sudo chown -R deploy:www-data /var/www/helpdesk
sudo find /var/www/helpdesk -type f -exec chmod 664 {} \;
sudo find /var/www/helpdesk -type d -exec chmod 775 {} \;
sudo chmod -R 775 /var/www/helpdesk/storage /var/www/helpdesk/bootstrap/cache
```

---

## 9. Nginx

`/etc/nginx/sites-available/helpdesk`:

```nginx
server {
    listen 80;
    server_name helpdesk.adhi.co.id;
    root /var/www/helpdesk/public;

    index index.php;
    charset utf-8;

    # Unggahan dokumen EVA bisa besar — samakan dengan
    # upload_max_filesize & post_max_size di php.ini.
    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;     # OCR sinkron sesekali lambat
    }

    location ~ /\.(?!well-known).* { deny all; }

    access_log /var/log/nginx/helpdesk-access.log;
    error_log  /var/log/nginx/helpdesk-error.log;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/helpdesk /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Naikkan juga batas unggah PHP di `/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 32M
post_max_size = 32M
memory_limit = 512M
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 10. HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d helpdesk.adhi.co.id
```

---

## 11. Queue worker (systemd) — **PENTING**

`/etc/systemd/system/helpdesk-worker.service`:

```ini
[Unit]
Description=Helpdesk queue worker
After=network.target mysql.service

[Service]
User=deploy
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/helpdesk
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
# Job indexing dokumen timeout-nya 900 detik; beri ruang lebih.
TimeoutStopSec=930

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now helpdesk-worker
sudo systemctl status helpdesk-worker
```

`--max-time=3600` membuat worker berhenti sendiri tiap jam dan dinyalakan ulang
systemd. Itu disengaja: proses PHP yang hidup berhari-hari akan menahan kode lama
di memori setelah deploy, dan kebocoran memori menumpuk.

---

## 12. Scheduler (cron) — **PENTING**

```bash
sudo crontab -u deploy -e
```

```cron
* * * * * cd /var/www/helpdesk && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Satu baris ini menjalankan ketiganya: `eva:snapshot-coverage` (01:00),
`eva:sweep-stuck-documents` (tiap 5 menit), `eva:purge-expired-logs` (02:00).

---

## 13. Skrip deploy ulang

Simpan sebagai `/var/www/helpdesk/deploy.sh`, `chmod +x`:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/helpdesk

php artisan down --retry=15 || true

git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force

# Cache LAMA dibuang dulu, baru dibangun ulang. `optimize` saja tidak cukup
# kalau ada config yang dihapus — nilainya akan bertahan di cache lama.
php artisan optimize:clear
php artisan optimize

# Worker memuat kode ke memori saat start; tanpa restart ia menjalankan
# kode versi sebelumnya sampai --max-time tercapai.
sudo systemctl restart helpdesk-worker

php artisan up
echo "Deploy selesai: $(git rev-parse --short HEAD)"
```

Beri izin `deploy` me-restart worker tanpa password —
`sudo visudo -f /etc/sudoers.d/helpdesk`:

```
deploy ALL=(root) NOPASSWD: /bin/systemctl restart helpdesk-worker
```

Pemakaian: `./deploy.sh`

---

## 14. Verifikasi setelah deploy

Jangan anggap selesai sebelum keenamnya lolos:

```bash
# 1. Halaman terbuka
curl -I https://helpdesk.adhi.co.id

# 2. Migrasi lengkap
php artisan migrate:status | grep -i pending    # harus kosong

# 3. FULLTEXT benar-benar terbentuk (kalau kosong, EVA jalan tapi payah)
mysql -u helpdesk -p helpdesk -e "SHOW INDEX FROM kb_articles WHERE Index_type='FULLTEXT';"

# 4. Worker hidup
sudo systemctl is-active helpdesk-worker

# 5. Scheduler terdaftar
php artisan schedule:list

# 6. OCR terjangkau oleh PHP
php artisan tinker --execute="echo shell_exec('tesseract --version 2>&1');"
```

Lalu **uji dengan tangan** di browser: unggah satu dokumen PDF di EVA →
Documents, tunggu statusnya jadi selesai, dan pastikan **jumlah potongannya
masuk akal**. Itu satu-satunya pengujian yang membuktikan rantai
upload → queue → OCR → chunk → index benar-benar tersambung.

---

## 15. Masalah yang sering muncul

| Gejala | Penyebab paling sering |
|---|---|
| Dokumen macet di `processing` | Worker mati. `systemctl status helpdesk-worker` |
| Dokumen selesai tapi 0 potongan | Tesseract/poppler tidak terjangkau PHP-FPM — isi `EVA_*_PATH` di `.env` |
| EVA menjawab payah padahal materi ada | Indeks FULLTEXT tidak terbentuk (cek langkah 14 no. 3) |
| Layar putih / 500 | `storage/logs/laravel.log`. Biasanya izin `storage/` |
| CSS/JS tidak muncul | `npm run build` belum jalan, atau `public/hot` tertinggal — hapus berkas itu |
| Perubahan `.env` tidak terbaca | `php artisan optimize:clear` lalu `optimize` |
